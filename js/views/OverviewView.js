// VIEW: moderation summary and recent activity.
import { ShellView, esc, ago } from './ShellView.js';

export class OverviewView extends ShellView {
  constructor() { super({ tabs: 'full', tab: 'Overview' }); }
  render(c, log) {
    const stat = (n, label, href) => `<a class="stat" href="${href}"><b>${n}</b>${label}</a>`;
    this.set(`
      <h2>Overview</h2><p class="sub">What needs your attention across your communities.</p>
      <div class="stats">
        ${stat(c.toApprove, 'To approve', 'queues.html')}${stat(c.reported, 'Reported', 'queues.html?tab=reported')}
        ${stat(c.approved, 'Approved posts', 'posts.html')}${stat(c.removed, 'Removed posts', 'posts.html')}${stat(c.comments, 'Comments', 'comments.html')}
      </div>
      <h3 class="chead">Recent moderator activity</h3>
      ${log.length ? log.map(l => `<div class="row-item"><span><b>${esc(l.mod)}</b> ${l.action.toLowerCase()} <a href="post.html?id=${l.postId}">${esc(l.title)}</a></span><small>${ago(l.t)}</small></div>`).join('')
        : '<p class="empty">No activity yet. Approve or remove a post from the queue and it shows up here.</p>'}`);
  }
}
