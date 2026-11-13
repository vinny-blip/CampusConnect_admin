<div class="page-heading">
  <h2>Reports Overview</h2>
  <p class="sub">A read-only summary of items awaiting review by Student Moderators.</p>
</div>
<section class="stat-grid login-log-stats reports-overview-stats" aria-label="Reports overview">
  <article class="stat-card report-stat-card">
    <span class="report-stat-icon"><?= icon_svg('queue') ?></span>
    <div><span class="stat-label">Pending post reports</span><strong><?= (int)$reportOverview['post_reports'] ?></strong></div>
    <span class="report-stat-detail">Reports awaiting review</span>
  </article>
  <article class="stat-card report-stat-card">
    <span class="report-stat-icon"><?= icon_svg('message') ?></span>
    <div><span class="stat-label">Pending comment reports</span><strong><?= (int)$reportOverview['comment_reports'] ?></strong></div>
    <span class="report-stat-detail">Reports awaiting review</span>
  </article>
  <article class="stat-card report-stat-card">
    <span class="report-stat-icon"><?= icon_svg('mail') ?></span>
    <div><span class="stat-label">Pending appeals</span><strong><?= (int)$reportOverview['appeals'] ?></strong></div>
    <span class="report-stat-detail">Appeals recorded as pending</span>
  </article>
  <article class="stat-card report-stat-card">
    <span class="report-stat-icon"><?= icon_svg('history') ?></span>
    <div><span class="stat-label">Posts awaiting review</span><strong><?= (int)$reportOverview['pending_posts'] ?></strong></div>
    <span class="report-stat-detail">Posts pending moderation</span>
  </article>
</section>
<div class="reports-overview-note">
  <?= icon_svg('info') ?>
  <p>Appeals are counted according to their recorded status; restoring an account does not automatically change an appeal's status.</p>
</div>
