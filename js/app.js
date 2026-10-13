// Builds every model once per page, and knows how to wipe the demo data.
import { UserModel } from './models/UserModel.js';
import { PostModel } from './models/PostModel.js';
import { CommunityModel } from './models/CommunityModel.js';
import { MessageModel } from './models/MessageModel.js';
import { NotificationModel } from './models/NotificationModel.js';
import { RestrictedModel } from './models/RestrictedModel.js';

export const createApp = () => ({
  users: new UserModel(), posts: new PostModel(), communities: new CommunityModel(),
  messages: new MessageModel(), notes: new NotificationModel(), restricted: new RestrictedModel()
});
/** Clears seeded data (not the accounts) so everything is re-created fresh on the next load. */
export const resetDemoData = () =>
  ['cc_posts_v2', 'cc_saved', 'cc_log', 'cc_communities', 'cc_msgs', 'cc_notes', 'cc_restricted'].forEach(k => localStorage.removeItem(k));
