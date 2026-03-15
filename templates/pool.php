<?php // pool.php — view-only gallery ?>
<h1 class="text-2xl font-bold"><?= $this->e($pool['name']) ?></h1>
<?php if ($pool['description']): ?>
  <p class="text-muted-foreground mt-1"><?= $this->e($pool['description']) ?></p>
<?php endif; ?>

<?php if (empty($pool['items'])): ?>
  <?= $this->partial('alert', ['title' => 'No items yet', 'body' => '<a href="/pools/' . (int)$pool['id'] . '/edit" class="underline">Add some.</a>', 'class' => 'mt-6']) ?>
<?php else: ?>
  <div class="mt-6 media-grid">
    <?php foreach ($pool['items'] as $item): ?>
      <a href="/pools/<?= (int)$pool['id'] ?>/m/<?= (int)$item['id'] ?>" class="relative block rounded overflow-hidden hover:opacity-90 transition-opacity group">
        <div class="aspect-square bg-muted overflow-hidden">
          <img src="/thumb/<?= (int)$item['id'] ?>" alt="Post #<?= (int)$item['id'] ?>"
               class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
        </div>
        <?php if (($item['kind'] ?? '') === 'video'): ?>
          <span class="absolute top-1 right-1 badge text-xs bg-black/70 text-white border-0">▶</span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
