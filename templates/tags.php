<?php // tags.php ?>
<div>
  <?php if (empty($tags)): ?>
    <?= $this->partial('alert', ['title' => 'No tags yet', 'body' => 'Tags are created automatically when added to media, or use the form on the right.']) ?>
  <?php else: ?>
    <div class="flex flex-wrap gap-2">
      <?php foreach ($tags as $tag): ?>
        <span class="badge-outline flex items-center gap-0.5 text-sm">
          <a href="/t/<?= urlencode($tag['name']) ?>" class="hover:underline px-1 py-0.5">
            <?= $this->e($tag['name']) ?>
            <span class="text-xs text-muted-foreground ml-1">(<?= (int)$tag['count'] ?>)</span>
          </a>
          <form action="/tags/<?= urlencode($tag['name']) ?>/delete" method="post" class="inline">
            <button type="submit" class="text-muted-foreground hover:text-destructive leading-none px-1" title="Delete tag globally">×</button>
          </form>
        </span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
