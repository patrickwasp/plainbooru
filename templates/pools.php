<?php
// pools.php
$totalPages = (int)ceil($total / $page_size);
?>
<div>
  <?php if (empty($pools)): ?>
    <?= $this->partial('alert', ['title' => 'No pools yet', 'body' => 'Use the form on the right to create one.']) ?>
  <?php else: ?>
    <div class="media-grid">
      <?php foreach ($pools as $pool): ?>
        <?php $topTags = !empty($pool['top_tags']) ? explode(',', $pool['top_tags']) : []; ?>
        <a href="/pools/<?= (int)$pool['id'] ?>" class="card block hover:shadow-md transition-shadow overflow-hidden flex flex-col aspect-square" style="padding-block:0;gap:0">
          <div class="flex-1 min-h-0 overflow-hidden">
            <?php if (!empty($pool['first_media_id'])): ?>
              <img src="/thumb/<?= (int)$pool['first_media_id'] ?>" alt=""
                   class="object-cover w-full h-full" loading="lazy">
            <?php else: ?>
              <div class="w-full h-full bg-muted flex items-center justify-center text-muted-foreground text-xs">No cover</div>
            <?php endif; ?>
          </div>
          <footer class="px-3 py-2 flex flex-col gap-1 text-sm">
            <div class="flex items-center gap-2 w-full">
              <span class="font-semibold text-sm truncate flex-1"><?= $this->e($pool['name']) ?></span>
              <span class="badge-outline text-xs shrink-0"><?= (int)($pool['items_count'] ?? 0) ?></span>
            </div>
            <?php if (!empty($topTags)): ?>
              <div class="flex gap-1 overflow-hidden w-full">
                <?php foreach ($topTags as $tag): ?>
                  <span class="badge text-xs"><?= $this->e($tag) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </footer>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="flex justify-center gap-1 mt-6">
        <?php if ($page > 1): ?>
          <a href="/pools?page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
        <?php endif; ?>
        <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
          <a href="/pools?page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
