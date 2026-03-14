<?php
// home.php - Latest uploads grid
$totalPages = (int)ceil($total / $page_size);
?>
<div class="flex items-center justify-between mb-4">
  <h1 class="text-2xl font-bold">Latest Uploads</h1>
  <span class="text-sm text-muted-foreground"><?= $total ?> posts</span>
</div>

<?php if (empty($media)): ?>
  <div class="alert">
    <span>No uploads yet. <a href="/upload" class="underline text-primary">Upload something!</a></span>
  </div>
<?php else: ?>
  <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3">
    <?php foreach ($media as $m): ?>
      <a href="/m/<?= (int)$m['id'] ?>" class="card overflow-hidden hover:shadow-md transition-shadow group">
        <div class="aspect-square bg-muted overflow-hidden">
          <img src="/thumb/<?= (int)$m['id'] ?>" alt="Post #<?= (int)$m['id'] ?>"
               class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
        </div>
        <?php if ($m['kind'] === 'video'): ?>
          <div class="p-1 text-center">
            <span class="badge text-xs">video</span>
          </div>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-1 mt-8">
      <?php if ($page > 1): ?>
        <a href="/?page=<?= $page - 1 ?>" class="btn-sm-outline">« Prev</a>
      <?php endif; ?>
      <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="/?page=<?= $page + 1 ?>" class="btn-sm-outline">Next »</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
