// VIEW: one post with its comments (the post detail mockup). Also the screen where a moderator checks a pending post.
import { ShellView, esc, ago, curl } from './ShellView.js';

export class PostView extends ShellView {
  constructor() { super({ tabs: 'short', tab: 'Posts' }); }

  render(p, back) {
    const pending = p.status === 'pending';
    this.set(`
      <p class="sub"><a href="${esc(back)}">← Back</a></p>
      <div class="review">
        <span class="tag ${p.status}">${p.status}</span>
        <a href="${curl(p.community)}"><b>${esc(p.cname)}</b></a><span>• ${esc(p.author)}</span><span>• ${ago(p.created)}</span>
        ${p.reported ? `<span class="flag">• Reported: ${esc(p.reportReason)}</span>` : ''}
        <span class="grow"></span>
        ${pending ? '<button class="button primary" data-act="approved">Approve</button><button class="button danger" data-act="removed">Remove</button>' : ''}
      </div>
      <div class="detail">
        <div>
          <div class="media"><img src="${p.image}" alt="Image attached to ${esc(p.author)}'s post"></div>
          <div class="cap"><button class="bookmark" data-act="save" aria-label="${p.saved ? 'Unsave post' : 'Save post'}">${p.saved ? '♥' : '♡'}</button><span>${esc(p.title)}</span></div>
        </div>
        <aside class="cbox">
          <b>💬 Comments (${p.commentList.length})</b>
          <div class="clist">${p.commentList.length ? p.commentList.map(c => `
            <div class="cm"><b>${esc(c.author)}</b><small>${ago(c.created)}</small><div>${esc(c.text)}</div>
              <div class="rx"><button data-act="like" data-cid="${c.id}">👍 ${c.likes}</button><button data-act="heart" data-cid="${c.id}">♥ ${c.hearts}</button>
              <button data-act="reply" data-who="${esc(c.author)}">Reply</button></div></div>`).join('') : '<p class="empty">No comments yet.</p>'}</div>
          <div class="cwrite"><input id="cin" placeholder="😊 Write a comment…" aria-label="Write a comment"><button id="csend" aria-label="Send comment">➤</button></div>
        </aside>
      </div>
      ${p.body ? `<p class="bodytext">${esc(p.body)}</p>` : ''}`);
  }
  notFound() { this.set('<h2>Post not found</h2><p class="sub">This post does not exist. <a href="posts.html">Go to all posts</a>.</p>'); }

  bindAction(h) { this.delegate('click', '[data-act]', el => h(el.dataset.act, el.dataset)); }
  bindComment(h) {
    const send = () => { const i = document.getElementById('cin'); h(i.value); };
    this.delegate('click', '#csend', send);
    this.page.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.id === 'cin') send(); });
  }
  prefill(text) { const i = document.getElementById('cin'); i.value = text; i.focus(); }
}
