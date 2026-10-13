// CONTROLLER: Overview tab.
import { PageController } from './PageController.js';

export class OverviewController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.render(app.posts.counts(), app.posts.activity(8));
  }
}
