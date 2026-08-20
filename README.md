# ISNA News Monitoring System

A PHP application for tracking, evaluating, and reporting daily news entries (correspondent / service / Jalali date), with Excel import, Word/Excel report export, a Trello-style task board, an evaluation/scoring module, and trend tracking. No database — all data is stored as JSON files. Login uses username + Google Authenticator (TOTP) one-time codes.

> This is a **code-only archive** for version control and handoff. Runtime data (`storage/data/*.json`, uploaded/archived Excel files, logs) is intentionally excluded via `.gitignore` and never committed.

## Requirements
- PHP hosting (tested on cPanel), no Composer or database required
- `ZipArchive` and `SimpleXML` PHP extensions (used for native `.docx`/`.xlsx` read/write — no external libraries)
- Write permission (e.g. 0775) on `storage/tmp`, `storage/archive`, `storage/data`
- Optional: cron access on the host, for the trends fetch job

## Setup
1. Upload all files to your host (e.g. `public_html/your-path/`).
2. Ensure `storage/tmp`, `storage/archive`, `storage/data` exist and are writable. Empty `.gitkeep` placeholders are included; the JSON data files themselves are created at runtime.
3. In `config.php`, change `ADMIN_SETUP_KEY` to a new secret value.
4. Visit `users_admin.php?key=YOUR_SECRET` to create the first user. A QR code is generated for scanning with Google Authenticator (or enter the Base32 secret manually).
5. After creating the needed users, change `ADMIN_SETUP_KEY` again or delete `users_admin.php` from the host.
6. (Optional) Set up the trends cron job — see [Trend tracking](#trend-tracking) below.

Every login requires a fresh 6-digit TOTP code; there are no static passwords.

## Data storage
- `storage/data/excel_files.json`, `excel_rows.json` — uploaded Excel files and their rows (permanent archive, soft edit/delete).
- `storage/data/news_entries.json` — news entries created by users (hard delete).
- `storage/data/users.json` — usernames and each user's Google Authenticator secret.
- `storage/data/tasks.json` — the task board (columns, cards, tags, notes).
- `storage/data/trends.json` (or similar, via `includes/trends.php`) — cached agency/source trend snapshots fetched by the cron job.
- All dates are stored in Jalali (Persian) calendar, format `YYYY/MM/DD`.

## Main pages

### News entry & reporting
- `index.php` — dashboard/landing page after login.
- `entry.php` — daily news entry (correspondent auto-detected from Excel or manual username) with a live table of same-day entries, filterable by service.
- `file_entry.php` / `file_entry_edit.php` — "entry from file": filter by date range → service → correspondent (only Excel rows with a correspondent value), then complete/edit/delete; results are saved into `news_entries` and appear in reports.
- `list_entries.php`, `entries_edit.php`, `entries_delete.php` — browse, edit, and hard-delete previously saved news entries.
- `save_entry.php` — backend handler that persists a news entry (used by `entry.php`/`file_entry.php`).
- `api_lookup.php` — AJAX endpoint to look up a news item by code/date, falling back to an ISNA ID lookup when not found in the uploaded Excel archive.

### Excel upload & file management
- `upload.php` — upload the daily Excel file (multi-step: `upload_step1.php` → `upload_confirm.php` → `upload_finalize.php`).
- `file_edit.php` / `file_delete.php` — edit or soft-delete an uploaded Excel file's rows/metadata.
- `backfill_monitor.php` — retroactively fill in monitoring/entry data for a user over a chosen date range.
- `backfill_views.php` — backfill view-count figures from archived Excel data.
- `backfill_site.php` — AJAX/batch backfill of per-site data, processing one archived file per request to avoid host timeouts on shared hosting.

### Reporting & export
- `report.php` — reporting by correspondent/service/date range, with Excel and Word export.
- `export_excel.php` — generates the Excel report (native `.xlsx` writer, no library).
- `export_word.php` — generates the Word report (daily monitoring sheet, single day only; native `.docx` writer, no library), using `includes/narrative.php` to turn raw entries into readable narrative text.

### Evaluation
- `evaluation.php` — scoring/evaluation dashboard: pick a date range (via the Jalali range picker) and granularity (day/month), filter by site/service, and view evaluation charts and breakdowns.
- `evaluation_api.php` — AJAX endpoint that computes and returns the evaluation data/metrics behind `evaluation.php`.

### Trend tracking
- `trends.php` — page for viewing cached news-agency/source trend data (ISNA prioritized, then other major agencies).
- `cron_fetch_trends.php` — fetches and stores fresh trend data; intended to run via host cron every 30 minutes:
  ```
  */30 * * * * /usr/bin/php /home/USERNAME/public_html/nezarat/cron_fetch_trends.php >> /home/USERNAME/public_html/nezarat/storage/tmp/trends_cron.log 2>&1
  ```

### Task board (میزکار)
- `tasks.php` — a Trello-style board (برای انجام / در حال انجام / بررسی / انجام‌شده) for team task management, with:
  - Drag-and-drop cards between columns, plus a round checkbox to mark a card done in one click.
  - Multi-assignee selection per task and a priority level (کم/متوسط/زیاد) shown as a colored border/badge.
  - **Tags**: free-text tags per task (with autocomplete of previously used tags), shown as badges on the card.
  - **Filters**: filter the whole board by assignee, tag, and a due-date range (using the same Jalali range-picker style as the evaluation page), applied instantly client-side with live per-column counts.
  - **Due date**: a clickable Jalali calendar picker (no manual typing), shared with the rest of the app.
  - **Two separate notes per task**: a "done note" written by whoever completes the task, and an independent "review note" written by a reviewer/manager — shown as compact badges on the collapsed card, full text visible only after opening it. Re-completing a task with an existing done note no longer re-prompts for one.
  - Columns auto-collapse cards beyond the first 20 with a "show more" button, so heavily loaded columns stay fast and scrollable.
  - Full-page background image with a glass/blur (frosted) styling on the filter bar, columns, and cards.
- `task_actions.php` — AJAX backend for all task board actions: `create`, `update`, `move`, `complete`, `review`, `delete`.
- `includes/tasks.php` — task board data layer (JSON-backed), including tag/review-note normalization for backward compatibility with older task records.

### Authentication & administration
- `login.php` / `logout.php` — authentication (username + TOTP code).
- `users_admin.php` — add a new user (protected by the setup key); generates a TOTP secret/QR code.
- `config.php` — site configuration, including `ADMIN_SETUP_KEY`.

## Included libraries (`includes/`)
- `auth.php` — session handling, login/logout, TOTP verification gate for pages (`requireLoginPage`) and APIs (`requireLoginApi`).
- `totp.php` — pure-PHP TOTP (RFC 6238) implementation compatible with Google Authenticator, no external library.
- `jsondb.php` — generic JSON read/update helper used as the storage layer for every module.
- `data.php` — data access functions for Excel files/rows and news entries.
- `tasks.php` — data access functions for the task board.
- `trends.php` — fetching/caching logic for news-agency trend data.
- `narrative.php` — converts raw entry data into human-readable narrative text for Word export, with simple "real news type" classification.
- `docx.php` — minimal native `.docx` writer (ZipArchive only, no external library).
- `xlsx.php` — minimal native `.xlsx` reader/writer (ZipArchive + SimpleXML only, no external library).
- `helpers.php` — shared utilities, including a precise Jalali/Gregorian conversion (ported from `jalaali-js`).
- `layout_top.php` / `layout_bottom.php` — shared HTML layout (navbar, footer, styling) plus the client-side Jalali calendar picker (`jalali-date-input` class) used across the app.

## Technical notes
- Jalali calendar uses a precise jalaali-js-based algorithm, implemented in both PHP and JS, validated across a wide date range.
- The date picker is enabled via the `jalali-date-input` class and works both as a single date field and as an "از/تا" range pair (used in `evaluation.php` and the task board filters); an early-close bug on month navigation has been fixed.
- All `.docx`/`.xlsx` generation is done natively with `ZipArchive`/`SimpleXML` — no third-party PHP packages or Composer dependency.
- The task board's client-side filtering and column pagination ("show more") avoid re-rendering or reloading the page, keeping the UI responsive as the number of tasks grows.

## Repository notes
- This repo excludes: JSON data files, uploaded/archived Excel files, and `error_log`. Keep those local-only on the production host.
- Original Persian README preserved as `README.fa.md`.
