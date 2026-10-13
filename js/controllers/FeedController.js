// CONTROLLER: home, newest, posts, control, saved, reported, community and search all show lists of posts.
import { PageController } from './PageController.js';

const COPY = {
  home: ['Home', 'Approved posts from the communities you moderate.'],
  newest: ['Newest', 'The latest approved posts, newest first.'],
  posts: ['Posts', 'Every post across your communities. Filter by status.'],
  control: ['Control Posts/Feed', 'Pin or remove posts in the feed. Pending posts have to be checked in the <a href="queues.html">queue</a> first.'],
  saved: ['Saved', 'Posts you saved for later.'],
  reported: ['Reported', 'Posts that members reported. Review pending ones in the <a href="queues.html?tab=reported">reported queue</a>.']
};

export class FeedController extends PageController {
  constructor(mode, app, view, slug = null) {
    super(app, view); if (this.dead) return;
    this.mode = mode; this.filter = 'all';
    this.slug = slug || this.params.get('c') || 'designatmobbin';
    this.q = this.params.get('q') || '';
    if (mode === 'search') view.setSearch(this.q);
    view.bindAction((act, id) => this.act(act, id));
    view.bindFilter(f => { this.filter = f; this.refresh(); });
    this.refresh();
  }

  refresh() {
    const { posts, communities } = this.app, status = this.filter === 'all' ? null : this.filter;
    const m = this.mode, [title, sub] = COPY[m] || [];
    const show = (list, extra = {}) => this.view.render({ title, sub, posts: this.named(list), ...extra });
    if (m === 'home') show(posts.find({ status: 'approved', sort: 'hot' }));
    else if (m === 'newest') show(posts.find({ status: 'approved', sort: 'new' }));
    else if (m === 'posts' || m === 'control') show(posts.find({ status, sort: 'new' }), { filter: this.filter });
    else if (m === 'saved') show(posts.saved());
    else if (m === 'reported') show(posts.find({ reported: true, sort: 'new' }));
    else if (m === 'community') {
      const c = communities.get(this.slug);
      if (!c) return this.view.render({ title: 'Community not found', sub: '<a href="communities.html">See your communities</a>', posts: [] });
      this.view.render({ about: c, posts: this.named(posts.find({ status: 'approved', community: c.slug, sort: 'hot' })) });
    } else if (m === 'search') {
      const found = posts.find({ q: this.q, sort: 'new' });
      this.view.render({
        title: this.q ? `Results for “${this.q}”` : 'Search', posts: this.named(found), communities: communities.search(this.q),
        sub: this.q ? `${found.length} posts found.` : 'Type something in the search bar above.'
      });
    }
  }

  async act(act, id) {
    const { posts } = this.app;
    if (act === 'like') posts.toggleLike(id);
    else if (act === 'save') this.view.toast(posts.toggleSave(id) ? 'Saved' : 'Removed from saved');
    else if (act === 'pin') posts.togglePin(id);
    else if (act === 'restore') { posts.restore(id, this.user.name); this.view.toast('Sent back to the queue'); }
    else if (act === 'remove') {
      if (!await this.view.confirm('Remove this post?', 'It will disappear from the feed. You can restore it to the queue later.', 'Remove')) return;
      const r = posts.setStatus(id, 'removed', this.user.name);
      this.view.toast(r.ok ? 'Post removed' : r.error);
    }
    this.refresh();
  }
}
