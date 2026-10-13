// CONTROLLER: Manage Moderated Communities.
import { PageController } from './PageController.js';

export class CommunitiesController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.bindAction((act, slug) => this.act(act, slug));
    this.refresh();
  }
  refresh() { this.view.render(this.app.communities.list()); }

  async act(act, slug) {
    const { communities, notes } = this.app, name = communities.name(slug);
    if (act === 'star') communities.toggleStar(slug);
    else if (act === 'Alumni' || act === 'Advisor') {
      communities.request(slug, act);
      notes.add(`${act} role requested for ${name}.`, 'communities.html');
      this.badges(); this.view.toast(`${act} role requested for ${name}`);
    } else if (act === 'roles' || act === 'learn') {
      await this.view.modal('Moderator roles', `<p><b>Moderator</b> checks the queues and keeps the feed tidy.</p>
        <p><b>Alumni</b> advises current moderators from experience and can help with mod mail.</p>
        <p><b>Advisor</b> is a faculty or staff member who oversees a community. You can moderate up to 5 communities at once.</p>`);
      return;
    } else if (act === 'leave') {
      if (!await this.view.confirm(`Leave ${name}?`, 'You will stop moderating this community. You can rejoin from Profile → Reset demo data.', 'Leave')) return;
      communities.leave(slug); this.view.toast(`You left ${name}`);
    } else return;
    this.refresh();
  }
}
