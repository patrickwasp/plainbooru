<?php // tags.php ?>
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-bold mb-4">All Tags</h1>

  <?php if (empty($tags)): ?>
    <div class="alert"><span>No tags yet.</span></div>
  <?php else: ?>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($tags as $tag): ?>
        <a href="/t/<?= urlencode($tag['name']) ?>"
           class="badge-outline hover:bg-accent text-sm px-2 py-0.5 rounded">
          <?= $this->e($tag['name']) ?>
          <span class="text-xs text-muted-foreground ml-1">(<?= (int)$tag['count'] ?>)</span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
