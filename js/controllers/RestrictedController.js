// CONTROLLER: Restricted tab.
import { PageController } from './PageController.js';

export class RestrictedController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.bindSubmit((username, reason) => {
      const r = app.restricted.add(username, reason);
      if (!r.ok) return view.error(r.error);
      view.toast(`${username.trim()} restricted`); this.refresh();
    });
    view.bindAction(user => { app.restricted.remove(user); view.toast(`${user} can post again`); this.refresh(); });
    this.refresh();
  }
  refresh() { this.view.render(this.app.restricted.list()); }
}
