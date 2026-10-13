// VIEW: notifications list.
import { ShellView, esc, ago } from './ShellView.js';

export class NotificationsView extends ShellView {
  constructor() { super({}); }
  render(list) {
    this.set(`
      <h2>Notifications</h2><p class="sub"><button class="linkbtn" data-act="all">Mark all as read</button></p>
      ${list.map(n => `<a class="row-item${n.read ? '' : ' unread'}" href="${n.link}" data-id="${n.id}"><span>${n.read ? '' : '● '}${esc(n.text)}</span><small>${ago(n.t)}</small></a>`).join('')}`);
  }
  bindOpen(h) { this.delegate('click', 'a[data-id]', el => h(Number(el.dataset.id))); }
  bindReadAll(h) { this.delegate('click', '[data-act="all"]', h); }
}
