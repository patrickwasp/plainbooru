<?php
/** @var array $sidebar_tags  [['name' => string, 'count' => int|null], ...] */
?>
<div class="flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-3">
  <h2 class="font-semibold text-sm">Tags</h2>
  <?php if (!empty($sidebar_tags)): ?>
    <div class="flex flex-wrap gap-1">
      <?php foreach ($sidebar_tags as $t): ?>
        <a href="/t/<?= urlencode($t['name']) ?>" class="badge-outline text-xs hover:bg-accent">
          <?= $this->e($t['name']) ?><?php if ($t['count'] !== null): ?><span class="text-muted-foreground ml-0.5"><?= (int)$t['count'] ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-xs text-muted-foreground">No tags yet.</p>
  <?php endif; ?>
</div>
