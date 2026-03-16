<?php // post_pool.php — media viewer with pool navigation context ?>
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

    <!-- Pool navigation -->
    <div class="shrink-0 border-b border-border px-5 py-3 flex flex-col gap-2">
      <a href="/pools/<?= (int)$pool['id'] ?>" class="text-xs text-muted-foreground hover:text-foreground hover:underline flex items-center gap-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        <?= $this->e($pool['name']) ?>
      </a>
      <div class="flex items-center gap-1">
        <?php if ($pool_prev !== null): ?>
          <a href="/pools/<?= (int)$pool['id'] ?>/m/<?= (int)$pool_prev ?>" class="btn-sm-outline px-2" title="Previous in pool">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </a>
        <?php else: ?>
          <span class="btn-sm-outline px-2 opacity-30 cursor-not-allowed">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </span>
        <?php endif; ?>
        <span class="text-xs text-muted-foreground flex-1 text-center"><?= (int)$pool_pos ?> / <?= (int)$pool_total ?></span>
        <?php if ($pool_next !== null): ?>
          <a href="/pools/<?= (int)$pool['id'] ?>/m/<?= (int)$pool_next ?>" class="btn-sm-outline px-2" title="Next in pool">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </a>
        <?php else: ?>
          <span class="btn-sm-outline px-2 opacity-30 cursor-not-allowed">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </span>
        <?php endif; ?>
      </div>
    </div>

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
          <?php foreach ($pools as $p): ?>
            <a href="/pools/<?= (int)$p['id'] ?>" class="text-xs text-muted-foreground hover:text-foreground hover:underline">
              <?= $this->e($p['name']) ?>
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
