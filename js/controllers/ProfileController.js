// CONTROLLER: Profile.
import { PageController } from './PageController.js';
import { resetDemoData } from '../app.js';

export class ProfileController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.bindSubmit(name => {
      const r = app.users.update(name);
      if (!r.ok) return view.error(r.error);
      this.user = app.users.current(); view.setUser(this.user); this.refresh(); view.toast('Profile updated');
    });
    view.bindReset(async () => {
      if (!await view.confirm('Reset demo data?', 'Posts, mail, notifications and communities go back to how they started. Accounts are kept.', 'Reset')) return;
      resetDemoData(); location.reload();
    });
    this.refresh();
  }
  refresh() {
    const { posts, communities } = this.app;
    this.view.render(this.user, { ...posts.counts(), communities: communities.list().length, saved: posts.saved().length });
  }
}
