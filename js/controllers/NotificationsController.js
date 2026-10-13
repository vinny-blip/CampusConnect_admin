// CONTROLLER: Notifications. Clicking one marks it read and follows its link.
import { PageController } from './PageController.js';

export class NotificationsController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.bindOpen(id => app.notes.read(id));
    view.bindReadAll(() => { app.notes.readAll(); this.refresh(); view.toast('All caught up'); });
    this.refresh();
  }
  refresh() { this.view.render(this.app.notes.list()); this.badges(); }
}
