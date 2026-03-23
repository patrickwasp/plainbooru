<?php
/** @var array $media */
/** @var array $pools */
/** @var bool  $can_edit_tags */
/** @var bool  $is_favorited */
/** @var bool  $can_vote */
/** @var int   $vote_score */
/** @var int|null $user_vote */
/** @var bool  $can_moderate */
/** @var bool  $is_owner */
?>

<!-- Tags: scrollable fill -->
<div class="flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-3">
  <h2 class="font-semibold text-sm">Tags</h2>
  <?php if (!empty($media['tags'])): ?>
    <?php $sortedTags = $media['tags']; sort($sortedTags); ?>
    <div class="flex flex-wrap gap-1">
      <?php foreach ($sortedTags as $tag): ?>
        <span class="badge-outline flex items-center gap-0.5 text-xs">
          <a href="/t/<?= urlencode($tag) ?>" class="hover:underline"><?= $this->e($tag) ?></a>
          <?php if ($can_edit_tags): ?>
            <form action="/m/<?= (int)$media['id'] ?>/tags/remove" method="post" class="inline">
              <?= $this->csrfInput() ?>
              <input type="hidden" name="tag" value="<?= $this->e($tag) ?>">
              <button type="submit" class="text-muted-foreground hover:text-destructive leading-none px-0.5">×</button>
            </form>
          <?php endif; ?>
        </span>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-xs text-muted-foreground">No tags yet.</p>
  <?php endif; ?>

  <?php if (!empty($pools)): ?>
    <div class="flex flex-col gap-1">
      <h2 class="font-semibold text-sm">Pools</h2>
      <?php foreach ($pools as $pool): ?>
        <a href="/pools/<?= (int)$pool['id'] ?>" class="text-xs text-muted-foreground hover:text-foreground hover:underline">
          <?= $this->e($pool['name']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($can_edit_tags): ?>
    <form action="/m/<?= (int)$media['id'] ?>/tags" method="post" class="flex gap-1 mt-auto">
      <?= $this->csrfInput() ?>
      <input type="text" name="tag" placeholder="Add tag…"
             class="input h-8 text-sm flex-1 min-w-0">
      <button type="submit" class="btn-sm-icon-outline">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
      </button>
    </form>
  <?php endif; ?>
</div>

<!-- Social: favorites + votes -->
<div class="shrink-0 border-t border-border px-5 py-3 flex items-center gap-3">

  <!-- Favorite toggle -->
  <form action="/m/<?= (int)$media['id'] ?>/favorite" method="post" class="contents">
    <?= $this->csrfInput() ?>
    <button type="submit"
            class="flex items-center text-xs btn-ghost px-2 py-1 rounded <?= $is_favorited ? 'text-yellow-500' : 'text-muted-foreground' ?>"
            title="<?= $is_favorited ? 'Remove from favorites' : 'Add to favorites' ?>">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
           fill="<?= $is_favorited ? 'currentColor' : 'none' ?>"
           stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
      </svg>
    </button>
  </form>

  <!-- Votes -->
  <?php if ($can_vote): ?>
    <div class="flex items-center gap-1 ml-auto">
      <form action="/m/<?= (int)$media['id'] ?>/vote" method="post" class="contents">
        <?= $this->csrfInput() ?>
        <input type="hidden" name="value" value="1">
        <button type="submit"
                class="btn-ghost px-1.5 py-1 rounded text-xs <?= $user_vote === 1 ? 'text-green-500' : 'text-muted-foreground' ?>"
                title="Upvote">▲</button>
      </form>
      <span class="text-xs font-mono w-6 text-center"><?= (int)$vote_score ?></span>
      <form action="/m/<?= (int)$media['id'] ?>/vote" method="post" class="contents">
        <?= $this->csrfInput() ?>
        <input type="hidden" name="value" value="-1">
        <button type="submit"
                class="btn-ghost px-1.5 py-1 rounded text-xs <?= $user_vote === -1 ? 'text-red-500' : 'text-muted-foreground' ?>"
                title="Downvote">▼</button>
      </form>
    </div>
  <?php else: ?>
    <span class="text-xs text-muted-foreground ml-auto"><?= (int)$vote_score > 0 ? '+' : '' ?><?= (int)$vote_score ?></span>
  <?php endif; ?>

</div>

<!-- Details: pinned to bottom, accordion opens upward -->
<?php
  $_rows = [
      ['ID',       '#' . (int)$media['id']],
      ['Type',     $this->e($media['mime'])],
      ['Size',     $this->formatBytes((int)$media['size_bytes'])],
  ];
  if ($media['width'] && $media['height']) {
      $_rows[] = ['Dimensions', (int)$media['width'] . ' × ' . (int)$media['height']];
  }
  if ($media['duration_seconds']) {
      $_rows[] = ['Duration', number_format((float)$media['duration_seconds'], 1) . 's'];
  }
  $_rows[] = ['Uploaded', $this->e(substr($media['created_at'], 0, 10))];
  $_id = (int)$media['id'];
  ob_start(); ?>
  <div role="group" class="button-group w-full">
    <a href="/file/<?= $_id ?>" download class="btn-sm-outline flex-1 justify-center">Download</a>
    <a href="/api/v1/media/<?= $_id ?>" class="btn-sm-outline" target="_blank">JSON ↗</a>
    <?php if ($can_moderate || $is_owner): ?>
      <form action="/m/<?= $_id ?>/delete" method="post">
        <?= $this->csrfInput() ?>
        <button type="submit" class="btn-sm-destructive">Delete</button>
      </form>
    <?php endif; ?>
  </div>
  <?php $_actions = ob_get_clean(); ?>
<div class="shrink-0 border-t">
  <?= $this->partial('details_accordion', ['title' => 'Details', 'rows' => $_rows, 'actions' => $_actions, 'upward' => true, 'class' => 'px-5']) ?>
</div>
