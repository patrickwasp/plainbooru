<?php // pool.php ?>
<div class="max-w-5xl mx-auto">
  <!-- Breadcrumb -->
  <nav class="text-sm text-muted-foreground mb-4 flex gap-1 items-center">
    <a href="/" class="hover:text-foreground">Home</a>
    <span>/</span>
    <a href="/pools" class="hover:text-foreground">Pools</a>
    <span>/</span>
    <span><?= $this->e($pool['name']) ?></span>
  </nav>

  <div class="flex items-start justify-between mb-4 flex-wrap gap-2">
    <div>
      <h1 class="text-2xl font-bold"><?= $this->e($pool['name']) ?></h1>
      <?php if ($pool['description']): ?>
        <p class="text-muted-foreground mt-1"><?= $this->e($pool['description']) ?></p>
      <?php endif; ?>
      <p class="text-xs text-muted-foreground mt-1">
        <?= count($pool['items']) ?> item<?= count($pool['items']) !== 1 ? 's' : '' ?>
        · Created <?= $this->e(substr($pool['created_at'], 0, 10)) ?>
      </p>
    </div>
    <a href="/api/v1/pools/<?= (int)$pool['id'] ?>" target="_blank" class="text-sm text-primary underline">JSON API</a>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-destructive mb-4 text-sm"><?= $this->e($error) ?></div>
  <?php endif; ?>

  <!-- Items grid -->
  <?php if (empty($pool['items'])): ?>
    <div class="alert mb-4"><span>No items in this pool yet.</span></div>
  <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3 mb-6">
      <?php foreach ($pool['items'] as $item): ?>
        <div class="relative card overflow-hidden shadow-sm">
          <a href="/m/<?= (int)$item['id'] ?>">
            <div class="aspect-square bg-muted overflow-hidden">
              <img src="/thumb/<?= (int)$item['id'] ?>" alt="Post #<?= (int)$item['id'] ?>"
                   class="w-full h-full object-cover" loading="lazy">
            </div>
          </a>
          <div class="absolute top-1 left-1 bg-black/60 text-white text-xs px-1 rounded">
            #<?= (int)$item['position'] ?>
          </div>
          <!-- Remove item -->
          <form action="/pools/<?= (int)$pool['id'] ?>/remove" method="post" class="absolute top-1 right-1">
            <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
            <button type="submit" class="bg-destructive text-destructive-foreground text-xs px-1 rounded hover:opacity-80" title="Remove">×</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Reorder form -->
    <div class="card p-4 shadow-sm mb-6">
      <h2 class="font-semibold mb-2">Reorder Items</h2>
      <p class="text-sm text-muted-foreground mb-2">Enter media IDs in desired order, one per line or comma-separated.</p>
      <form action="/pools/<?= (int)$pool['id'] ?>/reorder" method="post" class="flex flex-col gap-2">
        <textarea name="item_ids" rows="4" class="textarea font-mono text-sm"
                  placeholder="1&#10;5&#10;2&#10;..."><?php
          foreach ($pool['items'] as $item) {
              echo (int)$item['id'] . "\n";
          }
        ?></textarea>
        <button type="submit" class="btn-sm-outline w-fit">Save Order</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- Add item form -->
  <div class="card p-4 shadow-sm">
    <h2 class="font-semibold mb-3">Add Media to Pool</h2>
    <form action="/pools/<?= (int)$pool['id'] ?>/items" method="post" class="flex flex-wrap gap-2 items-end">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="media-id">Media ID</label>
        <input type="number" id="media-id" name="media_id" required min="1" placeholder="42"
               class="input h-8 text-sm w-28">
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="pos">Position (optional)</label>
        <input type="number" id="pos" name="position" min="0" placeholder="auto"
               class="input h-8 text-sm w-24">
      </div>
      <button type="submit" class="btn-sm-primary">Add</button>
    </form>
  </div>
</div>
