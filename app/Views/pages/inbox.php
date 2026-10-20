<?php $isModMail = $box === 'mod'; ?>
<h2><?= $isModMail ? 'Moderator Mail' : 'Messages' ?></h2>
<p class="sub"><?= $isModMail ? 'Conversations between students and the moderator team.' : 'Direct messages with other members.' ?></p>
<div class="inbox">
  <div class="tlist">
    <a class="titem new" href="<?= h($page) ?>.php"><?= icon_svg('plus') ?> New <?= $isModMail ? 'moderator mail' : 'message' ?></a>
    <?php foreach ($threads as $thread): ?>
      <?php $lastMessage = $thread['messages'][count($thread['messages']) - 1]; ?>
      <a class="titem<?= ($current['id'] ?? 0) === $thread['id'] ? ' on' : '' ?><?= $thread['unread'] ? ' unread' : '' ?>" href="<?= h($page) ?>.php?t=<?= (int)$thread['id'] ?>">
        <b><?= h($thread['with']) ?></b> <small><?= h(ago((int)$lastMessage['t'])) ?></small><br>
        <small><?= h($thread['subject'] ?: $lastMessage['text']) ?></small>
      </a>
    <?php endforeach; ?>
  </div>
  <section class="tview">
    <?php if ($current !== null): ?>
      <h3><?= h($current['subject'] ?: $current['with']) ?></h3>
      <p class="sub"><?= $isModMail ? 'From' : 'With' ?> <?= h($current['with']) ?></p>
      <div class="msgs">
        <?php foreach ($current['messages'] as $message): ?>
          <div class="bubble<?= $message['from'] === 'me' ? ' me' : '' ?>">
            <small><?= $message['from'] === 'me' ? 'You' : h($message['from']) ?> • <?= h(ago((int)$message['t'])) ?></small><br>
            <?= nl2br(h($message['text'])) ?>
          </div>
        <?php endforeach; ?>
      </div>
      <form class="reply" method="post">
        <?= $csrf ?><input type="hidden" name="action" value="reply"><input type="hidden" name="thread_id" value="<?= (int)$current['id'] ?>">
        <input name="text" placeholder="Write a reply…" aria-label="Write a reply" autocomplete="off" required>
        <button class="button primary">Send</button>
      </form>
    <?php else: ?>
      <h3>New <?= $isModMail ? 'moderator mail' : 'message' ?></h3>
      <form class="form" method="post">
        <?= $csrf ?><input type="hidden" name="action" value="new-message">
        <label for="who">To</label><input id="who" name="who" autocomplete="off" required>
        <?php if ($isModMail): ?><label for="subject">Subject</label><input id="subject" name="subject" autocomplete="off" required><?php endif; ?>
        <label for="text">Message</label><textarea id="text" name="text" rows="4" required></textarea>
        <button class="button primary">Send</button>
      </form>
    <?php endif; ?>
  </section>
</div>
