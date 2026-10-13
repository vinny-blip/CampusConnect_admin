// CONTROLLER: post detail. Opening a pending post counts as checking it, which unlocks Approve/Remove in the queue.
import { PageController } from './PageController.js';

export class PostController extends PageController {
  constructor(app, view) {
    super(app, view); if (this.dead) return;
    this.id = Number(this.params.get('id'));
    if (!app.posts.get(this.id)) return view.notFound();
    app.posts.markReviewed(this.id);
    const ref = document.referrer && new URL(document.referrer).origin === location.origin ? document.referrer : 'posts.html';
    this.back = this.params.has('review') ? 'queues.html' : ref;
    view.bindAction((act, d) => this.act(act, d));
    view.bindComment(text => this.comment(text));
    this.refresh();
  }
  refresh() { this.view.render(this.named([this.app.posts.get(this.id)])[0], this.back); }

  act(act, d) {
    const { posts } = this.app, cid = Number(d.cid);
    if (act === 'approved' || act === 'removed') {
      const r = posts.setStatus(this.id, act, this.user.name);
      if (!r.ok) return this.view.toast(r.error);
      this.view.toast(act === 'approved' ? 'Post approved' : 'Post removed');
      if (this.params.has('review')) return void setTimeout(() => { location.href = 'queues.html'; }, 700);
    } else if (act === 'save') this.view.toast(posts.toggleSave(this.id) ? 'Saved' : 'Removed from saved');
    else if (act === 'like') posts.reactComment(this.id, cid, 'likes');
    else if (act === 'heart') posts.reactComment(this.id, cid, 'hearts');
    else if (act === 'reply') return this.view.prefill(`@${d.who} `);
    this.refresh();
  }
  comment(text) {
    if (!text.trim()) return this.view.toast('Write a comment first');
    this.app.posts.addComment(this.id, this.user.name, text);
    this.refresh();
  }
}
