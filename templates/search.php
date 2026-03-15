<?php
// search.php
$totalPages = (int)ceil($total / $page_size);
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
        <a href="/m/<?= (int)$m['id'] ?>" class="relative block rounded overflow-hidden hover:opacity-90 transition-opacity group">
          <div class="aspect-square bg-muted overflow-hidden">
            <img src="/thumb/<?= (int)$m['id'] ?>" alt="Post #<?= (int)$m['id'] ?>"
                 class="w-full h-full object-cover" loading="lazy">
          </div>
          <?php if (($m['kind'] ?? '') === 'video'): ?>
            <span class="absolute top-1 left-1 bg-black/65 text-white text-xs w-5 h-5 flex items-center justify-center rounded leading-none pointer-events-none">▶</span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <?php
        $qs = http_build_query(array_filter(['tags' => $tags, 'q' => $q]));
        $sep = $qs ? '&' : '';
      ?>
      <div class="flex justify-center gap-1 mt-6">
        <?php if ($page > 1): ?>
          <a href="/search?<?= $qs ?><?= $sep ?>page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
        <?php endif; ?>
        <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
          <a href="/search?<?= $qs ?><?= $sep ?>page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
