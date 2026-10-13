// MODEL: mod mail (box 'mod') and direct messages (box 'dm'). Outgoing messages use from: 'me'.
const KEY = 'cc_msgs';
const m = (from, text, minutes) => ({ from, text, t: Date.now() - minutes * 60000 });
const seed = () => [
  { id: 1, box: 'mod', with: 'anonymous12', subject: 'Why was my post removed?', unread: true,
    messages: [m('anonymous12', 'Hi mods, my post about Calculus notes was removed. Can you tell me why?', 90)] },
  { id: 2, box: 'mod', with: 'maria.santos', subject: 'Starting a study-group community', unread: true,
    messages: [m('maria.santos', 'Can we open a community for study groups? I can help moderate it.', 240)] },
  { id: 3, box: 'mod', with: 'juan.delacruz', subject: "Spam in IT'ers", unread: false,
    messages: [m('juan.delacruz', "There is a lot of advertising in the IT'ers feed lately.", 600), m('me', 'Thanks, we are checking the reported posts.', 540)] },
  { id: 4, box: 'dm', with: 'Maria Santos', subject: '', unread: true,
    messages: [m('Maria Santos', 'Hi! Are you joining the moderators meeting tomorrow?', 45)] },
  { id: 5, box: 'dm', with: 'Juan Dela Cruz', subject: '', unread: false,
    messages: [m('Juan Dela Cruz', 'Please check the reported posts in Engineering.', 300), m('me', 'On it.', 280)] },
  { id: 6, box: 'dm', with: 'HANDA Official', subject: '', unread: true,
    messages: [m('HANDA Official', 'Reminder: please pin the evacuation notice for today.', 20)] }
];

export class MessageModel {
  constructor() { if (!localStorage.getItem(KEY)) this.#save(seed()); }
  #all() { return JSON.parse(localStorage.getItem(KEY)); }
  #save(l) { localStorage.setItem(KEY, JSON.stringify(l)); }
  #edit(id, fn) { const all = this.#all(), t = all.find(t => t.id === id); if (t) { fn(t); this.#save(all); } }

  threads(box) {
    const last = t => t.messages[t.messages.length - 1].t;
    return this.#all().filter(t => t.box === box).sort((a, b) => last(b) - last(a));
  }
  get(id) { return this.#all().find(t => t.id === id); }
  read(id) { this.#edit(id, t => { t.unread = false; }); }
  reply(id, from, text) { if (text.trim()) this.#edit(id, t => t.messages.push({ from, text: text.trim(), t: Date.now() })); }
  create(box, who, subject, text) {
    if (!who.trim()) return { ok: false, error: 'Enter who the message is for.' };
    if (!text.trim()) return { ok: false, error: 'Write a message first.' };
    if (box === 'mod' && !subject.trim()) return { ok: false, error: 'Add a subject.' };
    const all = this.#all(), id = Math.max(0, ...all.map(t => t.id)) + 1;
    all.push({ id, box, with: who.trim(), subject: subject.trim(), unread: false, messages: [{ from: 'me', text: text.trim(), t: Date.now() }] });
    this.#save(all);
    return { ok: true, id };
  }
  unread(box = null) { return this.#all().filter(t => t.unread && (!box || t.box === box)).length; }
}
