<?php
// pools.php
$totalPages = (int)ceil($total / $page_size);
?>
<div class="max-w-4xl mx-auto">
  <div class="flex items-center justify-between mb-4">
    <h1 class="text-2xl font-bold">Pools</h1>
    <span class="text-sm text-muted-foreground"><?= $total ?> pool<?= $total !== 1 ? 's' : '' ?></span>
  </div>

  <!-- Create pool form -->
  <div class="card p-4 shadow-sm mb-6">
    <h2 class="font-semibold mb-3">Create New Pool</h2>
    <?php if (!empty($error)): ?>
      <div class="alert alert-destructive py-2 text-sm mb-2"><?= $this->e($error) ?></div>
    <?php endif; ?>
    <form action="/pools" method="post" class="flex flex-wrap gap-2 items-end">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="pool-name">Name <span class="text-destructive">*</span></label>
        <input type="text" id="pool-name" name="name" required placeholder="My Collection"
               class="input h-8 text-sm w-44">
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="pool-desc">Description</label>
        <input type="text" id="pool-desc" name="description" placeholder="Optional description"
               class="input h-8 text-sm w-56">
      </div>
      <button type="submit" class="btn-sm-primary">Create</button>
    </form>
  </div>

  <?php if (empty($pools)): ?>
    <div class="alert"><span>No pools yet. Create one above!</span></div>
  <?php else: ?>
    <div class="grid sm:grid-cols-2 md:grid-cols-3 gap-4">
      <?php foreach ($pools as $pool): ?>
        <a href="/pools/<?= (int)$pool['id'] ?>" class="card p-4 shadow-sm hover:shadow-md transition-shadow block">
          <h2 class="font-semibold"><?= $this->e($pool['name']) ?></h2>
          <?php if ($pool['description']): ?>
            <p class="text-sm text-muted-foreground mt-1"><?= $this->e($pool['description']) ?></p>
          <?php endif; ?>
          <div class="text-xs text-muted-foreground mt-2">
            <?= (int)($pool['items_count'] ?? 0) ?> item<?= ($pool['items_count'] ?? 0) !== 1 ? 's' : '' ?>
            · <?= $this->e(substr($pool['created_at'], 0, 10)) ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div class="flex justify-center gap-1 mt-6">
        <?php if ($page > 1): ?>
          <a href="/pools?page=<?= $page - 1 ?>" class="btn-sm-outline">« Prev</a>
        <?php endif; ?>
        <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
          <a href="/pools?page=<?= $page + 1 ?>" class="btn-sm-outline">Next »</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
