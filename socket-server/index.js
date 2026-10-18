// Vote real-time bridge: Redis pub/sub -> Socket.IO rooms.
// No business logic, no DB access. It only: verifies a signed handshake token,
// joins the client to the rooms the token authorizes, and relays events
// published by Laravel on the Redis channel to those rooms.
import http from 'node:http';
import crypto from 'node:crypto';
import express from 'express';
import { Server } from 'socket.io';
import Redis from 'ioredis';

const PORT = parseInt(process.env.SOCKET_PORT || '3001', 10);
const REDIS_HOST = process.env.REDIS_HOST || 'redis';
const REDIS_PORT = parseInt(process.env.REDIS_PORT || '6379', 10);
// Empty / "null" means no auth (matches Laravel's handling of REDIS_PASSWORD=null).
const REDIS_PASSWORD = ['', 'null'].includes(process.env.REDIS_PASSWORD || '') ? undefined : process.env.REDIS_PASSWORD;
const CHANNEL = process.env.SOCKET_CHANNEL || 'vote.events';
// Shared secret with Laravel (derived from APP_KEY on the PHP side).
const SECRET = process.env.SOCKET_SECRET || '';

const app = express();
app.get('/health', (_req, res) => res.json({ ok: true, ts: Date.now() }));

const server = http.createServer(app);
const io = new Server(server, {
  path: '/socket.io/',
  cors: { origin: false },
});

// Verify a token of the form base64url(payload).hex(hmacSHA256(payload, SECRET)).
// payload = { uid, rooms: [...], exp } (exp = unix seconds).
function verifyToken(token) {
  if (!token || typeof token !== 'string' || !SECRET) return null;
  const dot = token.lastIndexOf('.');
  if (dot < 0) return null;
  const body = token.slice(0, dot);
  const sig = token.slice(dot + 1);
  const expected = crypto.createHmac('sha256', SECRET).update(body).digest('hex');
  // constant-time compare
  const a = Buffer.from(sig);
  const b = Buffer.from(expected);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return null;
  let payload;
  try {
    payload = JSON.parse(Buffer.from(body, 'base64url').toString('utf8'));
  } catch {
    return null;
  }
  if (!payload || typeof payload.exp !== 'number' || payload.exp < Math.floor(Date.now() / 1000)) {
    return null;
  }
  if (!Array.isArray(payload.rooms)) return null;
  return payload;
}

io.use((socket, next) => {
  const token = socket.handshake.auth?.token || socket.handshake.query?.token;
  const payload = verifyToken(token);
  if (!payload) return next(new Error('unauthorized'));
  socket.data.uid = payload.uid;
  socket.data.rooms = payload.rooms;
  next();
});

io.on('connection', (socket) => {
  for (const room of socket.data.rooms) socket.join(room);
  socket.emit('connected', { uid: socket.data.uid, rooms: socket.data.rooms });
});

// Redis subscriber: relay {event, room|rooms, payload} to the target room(s).
const sub = new Redis({ host: REDIS_HOST, port: REDIS_PORT, password: REDIS_PASSWORD, lazyConnect: false });
sub.on('error', (e) => console.error('[redis] error', e.message));
sub.subscribe(CHANNEL, (err) => {
  if (err) console.error('[redis] subscribe failed', err.message);
  else console.log(`[redis] subscribed to ${CHANNEL}`);
});
sub.on('message', (_channel, raw) => {
  let msg;
  try { msg = JSON.parse(raw); } catch { return; }
  const rooms = msg.rooms || (msg.room ? [msg.room] : []);
  if (!msg.event || rooms.length === 0) return;
  for (const room of rooms) io.to(room).emit(msg.event, msg.payload ?? {});
});

server.listen(PORT, () => console.log(`[socket] listening on ${PORT}, relaying '${CHANNEL}'`));
