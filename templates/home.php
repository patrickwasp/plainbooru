<?php
// home.php - Latest uploads grid
$totalPages = (int)ceil($total / $page_size);
?>

<?php if (empty($media)): ?>
  <?= $this->partial('alert', ['title' => 'No uploads yet', 'body' => '<a href="/upload" class="underline">Upload something!</a>']) ?>
<?php else: ?>
  <div class="media-grid">
    <?php foreach ($media as $m): ?>
      <a href="/m/<?= (int)$m['id'] ?>" class="relative block rounded overflow-hidden hover:opacity-90 transition-opacity group">
        <div class="aspect-square bg-muted overflow-hidden">
          <img src="/thumb/<?= (int)$m['id'] ?>" alt="Post #<?= (int)$m['id'] ?>"
               class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy">
        </div>
        <?php if ($m['kind'] === 'video'): ?>
          <span class="absolute top-1 right-1 badge text-xs bg-black/70 text-white border-0">▶ video</span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Pagination -->
  <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-1 mt-8">
      <?php if ($page > 1): ?>
        <a href="/?page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
      <?php endif; ?>
      <span class="btn-sm-outline opacity-50 cursor-default"><?= $page ?> / <?= $totalPages ?></span>
      <?php if ($page < $totalPages): ?>
        <a href="/?page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
