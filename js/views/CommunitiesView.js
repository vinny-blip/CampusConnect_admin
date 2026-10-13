// VIEW: Manage Moderated Communities (star, "..." menu, role requests).
import { ShellView, esc, curl } from './ShellView.js';

export class CommunitiesView extends ShellView {
  constructor() {
    super({ nav: 'communities', tabs: 'full' });
    // one document-level listener opens/closes the "..." menus
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-act="menu"]'), open = b && b.nextElementSibling;
      this.page.querySelectorAll('.menu').forEach(m => { m.hidden = m === open ? !m.hidden : true; });
    });
  }
  render(list) {
    this.set(`
      <h2>Manage Moderated Communities</h2>
      <p class="sub">You can moderate up to 5 communities. <button class="linkbtn" data-act="learn">Learn more about this.</button></p>
      <h3 class="chead">Communities (${list.length}/5)</h3>
      ${list.length ? list.map(c => `
      <div class="crow">
        <span class="cicon"></span>
        <div class="info"><a class="name" href="${curl(c.slug)}">${esc(c.name)}</a><br><small>${esc(c.visitors)} visitors • ${c.contrib} contributions past week</small>
          ${c.request ? `<small class="flag"> • ${c.request} role requested</small>` : ''}</div>
        <button class="star" data-act="star" data-slug="${c.slug}" aria-label="${c.starred ? 'Remove from favorites' : 'Add to favorites'}" aria-pressed="${c.starred}">${c.starred ? '★' : '☆'}</button>
        <div class="wrap">
          <button class="more" data-act="menu" aria-haspopup="menu" aria-label="More options for ${esc(c.name)}">⋯</button>
          <div class="menu" role="menu" hidden>
            <button role="menuitem" data-act="Alumni" data-slug="${c.slug}">Request Alumni Role</button>
            <button role="menuitem" data-act="Advisor" data-slug="${c.slug}">Request Advisor Role</button>
            <button role="menuitem" data-act="roles">Learn About Mod Roles</button><hr>
            <button role="menuitem" data-act="leave" data-slug="${c.slug}">Leave Mod Team</button>
          </div>
        </div>
      </div>`).join('') : '<p class="empty">You are not moderating any communities.</p>'}`);
  }
  bindAction(h) { this.delegate('click', '[data-act]', el => h(el.dataset.act, el.dataset.slug)); }
}
