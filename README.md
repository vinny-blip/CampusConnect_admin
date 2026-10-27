# CampusConnect

CampusConnect is a PHP MVC moderator dashboard that requires MySQL.

## Deploy to Hostinger

The project is designed to be uploaded to the domain's document root, normally
`public_html`. Upload the repository contents so that `.htaccess`, `app/`,
`config/`, and `public/` are directly inside that document root. The root
`.htaccess` sends application requests through `public/index.php`, keeps
`public/assets/` available, and blocks direct access to application and
configuration files.

1. Create a MySQL database and database user in hPanel → **Databases →
   MySQL Databases**. Record the database name, username, password, and host.
   For Hostinger databases hosted with the website, the host is usually
   `localhost`; `Hostinger` is not a valid hostname.
2. Open phpMyAdmin for that database and import
   [`config/database.sql`](./config/database.sql). If Hostinger rejects the
   `CREATE DATABASE` or `USE` statements, remove those two statements and
   import the remaining schema into the database selected in phpMyAdmin.
3. Configure `CC_DB_HOST`, `CC_DB_PORT`, `CC_DB_NAME`, `CC_DB_USER`, and
   `CC_DB_PASSWORD` as server environment variables. If Hostinger does not pass
   those variables to PHP, create `.env` beside the project's root `.htaccess`
   and `config/` folder using `.env.example` as the template. The private `.env`
   file is ignored by Git and blocked from web access, so it must be uploaded
   separately when deploying from Git. Never put real credentials in
   `config/database.php` or commit `.env`. PHP environment values take
   precedence over `.env`. Rotate any database password that was previously
   exposed while configuring deployment.
4. Set the domain's PHP version to a supported PHP 8.x release and ensure PDO
   MySQL, fileinfo, and GD/image support are enabled. PDO SQLite is not used
   by this application.
5. If AI moderation is used, configure `OPENAI_API_KEY` through Hostinger's
   server environment mechanism. If PHP does not receive Hostinger environment
   variables, create `config/openai.local.php` on the server with only this
   content, replacing the placeholder with the new key:

   ```php
   <?php
   return 'sk-your-new-key';
   ```

   This file is excluded from Git and the `config/` directory is blocked from
   web access by `.htaccess`. The mobile backend can read this same file when
   it is deployed beside the dashboard in the expected folder structure; if
   the mobile backend is deployed separately, create the same server-only
   file at `backend_setup/config/openai.local.php`. Existing deployments may
   also use `backend_setup/openai.local.php`. Never put the real key in Git,
   JavaScript, or a public file. If moderation is unavailable, the mobile
   backend writes a reason and (for API responses) the HTTP status to
   Hostinger's PHP error log under `[CampusConnect mobile AI moderation]`.
   Check that log after reproducing the issue; HTTP 401 indicates key
   authentication, HTTP 403 indicates access/permission, and HTTP 429 indicates
   rate or quota limits. The log does not include the API key or submitted text.
6. Visit the domain and register accounts before using the dashboard. Set the
   `role` column to `admin` in phpMyAdmin for each administrator account.

For Hostinger Git deployment, configure the database environment variables in
the hosting environment or separately upload `.env` beside `.htaccess` and
`config/` (for an `/admin` install, this is typically
`public_html/admin/.env`). Since `.env` is ignored by Git, it is not included in
Git deployments. For File Manager deployment, upload the private `.env` file
alongside `.htaccess`; do not include it in a public download or commit it to
Git. Verify that the `.env` file and `public_html/.htaccess` are in the project
root.

MySQL must be available for login and dashboard access. Passwords are hashed.
Set `admin_users.role` to `admin` for each administrator; new registrations
default to `moderator`.
The Profile page supports changing the display name, uploading a JPG, PNG, or
WebP profile picture (up to 5 MB), and changing the password after verifying
the current password. The app adds `admin_users.profile_image` automatically
to existing databases; uploaded images are stored under `public/uploads/profile/`.
Only administrators can open the Restricted page. The admin dashboard tables
are prefixed with `admin_` so they remain distinct from the mobile
application's `mobile_` tables.
Moderators receive an in-app notification for each AI-flagged mobile post
recorded in the last 30 days. The header bell displays the unread count and
checks for new notifications every 30 seconds while the dashboard is open.

The Mobile data pages read the shared `mobile_users`, `mobile_posts`, and
`mobile_comments` tables directly. Admin-owned moderation state is stored in
`admin_mobile_post_moderation`, `admin_mobile_comment_moderation`, and
`admin_mobile_user_moderation`; it is linked by the mobile record IDs and does
not copy mobile content into `admin_app_state`. Mobile comment reports are
stored in `mobile_comment_reports` and appear in the web Reported page. The
mobile schema must exist in the same Hostinger database for those pages to
display data.

## Pages

