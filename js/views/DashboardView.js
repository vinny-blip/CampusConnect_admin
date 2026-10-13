// VIEW: the Queues page. Two separate lists: posts waiting for approval and reported posts (with the reason).
import { ShellView, esc, ago, curl } from './ShellView.js';

export class DashboardView extends ShellView {
  constructor() { super({ nav: 'queues', tabs: 'full', fill: true }); }

  init() {
    this.set(`
      <h2>Queues</h2>
      <p class="sub">Open each post and check it before you approve or remove it. Reported posts are kept in their own list.</p>
      <div class="filters" id="filters">
        <button data-filter="approve"></button><button data-filter="reported"></button>
        <input id="qsearch" class="localsearch" type="search" placeholder="Filter this queue" aria-label="Filter this queue">
        <span id="count" class="count"></span>
      </div>
      <section id="queue" class="queue" aria-live="polite"></section>`);
    this.queue = document.getElementById('queue');
    this.count = document.getElementById('count');
    this.filters = document.getElementById('filters');
  }
  render({ kind, counts, posts }) {
    const [a, r] = this.filters.querySelectorAll('button');
    a.textContent = `To approve (${counts.toApprove})`; r.textContent = `Reported (${counts.reported})`;
    this.setActiveFilter(kind);
    this.count.textContent = `${posts.length} shown`;
    this.queue.innerHTML = posts.length ? posts.map(p => {
      const lock = p.reviewed ? '' : ' disabled title="Review this post first"';
      return `
      <article class="post" data-id="${p.id}">
        <div>
          <div class="meta">
            <span class="avatar sm">${esc(p.author[0].toUpperCase())}</span><b>${esc(p.author)}</b>
            <span>• <a href="${curl(p.community)}">${esc(p.cname)}</a></span><span>• ${ago(p.created)}</span><span>• ${p.likes} likes</span><span>• ${p.commentCount} comments</span>
            ${p.reported ? `<span class="flag">• Reported: ${esc(p.reportReason)}</span>` : ''}
          </div>
          <p class="title"><a href="post.html?id=${p.id}&review=1">${esc(p.title)}</a></p>
          <span class="tag ${p.reviewed ? 'approved' : 'pending'}">${p.reviewed ? 'Checked' : 'Needs review'}</span>
        </div>
        <img class="thumb" src="${p.image}" alt="Attached image for ${esc(p.author)}'s post">
        <div class="acts">
          <a href="post.html?id=${p.id}&review=1">${p.reviewed ? 'View' : 'Review'}</a>
          <button class="ok" data-act="approved"${lock}>Approve</button>
          <button data-act="removed"${lock}>Remove</button>
        </div>
      </article>`;
    }).join('') : `<p class="empty">${kind === 'reported' ? 'No reported posts waiting. Nice work.' : 'Nothing left to approve. Nice work.'}</p>`;
  }
  setActiveFilter(name) {
    this.filters.querySelectorAll('button').forEach(b => b.classList.toggle('on', b.dataset.filter === name));
  }
  bindFilter(h) { this.filters.addEventListener('click', e => e.target.dataset.filter && h(e.target.dataset.filter)); }
  bindSearch(h) { document.getElementById('qsearch').addEventListener('input', e => h(e.target.value)); }
  bindAction(h) {
    this.queue.addEventListener('click', e => {
      const act = e.target.dataset.act;
      if (act) h(Number(e.target.closest('.post').dataset.id), act);
    });
  }
}
