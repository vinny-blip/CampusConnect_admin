// VIEW: the moderator's profile.
import { ShellView, esc, initials } from './ShellView.js';

export class ProfileView extends ShellView {
  constructor() { super({}); }
  render(user, counts) {
    this.set(`
      <h2>Profile</h2>
      <div class="about"><span class="avatar big">${esc(initials(user.name))}</span><div><b>${esc(user.name)}</b><p class="sub">${esc(user.email)} • Student moderator</p></div></div>
      <div class="stats"><a class="stat" href="queues.html"><b>${counts.toApprove + counts.reported}</b>In your queues</a><a class="stat" href="communities.html"><b>${counts.communities}</b>Communities</a>
        <a class="stat" href="saved.html"><b>${counts.saved}</b>Saved posts</a></div>
      <form class="form" novalidate><label for="name">Display name</label><input id="name" name="name" value="${esc(user.name)}">
        <p id="msg" class="msg" role="alert"></p><button class="button primary">Save changes</button></form>
      <h3 class="chead">Demo data</h3><p class="sub">Put the posts, mail and notifications back to how they started.</p>
      <button class="button" data-act="reset">Reset demo data</button>`);
  }
  error(text) { document.getElementById('msg').textContent = text; }
  bindSubmit(h) { this.page.addEventListener('submit', e => { e.preventDefault(); h(e.target.elements.name.value); }); }
  bindReset(h) { this.delegate('click', '[data-act="reset"]', h); }
}
