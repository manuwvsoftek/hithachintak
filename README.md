# Hithachintak Abhiyan

Enrolment, collection and receipting platform for Vishwa Hindu Parishad's
Hithachintak Abhiyan — covering three programmes (Hithachintak membership,
Dharma Raksha Nidhi donations, Magazine Subscription), a four-level
geographic/role hierarchy (Prant → Jila → Prakhand; Super Admin → Pranta
Admin → Sub Admin → Karyakarta), Cashfree payments (one merchant account
per Prant), MSG91 SMS/OTP/WhatsApp, and email receipts.

Built on CodeIgniter 4 + MySQL. This is a standalone project living in this
`vhp/` directory — it does not share code or a database with anything else
in the parent repository.

## Requirements

- PHP 8.2+ (developed on 8.4), extensions: `intl`, `mbstring`, `mysqlnd`,
  `curl`, `json` (on by default)
- MySQL 8.x or MariaDB 10.4+
- Composer

## Setup

```bash
composer install
cp env .env
php spark key:generate
```

Edit `.env`: set `database.default.*` to your MySQL credentials and
`app.baseURL` to your domain. See the comments in `env` for what each
setting does — payment/SMS/WhatsApp/email credentials are **not**
configured here; they're set at runtime (see below).

```bash
php spark migrate --all
php spark db:seed ProgrammeSeeder
php spark db:seed RolePermissionSeeder
php spark db:seed LocationSeeder       # starter Prant/Jila/Prakhand tree — see note below
php spark db:seed MessageTemplateSeeder
```

### First Super Admin login

```bash
# in .env:
seed.superAdminPhone = 9XXXXXXXXX
seed.superAdminPassword = <a strong password>
php spark db:seed DevSuperAdminSeeder
# then remove those two lines from .env — they're one-time bootstrap only
```

The seeded account has `must_reset_password` set, so its first login
forces a password change. In production, either do the above with a
genuinely strong password, or skip the seeder and insert the first row
into `users` by hand with a securely generated bcrypt hash.

### Run it

```bash
php spark serve
```

Point your web server's document root at `public/`, not the repo root —
`index.php` lives inside `public/`.

## Architecture notes

- **RBAC**: every module (Dashboard, Masters, Users & Hierarchy,
  Enrolments, Collections, Reports, Settings & Integrations) has a
  `None`/`Own`/`View`/`Edit`/`Full` access level per role, stored in
  `role_permissions` and editable live from Users & Hierarchy (Super
  Admin only). Enforced server-side on every route via the `permission`
  filter (`app/Filters/RequirePermission.php`) — the UI hides
  inaccessible links, but the backend is the actual gate. User
  creation/blocking/password-reset additionally enforce a role hierarchy
  (`App\Entities\User::outranks()`) so, e.g., a Sub Admin can create
  Karyakartas but never another Sub Admin or Pranta Admin, and can't
  touch accounts outside their own Jila.
- **Secrets at rest**: Cashfree keys, the MSG91 auth key, and SMTP/API
  passwords are encrypted with the app's `encryption.key` before being
  stored (`App\Libraries\Secrets\Vault`) and are only ever decrypted
  server-side when making the actual API call.
- **Payments**: each Prant has its own Cashfree merchant account
  (`gateway_credentials`, one row per Prant). `App\Libraries\Cashfree\CashfreeClient`
  creates orders and verifies webhook signatures per-Prant. The webhook
  endpoint is `POST /webhooks/cashfree/{prant_id}` — Cashfree needs this
  configured against each Prant's own merchant dashboard, and the exact
  URL is shown (auto-generated) in Settings & Integrations once a Prant
  is configured.
- **Messaging**: MSG91 SMS (`App\Libraries\Msg91\Msg91Sms`) and MSG91
  WhatsApp (`App\Libraries\Msg91\Msg91WhatsApp`) are template-driven —
  each purpose (OTP, receipt notification, credential issue, etc.) maps
  to a DLT-registered SMS template or an approved WhatsApp template,
  editable under Settings & Integrations. **Until MSG91 credentials are
  configured, the app logs the message (including the OTP) instead of
  sending it** — this makes the whole enrolment/auth flow testable
  end-to-end without a live MSG91 account; check `writable/logs/` for
  these. This fallback is disabled outside `development`/`testing`
  environments (`ENVIRONMENT` in `.env`).
- **Enrolment engine** (`App\Libraries\Enrolment\EnrolmentService`): the
  single implementation behind both the Admin "New Enrolment" panel and
  the Karyakarta mobile app — member/enrolment creation, OTP issue and
  verification, Cashfree UPI/QR order creation, cash collection, and
  receipt numbering (`HC/2026/000001`-style, atomic per programme+year).
  Hithachintak cash specifically requires the collecting user's UPI
  remittance to be confirmed *before* a receipt is issued; every other
  programme's cash receipt is issued immediately, with the actual
  remittance reconciled later from Collections.
- **Karyakarta mobile surface** (`/app`): a lightweight, phone-first home
  screen (today's count, cash to remit, recent activity) with a bottom
  tab bar. It links into the same `/admin/enrolments/*` wizard and lists
  that the Admin panel uses — those are already responsive and already
  scoped to "my own records" for a Karyakarta via RBAC, so there's no
  separate/duplicate enrolment flow to maintain.

## Location data

`LocationSeeder` only populates Uttar Karnataka with a handful of sample
Jilas/Prakhands (enough to exercise the app end-to-end) plus empty shells
for a dozen other Prants. The full VHP Prant/Jila/Prakhand list needs to
be entered via Masters → Locations (Add Jila / Add Prakhand) — there's no
authoritative machine-readable source for it bundled with this project.

## Known follow-ups

- PDF receipt download is stubbed as a "Download" concept in the
  original design but not yet wired to a PDF library — receipts
  currently render as an HTML page and email as HTML.
- Reports/Enrolments export is CSV; there's no native Excel (.xlsx)
  export yet.
- The Admin UI itself is English-only; only the *member-facing* content
  (enrolment form language choice, receipts) is multi-language, matching
  what member enrolment stores per record.
- Login rate-limiting is per-phone (lockout after 5 failures); there's
  no IP-level global throttle. For internet-facing production
  deployments, put this behind a WAF/rate-limiter (Cloudflare, etc.) as
  well.
- No automated test suite yet beyond the framework's own scaffolding —
  all flows in this build were verified manually end-to-end over live
  HTTP against a seeded dataset (see commit history for what was
  checked at each stage).

## Deployment checklist

1. `CI_ENVIRONMENT = production`, `app.forceGlobalSecureRequests = true`,
   `cookie.secure = true` (all in `.env`, once you're on HTTPS).
2. A fresh `encryption.key` (never reuse a dev one).
3. `php spark migrate --all` against your production database, then the
   seeders listed above (skip `DevSuperAdminSeeder` unless you set real
   bootstrap credentials first).
4. Configure each Prant's Cashfree merchant credentials, MSG91 SMS/
   WhatsApp, and email gateway from Settings & Integrations, then use
   the "send test" button on each before going live.
5. Point Cashfree's webhook (shown per-Prant once configured) at
   `https://yourdomain.com/webhooks/cashfree/{prant_id}`.
6. If running more than one app server, switch `session.driver` to the
   Database or Redis handler so sessions are shared.
