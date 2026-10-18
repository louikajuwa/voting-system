# How BallotBox Works

A plain-language map of the codebase: for each feature, which files are involved and what triggers
what. This is an **overview of the flow**, not a line-by-line code explanation.

---

## 1. The big picture

### Docker services (who does what)
| Service | Role |
|---------|------|
| `nginx` (:8080) | Front door. Serves static files, proxies PHP to `app`, proxies `/socket.io/` to `socket`. |
| `app` (PHP-FPM / Laravel) | All the application logic — routes, controllers, Blade, DB. |
| `mysql` | Database (organizations, elections, candidates, votes, users, roles). |
| `redis` | Cache, sessions, queue, **and the pub/sub channel for live vote updates**. |
| `socket` (Node) | `socket-server/index.js` — bridges Redis → browsers over WebSockets. |
| `queue` / `scheduler` | Background worker + cron (auto opens/closes elections every minute). |

### Request lifecycle (every page/action)
```
Browser ──HTTP──▶ nginx ──▶ Laravel route (routes/*.php)
                                │
                          middleware (auth? role?)
                                │
                          Controller@method
                                │
                 ┌──────────────┴───────────────┐
          first page load                 in-page action (AJAX)
                 │                               │
          returns a Blade view            returns a JSON envelope
          (full HTML page)                { result, message, data }
                 │                               │
             Browser renders            Alpine JS updates the page
                                          (no reload)
```

### Two response styles
- **Full-page Blade** — the first time you open a URL (e.g. the dashboard shell, a ballot page).
- **AJAX JSON envelope** — every button/search/save after that. Uniform shape:
  ```json
  { "result": "success", "message": "Election created.", "data": { ... } }
  { "result": "error",   "message": "Only an Admin can delete elections." }
  ```
  Exception: **form validation** uses Laravel's native `422 { message, errors }`, which the forms
  map to red text under each field.

### The realtime path (used by voting)
```
Someone votes ▶ Laravel saves it ▶ fires VoteCast event ▶ PublishVoteTally listener
      ▶ RealtimeService publishes JSON to Redis channel "vote.events"
      ▶ Node socket-server (subscribed to that channel) relays it to the right "room"
      ▶ every browser on that results page / dashboard updates instantly (no refresh)
```

---

## 2. The building blocks

| Thing | File | What it is |
|-------|------|------------|
| Public routes | `routes/web.php` | `/`, ballots, results (no login) |
| Admin routes | `routes/web.php` | `/dashboard`, `/admin/*` (login + role gated) |
| Auth routes | `routes/auth.php` | login, register, password reset (Breeze) |
| Scheduler | `routes/console.php` | registers the auto open/close command |
| Admin shell | `resources/views/layouts/app.blade.php` | sidebar + topbar + mobile hamburger |
| Voter shell | `resources/views/components/public-layout.blade.php` | public header |
| Auth shell | `resources/views/layouts/guest.blade.php` | login/register card |
| Shared head | `resources/views/partials/assets.blade.php` | loads CSS, theme toggle, **`toast()`** + **`ajaxForm()`** |
| Design system | `public/css/ballotbox.css` | all colors/spacing/components, light + dark |
| Realtime (PHP) | `app/Events/VoteCast.php`, `app/Listeners/PublishVoteTally.php`, `app/Services/RealtimeService.php` | fire + publish live updates |
| Realtime (Node) | `socket-server/index.js` | relays Redis messages to browsers |

**Two JS helpers you'll see everywhere** (defined in `partials/assets.blade.php`):
- **`ajaxForm({ onOk })`** — attach to a `<form>`; it submits via `fetch` (no reload), shows field
  errors on 422, toasts the message, and calls `onOk(data)` on success.
- **`toast(message, type)`** — pops a small success/error notification.

---

## 3. How each feature flows

> Legend — **Access**: `public` = no login · `auth` = logged in · `role` = Admin/Manager gated.

### Login
- **Files:** `routes/auth.php` → `app/Http/Controllers/Auth/AuthenticatedSessionController@store` · view `resources/views/auth/login.blade.php`
- **Flow:** submit form → `POST /login` → validates email/password → starts session → redirects to `/dashboard`.
- **Access:** public (guest only)

### Register (creates the organization)
- **Files:** `routes/auth.php` → `Auth/RegisteredUserController@store` · view `auth/register.blade.php`
- **Flow:** submit → creates a new **Organization**, creates the user, gives them the **Admin** role
  (all in one DB transaction) → logs in → `/dashboard`.
- **Access:** public (guest only)

### Dashboard (overview + live tiles + elections table)
- **Files:** `Admin/DashboardController@index` (page), `@elections` + `@stats` (JSON) · view `dashboard.blade.php` (Alpine component `dashboard()`) · live JS via `socket.io`
- **Flow:** open `/dashboard` → Blade renders the shell → Alpine `load()` fetches `/dashboard/elections`
  (JSON) and draws the table → search/filter/paginate re-fetch (no reload) → when a vote happens
  anywhere in the org, the `org.{id}` socket room pushes an update and the tiles/counts bump live.
- **Access:** auth

### Create / Edit an election
- **Files:** `Admin/ElectionController@store` / `@update` · modal markup in `dashboard.blade.php`
  (create) and `elections/show.blade.php` (edit), both using `ajaxForm()`
- **Flow:** click "New election" → modal opens → submit → `POST`/`PUT` → JSON envelope → toast +
  the table/header updates in place. No page navigation.
- **Access:** role (Admin or Manager)

