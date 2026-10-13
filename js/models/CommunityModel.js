// MODEL: the communities this moderator manages.
const KEY = 'cc_communities';
const SEED = [
  { slug: 'itters', name: "IT'ers", visitors: '2k', contrib: 200, starred: true, mod: true, request: null },
  { slug: 'engineering', name: "Engineering'ers", visitors: '2.5k', contrib: 90, starred: false, mod: true, request: null },
  { slug: 'educers', name: "Educ'ers", visitors: '1k', contrib: 112, starred: false, mod: true, request: null },
  { slug: 'cbaers', name: "CBA'ers", visitors: '3.1k', contrib: 112, starred: false, mod: true, request: null },
  { slug: 'designatmobbin', name: 'r/ designatmobbin', visitors: '1.2k', contrib: 64, starred: false, mod: true, request: null }
];

export class CommunityModel {
  constructor() { if (!localStorage.getItem(KEY)) this.#save(SEED); }
  #all() { return JSON.parse(localStorage.getItem(KEY)); }
  #save(l) { localStorage.setItem(KEY, JSON.stringify(l)); }
  #edit(slug, fn) { const all = this.#all(), c = all.find(c => c.slug === slug); if (c) { fn(c); this.#save(all); } }

  list() { return this.#all().filter(c => c.mod); }
  get(slug) { return this.#all().find(c => c.slug === slug); }
  name(slug) { return this.get(slug)?.name || slug; }
  search(q) { const s = q.trim().toLowerCase(); return s ? this.#all().filter(c => c.name.toLowerCase().includes(s)) : []; }
  toggleStar(slug) { this.#edit(slug, c => { c.starred = !c.starred; }); }
  request(slug, role) { this.#edit(slug, c => { c.request = role; }); }
  leave(slug) { this.#edit(slug, c => { c.mod = false; c.starred = false; }); }
}
