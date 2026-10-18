# Changelog

## 2026-10-18 — Review and hardening pass

### Manual steps after pulling
1. Back up the database, then run `docker compose exec app php artisan migrate`. This adds the `audit_logs` table, changes the `votes.candidate_id` FK to RESTRICT, and adds 3 indexes. Both migrations are reversible.
2. Rebuild and recreate the stack with `docker compose up -d --build`. phpMyAdmin is now opt-in: `docker compose --profile tools up -d phpmyadmin`.
3. In `.env`:
   - Rotate `SOCKET_SECRET` (`openssl rand -hex 32`).
   - Set `REDIS_PASSWORD`.
   - Optionally set `TRUSTED_PROXIES` and `SOCKET_TOKEN_TTL`.
   - For production, set `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` and `SESSION_ENCRYPT=true`.
4. Existing users with no `email_verified_at` will be sent to the verification notice before they can reach the dashboard. Verify them, or mark them verified.

### Security
- Trusted proxies are restricted to private and Docker ranges (`TRUSTED_PROXIES`), and nginx overwrites `X-Forwarded-For`. Clients can no longer spoof their IP to dodge throttles.
- New `SecurityHeaders` middleware: X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy and CSP, plus HSTS on HTTPS.
- Email verification is required for the dashboard and admin routes, and `POST /register` is throttled at 5 per minute.
- Voter-level data (votes log, `votes.csv`, `votes.xlsx`) is Admin-only.
- CSV/XLSX formula-injection escaping (`App\Support\Csv`) and LIKE wildcard escaping (`App\Support\Like`).
- Members:
  - The last Admin can't be deleted or demoted, and Admins can't change their own role.
  - The temporary password is no longer flashed to the session.
- The hidden-results embargo is now enforced for both the socket and the tally endpoint.
- `spatie/laravel-permission` moved to `require`.
- Dependencies:
  - `composer update` within constraints: 61 packages, including laravel/framework 12.70, league/commonmark 2.10 and maatwebsite/excel 3.1.70.
  - Socket server: added a lockfile, now builds with `npm ci` and runs as the `node` user.
  - Both audits are clean.
- SRI and `crossorigin` added to every CDN script. The Tailwind Play CDN was removed.

### Bug fixes
- Deleting a candidate no longer deletes its votes (FK RESTRICT, plus a 409 guard once live, closed, or voted).
- Election status follows a state machine:
  - Closed is terminal.
  - Going live needs an approved candidate.
  - Voting-window edits lock once live.
  - The scheduler follows the same rules.
- Vote casting re-checks the window and the candidate inside a locked transaction. Only true duplicates report "already voted".
- Tallies count approved candidates only, consistently across the results page, live feed and admin page.
- The organization timezone is applied to voting windows and to "votes today".
- The home page no longer lists elections whose window has expired.
- The thanks page requires session evidence of a vote.
- `hide_results` can now be set (validation plus a form checkbox).
- The "scheduled" status pill had no style.
- The candidate modal re-uploaded a stale photo.
- The create-election modal never reset.
- The election forms offered statuses the server rejects.
- `composer setup` and `composer dev` called npm build scripts that no longer exist.

### New features
- Audit log (`audit_logs`, `AuditLog::record()`) for election, candidate, member and organization changes, shown in an Activity card on the election page.
- Ballot confirm step (Select → Confirm → Done).
- Custom error pages for 403, 404, 419, 429, 500 and 503.
- Results page auto-refreshes once hidden results become visible.

### UI/UX and accessibility
- Breeze auth and profile pages ported to `ballotbox.css`. Dark mode now works there.
- Skip link, landmarks and `aria-current`. The mobile drawer is `inert` when closed. Modals have focus trap, focus return and Escape to close.
- The ballot is keyboard-accessible (fieldset/legend, visually-hidden radios), with all errors announced through `role="alert"`.
- Global `:focus-visible` ring, and contrast tokens retuned to at least 4.5:1 in light and dark themes.
- Confirmations for Go live, back to Draft, Close and member role changes. Busy guards on every mutating action.
- Load errors show a retry row. Expired sessions (401/419) redirect to login or reload the page. Toasts stack.
- Mobile: form rows collapse under 560px, the dropzone has a maximum size, modals scroll, and there is no horizontal overflow from 375px to 1920px.
- A guest "Log in" link in the public header.
- The duplicate "New election" button was removed from the dashboard toolbar.

### Refactoring
- The duplicated `authorizeOrg()` logic now lives in the `App\Http\Controllers\Concerns\AuthorizesOrganization` trait.
- New `Election` helpers: `approvedCandidates()`, `canTransitionTo()`, `timezone()`, `formatLocal()`, `parseLocal()` and `todayRangeUtc()`.
- Removed the unused Vite, Tailwind and PostCSS configs, `resources/js`, `resources/css`, 4 unused Breeze components and dead CSS rules.

### Performance
- Indexes: `votes(election_id, candidate_id)`, `votes(created_at)` and `candidates(election_id, status)`.
- Eager loading of approved candidates, so tallies no longer re-query per call. `whereDate` (non-sargable) was replaced with UTC ranges for "today".

### Docker and config
- MySQL and phpMyAdmin bind to `127.0.0.1`, and phpMyAdmin moved under the `tools` profile.
- Optional Redis `requirepass`, also wired into the socket server.
- App healthcheck. nginx waits for healthy app and socket.
- The scheduler uses `schedule:work`, and the queue and scheduler stop gracefully.
- The Makefile no longer runs `chmod 777`. Fixed the `php.ini` header and added a production note.
- `.env.example` documents the production and session settings, `TRUSTED_PROXIES` and `SOCKET_TOKEN_TTL`. The README has a new Security and configuration section.

### Tests
- 54 new feature tests: 37 → 91 passing. See `TEST_REPORT.md`.
