<?php
// search.php
$totalPages = (int)ceil($total / $page_size);
?>
<div class="flex flex-col gap-4">

  <div class="card p-4 shadow-sm">
    <form action="/search" method="get" class="flex flex-col sm:flex-row flex-wrap gap-2 items-end">
      <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
        <label class="text-sm font-medium" for="tags-input">Tags</label>
        <input type="text" id="tags-input" name="tags" value="<?= $this->e($tags) ?>"
               placeholder="cat blue_eyes" class="input h-8 text-sm w-full">
      </div>
      <div class="flex flex-col gap-1 flex-1 min-w-[140px]">
        <label class="text-sm font-medium" for="q-input">Filename / Source</label>
        <input type="text" id="q-input" name="q" value="<?= $this->e($q) ?>"
               placeholder="keyword" class="input h-8 text-sm w-full">
      </div>
      <div class="flex gap-2 items-end">
        <button type="submit" class="btn-sm-primary">Search</button>
        <a href="/search" class="btn-sm-outline">Reset</a>
      </div>
    </form>
  </div>

  <?php if (!empty($tags) || !empty($q)): ?>
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
      <span class="badge"><?= $total ?></span>
      result<?= $total !== 1 ? 's' : '' ?>
      <?= !empty($tags) ? ' for tags: <strong>' . $this->e($tags) . '</strong>' : '' ?>
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
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
          </div>
          <?php if (($m['kind'] ?? '') === 'video'): ?>
            <span class="absolute top-1 right-1 badge text-xs bg-black/70 text-white border-0">▶ video</span>
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
