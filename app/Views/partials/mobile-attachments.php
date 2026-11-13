<?php
$attachmentUrls = array_values(array_filter(
    array_map('trim', explode(',', (string)($attachmentValue ?? ''))),
    static function (string $url): bool {
        $parts = parse_url($url);
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && in_array(strtolower($parts['scheme']), ['http', 'https'], true);
    }
));
?>
<?php if ($attachmentUrls): ?>
  <section class="mobile-attachments" aria-label="Attachments">
    <h4>Attachments</h4>
    <div class="mobile-attachment-list">
      <?php foreach ($attachmentUrls as $index => $attachmentUrl): ?>
        <?php
          $attachmentPath = (string)(parse_url($attachmentUrl, PHP_URL_PATH) ?? '');
          $attachmentName = rawurldecode(basename($attachmentPath));
          if ($attachmentName === '' || $attachmentName === '.' || $attachmentName === '/') {
              $attachmentName = 'Attachment ' . ($index + 1);
          }
          $attachmentExtension = strtolower(pathinfo($attachmentName, PATHINFO_EXTENSION));
          $isImageAttachment = in_array($attachmentExtension, ['jpg', 'jpeg', 'png'], true);
        ?>
        <div class="mobile-attachment">
          <?php if ($isImageAttachment): ?>
            <a class="mobile-attachment-preview" href="<?= h($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
              <img src="<?= h($attachmentUrl) ?>" alt="<?= h($attachmentName) ?>" loading="lazy">
            </a>
          <?php endif; ?>
          <a class="mobile-attachment-link" href="<?= h($attachmentUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= $isImageAttachment ? 'Open image: ' : 'Open or download: ' ?><?= h($attachmentName) ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>
