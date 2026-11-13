<dialog class="suspension-dialog" data-suspension-dialog aria-labelledby="suspension-dialog-title">
  <form method="post" class="suspension-dialog-form">
    <?= $csrf ?>
    <input type="hidden" name="action" value="<?= h($suspensionAction) ?>">
    <input type="hidden" name="<?= h($suspensionIdName) ?>" value="" data-suspension-user-id>
    <input type="hidden" name="suspended_until" value="" data-suspension-value>
    <input type="hidden" name="suspension_timezone_offset" value="" data-suspension-timezone>
    <header class="suspension-dialog-heading">
      <span class="suspension-dialog-icon" aria-hidden="true">!</span>
      <div>
        <h3 id="suspension-dialog-title"><?= h($suspensionTitle) ?></h3>
        <p>Choose when this account can return.</p>
      </div>
    </header>
    <?php if (!empty($suspensionReasonRequired)): ?>
      <label class="suspension-reason">Reason for suspension
        <input type="text" name="reason" maxlength="500" required>
      </label>
    <?php endif; ?>
    <section class="suspension-calendar" aria-label="Choose suspension end date">
      <div class="suspension-calendar-heading">
        <button class="calendar-nav" type="button" aria-label="Previous month" data-calendar-previous>&lsaquo;</button>
        <strong data-calendar-month></strong>
        <button class="calendar-nav" type="button" aria-label="Next month" data-calendar-next>&rsaquo;</button>
      </div>
      <div class="suspension-weekdays" aria-hidden="true">
        <span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span><span>Sun</span>
      </div>
      <div class="suspension-calendar-days" role="group" aria-label="Calendar dates" data-calendar-days></div>
    </section>
    <section class="suspension-time" aria-label="Choose suspension end time">
      <label>Lift time</label>
      <div class="suspension-time-controls">
        <select aria-label="Hour" data-suspension-hour>
          <option value="">Hour</option>
          <?php for ($hour = 1; $hour <= 12; $hour++): ?>
            <option value="<?= $hour ?>"><?= sprintf('%02d', $hour) ?></option>
          <?php endfor; ?>
        </select>
        <span>:</span>
        <select aria-label="Minute" data-suspension-minute>
          <option value="">Min</option>
          <?php for ($minute = 0; $minute < 60; $minute++): ?>
            <option value="<?= $minute ?>"><?= sprintf('%02d', $minute) ?></option>
          <?php endfor; ?>
        </select>
        <select aria-label="AM or PM" data-suspension-period>
          <option value="">AM/PM</option>
          <option value="AM">AM</option>
          <option value="PM">PM</option>
        </select>
      </div>
    </section>
    <p class="suspension-selection" aria-live="polite" tabindex="-1" data-suspension-selection>Select a date and time.</p>
    <div class="suspension-dialog-actions">
      <button class="btn-action secondary" type="button" data-close-suspension>Cancel</button>
      <button class="btn-action remove" type="submit" data-suspension-submit disabled>Confirm suspension</button>
    </div>
  </form>
</dialog>
