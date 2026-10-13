// VIEW: shared by login.html and register.html. Only touches the DOM.
export class AuthView {
  constructor() {
    this.form = document.querySelector('form');
    this.msg = document.getElementById('msg');
  }
  onSubmit(handler) {
    this.form.addEventListener('submit', e => { e.preventDefault(); handler(Object.fromEntries(new FormData(this.form))); });
    this.form.addEventListener('input', e => e.target.classList.remove('field-error'));
  }
  error(text, fields = []) {
    this.form.querySelectorAll('.field-error').forEach(input => input.classList.remove('field-error'));
    fields.forEach(field => this.form.elements[field]?.classList.add('field-error'));
    this.msg.style.color = 'var(--red)';
    this.msg.textContent = text;
    this.msg.classList.remove('shake');
    void this.msg.offsetWidth;
    this.msg.classList.add('shake');
  }
  success(text) { this.msg.style.color = '#1e7a34'; this.msg.textContent = text; this.msg.classList.remove('shake'); }
  go(url, delay = 0) { setTimeout(() => (location.href = url), delay); }
}
