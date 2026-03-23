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
    <?php
      $pendingCount   = ($is_own ?? false) ? count(array_filter($media ?? [], fn($m) => !empty($m['pending_at']))) : 0;
      $publishedCount = ($is_own ?? false) ? ((int)$total - $pendingCount) : (int)$total;
    ?>
    <h2 class="text-lg font-semibold">
      Uploads (<?= $publishedCount ?>)
      <?php if ($pendingCount > 0): ?>
        <span class="ml-1 badge text-xs bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30"><?= $pendingCount ?> pending</span>
      <?php endif; ?>
    </h2>

    <?php if ($pendingCount > 0): ?>
      <div class="rounded-md border border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400 text-sm px-4 py-2">
        <?= $pendingCount === 1 ? '1 upload is' : "$pendingCount uploads are" ?> awaiting moderation and not yet visible to others.
      </div>
    <?php endif; ?>

    <?php if (empty($media)): ?>
      <p class="text-sm text-muted-foreground">No uploads yet.</p>
    <?php else: ?>
      <div class="media-grid">
        <?php foreach ($media as $m): ?>
          <?php if (!empty($m['pending_at'])): ?>
            <div class="relative">
              <?= $this->partial('media_card', ['m' => $m]) ?>
              <div class="absolute inset-0 rounded overflow-hidden pointer-events-none">
                <div class="absolute bottom-0 left-0 right-0 bg-amber-500/80 text-white text-xs text-center py-0.5 font-medium">Pending</div>
              </div>
            </div>
          <?php else: ?>
            <?= $this->partial('media_card', ['m' => $m]) ?>
          <?php endif; ?>
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
