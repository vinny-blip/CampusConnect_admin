# CampusConnect

CampusConnect is a PHP MVC moderator dashboard that runs on XAMPP. It uses MySQL when available and automatically falls back to a local SQLite file when MySQL is unavailable or its tables have not been created.

## Run with XAMPP

1. Keep this folder under `C:\xampp\htdocs\CampusConnect2`.
2. Start Apache in the XAMPP Control Panel. MySQL is optional.
3. MySQL is optional. If it is unavailable or its tables are missing, the app creates and uses `app/data/campusconnect.sqlite` automatically (PHP's PDO SQLite extension must be enabled). To use MySQL instead, start it and import [`config/database.sql`](./config/database.sql) in phpMyAdmin.
4. If your MySQL settings differ from XAMPP defaults, set `CC_DB_HOST`, `CC_DB_PORT`, `CC_DB_NAME`, `CC_DB_USER`, and `CC_DB_PASSWORD` in the Apache environment. Defaults are in [`config/database.php`](./config/database.php).
5. To use AI moderation, set `OPENAI_API_KEY` in the Apache environment. Do not commit the key to the repository.
6. Open `http://localhost/CampusConnect2/`.

## Deploy to Hostinger

The project is designed to be uploaded to the domain's document root, normally
`public_html`. Upload the repository contents so that `.htaccess`, `app/`,
`config/`, and `public/` are directly inside that document root. The root
`.htaccess` sends application requests through `public/index.php`, keeps
`public/assets/` available, and blocks direct access to application and
configuration files.

1. Create a MySQL database and database user in hPanel → **Databases →
   MySQL Databases**. Record the database name, username, password, and host.
2. Open phpMyAdmin for that database and import
   [`config/database.sql`](./config/database.sql). If Hostinger rejects the
   `CREATE DATABASE` or `USE` statements, remove those two statements and
   import the remaining schema into the database selected in phpMyAdmin.
3. The server-only `config/database.local.php` contains the Hostinger database
   connection values. Keep this file server-only. It is ignored by Git and
   blocked by Apache, so the password is not published. Because credentials
   may have been exposed while configuring deployment, rotate the database
   password after the site is working.
4. Set the domain's PHP version to a supported PHP 8.x release and ensure PDO
   MySQL, fileinfo, and GD/image support are enabled. SQLite is only a local
   fallback and is not required on Hostinger.
5. If AI moderation is used, configure `OPENAI_API_KEY` through Hostinger's
   server environment mechanism or add the existing server-side secret using
   the hosting control panel. Never put that key in Git or in a public file.
6. Visit the domain. The first request creates the demo moderator account in
   MySQL; then register the configured admin email before using admin-only
   features.

For Hostinger Git deployment, deploy the repository into `public_html`, then
create `config/database.local.php` through File Manager or SSH after each
fresh deployment. For File Manager deployment, upload a ZIP, extract it
directly into `public_html`, and verify that `public_html/.htaccess` exists.

The demo account is `mod@campus.edu.ph` / `mod12345`. The dashboard and demo login both work without a MySQL server. Demo data is created the first time each account visits the app. Passwords are hashed; each account has its own app data.

The admin account is selected by `CC_ADMIN_EMAIL`, which defaults to `s2300416@usls.edu.ph`. That account must be registered and is the only account treated as an admin; other accounts are moderators. Moderators can manage up to 10 communities and create one with a name, category, and description. Only the admin can open the Restricted page, where restrictions are stored globally and prevent the selected moderator account from creating posts or comments. The admin dashboard tables are prefixed with `admin_` (`admin_users`, `admin_app_state`, `admin_user_restrictions`, and `admin_ai_moderation_settings`) so they remain distinct from the mobile application's `mobile_` tables.

## Pages

| Page | Route |
|---|---|
| Login / Register | `login.php`, `register.php` |
| Home / Newest | `home.php`, `newest.php` |
| Control Posts/Feed | `control.php` |
| Queues | `queues.php` (`dashboard.php` forwards here) |
| Mod Mail / Messages | `modmail.php`, `messages.php` |
| Communities | `designatmobbin.php`, `community.php?c=slug`, `communities.php`, `create-community.php` |
| About Us | `about.php` |
| FAQs | `faqs.php` |
| Post detail + comments | `post.php?id=1` |
| Overview, Posts, Comments, Saved, Restricted, Reported | `overview.php`, `posts.php`, `comments.php`, `saved.php`, `restricted.php`, `reported.php` |
| Create Post, Notifications, Profile, Search | `create.php`, `notifications.php`, `profile.php`, `search.php` |

## Reporting and moderation

Users report posts and comments through the mobile app as non-academic, toxic or harassing, plagiarized, or another guideline violation. Reported posts and comments appear in **Queues → Reported** for web moderators to review. Moderators can dismiss reports, approve pending posts, hide posts or comments, and permanently delete reported content.

Demo data includes report examples that demonstrate unrelated promotions, personal harassment, and uncredited academic work. These are fictional examples for testing the moderation workflow; existing customized posts are not replaced.

On a post's detail page, moderators can edit its title and text, add up to five tags, lock or unlock comments, and permanently delete comments. Hidden comments can be restored from the Comments page. Any student moderator can toggle the globally shared AI Moderation setting, which calls OpenAI's Moderation API for submitted text and supported images. Flagged posts stay pending and flagged comments stay hidden until the admin reviews them; API errors also hold content for review. The AI never removes content. Enabling this feature sends submission text and supported images to OpenAI, so disclose this in the student privacy notice. The API does not determine academic relevance or plagiarism, so those guidelines still require other checks and human review.

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

The application uses `public/index.php` as its front controller. `app/bootstrap.php` starts the session and registers the application autoloader, `app/Router.php` normalizes route names and aliases, and `PageController` coordinates the request with the model layer. `ViewRenderer` selects the authentication or dashboard layout, while the files under `app/Views/pages/` remain presentation-only templates. The individual files in `public/` are compatibility shims for direct XAMPP access; normal requests are routed through the front controller by the project-root `.htaccess`. The rewrite rules also keep `/assets/...` public while blocking direct access to application and configuration folders.
