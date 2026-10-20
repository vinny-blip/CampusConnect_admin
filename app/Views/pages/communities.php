<h2>Manage Moderated Communities</h2>
<p class="sub">You can moderate up to 5 communities. <a href="#roles">Learn more about moderator roles.</a></p>
<h3 class="chead">Communities (<?= (int)$communityCount ?>/5)</h3>
<?php if ($communities): ?>
  <?php foreach ($communities as $community): ?>
    <div class="crow">
      <span class="cicon"></span>
      <div class="info">
        <a class="name" href="<?= h(community_url($community['slug'])) ?>"><?= h($community['name']) ?></a><br>
        <small><?= h($community['visitors']) ?> visitors • <?= (int)$community['contrib'] ?> contributions past week</small>
        <?php if ($community['request']): ?><small class="flag"> • <?= h($community['request']) ?> role requested</small><?php endif; ?>
      </div>
      <form class="inline-action" method="post"><?= $csrf ?><input type="hidden" name="action" value="star"><input type="hidden" name="slug" value="<?= h($community['slug']) ?>">
        <button class="star" aria-label="<?= $community['starred'] ? 'Remove from favorites' : 'Add to favorites' ?>" aria-pressed="<?= $community['starred'] ? 'true' : 'false' ?>"><?= icon_svg($community['starred'] ? 'star-filled' : 'star') ?></button></form>
      <details class="community-menu">
        <summary class="more" aria-label="More options for <?= h($community['name']) ?>">⋯</summary>
        <div class="menu community-menu-items">
          <?php foreach (['Alumni', 'Advisor'] as $role): ?>
            <form method="post"><?= $csrf ?><input type="hidden" name="action" value="<?= $role ?>"><input type="hidden" name="slug" value="<?= h($community['slug']) ?>"><button>Request <?= $role ?> Role</button></form>
          <?php endforeach; ?>
          <a href="#roles">Learn About Mod Roles</a><hr>
          <form method="post"><?= $csrf ?><input type="hidden" name="action" value="leave"><input type="hidden" name="slug" value="<?= h($community['slug']) ?>"><button>Leave Mod Team</button></form>
        </div>
      </details>
    </div>
  <?php endforeach; ?>
<?php else: ?><p class="empty">You are not moderating any communities.</p><?php endif; ?>
<div id="roles" class="role-info">
  <h3 class="chead">Moderator roles</h3>
  <p><b>Moderator</b> checks the queues and keeps the feed tidy.</p>
  <p><b>Alumni</b> advises current moderators from experience and can help with mod mail.</p>
  <p><b>Advisor</b> is a faculty or staff member who oversees a community. You can moderate up to 5 communities at once.</p>
</div>
