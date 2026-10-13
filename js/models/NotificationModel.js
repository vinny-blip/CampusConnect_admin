// MODEL: notifications (the bell icon).
const KEY = 'cc_notes';
const seed = () => [
  { id: 1, text: 'A post in Educ\'ers was reported for harassment.', link: 'queues.html?tab=reported', t: Date.now() - 12 * 60000, read: false },
  { id: 2, text: 'New mod mail: "Why was my post removed?"', link: 'modmail.html?t=1', t: Date.now() - 90 * 60000, read: false },
  { id: 3, text: 'HANDA Official sent you a message.', link: 'messages.html?t=6', t: Date.now() - 180 * 60000, read: false },
  { id: 4, text: 'Your community list was updated.', link: 'communities.html', t: Date.now() - 1500 * 60000, read: true },
  { id: 5, text: 'Weekly summary: see what happened in your communities.', link: 'overview.html', t: Date.now() - 3000 * 60000, read: true }
];

export class NotificationModel {
  constructor() { if (!localStorage.getItem(KEY)) this.#save(seed()); }
  #all() { return JSON.parse(localStorage.getItem(KEY)); }
  #save(l) { localStorage.setItem(KEY, JSON.stringify(l)); }

  list() { return this.#all().sort((a, b) => b.t - a.t); }
  unread() { return this.#all().filter(n => !n.read).length; }
  read(id) { const all = this.#all(), n = all.find(n => n.id === id); if (n) { n.read = true; this.#save(all); } }
  readAll() { this.#save(this.#all().map(n => ({ ...n, read: true }))); }
  add(text, link = 'overview.html') {
    const all = this.#all();
    all.push({ id: Math.max(0, ...all.map(n => n.id)) + 1, text, link, t: Date.now(), read: false });
    this.#save(all);
  }
}
