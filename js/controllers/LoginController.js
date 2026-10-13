// CONTROLLER: connects login view <-> user model.
export class LoginController {
  constructor(model, view) {
    if (model.current()) return view.go('home.html');
    view.onSubmit(({ email, password }) => {
      const r = model.login(email, password);
      r.ok ? view.go('home.html') : view.error(r.error);
    });
  }
}
