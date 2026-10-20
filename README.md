# CampusConnect

CampusConnect is a PHP MVC moderator dashboard that runs on XAMPP. It uses MySQL when available and automatically falls back to a local SQLite file when MySQL is unavailable or its tables have not been created.

## Run with XAMPP

1. Keep this folder under `C:\xampp\htdocs\CampusConnect2`.
2. Start Apache in the XAMPP Control Panel. MySQL is optional.
3. MySQL is optional. If it is unavailable or its tables are missing, the app creates and uses `app/data/campusconnect.sqlite` automatically (PHP's PDO SQLite extension must be enabled). To use MySQL instead, start it and import [`config/database.sql`](./config/database.sql) in phpMyAdmin.
4. If your MySQL settings differ from XAMPP defaults, set `CC_DB_HOST`, `CC_DB_PORT`, `CC_DB_NAME`, `CC_DB_USER`, and `CC_DB_PASSWORD` in the Apache environment. Defaults are in [`config/database.php`](./config/database.php).
5. Open `http://localhost/CampusConnect2/`.

The demo account is `mod@campus.edu.ph` / `mod12345`. The dashboard and demo login both work without a MySQL server. Demo data is created the first time each account visits the app. Passwords are hashed; each account has its own app data.

The admin account is selected by `CC_ADMIN_EMAIL`, which defaults to `s2300416@usls.edu.ph`. That account must be registered and is the only account treated as an admin; other accounts are moderators. Only the admin can open the Restricted page, where restrictions are stored globally and prevent the selected moderator account from creating posts or comments. The app creates the restrictions table automatically for SQLite and MySQL; `config/database.sql` also defines it for fresh MySQL installs.

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

## Queue rules

Pending posts are split into **To approve** and **Reported**. Open a post to review it before approving or removing it. The queue's AI Moderation switch saves a per-account preference only; it does not automatically review or change posts.

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
