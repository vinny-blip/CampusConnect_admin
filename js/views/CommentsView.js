// VIEW: all comments across posts, with a remove button.
import { ShellView, esc, ago } from './ShellView.js';

export class CommentsView extends ShellView {
  constructor() { super({ tabs: 'full', tab: 'Comments' }); }
  render(list) {
    this.set(`
      <h2>Comments</h2><p class="sub">${list.length} comments. Remove any that break the rules.</p>
      ${list.length ? list.slice(0, 60).map(c => `
        <div class="row-item"><div><b>${esc(c.author)}</b> <small>${ago(c.created)} on</small> <a href="post.html?id=${c.postId}">${esc(c.postTitle)}</a><div>${esc(c.text)}</div></div>
        <button class="chip" data-act="delete" data-post="${c.postId}" data-cid="${c.id}">Remove</button></div>`).join('') : '<p class="empty">No comments to show.</p>'}`);
  }
  bindAction(h) { this.delegate('click', '[data-act="delete"]', el => h(Number(el.dataset.post), Number(el.dataset.cid))); }
}
