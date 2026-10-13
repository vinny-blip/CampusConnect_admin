// CONTROLLER: Mod Mail (box 'mod') and Messages (box 'dm').
import { PageController } from './PageController.js';

export class InboxController extends PageController {
  constructor(box, app, view) {
    super(app, view); if (this.dead) return;
    this.box = box;
    this.cur = Number(this.params.get('t')) || null;
    if (this.cur && app.messages.get(this.cur)?.box !== box) this.cur = null;
    if (this.cur) app.messages.read(this.cur);
    view.bindAction((act, id) => {
      if (act === 'new') this.cur = null;
      else if (act === 'open') { this.cur = id; app.messages.read(id); }
      this.refresh();
    });
    view.bindSubmit((kind, id, f) => {
      if (kind === 'reply') app.messages.reply(id, 'me', f.text);
      else {
        const r = app.messages.create(box, f.who, f.subject || '', f.text);
        if (!r.ok) return view.error(r.error);
        this.cur = r.id; view.toast('Message sent');
      }
      this.refresh();
    });
    this.refresh();
  }
  refresh() {
    const { messages } = this.app;
    this.view.render({ threads: messages.threads(this.box), current: this.cur ? messages.get(this.cur) : null });
    this.badges();
  }
}
