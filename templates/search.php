<?php
// search.php
$totalPages = (int)ceil($total / $page_size);
?>
<div class="flex flex-col gap-4">
  <h1 class="text-2xl font-bold">Search</h1>

  <div class="card p-4 shadow-sm">
    <form action="/search" method="get" class="flex flex-wrap gap-2 items-end">
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="tags-input">Tags</label>
        <input type="text" id="tags-input" name="tags" value="<?= $this->e($tags) ?>"
               placeholder="cat blue_eyes" class="input h-8 text-sm w-40">
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="q-input">Filename / Source</label>
        <input type="text" id="q-input" name="q" value="<?= $this->e($q) ?>"
               placeholder="keyword" class="input h-8 text-sm w-40">
      </div>
      <button type="submit" class="btn-sm-primary">Search</button>
      <a href="/search" class="btn-sm-outline">Reset</a>
    </form>
  </div>

  <?php if (!empty($tags) || !empty($q)): ?>
    <div class="text-sm text-muted-foreground">
      <?= $total ?> result<?= $total !== 1 ? 's' : '' ?>
      <?= !empty($tags) ? ' for tags: <strong>' . $this->e($tags) . '</strong>' : '' ?>
      <?= !empty($q) ? ' matching: <strong>' . $this->e($q) . '</strong>' : '' ?>
    </div>
  <?php endif; ?>

  <?php if (empty($media)): ?>
    <div class="alert"><span>No results found.</span></div>
  <?php else: ?>
    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
      <?php foreach ($media as $m): ?>
        <a href="/m/<?= (int)$m['id'] ?>" class="card overflow-hidden hover:shadow-md transition-shadow group">
          <div class="aspect-square bg-muted overflow-hidden">
            <img src="/thumb/<?= (int)$m['id'] ?>" alt="Post #<?= (int)$m['id'] ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
          </div>
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
          <a href="/search?<?= $qs ?><?= $sep ?>page=<?= $page - 1 ?>" class="btn-sm-outline">« Prev</a>
        <?php endif; ?>
        <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
          <a href="/search?<?= $qs ?><?= $sep ?>page=<?= $page + 1 ?>" class="btn-sm-outline">Next »</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>
