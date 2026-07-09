# Library No Dues Management System

A self-contained PHP + MySQL web app for managing "No Dues" clearance
applications — designed to run **alongside Koha on the same server**
without touching Koha's database, Apache configuration, or uptime.

## What's in this build

This started from the version I built you, then went through several
rounds of extension on Replit (walk-in public applications, ticket
tracking, a nicer certificate design, staff editing). I picked that up,
finished the change you'd asked Replit for, fixed a few real bugs that
were introduced along the way, and hardened the installer for your
actual Koha server. Specifically:

**Finished, as requested:**
- Department and Designation on the public apply form are now free-text
  boxes (with autocomplete suggestions from existing entries — still a
  plain text box, just less prone to typos/duplicates), not dropdowns
- The "Staff Login" button is gone from the public apply form; staff
  reach their portal directly via `/login.php`
- Library card number stays optional on the apply form

**Bugs fixed before this went anywhere near your server:**
- The database was missing the `applicant_library_card` column that the
  apply form was already trying to save into — every walk-in submission
  with a library card number would have crashed with a 500 error
- Free-text Department/Designation now do a find-or-create against the
  existing lookup tables, so Admin reports/filters keep working
  correctly instead of silently failing on unrecognized values
- Front Desk, E-Resources, Librarian dashboards, and the printed
  certificate were all reading the library card number only from linked
  user accounts — walk-in applicants' card numbers (typed on the public
  form) were being silently dropped, so Koha checks for walk-ins always
  fell back to unverified/simulated data even when the patron *did*
  provide their card number. Fixed everywhere.
- Admin's "Create Staff Account" never asked for a username, but login
  was switched to username-based auth in an earlier session — any staff
  account created through the admin panel would have been permanently
  unable to log in. Fixed: the form now collects (or auto-suggests) a
  username, and the default admin account has one too.
- Front Desk's dashboard looked up each row's department name with a
  separate query inside a loop (N+1 queries) — replaced with a single
  join.
- Admin → Reports used an inner join that silently excluded every
  walk-in application (the main intake path now) from reports and CSV
  exports. Fixed to a left join so walk-ins show up correctly.

**Removed / replaced for production safety:**
- `start.sh` (Replit's dev launcher) is gone — it ran a throwaway
  MariaDB instance with `--skip-grant-tables` on a random port, which
  is fine for a sandbox and unsafe anywhere near a real server.
- `seed.php` and `demo.php` still exist as optional, manual, confirmation-
  gated scripts for trying the workflow with fake data — but `install.sh`
  never runs them automatically, so a real install starts with zero
  fake accounts or fake applications.
- `includes/config.php` / `includes/db.php` now connect over standard
  TCP (like any normal MySQL client), instead of a hardcoded Replit
  socket path — this is what lets it talk to the same MariaDB server
  Koha already uses.

## Why this is safe to install alongside Koha

- **Isolated database.** Everything lives in a new `nodues_system`
  database, accessed by a new `nodues_user` MySQL account that only has
  privileges on that one database. It never reads, writes, or requests
  access to any `koha_*` database.
- **Isolated port.** The installer adds its own Apache vhost on its own
  port (default `8091`) instead of editing Koha's vhosts, the default
  site, or anything Koha depends on. Koha's own Apache config files are
  never opened.
- **Reload, never restart.** The installer only ever runs
  `systemctl reload apache2` — never `restart` — so live Koha/OPAC
  sessions in progress are not interrupted.
- **Never touches your database server's root password**, and never
  installs a second database server.
- **Idempotent.** Re-running `install.sh` (e.g. to fix something) skips
  any step that's already done — it won't duplicate the database, reset
  the admin password, or re-import the schema over existing data.

## Install

```bash
sudo bash install.sh
```

It will ask you for:
- The LAN IP/hostname staff and patrons will use to reach this app
- A port for it to run on (default `8091` — pick anything free; this is
  separate from whatever port(s) Koha already uses)
- An admin username, email, and password
- Optionally, your Koha REST API URL and a service account (can be left
  blank and configured later)

At the end you'll get a summary with the exact URLs to share with staff
and patrons.

## After install

1. Log in as admin at `http://<server-ip>:<port>/login.php` and create
   real Front Desk, E-Resources, and Librarian accounts under
   **Admin → Users**. Each one gets a username automatically suggested
   from their email if you leave that field blank.
2. Share the public application form with patrons:
   `http://<server-ip>:<port>/apply.php`
3. Staff always log in directly at:
   `http://<server-ip>:<port>/login.php`
4. Optional — try the whole workflow with fake data first, from the app
   directory (`/var/www/nodues` by default):
   ```bash
   sudo -u www-data php seed.php   # creates 3 test staff accounts (asks to confirm)
   sudo -u www-data php demo.php   # creates 4 sample applications (asks to confirm)
   ```
   Both are safe to skip entirely — a fresh install starts empty.

## Connecting to real Koha data

`KOHA_API_LIVE` is `false` by default in `includes/config.php`, so
"Check Koha" returns clearly-labelled simulated circulation data — this
lets you test the whole approval pipeline before wiring up Koha.

To go live:
1. In Koha, enable the REST API (`RESTBasicAuth` / `RESTPublicAPI`
   system preferences) and create a staff account with circulation
   permissions for this app to use.
2. Edit `/var/www/nodues/includes/config.php`:
   ```php
   define('KOHA_API_BASE', 'http://<koha-server-ip>:8080/api/v1');
   define('KOHA_API_USER', 'your_koha_service_account');
   define('KOHA_API_PASS', 'its_password');
   define('KOHA_API_LIVE', true);
   ```
3. Front Desk's "Check Koha" button now pulls real books-issued,
   outstanding fines, lost items, and account status via Koha's
   `/patrons`, `/checkouts`, and `/patrons/{id}/account` endpoints.

If Koha becomes unreachable, "Check Koha" shows a clear error instead of
silently failing — it never lets an application through without a
successful check.

## Notes worth knowing

- **QR codes on certificates** use a free external QR image service
  (`api.qrserver.com`), which needs internet access. If your server has
  none, the certificate still prints fine — just without the QR
  graphic. Say the word if you'd like a fully offline QR library wired
  in instead.
- Public walk-in applications don't require an account — the applicant
  types their details directly into `apply.php` and gets a ticket
  number to track at `track.php`. Accounts (`register.php`) are no
  longer the primary intake path; that page now just redirects to
  `apply.php`.
- All passwords are hashed with PHP's `password_hash()` (bcrypt). CSRF
  tokens protect every form submission.

## Folder structure

```
nodues_app/
├── install.sh              installer — safe alongside Koha
├── schema.sql               database schema
├── seed.php / demo.php      optional, manual, confirmation-gated test data
├── includes/                config, db, auth, Koha API, shared layout
├── login.php / apply.php / track.php / verify.php / logout.php
├── user/                     patron dashboard (for account holders), certificate
├── frontdesk/                Koha check, forward, completed/print
├── eresources/                pending queue, forward/reject
├── librarian/                 approval queue, digital sign-off
├── admin/                     users, departments, reports, audit log
├── edit_application.php       staff-side edit of any application's details
└── assets/                    Bootstrap CSS/JS (local, no CDN)
```
