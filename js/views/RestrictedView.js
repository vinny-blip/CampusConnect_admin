// VIEW: restricted users, with a form to add one.
import { ShellView, esc, ago } from './ShellView.js';

export class RestrictedView extends ShellView {
  constructor() { super({ tabs: 'full', tab: 'Restricted' }); }
  render(list) {
    this.set(`
      <h2>Restricted</h2><p class="sub">Restricted members cannot post or comment.</p>
      <form class="form inline" novalidate><input name="username" placeholder="Username" aria-label="Username"><input name="reason" placeholder="Reason" aria-label="Reason">
        <button class="button primary">Restrict</button></form><p id="msg" class="msg" role="alert"></p>
      ${list.length ? list.map(r => `<div class="row-item"><span><b>${esc(r.username)}</b> • ${esc(r.reason)} <small>${ago(r.t)}</small></span>
        <button class="chip" data-act="unrestrict" data-user="${esc(r.username)}">Unrestrict</button></div>`).join('') : '<p class="empty">Nobody is restricted.</p>'}`);
  }
  error(text) { document.getElementById('msg').textContent = text; }
  bindSubmit(h) { this.page.addEventListener('submit', e => { e.preventDefault(); h(e.target.elements.username.value, e.target.elements.reason.value); }); }
  bindAction(h) { this.delegate('click', '[data-act="unrestrict"]', el => h(el.dataset.user)); }
}
