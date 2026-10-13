// VIEW: the Create Post form.
import { ShellView, esc } from './ShellView.js';

export class CreatePostView extends ShellView {
  constructor() { super({}); }
  render(communities) {
    this.set(`
      <h2>Create post</h2><p class="sub">Posts from moderators are published straight away.</p>
      <form class="form" novalidate>
        <label for="community">Community</label>
        <select id="community" name="community"><option value="">Choose a community</option>${communities.map(c => `<option value="${c.slug}">${esc(c.name)}</option>`).join('')}</select>
        <label for="title">Title</label><input id="title" name="title" maxlength="140" autocomplete="off">
        <label for="body">Text (optional)</label><textarea id="body" name="body" rows="5"></textarea>
        <label for="image">Image (optional, under 400 KB)</label><input id="image" name="image" type="file" accept="image/*">
        <p id="msg" class="msg" role="alert"></p>
        <button class="button primary" type="submit">Post</button> <a class="button" href="home.html">Cancel</a>
      </form>`);
  }
  error(text) { document.getElementById('msg').textContent = text; }
  bindSubmit(h) {
    this.page.addEventListener('submit', async e => {
      e.preventDefault();
      const f = e.target.elements, file = f.image.files[0];
      let image = null;
      if (file) {
        if (file.size > 400000) return this.error('That image is over 400 KB. Choose a smaller one.');
        image = await new Promise(res => { const r = new FileReader(); r.onload = () => res(r.result); r.readAsDataURL(file); });
      }
      h({ community: f.community.value, title: f.title.value, body: f.body.value, image });
    });
  }
}
