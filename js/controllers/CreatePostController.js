// CONTROLLER: Create Post.
import { PageController } from './PageController.js';

export class CreatePostController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    view.render(app.communities.list());
    view.bindSubmit(data => {
      const r = app.posts.add({ author: this.user.name, ...data });
      if (!r.ok) return view.error(r.error);
      view.toast('Post published');
      setTimeout(() => { location.href = `post.html?id=${r.id}`; }, 600);
    });
  }
}
