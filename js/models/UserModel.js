// MODEL: users + session. localStorage stands in for a database (demo only, passwords are NOT secure here).
const USERS = 'cc_users', SESSION = 'cc_session';
const EMAIL_RE = /^[^\s@]+@[^\s@]+\.edu(\.[a-z]{2})?$/i;

export class UserModel {
  constructor() {
    if (!localStorage.getItem(USERS)) {
      this.#save([{ name: 'Demo Moderator', email: 'mod@campus.edu.ph', password: 'mod12345' }]);
    }
  }
  #all() { return JSON.parse(localStorage.getItem(USERS)) || []; }
  #save(list) { localStorage.setItem(USERS, JSON.stringify(list)); }

  register({ firstName, lastName, email, password, confirm }) {
    const missingNameFields = [];
    if (!firstName.trim()) missingNameFields.push('firstName');
    if (!lastName.trim()) missingNameFields.push('lastName');
    if (missingNameFields.length) return { ok: false, error: 'Enter your first and last name.', fields: missingNameFields };
    if (!EMAIL_RE.test(email)) return { ok: false, error: 'Use your institutional email (ends in .edu or .edu.ph).', fields: ['email'] };
    if (password.length < 8) return { ok: false, error: 'Password must be at least 8 characters.', fields: ['password'] };
    if (password !== confirm) return { ok: false, error: 'Passwords do not match.', fields: ['confirm'] };
    const users = this.#all();
    if (users.some(u => u.email.toLowerCase() === email.toLowerCase()))
      return { ok: false, error: 'That email is already registered. Log in instead.', fields: ['email'] };
    users.push({ name: `${firstName.trim()} ${lastName.trim()}`, email, password });
    this.#save(users);
    return { ok: true };
  }
  login(email, password) {
    const u = this.#all().find(u => u.email.toLowerCase() === email.toLowerCase() && u.password === password);
    if (!u) return { ok: false, error: 'Wrong email or password.' };
    localStorage.setItem(SESSION, JSON.stringify({ name: u.name, email: u.email }));
    return { ok: true };
  }
  update(name) {
    const n = name.trim();
    if (!n) return { ok: false, error: 'Name cannot be empty.' };
    const s = this.current(), users = this.#all(), u = users.find(u => u.email === s.email);
    if (u) u.name = n;
    this.#save(users);
    localStorage.setItem(SESSION, JSON.stringify({ ...s, name: n }));
    return { ok: true };
  }
  current() { return JSON.parse(localStorage.getItem(SESSION) || 'null'); }
  logout() { localStorage.removeItem(SESSION); }
}