| Page | Route |
|---|---|
| Login / Register | `login.php`, `register.php` |
| Home | `home.php` |
| Control Feed | `control.php` |
| Queues | Removed; `queues.php` redirects to `control.php` for backwards compatibility |
| About Us | `about.php` |
| FAQs | `faqs.php` |
| Post detail + comments | `post.php?id=1` |
| Overview, Posts, Comments, Restricted, Reported, Removed, Dismissed | `overview.php`, `posts.php`, `comments.php`, `restricted.php`, `reported.php`, `removed.php`, `dismissed.php` |
| Notifications, Profile, Search | `notifications.php`, `profile.php`, `search.php` |
| Home, Posts, Reported, Comments | `home.php`, `posts.php`, `reported.php`, `comments.php` |
| Mobile app login session history | `logs.php` |

Mobile app login logs record successful mobile-app sign-ins and explicit
sign-outs. The active-user count uses a five-minute activity window refreshed
by a heartbeat while the mobile app is in the foreground. App closures and
connection losses cannot send a reliable logout event, so those logout times
are marked Timed Out. Suspended or banned users are signed out on the next
foreground heartbeat (within about 15 seconds) and shown a restriction message.
The heartbeat checks both new token-based sessions and existing app sessions
that only have a saved user ID. Deploy the updated mobile
`mobile_session_heartbeat.php` backend file, then build and distribute the
updated Android app. The dashboard creates the
shared session-log table automatically; importing `CampusConnect/backend_setup/campusconnect_db.sql`
also includes its schema.

The existing Home, Posts, Control Feed, Reported, and Comments
pages display the shared mobile application data directly. The removed Queues
route redirects to Control Feed. The separate
`mobile-*.php` routes remain available as compatibility entry points, but are
not part of the main navigation.

## Reporting and moderation

The mobile schema provides users, posts, comments, and `mobile_reports`. The
web admin reads those report records directly and displays pending reports in
**Mobile data → Reports**. Each report includes the reported post, reporter,
reason, optional comments, status, and timestamp. A moderator can dismiss a
pending report from the web page; this updates that report's `status` without
deleting the mobile post.

Reports submitted for comments appear in **Mobile data → Reported** under
Current reports, including the comment, its author, the reporter,
reason, and optional details. A moderator can remove the reported comment or
dismiss the report without removing the comment.
Dismissed post and comment reports appear on the **Dismissed** page beside
**Removed** and can be restored to Current reports.
Removed comments are archived and available at **Mobile data → Removed** by
selecting **Comments**. Removing a comment from a report opens that archive.
The web Comments list reads the same `mobile_comments` records as the app, so
user edits and deletions are reflected there.

Suspending a mobile user requires a lift date and time in the moderator's local
time zone. New deadlines are stored in UTC to avoid differences between the
browser, PHP, and MySQL server clocks. The Users page checks every 15 seconds
and automatically restores expired suspensions; reopening the page also
performs the check. The moderator can still use **Restore** to end a suspension
early.

When AI Moderation is enabled in **Queues**, the mobile API submission
endpoints call OpenAI Moderation for new post titles/content and comments.
Flagged posts are saved but held as `pending`; flagged comments are saved but
held as `hidden`. The result is stored in the admin mobile moderation tables
for moderator review. If the API key is missing or the API is unavailable,
new content is also held for review rather than being silently treated as
approved. The AI does not delete mobile content.

Removing a post from the web admin permanently deletes it from
`mobile_posts` (including its comments and reports through the database
relationships) and creates a `mobile_notifications` record for the post owner.
The mobile app can load that record through `get_notifications.php` and show
the community-guidelines removal message to the user.

The backend also applies a profanity safeguard for common explicit terms,
including common punctuation, spacing, and number substitutions used to evade
a simple word filter. Obvious content is held even if the external moderation
response does not cross its classification threshold.

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

  helpers.php         Shared view helpers
config/
  database.php        Loads database settings from the environment
  database.sql        Schema
.env.example          Template for local database settings
.env                  Private local database settings (not committed)
public/
  assets/             CSS and images
  *.php               Page entry points
```

The application uses `public/index.php` as its front controller. `app/bootstrap.php` starts the session and registers the application autoloader, `app/Router.php` normalizes route names and aliases, and `PageController` coordinates the request with the model layer. `ViewRenderer` selects the authentication or dashboard layout, while the files under `app/Views/pages/` remain presentation-only templates. The individual files in `public/` are compatibility shims for direct PHP access; normal requests are routed through the front controller by the project-root `.htaccess`. The rewrite rules also keep `/assets/...` public while blocking direct access to application and configuration folders. If MySQL cannot be connected, the application stops with a setup error and never switches to local storage.

On the first request after this release, the application clears legacy
application-state records from the former sample-data implementation. Registered
accounts are preserved; posts, notifications, and
moderation history start empty and are created only through the
application.
