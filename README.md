# Upohar – PHP + MySQL

## Setup (XAMPP / WAMP)
1. Copy this folder to `htdocs/upohar`.
2. Start Apache + MySQL, open phpMyAdmin → **Import** → `database/upohar.sql`
   (it creates the database **upohar**, all tables, 33 shop products and 100 finder gifts).
3. Open `http://localhost/upohar/`.
4. DB user/password: `config/config.php` (default `root` / empty).
5. AI suggester: paste your new Google AI Studio key in `config/config.php` (`GEMINI_API_KEY`).

## Structure
- Pages: `index.php` `gifts.php` `filter.php` `ai.php` `schedule.php` `about.php` `login.php` `signup.php` `dashboard.php` `seller*.php`
- `api/` cart.php, schedule.php, ai.php (JSON endpoints)
- `includes/` shared helpers + navbars, `config/` settings + DB connection
- `assets/` css / js / img, `uploads/` schedule photos, `cron/send_reminders.php` email reminders
- Tables: users, sellers, products, filter_gifts, orders, order_items, schedules, ai_chats

## Notes
- Sellers start on the Free plan (5 products). The plan they pick is saved as `requested_plan`;
  set `plan` to silver/gold in phpMyAdmin after they pay.
- Finder images named `26.jpg … 98.jpg` were not in the archive – add them to `assets/img/` and
  update the `image` column (or replace with links) for those rows.
- Reminders: add a cron job running `php cron/send_reminders.php` every 10–15 minutes.
