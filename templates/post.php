<?php // post.php ?>
<?php $lb = 'lb-' . (int)$media['id']; ?>
<div class="flex flex-1 overflow-hidden w-full">

  <!-- Media -->
  <div class="flex-1 min-w-0 flex items-center justify-center p-6 overflow-hidden">
    <?php if ($media['kind'] === 'image'): ?>
      <a href="#<?= $lb ?>" class="block max-w-full max-h-full cursor-zoom-in">
        <img src="/file/<?= (int)$media['id'] ?>"
             alt="Post #<?= (int)$media['id'] ?>"
             class="max-w-full max-h-[calc(100dvh-3.5rem-3rem)] object-contain rounded-sm">
      </a>
    <?php else: ?>
      <video controls preload="metadata" class="max-w-full max-h-[calc(100dvh-3.5rem-3rem)] rounded-sm"
             poster="/thumb/<?= (int)$media['id'] ?>">
        <source src="/file/<?= (int)$media['id'] ?>" type="<?= $this->e($media['mime']) ?>">
        Your browser does not support video playback.
      </video>
    <?php endif; ?>
  </div>

  <!-- Right sidebar: fixed to right edge of viewport -->
  <aside class="w-64 shrink-0 border-l border-border flex flex-col">

    <!-- Tags: scrollable fill -->
    <div class="flex-1 overflow-y-auto px-5 py-4 flex flex-col gap-3">
      <h2 class="font-semibold text-sm">Tags</h2>
      <?php if (!empty($media['tags'])): ?>
        <?php $sortedTags = $media['tags']; sort($sortedTags); ?>
        <div class="flex flex-wrap gap-1">
          <?php foreach ($sortedTags as $tag): ?>
            <span class="badge-outline flex items-center gap-0.5 text-xs">
              <a href="/t/<?= urlencode($tag) ?>" class="hover:underline"><?= $this->e($tag) ?></a>
              <form action="/m/<?= (int)$media['id'] ?>/tags/remove" method="post" class="inline">
                <input type="hidden" name="tag" value="<?= $this->e($tag) ?>">
                <button type="submit" class="text-muted-foreground hover:text-destructive leading-none px-0.5">×</button>
              </form>
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

      <form action="/m/<?= (int)$media['id'] ?>/tags" method="post" class="flex gap-1 mt-auto">
        <input type="text" name="tag" placeholder="Add tag…"
               class="input h-8 text-sm flex-1 min-w-0">
        <button type="submit" class="btn-sm-icon-outline">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
        </button>
      </form>
    </div>

    <!-- Details: pinned to bottom, accordion opens upward -->
    <?php
      $bytes = (int)$media['size_bytes'];
      $units = ['B', 'KB', 'MB', 'GB', 'TB'];
      $i = 0;
      while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }
      $_rows = [
          ['ID',       '#' . (int)$media['id']],
          ['Type',     $this->e($media['mime'])],
          ['Size',     number_format($bytes, 2) . ' ' . $units[$i]],
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
        <form action="/m/<?= $_id ?>/delete" method="post">
          <button type="submit" class="btn-sm-destructive">Delete</button>
        </form>
      </div>
      <?php $_actions = ob_get_clean(); ?>
    <div class="shrink-0 border-t">
      <?= $this->partial('details_accordion', ['title' => 'Details', 'rows' => $_rows, 'actions' => $_actions, 'upward' => true, 'class' => 'px-5']) ?>
    </div>

  </aside>

</div>

<?php if ($media['kind'] === 'image'): ?>
<!-- CSS-only lightbox via :target -->
<div id="<?= $lb ?>" class="lightbox">
  <a href="#" aria-label="Close lightbox">
    <img src="/file/<?= (int)$media['id'] ?>"
         alt="Post #<?= (int)$media['id'] ?> full size">
  </a>
</div>
<?php endif; ?>
