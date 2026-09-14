# Coding Guardrails — Hithachintak Abhiyan (CodeIgniter 4)

This file is read automatically at the start of every session in this
project. It is the master guardrail list for all code generated, reviewed,
or corrected here. Apply it to every request, not just ones that mention
"code quality" — verify new code against it before calling a task done,
and when touching old code that violates it, fix it in the same pass if
the fix is small and safe; flag larger fixes rather than doing them
silently as a side effect of an unrelated task.

Each rule below is stated as a firm default. Where this project has a
deliberate, reasoned exception, it's written down next to the rule —
that's not an invitation to add more exceptions casually; a new one needs
the same kind of reasoning, not just convenience.

## 1. Version & Syntax

- CodeIgniter 4.x idiom only. Never `$this->load->view()`, `$this->db->query()`
  as a default reach, `$this->input->post()`, or other CI3-isms.
- PSR-12 formatting throughout.
- `declare(strict_types=1);` as the first statement (before `namespace`) in
  every PHP file under `app/` **except `app/Views/*`** (templates mixing
  HTML+PHP; adding it there is low-value and non-standard) — already applied
  repo-wide.
  - **The one real gotcha, confirmed by reading the framework source
    (`CodeIgniter::runController()`), not assumed:** the dispatcher calls
    `$controller->{$method}(...$params)` with raw route-segment strings —
    there is no reflection-based casting to a method's declared parameter
    types. Under weak typing this silently works because PHP coerces a
    numeric string like `"4"` into an `int $id` parameter; under
    `strict_types=1` that coercion is gone and the call throws a `TypeError`
    on the very first request. **Rule:** a controller action bound to a route
    segment must never scalar-type-hint that parameter (`int $id`, `float`,
    `bool`). Accept it untyped and cast explicitly as the method's first
    statement (`$id = (int) $id;`). This is not optional — it was a live bug
    caught in this repo (35 methods across 8 controllers) and re-introducing
    it elsewhere reintroduces the same class of 500 error. Private/protected
    helper methods *not* bound to a route (e.g. `authorize(int $id)` called
    internally with an already-cast int) are fine to keep scalar-typed.
- Prefer union/nullable types (`float|int|string|null`) over `mixed` when the
  real domain is known — narrower types catch more at the boundary.

## 2. Architecture & Controllers

- Controllers stay thin: HTTP in/out only — read the request, call a Model
  or Service, return a `view()`/response. No query-building, no cross-cutting
  business rules, no money/date arithmetic living only in a controller.
- Business logic and anything with more than one call site lives in a
  `Service`/`Library` class (see `app/Libraries/Enrolment/EnrolmentService.php`
  for the shape: one `start()`/`confirm*()` per domain transition, private
  helpers for the arithmetic) or in the Model.
