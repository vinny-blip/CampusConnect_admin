// CONTROLLER: connects registration view <-> user model.
export class RegisterController {
  constructor(model, view) {
    view.onSubmit(data => {
      const r = model.register(data);
      if (!r.ok) return view.error(r.error, r.fields);
      view.success('Account created. Taking you to login…');
      view.go('login.html', 900);
    });
  }
}
