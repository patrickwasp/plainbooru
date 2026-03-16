<?php
// home.php - Latest uploads grid
?>

<?php if (empty($media)): ?>
  <?= $this->partial('alert', ['title' => 'No uploads yet', 'body' => '<a href="/upload" class="underline">Upload something!</a>']) ?>
<?php else: ?>
  <div class="media-grid">
    <?php foreach ($media as $m): ?>
      <?= $this->partial('media_card', ['m' => $m]) ?>
    <?php endforeach; ?>
  </div>

  <?= $this->partial('pagination', ['page' => $page, 'totalPages' => $totalPages, 'base' => '/?', 'class' => 'mt-8']) ?>
<?php endif; ?>
