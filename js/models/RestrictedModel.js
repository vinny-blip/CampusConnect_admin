// MODEL: restricted users (members who cannot post or comment).
const KEY = 'cc_restricted';
const seed = () => [
  { username: 'anonymous7', reason: 'Repeated spam in IT\'ers', t: Date.now() - 4000 * 60000 },
  { username: 'anonymous19', reason: 'Harassment in comments', t: Date.now() - 9000 * 60000 },
  { username: 'anonymous33', reason: 'Selling items without permission', t: Date.now() - 15000 * 60000 }
];

export class RestrictedModel {
  constructor() { if (!localStorage.getItem(KEY)) this.#save(seed()); }
  #all() { return JSON.parse(localStorage.getItem(KEY)); }
  #save(l) { localStorage.setItem(KEY, JSON.stringify(l)); }

  list() { return this.#all().sort((a, b) => b.t - a.t); }
  add(username, reason) {
    const u = username.trim();
    if (!u) return { ok: false, error: 'Enter a username to restrict.' };
    if (!reason.trim()) return { ok: false, error: 'Add a reason so other moderators know why.' };
    const all = this.#all();
    if (all.some(r => r.username.toLowerCase() === u.toLowerCase())) return { ok: false, error: `${u} is already restricted.` };
    all.push({ username: u, reason: reason.trim(), t: Date.now() });
    this.#save(all);
    return { ok: true };
  }
  remove(username) { this.#save(this.#all().filter(r => r.username !== username)); }
}
