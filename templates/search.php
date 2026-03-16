<?php
// search.php
?>
<div class="flex flex-col gap-4">

  <?php if (!empty($tags) || !empty($q)): ?>
    <?php $parsed = \Plainbooru\Media\MediaService::parseSearchQuery($tags); ?>
    <div class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
      <span class="badge"><?= $total ?></span>
      result<?= $total !== 1 ? 's' : '' ?>
      <?php foreach ($parsed['required'] as $t): ?>
        <span class="badge"><?= $this->e($t) ?></span>
      <?php endforeach; ?>
      <?php foreach ($parsed['union'] as $t): ?>
        <span class="badge bg-blue-500/10 text-blue-700 dark:text-blue-300">~<?= $this->e($t) ?></span>
      <?php endforeach; ?>
      <?php foreach ($parsed['excluded'] as $t): ?>
        <span class="badge bg-red-500/10 text-red-700 dark:text-red-300">-<?= $this->e($t) ?></span>
      <?php endforeach; ?>
      <?= !empty($q) ? ' matching: <strong>' . $this->e($q) . '</strong>' : '' ?>
    </div>
  <?php endif; ?>

  <?php if (empty($media)): ?>
    <?= $this->partial('alert', ['title' => 'No results found', 'body' => 'Try different tags or a broader search.']) ?>
  <?php else: ?>
    <div class="media-grid">
      <?php foreach ($media as $m): ?>
        <?= $this->partial('media_card', ['m' => $m]) ?>
      <?php endforeach; ?>
    </div>

    <?php
      $qs   = http_build_query(array_filter(['tags' => $tags, 'q' => $q]));
      $base = '/search?' . ($qs ? $qs . '&' : '');
    ?>
    <?= $this->partial('pagination', ['page' => $page, 'totalPages' => $totalPages, 'base' => $base]) ?>
  <?php endif; ?>
</div>
