<?php
// profile.php — public user profile page
$roleLabels = ['admin' => 'Admin', 'moderator' => 'Moderator', 'trusted' => 'Trusted', 'user' => 'User'];
$roleLabel  = $roleLabels[$profile['role'] ?? 'user'] ?? 'User';
?>
<div class="flex flex-col gap-8">

  <!-- Profile header -->
  <div class="flex flex-col gap-2">
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-bold"><?= $this->e($profile['username']) ?></h1>
      <span class="badge"><?= $this->e($roleLabel) ?></span>
      <?php if (!empty($profile['banned_at'])): ?>
        <span class="badge badge-destructive">Banned</span>
      <?php endif; ?>
    </div>
    <p class="text-sm text-muted-foreground">
      Joined <?= $this->e(substr($profile['created_at'] ?? '', 0, 10)) ?>
      · <a href="/u/<?= urlencode($profile['username']) ?>/favorites" class="hover:underline">Favorites</a>
    </p>
    <?php if (!empty($profile['bio'])): ?>
      <p class="text-sm max-w-prose"><?= nl2br($this->e($profile['bio'])) ?></p>
    <?php endif; ?>
  </div>

  <!-- Uploads -->
  <div class="flex flex-col gap-4">
    <h2 class="text-lg font-semibold">Uploads (<?= (int)$total ?>)</h2>

    <?php if (empty($media)): ?>
      <p class="text-sm text-muted-foreground">No uploads yet.</p>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($media as $m): ?>
          <?= $this->partial('media_card', ['m' => $m]) ?>
        <?php endforeach; ?>
      </div>

      <?= $this->partial('pagination', [
        'page'       => $page,
        'totalPages' => $totalPages,
        'base'       => '/u/' . urlencode($profile['username']) . '?',
        'class'      => 'mt-4',
      ]) ?>
    <?php endif; ?>
  </div>

</div>
