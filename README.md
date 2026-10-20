# CampusConnect

CampusConnect is a PHP MVC moderator dashboard that runs on XAMPP. It uses MySQL when available and automatically falls back to a local SQLite file when MySQL is unavailable or its tables have not been created.

## Run with XAMPP

1. Keep this folder under `C:\xampp\htdocs\CampusConnect2`.
2. Start Apache in the XAMPP Control Panel. MySQL is optional.
3. MySQL is optional. If it is unavailable or its tables are missing, the app creates and uses `app/data/campusconnect.sqlite` automatically (PHP's PDO SQLite extension must be enabled). To use MySQL instead, start it and import [`config/database.sql`](./config/database.sql) in phpMyAdmin.
4. If your MySQL settings differ from XAMPP defaults, set `CC_DB_HOST`, `CC_DB_PORT`, `CC_DB_NAME`, `CC_DB_USER`, and `CC_DB_PASSWORD` in the Apache environment. Defaults are in [`config/database.php`](./config/database.php).
5. Open `http://localhost/CampusConnect2/`.

The demo account is `mod@campus.edu.ph` / `mod12345`. The dashboard and demo login both work without a MySQL server. Demo data is created the first time each account visits the app. Passwords are hashed; each account has its own app data.

## Pages

| Page | Route |
|---|---|
| Login / Register | `login.php`, `register.php` |
| Home / Newest | `home.php`, `newest.php` |
| Control Posts/Feed | `control.php` |
| Queues | `queues.php` (`dashboard.php` forwards here) |
| Mod Mail / Messages | `modmail.php`, `messages.php` |
| Communities | `designatmobbin.php`, `community.php?c=slug`, `communities.php` |
| About Us | `about.php` |
| FAQs | `faqs.php` |
| Post detail + comments | `post.php?id=1` |
| Overview, Posts, Comments, Saved, Restricted, Reported | `overview.php`, `posts.php`, `comments.php`, `saved.php`, `restricted.php`, `reported.php` |
| Create Post, Notifications, Profile, Search | `create.php`, `notifications.php`, `profile.php`, `search.php` |

## Reporting and moderation

Students can report posts and comments as non-academic, toxic or harassing, plagiarized, or another guideline violation. Reported posts and comments appear in **Queues → Reported** for moderator review. Moderators can dismiss reports, approve pending posts, hide posts or comments, and permanently delete reported content.

Demo data includes report examples that demonstrate unrelated promotions, personal harassment, and uncredited academic work. These are fictional examples for testing the moderation workflow; existing customized posts are not replaced.

On a post's detail page, moderators can edit its title and text, add up to five tags, and lock or unlock comments. Hidden comments can be restored from the Comments page. The queue's AI Moderation switch saves a per-account preference only; it does not automatically review or change posts.

The Home page shows separate containers for the top five most-liked approved posts created today and a post/comment report chart for the past 7 days; Overview shows the report chart for the past 14 days. Report totals remain after a report is dismissed or its content is deleted. Posts show their original posting date, and removed posts show the moderator removal date. Older reports/removals without stored timestamps are identified as unavailable rather than assigned an estimated date.

The top-liked list is ranked by total likes among approved posts created since local midnight.

## Structure

```text
app/
  Controllers/        Request handling and model loading
  Models/             One model class per file
  Views/              Shared layout and page templates
  Database.php        MySQL connection
  data/               Automatically created local SQLite storage
  helpers.php         Shared view helpers
config/
  database.php        Database settings
  database.sql        Schema
public/
  assets/             CSS and images
  *.php               Page entry points
```

Each route in `public/` loads `PageController.php` directly; no separate bootstrap file is used. The controller starts the session and loads the database, helper functions, and models. The project-root `.htaccess` forwards requests to `public/` and blocks direct access to the application and configuration folders.
