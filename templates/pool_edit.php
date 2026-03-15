<?php
/** @var array $pool */
/** @var string|null $error */
?>
<div class="max-w-3xl mx-auto flex flex-col gap-5">

  <!-- Header -->
  <div class="flex items-start justify-between flex-wrap gap-3 pb-5 border-b border-border">
    <div>
      <p class="text-xs font-medium uppercase tracking-widest text-muted-foreground mb-1">Pool Editor</p>
      <h1 class="text-2xl font-bold leading-tight"><?= $this->e($pool['name']) ?></h1>
      <a href="/pools/<?= (int)$pool['id'] ?>" class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground mt-1">
        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
        View pool
      </a>
    </div>
    <form action="/pools/<?= (int)$pool['id'] ?>/delete" method="post">
      <button type="submit" class="btn-destructive btn-sm">Delete Pool</button>
    </form>
  </div>

  <?php if (!empty($error)): ?>
    <?= $this->partial('alert_error', ['error' => $error, 'class' => 'text-sm']) ?>
  <?php endif; ?>

  <!-- Rename / Description -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
      <h2 class="text-sm font-semibold">Rename &amp; Description</h2>
    </div>
    <form action="/pools/<?= (int)$pool['id'] ?>/update" method="post" class="flex flex-col gap-4 p-5">
      <div class="grid sm:grid-cols-2 gap-4">
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium">Name</label>
          <input type="text" name="name" required value="<?= $this->e($pool['name']) ?>" class="input h-9 text-sm">
        </div>
        <div class="flex flex-col gap-1.5">
          <label class="text-sm font-medium">
            Description
            <span class="text-xs font-normal text-muted-foreground ml-1">optional</span>
          </label>
          <input type="text" name="description" value="<?= $this->e($pool['description'] ?? '') ?>" class="input h-9 text-sm" placeholder="Short description…">
        </div>
      </div>
      <div>
        <button type="submit" class="btn-sm-primary">Save changes</button>
      </div>
    </form>
  </section>

  <!-- Pool Tags -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg>
      <h2 class="text-sm font-semibold">Pool Tags</h2>
    </div>
    <div class="p-5 flex flex-col gap-4">
      <?php if (!empty($pool['tags'])): ?>
        <div class="flex flex-wrap gap-1.5">
          <?php foreach ($pool['tags'] as $tag): ?>
            <span class="badge-outline inline-flex items-center gap-1 text-xs py-0.5 px-2">
              <?= $this->e($tag) ?>
              <form action="/pools/<?= (int)$pool['id'] ?>/tags/remove" method="post" class="inline leading-none">
                <input type="hidden" name="tag" value="<?= $this->e($tag) ?>">
                <button type="submit" class="text-muted-foreground hover:text-destructive leading-none" title="Remove tag">
                  <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
              </form>
            </span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <form action="/pools/<?= (int)$pool['id'] ?>/tags" method="post" class="flex gap-2">
        <input type="text" name="tag" placeholder="Add a tag…" class="input h-9 text-sm flex-1">
        <button type="submit" class="btn-sm-outline">Add</button>
      </form>
    </div>
  </section>

  <!-- Upload Media to Pool -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
      <h2 class="text-sm font-semibold">Upload Media to Pool</h2>
      <span class="ml-auto text-xs text-muted-foreground">JPEG · PNG · GIF · WebP · MP4 · WebM</span>
    </div>

    <?= $this->partial('upload_forms', [
        'action'       => '/pools/' . (int)$pool['id'] . '/upload',
        'button_files' => 'Upload &amp; Add to Pool',
        'button_dir'   => 'Upload Directory &amp; Add to Pool',
    ]) ?>
  </section>

  <!-- Items Grid -->
  <?php if (!empty($pool['items'])): ?>
    <section id="items" class="card shadow-sm overflow-hidden p-0 gap-0 scroll-mt-28 md:scroll-mt-16">
      <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0"><rect width="7" height="7" x="3" y="3" rx="1"/><rect width="7" height="7" x="14" y="3" rx="1"/><rect width="7" height="7" x="14" y="14" rx="1"/><rect width="7" height="7" x="3" y="14" rx="1"/></svg>
        <h2 class="text-sm font-semibold">Items</h2>
        <span class="ml-1 text-xs text-muted-foreground">(<?= count($pool['items']) ?>)</span>
      </div>
      <div class="p-5 flex flex-col gap-5">
        <?php $itemCount = count($pool['items']); ?>
        <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-2.5">
          <?php foreach ($pool['items'] as $idx => $item): ?>
            <div class="relative rounded-md overflow-hidden ring-1 ring-border">
              <a href="/m/<?= (int)$item['id'] ?>">
                <div class="aspect-square bg-muted overflow-hidden">
                  <img src="/thumb/<?= (int)$item['id'] ?>" alt="Post #<?= (int)$item['id'] ?>"
                       class="w-full h-full object-cover hover:opacity-90 transition-opacity" loading="lazy">
                </div>
              </a>

              <!-- Delete – top right -->
              <form action="/pools/<?= (int)$pool['id'] ?>/remove" method="post" class="absolute top-1 right-1">
                <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
                <button type="submit" title="Remove from pool" class="bg-black/65 text-white text-xs w-5 h-5 flex items-center justify-center rounded leading-none hover:bg-destructive transition-colors">
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
              </form>

              <!-- Move left – bottom left -->
              <?php if ($idx > 0): ?>
                <form action="/pools/<?= (int)$pool['id'] ?>/move" method="post" class="absolute bottom-1 left-1">
                  <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
                  <input type="hidden" name="direction" value="prev">
                  <button type="submit" title="Move left" class="bg-black/65 text-white w-5 h-5 flex items-center justify-center rounded leading-none hover:bg-black/90 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                  </button>
                </form>
              <?php endif; ?>

              <!-- Move right – bottom right -->
              <?php if ($idx < $itemCount - 1): ?>
                <form action="/pools/<?= (int)$pool['id'] ?>/move" method="post" class="absolute bottom-1 right-1">
                  <input type="hidden" name="media_id" value="<?= (int)$item['id'] ?>">
                  <input type="hidden" name="direction" value="next">
                  <button type="submit" title="Move right" class="bg-black/65 text-white w-5 h-5 flex items-center justify-center rounded leading-none hover:bg-black/90 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

      </div>
    </section>
  <?php else: ?>
    <?= $this->partial('alert', ['title' => 'No items yet', 'body' => 'Upload media above or add by ID below.']) ?>
  <?php endif; ?>

  <!-- Add Existing Media by ID -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
      <h2 class="text-sm font-semibold">Add Existing Media by ID</h2>
    </div>
    <form action="/pools/<?= (int)$pool['id'] ?>/items" method="post" class="flex flex-wrap gap-3 items-end p-5">
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Media ID</label>
        <input type="number" name="media_id" required min="1" placeholder="42" class="input h-9 text-sm w-28">
      </div>
      <button type="submit" class="btn-sm-primary">Add to Pool</button>
    </form>
  </section>

</div>
