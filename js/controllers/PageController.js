// CONTROLLER (base): every page after login. Guards the route, then sets up the shared top bar.
export class PageController {
  constructor(app, view) {
    this.app = app; this.view = view;
    this.user = app.users.current();
    this.dead = !this.user;
    if (this.dead) { location.href = 'login.html'; return; }       // route guard
    view.setUser(this.user);
    this.badges();
    view.bindLogout(() => { app.users.logout(); location.href = 'login.html'; });
  }
  badges() { this.view.setBadges(this.app.notes.unread(), this.app.messages.unread('dm')); }
  /** Adds the community's display name to each post. */
  named(posts) { return posts.map(p => ({ ...p, cname: this.app.communities.name(p.community) })); }
  get params() { return new URLSearchParams(location.search); }
}