### Change status / Delete election
- **Files:** `ElectionController@status` (AJAX) · `@destroy` (soft delete) · confirm modals in `elections/show.blade.php`
- **Flow:** status buttons → AJAX → the status pill updates instantly. Delete → confirm popup →
  soft-delete (votes are kept) → back to dashboard. Delete is **Admin-only**.
- **Access:** role (delete = Admin)

### Candidates (add / edit / remove)
- **Files:** `Admin/CandidateController@list` (JSON) `@store` `@update` `@destroy` · `elections/show.blade.php` (Alpine `candidateTable()` + modals)
- **Flow:** the candidate list loads via `/admin/elections/{slug}/candidates-list` → add/edit open a
  modal (with photo upload preview) → save via AJAX → list refreshes in place → remove asks for
  confirmation first.
- **Access:** role (Admin or Manager)

### Voting (the public ballot) — no login
- **Files:** `VoteController@show` (ballot) `@store` (cast) + `app/Services/CastVote.php` · view `vote/ballot.blade.php`
- **Flow:** open `/vote/{slug}` → pick a candidate + enter email → submit via AJAX → `CastVote`
  checks the election is open, the candidate is approved, and the email hasn't voted (DB unique
  constraint is the real guard) → saves the vote → **fires the `VoteCast` event** → returns JSON
  success → the page swaps to an inline "Your vote is counted" state (no navigation).
- **Access:** public

### Live results + realtime (the full chain)
- **Files:** `ResultsController@show` (page) `@tally` (JSON poll) · view `results/show.blade.php`
  (Chart.js + socket.io) · PHP realtime trio + `socket-server/index.js`
- **Flow:**
  ```
  vote saved (CastVote)
    ▶ VoteCast event
    ▶ PublishVoteTally listener  (app/Listeners)
    ▶ RealtimeService->publish() to Redis "vote.events"  (app/Services)
    ▶ socket-server/index.js relays to room "election.{id}"  (Node)
    ▶ browser on the results page updates the bars + chart live
  ```
  A 15-second poll of `/vote/{slug}/tally` is the fallback if the socket drops.
- **Access:** public

### Voter audit log (who voted)
- **Files:** `ElectionController@votes` (JSON: email search + date range + paging) · `votesTable()` in `elections/show.blade.php`
- **Flow:** on the manage page, the audit table fetches `/admin/elections/{slug}/votes` → search by
  email / filter by date / paginate, all AJAX. Emails are shown in full — this table is admin-only.
- **Access:** role (admin area)

### Members
- **Files:** `Admin/MemberController@index` `@list` `@store` `@update` `@destroy` · view `admin/members.blade.php`
- **Flow:** list loads via AJAX → "Add member" modal creates a user + shows a one-time password →
  role change is an inline dropdown (AJAX) → remove asks for confirmation.
- **Access:** role (**Admin only**)

### Organization settings
- **Files:** `Admin/OrganizationController@edit` `@update` · view `admin/organization.blade.php`
- **Flow:** edit name / timezone / logo → AJAX save → toast, and the sidebar name/logo update live.
- **Access:** role (**Admin only**)

### Analytics
- **Files:** `Admin/AnalyticsController@index` (page) `@data` (JSON) · view `admin/analytics.blade.php` (Chart.js)
- **Flow:** page loads → Alpine fetches `/admin/analytics/data` → draws the "votes over time" line
  chart and "votes by election" bar chart.
- **Access:** role (Admin or Manager)

### Exports (CSV / Excel)
- **Files:** `Admin/ReportController@csv` `@votesCsv` `@votesExcel` + `app/Exports/VotesExport.php` · date-range modal in `elections/show.blade.php`
- **Flow:** "Export" opens a modal → pick a date range → the CSV/Excel links carry `?from=&to=` →
  the browser downloads the file (Excel via the `maatwebsite/excel` package).
- **Access:** role (admin area)

### Auto open / close elections
- **Files:** `app/Console/Commands/TransitionElections.php` · registered in `routes/console.php`
- **Flow:** the `scheduler` container runs `schedule:run` every minute → the command flips
  `scheduled → live` at `opens_at` and `live → closed` at `closes_at`. No human action needed.
- **Access:** system (background)

---

## 4. "Where do I look when…"

| I want to change… | Open this |
|-------------------|-----------|
| How a page **looks** | the feature's `.blade.php` + `public/css/ballotbox.css` |
| What an action **saves / validates** | the controller method + its Form Request in `app/Http/Requests/` |
| The **data** in a table (columns, search, paging) | the `@list` / `@elections` / `@votes` JSON method + that view's Alpine `load()` |
| The **response shape** of AJAX calls | `app/Http/Controllers/Controller.php` (`ok()` / `fail()` / `respond()`) |
| **Live/realtime** behavior | `app/Listeners/PublishVoteTally.php` (what's sent) + `socket-server/index.js` (how it's relayed) |
| **Who can do what** | `routes/web.php` middleware (`auth`, `role:Admin|Manager`, `role:Admin`) |
| The **login / register** logic | `app/Http/Controllers/Auth/*` + `routes/auth.php` |
| **Shared JS** (form submit, toast, theme) | `resources/views/partials/assets.blade.php` |

---

## Quick reference — URLs

| URL | Page | Access |
|-----|------|--------|
| `/` | Live elections (public landing) | public |
| `/vote/{slug}` | Ballot | public |
| `/vote/{slug}/results` | Live results | public |
| `/login`, `/register` | Auth | guest |
| `/dashboard` | Admin overview | auth |
| `/admin/elections/{slug}` | Manage election (candidates, votes, export) | role |
| `/admin/members`, `/admin/organization` | Members, Org settings | Admin |
| `/admin/analytics` | Charts | role |
| phpMyAdmin `:8081` | Database UI | — |
