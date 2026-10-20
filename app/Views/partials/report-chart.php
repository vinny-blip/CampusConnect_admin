<?php
$reportChartCompact = $reportChartCompact ?? false;
$reportDays = count($reportCountsByDate);
$reportTotal = array_sum(array_column($reportCountsByDate, 'count'));
$reportMax = max(1, max(array_column($reportCountsByDate, 'count')));
$chartWidth = $reportChartCompact ? 360 : 840;
$chartHeight = $reportChartCompact ? 210 : 250;
$chartLeft = $reportChartCompact ? 34 : 48;
$chartTop = $reportChartCompact ? 18 : 20;
$chartPlotWidth = $reportChartCompact ? 310 : 770;
$chartPlotHeight = $reportChartCompact ? 145 : 170;
$chartStep = $chartPlotWidth / $reportDays;
$barWidth = $chartStep * .56;
?>
<section class="report-chart-card<?= $reportChartCompact ? ' compact' : '' ?>" aria-labelledby="report-chart-title">
  <div class="report-chart-heading">
    <div>
      <h3 id="report-chart-title">Reported content by date</h3>
      <p>Post and comment reports submitted in the past <?= (int)$reportDays ?> days.</p>
    </div>
    <b><?= (int)$reportTotal ?> reports</b>
  </div>
  <?php if (!$reportChartCompact): ?><p class="report-chart-note">Dismissed or deleted reports remain in these totals. Older reports are not included because their original report dates were not recorded.</p><?php endif; ?>
  <div class="report-chart-scroll">
    <svg class="report-chart" viewBox="0 0 <?= $chartWidth ?> <?= $chartHeight ?>" role="img" aria-labelledby="report-chart-title report-chart-description">
      <desc id="report-chart-description">Bar chart showing <?= (int)$reportTotal ?> reports across the past <?= (int)$reportDays ?> days.</desc>
      <?php for ($line = 0; $line <= 4; $line++): ?>
        <?php
        $gridY = $chartTop + ($chartPlotHeight / 4) * $line;
        $gridValue = (int)round($reportMax * (4 - $line) / 4);
        ?>
        <line x1="<?= $chartLeft ?>" y1="<?= $gridY ?>" x2="<?= $chartLeft + $chartPlotWidth ?>" y2="<?= $gridY ?>" class="chart-grid"></line>
        <text x="<?= $chartLeft - 10 ?>" y="<?= $gridY + 4 ?>" class="chart-axis" text-anchor="end"><?= $gridValue ?></text>
      <?php endfor; ?>
      <?php foreach ($reportCountsByDate as $index => $day): ?>
        <?php
        $barHeight = (int)round($day['count'] / $reportMax * $chartPlotHeight);
        $barX = $chartLeft + $index * $chartStep + ($chartStep - $barWidth) / 2;
        $barY = $chartTop + $chartPlotHeight - $barHeight;
        ?>
        <rect x="<?= round($barX, 1) ?>" y="<?= $barY ?>" width="<?= round($barWidth, 1) ?>" height="<?= max($barHeight, 1) ?>" rx="4" class="chart-bar">
          <title><?= h($day['date']) ?>: <?= (int)$day['count'] ?> reports</title>
        </rect>
        <?php if ($day['count'] > 0): ?><text x="<?= round($barX + $barWidth / 2, 1) ?>" y="<?= max($barY - 6, 12) ?>" class="chart-value" text-anchor="middle"><?= (int)$day['count'] ?></text><?php endif; ?>
        <?php if ($index % 2 === 0 || $index === $reportDays - 1): ?>
          <text x="<?= round($barX + $barWidth / 2, 1) ?>" y="<?= $chartTop + $chartPlotHeight + 22 ?>" class="chart-axis" text-anchor="middle"><?= h($day['label']) ?></text>
        <?php endif; ?>
      <?php endforeach; ?>
    </svg>
  </div>
</section>
