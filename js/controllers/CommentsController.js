// CONTROLLER: Comments tab.
import { PageController } from './PageController.js';

export class CommentsController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.bindAction(async (postId, cid) => {
      if (!await view.confirm('Remove this comment?', 'This cannot be undone.', 'Remove')) return;
      app.posts.removeComment(postId, cid); view.toast('Comment removed'); this.refresh();
    });
    this.refresh();
  }
  refresh() { this.view.render(this.app.posts.comments()); }
}
