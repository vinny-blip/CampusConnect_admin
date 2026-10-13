// VIEW: Mod Mail and Messages share one two-pane layout (thread list + conversation).
import { ShellView, esc, ago } from './ShellView.js';

export class InboxView extends ShellView {
  constructor(box) { super(box === 'mod' ? { nav: 'modmail' } : {}); this.box = box; }

  render({ threads, current }) {
    const mod = this.box === 'mod';
    this.set(`
      <h2>${mod ? 'Mod Mail' : 'Messages'}</h2>
      <p class="sub">${mod ? 'Conversations between students and the moderator team.' : 'Direct messages with other members.'}</p>
      <div class="inbox">
        <div class="tlist"><button class="titem new" data-act="new">＋ New ${mod ? 'mod mail' : 'message'}</button>
          ${threads.map(t => `<button class="titem${t.id === current?.id ? ' on' : ''}${t.unread ? ' unread' : ''}" data-act="open" data-id="${t.id}">
            <b>${esc(t.with)}</b> <small>${ago(t.messages[t.messages.length - 1].t)}</small><br><small>${esc(t.subject || t.messages[t.messages.length - 1].text)}</small></button>`).join('')}</div>
        <section class="tview">${current ? this.#thread(current) : this.#form(mod)}</section>
      </div>`);
    const m = this.page.querySelector('.msgs'); if (m) m.scrollTop = m.scrollHeight;
  }
  #thread(t) {
    return `<h3>${esc(t.subject || t.with)}</h3><p class="sub">${this.box === 'mod' ? 'From' : 'With'} ${esc(t.with)}</p>
      <div class="msgs">${t.messages.map(m => `<div class="bubble${m.from === 'me' ? ' me' : ''}"><small>${m.from === 'me' ? 'You' : esc(m.from)} • ${ago(m.t)}</small><br>${esc(m.text)}</div>`).join('')}</div>
      <form class="reply" data-kind="reply" data-id="${t.id}"><input name="text" placeholder="Write a reply…" aria-label="Write a reply" autocomplete="off"><button class="button primary">Send</button></form>`;
  }
  #form(mod) {
    return `<h3>New ${mod ? 'mod mail' : 'message'}</h3>
      <form class="form" data-kind="new"><label for="who">To</label><input id="who" name="who" autocomplete="off">
      ${mod ? '<label for="subject">Subject</label><input id="subject" name="subject" autocomplete="off">' : ''}
      <label for="text">Message</label><textarea id="text" name="text" rows="4"></textarea>
      <p id="msg" class="msg" role="alert"></p><button class="button primary">Send</button></form>`;
  }
  error(text) { const m = document.getElementById('msg'); if (m) m.textContent = text; }
  bindAction(h) { this.delegate('click', '[data-act]', el => h(el.dataset.act, Number(el.dataset.id))); }
  bindSubmit(h) {
    this.page.addEventListener('submit', e => {
      e.preventDefault();
      h(e.target.dataset.kind, Number(e.target.dataset.id), Object.fromEntries(new FormData(e.target)));
    });
  }
}
