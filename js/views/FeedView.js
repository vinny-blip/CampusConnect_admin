// VIEW: lists of post cards. One class serves home, newest, posts, control, saved, reported, community and search.
import { ShellView, esc, ago, curl } from './ShellView.js';

const CFG = (mode, slug) => ({
  home: { nav: 'home' }, newest: { nav: 'newest' },
  control: { nav: 'control', tabs: 'full' },
  posts: { tabs: 'full', tab: 'Posts' }, saved: { tabs: 'full', tab: 'Saved' }, reported: { tabs: 'full', tab: 'Reported' },
  community: { nav: slug === 'designatmobbin' ? 'community' : null }, search: {}
})[mode];
const cap = s => s[0].toUpperCase() + s.slice(1);

export class FeedView extends ShellView {
  constructor(mode, slug) { super(CFG(mode, slug)); this.mode = mode; }

  render({ title, sub = '', posts, filter = null, about = null, communities = null }) {
    this.set(`
      ${about ? `<div class="about"><span class="cicon big"></span><div><h2>${esc(about.name)}</h2>
        <p class="sub">${esc(about.visitors)} visitors • ${about.contrib} contributions past week</p></div>
        <a class="button" href="communities.html">Manage</a></div>` : `<h2>${esc(title)}</h2>`}
      ${sub ? `<p class="sub">${sub}</p>` : ''}
      ${communities ? `<h3 class="chead">Communities</h3>${communities.length ? communities.map(c => `
        <div class="crow"><span class="cicon"></span><div class="info"><a class="name" href="${curl(c.slug)}">${esc(c.name)}</a><br>
        <small>${esc(c.visitors)} visitors • ${c.contrib} contributions past week</small></div></div>`).join('') : '<p class="empty">No matching communities.</p>'}<h3 class="chead">Posts</h3>` : ''}
      ${filter ? `<div class="filters" id="filters">${['all', 'pending', 'approved', 'removed'].map(f =>
        `<button data-filter="${f}" class="${f === filter ? 'on' : ''}">${cap(f)}</button>`).join('')}<span class="count">${posts.length} posts</span></div>` : ''}
      <div>${posts.length ? posts.map(p => this.#card(p)).join('') : '<p class="empty">Nothing to show here yet.</p>'}</div>`);
  }

  #card(p) {
    const showTag = !['home', 'newest', 'community'].includes(this.mode);
    return `<article class="card">
      <div>
        <div class="meta"><span class="avatar sm">${esc(p.author[0].toUpperCase())}</span>
          <a href="${curl(p.community)}"><b>${esc(p.cname)}</b></a><span>• ${esc(p.author)}</span><span>• ${ago(p.created)}</span>
          ${p.pinned ? '<span class="tag">📌 Pinned</span>' : ''}${showTag ? `<span class="tag ${p.status}">${p.status}</span>` : ''}
          ${p.reported && p.status === 'pending' ? `<span class="flag">• Reported: ${esc(p.reportReason)}</span>` : ''}
        </div>
        <h3><a href="post.html?id=${p.id}${p.status === 'pending' ? '&review=1' : ''}">${esc(p.title)}</a></h3>
        <div class="foot">${this.#foot(p)}</div>
      </div>
      <img class="thumb" src="${p.image}" alt="Image attached to ${esc(p.author)}'s post">
    </article>`;
  }

  #foot(p) {
    const id = `data-id="${p.id}"`;
    const like = `<button class="chip" data-act="like" ${id}>${p.liked ? '♥' : '♡'} ${p.likes}</button>`;
    const cm = `<a class="chip" href="post.html?id=${p.id}">💬 ${p.commentCount}</a>`;
    const save = `<button class="chip" data-act="save" ${id}>${p.saved ? '★ Saved' : '☆ Save'}</button>`;
    const review = `<a class="chip ok" href="post.html?id=${p.id}&review=1">Review</a>`;
    if (this.mode === 'control') {
      if (p.status === 'approved') return `${cm}<button class="chip" data-act="pin" ${id}>${p.pinned ? 'Unpin' : 'Pin'}</button><button class="chip" data-act="remove" ${id}>Remove</button>`;
      if (p.status === 'removed') return `${cm}<button class="chip" data-act="restore" ${id}>Restore to queue</button>`;
      return `${cm}${review}`;
    }
    if (this.mode === 'reported') return `${cm}${p.status === 'pending' ? review : ''}`;
    return `${like}${cm}${save}${['posts', 'search'].includes(this.mode) && p.status === 'pending' ? review : ''}`;
  }

  bindAction(h) { this.delegate('click', '[data-act]', el => h(el.dataset.act, Number(el.dataset.id))); }
  bindFilter(h) { this.delegate('click', '[data-filter]', el => h(el.dataset.filter)); }
}
