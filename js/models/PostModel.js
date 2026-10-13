// MODEL: posts, their comments, saved posts and the moderation log. Seeds 40 fake posts the first time it runs.
const KEY = 'cc_posts_v2', SAVED = 'cc_saved', LOG = 'cc_log';
const SLUGS = ['itters', 'engineering', 'educers', 'cbaers', 'designatmobbin'];
const TITLES = [
  'Hi! Can anyone help me with this one, please?',
  'How do I solve this limit problem?',
  'Anyone have notes for Calculus 2 midterms?',
  'Is the library open on weekends?',
  'Looking for a study group for Physics 101',
  'Can someone explain this derivative rule?',
  'Selling my used Engineering textbook',
  'Who has the schedule for enrollment week?'
];
const REASONS = [
  'Spam or advertising', 'Harassment or bullying', 'Misleading information',
  'Off-topic for this community', 'Inappropriate content', 'Selling without permission'
];
const COMMENTS = [
  ['Juan Dela Cruz', 'The road near Lacson Street is flooded, take another route.'],
  ['Maria Santos', 'Thanks for the update! Very helpful.'],
  ['HANDA Official', 'The evacuation center is open until 8 PM.'],
  ['Ana Reyes', 'Same question here, following this thread.'],
  ['Carlo Lim', 'Try the notes posted in the group files.']
];
const PALETTE = [['#9ccbff', '#b2b0d6'], ['#c8e6a0', '#8fcf7a'], ['#ffd9a0', '#f4a6a0'], ['#d4c4f0', '#9ccbff'], ['#bfe9e1', '#8bc4d8']];
const DOTS = ['#e63946', '#f4a261', '#2a9d8f', '#9b5de5', '#ffd166', '#4d96ff'];

/** Small generated illustration (a ring of friends) so every post has a real image. */
export const art = seed => {
  const [bg, ground] = PALETTE[seed % PALETTE.length];
  const dots = DOTS.map((c, i) => {
    const t = (i / 6) * Math.PI * 2;
    return `<circle cx="${(160 + Math.cos(t) * 55).toFixed(1)}" cy="${(95 + Math.sin(t) * 30).toFixed(1)}" r="14" fill="${c}"/>`;
  }).join('');
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 190"><rect width="320" height="190" fill="${bg}"/><ellipse cx="160" cy="105" rx="120" ry="60" fill="${ground}"/>${dots}</svg>`;
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
};

const ago = m => Date.now() - m * 60000;
const read = (k, d) => JSON.parse(localStorage.getItem(k) || 'null') ?? d;

export class PostModel {
  constructor() { if (!localStorage.getItem(KEY)) this.reset(); }

