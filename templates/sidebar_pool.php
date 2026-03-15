<?php
// sidebar_pool.php — pool page sidebar: tags + details accordion
// $pool: pool array
?>

<!-- Tags: scrollable fill -->
<div class="flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-3">
  <h2 class="font-semibold text-sm">Tags</h2>
  <?php if (!empty($pool['tags'])): ?>
    <div class="flex flex-wrap gap-1">
      <?php foreach ($pool['tags'] as $tag): ?>
        <a href="/t/<?= urlencode($tag) ?>" class="badge-outline text-xs hover:bg-accent"><?= $this->e($tag) ?></a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <p class="text-xs text-muted-foreground">No tags yet.</p>
  <?php endif; ?>

  <form action="/pools/<?= (int)$pool['id'] ?>/tags" method="post" class="flex gap-1 mt-auto">
    <input type="hidden" name="return" value="/pools/<?= (int)$pool['id'] ?>">
    <input type="text" name="tag" placeholder="Add tag…"
           class="input h-8 text-sm flex-1 min-w-0">
    <button type="submit" class="btn-sm-icon-outline">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
    </button>
  </form>
</div>

<!-- Details: pinned to bottom, accordion opens upward -->
<?php
  $_count = count($pool['items']);
  $_pid   = (int)$pool['id'];
  $_rows  = [
      ['Items',   $_count . ' item' . ($_count !== 1 ? 's' : '')],
      ['Created', $this->e(substr($pool['created_at'], 0, 10))],
  ];
  $_actions = '<a href="/pools/' . $_pid . '/edit" class="btn-outline w-full">Edit Pool</a>';
?>
<div class="shrink-0 border-t">
  <?= $this->partial('details_accordion', ['title' => 'Details', 'rows' => $_rows, 'actions' => $_actions, 'upward' => true, 'class' => 'px-5']) ?>
</div>
