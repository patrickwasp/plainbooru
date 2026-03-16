<?php
/**
 * Pagination nav partial.
 *
 * @var int    $page       Current page number.
 * @var int    $totalPages Total number of pages.
 * @var string $base       URL prefix that already includes all filter query params and ends
 *                         with either '?' or '&' so 'page=N' can be appended directly.
 *                         Examples: '/?'  '/pools?'  '/search?tags=foo&'
 * @var string $class      Optional extra wrapper class (default: 'mt-6').
 */
?>
<?php if ($totalPages > 1): ?>
  <div class="flex justify-center gap-1 <?= $class ?? 'mt-6' ?>">
    <?php if ($page > 1): ?>
      <a href="<?= $base ?>page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
    <?php endif; ?>
    <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
    <?php if ($page < $totalPages): ?>
      <a href="<?= $base ?>page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