  reset() {
    this.#save(Array.from({ length: 40 }, (_, i) => {
      const status = i % 5 === 4 ? 'approved' : 'pending';
      const reported = status === 'pending' && i % 6 === 0;
      const n = i === 0 ? 3 : i % 5;
      return {
        id: i + 1,
        author: `anonymous${i + 1}`,
        community: SLUGS[i % SLUGS.length],
        title: i === 0 ? 'Cherish the moments with your friends!' : TITLES[i % TITLES.length],
        body: i === 0
          ? 'Take a moment today to thank the people who made this semester easier. Share a memory with your friends in the comments.'
          : 'Posted by a student. Replies and notes from classmates are welcome in the comments.',
        image: art(i),
        created: ago(5 + i * 13),
        likes: (i * 7) % 45 + 3, liked: false, pinned: false,
        status, reported,
        reportReason: reported ? REASONS[(i / 6) % REASONS.length] : '',
        reviewed: false,
        commentList: Array.from({ length: n }, (_, k) => ({
          id: k + 1, author: COMMENTS[(i + k) % COMMENTS.length][0], text: COMMENTS[(i + k) % COMMENTS.length][1],
          created: ago(15 + k * 20), likes: (k * 5 + i) % 15, hearts: (k * 3 + i) % 12
        }))
      };
    }));
    localStorage.setItem(SAVED, '[]'); localStorage.setItem(LOG, '[]');
  }

  #all() { return read(KEY, []); }
  #save(p) { localStorage.setItem(KEY, JSON.stringify(p)); }
  #ids() { return read(SAVED, []); }
  #v(p) { return { ...p, commentCount: p.commentList.length, saved: this.#ids().includes(p.id) }; }
  #log(mod, action, p) {
    const log = read(LOG, []);
    log.unshift({ t: Date.now(), mod, action, postId: p.id, title: p.title });
    localStorage.setItem(LOG, JSON.stringify(log.slice(0, 30)));
  }
  #edit(id, fn) {
    const all = this.#all(), p = all.find(p => p.id === id);
    if (!p) return false;
    fn(p); this.#save(all); return true;
  }

  get(id) { const p = this.#all().find(p => p.id === id); return p && this.#v(p); }

  /** status: 'pending' | 'approved' | 'removed' | null.  sort: 'new' | 'hot'.  Pinned posts always come first. */
  find({ status = null, community = null, q = '', reported = null, sort = 'new' } = {}) {
    const s = q.trim().toLowerCase();
    return this.#all()
      .filter(p => (!status || p.status === status) && (!community || p.community === community) && (reported === null || p.reported === reported))
      .filter(p => !s || p.title.toLowerCase().includes(s) || p.author.toLowerCase().includes(s) || p.community.includes(s))
      .sort((a, b) => (b.pinned - a.pinned) || (sort === 'hot' ? b.likes - a.likes : b.created - a.created))
      .map(p => this.#v(p));
  }

  /** The two moderation queues. kind: 'approve' (normal pending posts) | 'reported' (pending + reported). */
  queue(kind, q = '') { return this.find({ status: 'pending', reported: kind === 'reported', q }); }

  counts() {
    const a = this.#all(), n = f => a.filter(f).length;
    return {
      toApprove: n(p => p.status === 'pending' && !p.reported),
      reported: n(p => p.status === 'pending' && p.reported),
      approved: n(p => p.status === 'approved'),
      removed: n(p => p.status === 'removed'),
      comments: a.reduce((s, p) => s + p.commentList.length, 0),
      total: a.length
    };
  }

  /** A moderator opening a post counts as checking it; approve/remove stay locked until then. */
  markReviewed(id) { this.#edit(id, p => { if (p.status === 'pending') p.reviewed = true; }); }

  setStatus(id, status, mod = 'Moderator') {
    const all = this.#all(), p = all.find(p => p.id === id);
    if (!p) return { ok: false, error: 'Post not found.' };
    if (p.status === 'pending' && !p.reviewed) return { ok: false, error: 'Open and check this post before you approve or remove it.' };
    p.status = status; if (status === 'removed') p.pinned = false;
    this.#save(all); this.#log(mod, status === 'approved' ? 'Approved' : 'Removed', p);
    return { ok: true };
  }
  restore(id, mod = 'Moderator') {
    const all = this.#all(), p = all.find(p => p.id === id);
    if (!p) return;
    p.status = 'pending'; p.reviewed = false;
    this.#save(all); this.#log(mod, 'Sent back to the queue', p);
  }
  togglePin(id) { this.#edit(id, p => { if (p.status === 'approved') p.pinned = !p.pinned; }); }
  toggleLike(id) { this.#edit(id, p => { p.liked = !p.liked; p.likes += p.liked ? 1 : -1; }); }
  toggleSave(id) {
    const ids = this.#ids(), on = !ids.includes(id);
    localStorage.setItem(SAVED, JSON.stringify(on ? [...ids, id] : ids.filter(i => i !== id)));
    return on;
  }
  saved() { const ids = this.#ids(); return this.#all().filter(p => ids.includes(p.id)).map(p => this.#v(p)); }

  addComment(id, author, text) {
    if (!text.trim()) return false;
    return this.#edit(id, p => p.commentList.push({
      id: Math.max(0, ...p.commentList.map(c => c.id)) + 1, author, text: text.trim(), created: Date.now(), likes: 0, hearts: 0
    }));
  }
  removeComment(id, cid) { this.#edit(id, p => { p.commentList = p.commentList.filter(c => c.id !== cid); }); }
  reactComment(id, cid, key) { this.#edit(id, p => { const c = p.commentList.find(c => c.id === cid); if (c) c[key]++; }); }
  /** Every comment on every post, newest first. */
  comments() {
    return this.#all().flatMap(p => p.commentList.map(c => ({ ...c, postId: p.id, postTitle: p.title })))
      .sort((a, b) => b.created - a.created);
  }

  /** Posts made by a moderator are published straight away. */
  add({ author, community, title, body = '', image = null }) {
    if (!community) return { ok: false, error: 'Choose a community to post in.' };
    if (title.trim().length < 5) return { ok: false, error: 'Title must be at least 5 characters.' };
    const all = this.#all(), id = Math.max(0, ...all.map(p => p.id)) + 1;
    const p = {
      id, author, community, title: title.trim(), body: body.trim(), image: image || art(id), created: Date.now(),
      likes: 0, liked: false, pinned: false, status: 'approved', reported: false, reportReason: '', reviewed: true, commentList: []
    };
    all.push(p); this.#save(all); this.#log(author, 'Posted', p);
    return { ok: true, id };
  }
  activity(n = 8) { return read(LOG, []).slice(0, n); }
}
