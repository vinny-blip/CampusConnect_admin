// CONTROLLER: the Queues page. Approve/Remove are locked until the moderator has opened (checked) the post.
import { PageController } from './PageController.js';

export class DashboardController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    this.kind = this.params.get('tab') === 'reported' ? 'reported' : 'approve';
    this.query = '';
    view.init();
    view.bindFilter(k => { this.kind = k; this.refresh(); });
    view.bindSearch(q => { this.query = q; this.refresh(); });
    view.bindAction((id, status) => {
      const r = app.posts.setStatus(id, status, this.user.name);
      view.toast(r.ok ? (status === 'approved' ? 'Post approved' : 'Post removed') : r.error);
      this.refresh();
    });
    this.refresh();
  }
  refresh() {
    const { posts } = this.app;
    this.view.render({ kind: this.kind, counts: posts.counts(), posts: this.named(posts.queue(this.kind, this.query)) });
  }
}
