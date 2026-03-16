<?php
// favorites.php — user's favorited media
?>
<div class="flex flex-col gap-6">

  <div class="flex flex-col gap-1">
    <h1 class="text-2xl font-bold">
      <a href="/u/<?= urlencode($profile['username']) ?>" class="hover:underline"><?= $this->e($profile['username']) ?></a>'s Favorites
    </h1>
    <p class="text-sm text-muted-foreground"><?= (int)$total ?> item<?= $total !== 1 ? 's' : '' ?></p>
  </div>

  <?php if (empty($media)): ?>
    <p class="text-sm text-muted-foreground">No favorites yet.</p>
  <?php else: ?>
    <div class="media-grid">
      <?php foreach ($media as $m): ?>
        <?= $this->partial('media_card', ['m' => $m]) ?>
      <?php endforeach; ?>
    </div>

    <?= $this->partial('pagination', [
      'page'       => $page,
      'totalPages' => $totalPages,
      'base'       => '/u/' . urlencode($profile['username']) . '/favorites?',
      'class'      => 'mt-4',
    ]) ?>
  <?php endif; ?>

</div>
