# CampusConnect (MVC, front end only, no XAMPP)
1. Open this folder in VS Code  2. Right-click `login.html` → "Open with Live Server"
Demo login: mod@campus.edu.ph / mod12345

Data lives in localStorage. To put the seeded posts, mail, notifications and communities back: Profile → "Reset demo data".

## Pages
| Page | File |
|---|---|
| Login / Register | `login.html`, `register.html` |
| Home / Newest | `home.html`, `newest.html` |
| Control Posts/Feed | `control.html` |
| Queues (to approve + reported, with reasons) | `queues.html` (`dashboard.html` forwards here) |
| Mod Mail / Messages | `modmail.html`, `messages.html` |
| r/ designatmobbin / other communities | `designatmobbin.html`, `community.html?c=slug` |
| Manage Moderated Communities (the "..." menu is on each row) | `communities.html` |
| Post detail + comments | `post.html?id=1` |
| Tabs: Overview, Posts, Comments, Saved, Restricted, Reported | `overview.html`, `posts.html`, `comments.html`, `saved.html`, `restricted.html`, `reported.html` |
| Create Post, Notifications, Profile, Search | `create.html`, `notifications.html`, `profile.html`, `search.html` |

## Queue rules
Pending posts are split into **To approve** and **Reported** (each reported post shows its reason). Approve/Remove stay locked until the moderator opens the post and checks it.

## Structure
`js/models` data (localStorage) · `js/views` DOM only (`ShellView` is the shared top bar/sidebar/tabs) · `js/controllers` wiring (`PageController` guards every page).