- Use `$this->request->getPost()/getGet()`, `$this->response->setJSON()`,
  `redirect()->to()->with()` — the framework's own request/response API,
  not superglobals (`$_POST`, `$_GET`) directly in a controller. (Views may
  read `$_GET` for building/echoing the *current* query string back into a
  form or pagination link — that's a display concern, not request handling.)
- Shared per-request filtering logic (e.g. the same GET filters applied to
  a list, its totals, and its CSV/Excel/PDF export) belongs in one private
  method the controller calls from all four places — never duplicate the
  `where()` chain, or the export will quietly drift from what's on screen.

## 3. Models & Data Security

- Extend `CodeIgniter\Model`; use the Query Builder. No raw SQL unless the
  Query Builder genuinely cannot express it (e.g. an atomic
  `INSERT ... ON DUPLICATE KEY UPDATE` upsert for a sequence counter — see
  `EnrolmentService::nextReceiptNumber()`) — and even then every value goes
  in as a bound `?` placeholder, never string-interpolated into the SQL.
- Every Model declares `$allowedFields` — no exceptions, since it's the
  mass-assignment guard for `insert()`/`update()`.
- **Qualify column names with the table when a query might ever be joined.**
  `where('prant_id', ...)` is fine today and an "ambiguous column" 500
  tomorrow the moment a join adds a same-named column from another table —
  this happened for real in this repo (`EnrolmentModel::scopedTo()` /
  `UserModel::visibleTo()`, once `users` gained its own `prant_id`/`jila_id`/
  `prakhand_id`). Any `where()`/`select()` inside a reusable scoping method
  (`scopedTo()`, `visibleTo()`, anything a controller composes with
  `withDetails()` or an ad-hoc `join()`) qualifies its columns
  (`enrolments.prant_id`), full stop — don't wait for the join that breaks it.
- Know each Model's `$returnType`. Several here return Entities
  (`App\Entities\User`, `Enrolment`, …), not arrays — `$row['id']` on an
  Entity is a 500, not a warning. Check the Model before assuming array
  access; this was a real bug in this repo's own Reports view.
- Entities that cast a column (`protected $casts = ['prant_id' => 'integer']`)
  give you a real typed value from `->prant_id` — prefer reading through the
  Entity over re-casting the same value ad hoc at every call site.

## 4. Form Validation & Security

- Validate in the controller (`$this->validate()`/`validateData()`) or a
  named Validation rule group — never trust `$this->request->getPost()`
  values past that point without either validation or an explicit cast.
- Every state-changing form includes `<?= csrf_field() ?>`; every such POST
  route sits behind `authGuard` (or is a signature-verified webhook —
  see below).
- **Never trust a client-submitted amount, quantity, or anything else that
  determines money moved.** Recompute the authoritative value server-side
  from data the server itself controls (e.g. Hithachintak's enrolment
  amount is `rate × (1 + family-member count)`, computed from the
  server-validated, server-capped family list — never from the `amount`
  field the browser happened to submit, even though the browser also shows
  that computed value for UX). If a client-side cap exists (e.g. max 5
  family members) for UX, enforce the same cap server-side independently —
  the client-side one is a convenience, not a security boundary.
- A webhook endpoint (Cashfree payment callbacks) is not behind `authGuard`
  by nature (the caller isn't a logged-in user) — it must instead verify a
  cryptographic signature (HMAC over the raw body + timestamp, compared with
  `hash_equals()`) before trusting *anything* in the payload, including
  which record it claims to update.
- Rate/attempt-limit anything OTP- or password-reset-shaped (this repo:
  `otp_attempts` cap, resend cooldown) — an unlimited-attempt OTP endpoint is
  a brute-force oracle.

## 5. Error Handling & Views

- Never `echo`/`print_r`/`var_dump` in a controller — return `view()` or a
  `Response` object. (`var_dump` is fine transiently while debugging locally,
  never in a commit.)
- Every dynamic value printed in a view goes through `esc()` (default
  `'html'` context; use `esc($x, 'js')`/`'attr'`/`'url'` when embedding into
  a JS string, an HTML attribute, or a URL specifically — `'html'` escaping
  is not sufficient in those contexts).
- Inline `<script>` blocks use the CSP nonce helper
  (`<script <?= csp_script_nonce() ?>>`) instead of loosening the CSP; put
  reusable behavior in `public/assets/js/app.js` keyed off a `data-*`
  attribute rather than inline `onclick=`/`onchange=` (those can't carry a
  nonce and force weakening the CSP for the whole app).
- A `PageNotFoundException` (or similar) for "not found / not yours" beats a
  silent redirect that hides an authorization failure as if it were normal
  navigation.

## 6. Schema, migrations, and process discipline

- Every schema change is a Migration (with a real, working `down()`),
  applied via `php spark migrate` — never a manual `ALTER TABLE` against a
  running database, dev included. Seeders are for reference/lookup/dev data,
  not schema.
- After any Model/scoping/permission change, **test it as every affected
  role, not just Super Admin.** Super Admin's `scopedTo()`/`visibleTo()`
  branch is an unconditional early return — it cannot exercise the
  qualified-column or Entity-vs-array bugs above; those only show up once a
  real Pranta Admin/Jila Admin/Prakhand Admin/Karyakarta account hits the
  same code path. Seed at least one full Prant→Jila→Prakhand→Karyakarta
  chain and log in as each level before calling a permissions-adjacent
  change done. (Confirmed necessary in this repo — two live 500s were only
  caught this way, not by testing as Super Admin alone.)
- Prefer live HTTP verification over static reasoning for anything
  touching auth, payment state machines, or webhooks: run `php spark serve`,
  drive it with `curl` + a cookie jar (extract the CSRF token from the page,
  resubmit it — a stale/reused token is the most common false failure), and
  read the real DB state back (`sqlite3`/`SQLite3` in dev) rather than
  trusting the HTTP status code alone. A signed-webhook flow can be exercised
  end-to-end without a live payment gateway by computing the HMAC yourself
  with the same secret the gateway credential row holds.
- `CI_ENVIRONMENT` controls the Debug Toolbar/Kint (`CI_DEBUG`) — anything
  other than `production` appends toolbar/Kint markup to **every** response
  body, including non-HTML ones (confirmed: it showed up in a webhook's
  plain-text `ok` reply). A screenshot showing stray script/orange-box
  artifacts on a "real" page almost always means that server wasn't running
  with `CI_ENVIRONMENT=production` — check that first, don't assume it's an
  application bug.
- i18n: this app's admin UI is deliberately English-only; only the
  member-facing enrolment-form section labels/buttons and the receipt are
  translated, through `App\Libraries\Enrolment\I18n::t($lang)`. When adding
  a new translatable label, add the key to `data/i18n.json` for all 13
  languages (mirror the shape already there) rather than inventing a
  parallel translation mechanism — don't translate field-level labels
  (Full name, Address, Prant/Jila/Prakhand) that the source design
  deliberately leaves in English; check what's already translated before
  guessing scope.

## 7. General practices (not project-specific, still enforced here)

- No secrets/credentials in code or version control. Gateway/SMS/WhatsApp
  credentials are encrypted at rest (`App\Libraries\Secrets\Vault`) and read
  from `.env`/the DB, never hardcoded.
- Passwords: `password_hash()`/`password_verify()` only. Never store or log
  a plaintext password past the moment it's generated for one-time display
  (e.g. a temp password shown once in a flash message) — don't add a
  "view password" feature; that's a standing vulnerability, not a
  convenience (declined once already in this project for that reason).
- Avoid N+1 queries: use `join()`/`withDetails()`-style eager loading over
  a loop that queries per row.
- Keep authorization checks server-side and centralized (route `filter`
  groups / `permission:Module,Level`), never inferred from what the UI
  merely hides — a hidden button is not access control.
- Don't add speculative abstractions, config flags, or "just in case"
  fields for requirements nobody has asked for yet; this cuts both ways with
  the "fix it properly" rules above — fix real, demonstrated risk (like the
  strict_types/route-param class of bug), don't invent new layers.
- When a fix changes behavior for every role or every request path (RBAC
  scoping, a shared filter method, a Model's `$returnType`), re-run the
  smoke test across roles/paths before calling it done — see §6.
