// VIEW (base): the CampusConnect frame (top bar, sidebar, tabs) shared by every page after login.
// Page views extend this class and fill the #page area. No data logic here.
export const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
export const ago = ts => {
  const m = Math.max(1, Math.round((Date.now() - ts) / 60000));
  return m < 60 ? `${m}m` : m < 1440 ? `${Math.floor(m / 60)}h` : `${Math.floor(m / 1440)}d`;
};
export const initials = n => n.split(/\s+/).map(w => w[0] || '').join('').slice(0, 2).toUpperCase();
export const curl = slug => slug === 'designatmobbin' ? 'designatmobbin.html' : `community.html?c=${slug}`;

const TABS = [['Overview', 'overview.html'], ['Posts', 'posts.html'], ['Comments', 'comments.html'],
  ['Saved', 'saved.html'], ['Restricted', 'restricted.html'], ['Reported', 'reported.html']];
const SHORT = ['Overview', 'Posts', 'Reported'];

export class ShellView {
  /** nav: highlighted sidebar item. tabs: 'full' | 'short' | null. tab: highlighted tab. fill: let the page manage its own scrolling. */
  constructor({ nav = null, tab = null, tabs = null, fill = false } = {}) {
    const link = (key, text, href, ico = '') =>
      `<a href="${href}" class="${key === nav ? 'on' : ''}"${key === nav ? ' aria-current="page"' : ''}>${ico ? `<span class="ico" aria-hidden="true">${ico}</span>` : ''}${text}</a>`;
    const list = tabs === 'short' ? TABS.filter(t => SHORT.includes(t[0])) : tabs ? TABS : [];
    document.getElementById('app').innerHTML = `
    <div class="shell">
      <header class="topbar">
        <a class="brand" href="home.html"><img src="campusLogo.png" alt="">CampusConnect</a>
        <form class="searchform" action="search.html" method="get" role="search">
          <input id="search" class="search" name="q" type="search" placeholder="Search CampusConnect" aria-label="Search CampusConnect">
        </form>
        <div class="top-actions">
          <a class="icon-btn" href="messages.html" aria-label="Messages" title="Messages">💬<span id="msgBadge" class="badge"></span></a>
          <a class="create" href="create.html"><span aria-hidden="true">＋</span> Create Post</a>
          <a class="icon-btn" href="notifications.html" aria-label="Notifications" title="Notifications">🔔<span id="noteBadge" class="badge"></span></a>
          <a id="avatar" class="avatar" href="profile.html" aria-label="Profile" title="Profile"></a>
        </div>
      </header>
      <div class="body">
        <nav class="sidebar" aria-label="Main">
          ${link('home', 'Home', 'home.html', '⌂')}${link('newest', 'Newest', 'newest.html', '↗')}${link('control', 'Control Posts/Feed', 'control.html')}
          <h4><a href="communities.html" class="${nav === 'communities' ? 'on' : ''}">Moderating</a></h4>
          ${link('queues', 'Queues', 'queues.html', '▣')}${link('modmail', 'Mod Mail', 'modmail.html', '✉')}${link('community', 'r/ designatmobbin', 'designatmobbin.html', '●')}
          <button id="logout" class="logout" type="button">Log out</button>
        </nav>
        <main class="main">
          ${list.length ? `<nav class="tabs" aria-label="Moderation">${list.map(([n, h]) => `<a href="${h}" class="${n === tab ? 'on' : ''}">${n}</a>`).join('')}</nav>` : ''}
          <div id="page" class="page${fill ? ' fill' : ''}"></div>
        </main>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', '<div id="toast" class="toast" role="status"></div>');
    this.page = document.getElementById('page');
    this.toastEl = document.getElementById('toast');
  }
  set(html) { this.page.innerHTML = html; }
  setUser(user) { document.getElementById('avatar').textContent = initials(user.name); }
  setBadges(notes, msgs) {
    document.getElementById('noteBadge').textContent = notes || '';
    document.getElementById('msgBadge').textContent = msgs || '';
  }
  setSearch(v) { document.getElementById('search').value = v; }
  bindLogout(h) { document.getElementById('logout').addEventListener('click', h); }
  /** Event delegation inside the page area, so re-rendering never loses handlers. */
  delegate(type, selector, h) {
    this.page.addEventListener(type, e => { const el = e.target.closest(selector); if (el) h(el, e); });
  }
  toast(text) {
    this.toastEl.textContent = text; this.toastEl.classList.add('show');
    clearTimeout(this._t); this._t = setTimeout(() => this.toastEl.classList.remove('show'), 1800);
  }
  /** Resolves with the value of the pressed button (false when dismissed). buttons: [[label, value], ...] */
  modal(title, html, buttons = [['Close', true]]) {
    return new Promise(resolve => {
      const back = document.createElement('div');
      back.className = 'modal-back';
      back.innerHTML = `<div class="modal" role="dialog" aria-modal="true" aria-label="${esc(title)}"><h3>${esc(title)}</h3><div>${html}</div>
        <div class="row">${buttons.map(([l], i) => `<button class="button${i === buttons.length - 1 ? ' primary' : ''}" data-v="${i}">${esc(l)}</button>`).join('')}</div></div>`;
      const done = v => { back.remove(); document.removeEventListener('keydown', onKey); resolve(v); };
      const onKey = e => { if (e.key === 'Escape') done(false); };
      back.addEventListener('click', e => {
        const b = e.target.closest('[data-v]');
        if (b) done(buttons[b.dataset.v][1]); else if (e.target === back) done(false);
      });
      document.addEventListener('keydown', onKey);
      document.body.append(back);
      back.querySelector('[data-v]:last-child').focus();
    });
  }
  confirm(title, text, ok = 'Confirm') { return this.modal(title, `<p>${esc(text)}</p>`, [['Cancel', false], [ok, true]]); }
}
