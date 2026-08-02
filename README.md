# ISNA News Monitoring System

A PHP application for tracking and reporting daily news entries (correspondent / service / Jalali date), with Excel import and Word/Excel report export. No database — all data is stored as JSON files. Login uses username + Google Authenticator (TOTP) one-time codes.

> This is a **code-only archive** for version control and handoff. Runtime data (`storage/data/*.json`, uploaded/archived Excel files, logs) is intentionally excluded via `.gitignore` and never committed.

## Requirements
- PHP hosting (tested on cPanel), no Composer or database required
- Write permission (e.g. 0775) on `storage/tmp`, `storage/archive`, `storage/data`

## Setup
1. Upload all files to your host (e.g. `public_html/your-path/`).
2. Ensure `storage/tmp`, `storage/archive`, `storage/data` exist and are writable. Empty `.gitkeep` placeholders are included; the JSON data files themselves are created at runtime.
3. In `config.php`, change `ADMIN_SETUP_KEY` to a new secret value.
4. Visit `users_admin.php?key=YOUR_SECRET` to create the first user. A QR code is generated for scanning with Google Authenticator (or enter the Base32 secret manually).
5. After creating the needed users, change `ADMIN_SETUP_KEY` again or delete `users_admin.php` from the host.

Every login requires a fresh 6-digit TOTP code; there are no static passwords.

## Data storage
- `storage/data/excel_files.json`, `excel_rows.json` — uploaded Excel files and their rows (permanent archive, soft edit/delete).
- `storage/data/news_entries.json` — news entries created by users (hard delete).
- `storage/data/users.json` — usernames and each user's Google Authenticator secret.
- All dates are stored in Jalali (Persian) calendar, format `YYYY/MM/DD`.

## Main pages
- `entry.php` — daily news entry (correspondent auto-detected from Excel or manual username) with a live table of same-day entries, filterable by service.
- `file_entry.php` / `file_entry_edit.php` — "entry from file": filter by date range → service → correspondent (only Excel rows with a correspondent value), then complete/edit/delete; results are saved into `news_entries` and appear in reports.
- `upload.php` — upload/edit/delete the daily Excel file.
- `report.php` — reporting by correspondent/service/date range, with Excel and Word export (daily monitoring sheet, single day only).
- `login.php` / `logout.php` — authentication.
- `users_admin.php` — add a new user (protected by the setup key).

## Technical notes
- Jalali calendar uses a precise jalaali-js-based algorithm, implemented in both PHP and JS, validated across a wide date range.
- The date picker is enabled via the `jalali-date-input` class; an early-close bug on month navigation has been fixed.

## Repository notes
- This repo excludes: JSON data files, uploaded/archived Excel files, and `error_log`. Keep those local-only on the production host.
- Original Persian README preserved as `README.fa.md`.
