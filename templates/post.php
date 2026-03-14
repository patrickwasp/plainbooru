<?php // post.php ?>
<div class="max-w-5xl mx-auto">
  <!-- Breadcrumb -->
  <nav class="text-sm text-muted-foreground mb-4 flex gap-1 items-center">
    <a href="/" class="hover:text-foreground">Home</a>
    <span>/</span>
    <span>Post #<?= (int)$media['id'] ?></span>
  </nav>

  <!-- Navigation -->
  <div class="flex justify-between mb-3">
    <?php if ($prevId): ?>
      <a href="/m/<?= (int)$prevId ?>" class="btn-sm-outline">« Previous</a>
    <?php else: ?>
      <span></span>
    <?php endif; ?>
    <?php if ($nextId): ?>
      <a href="/m/<?= (int)$nextId ?>" class="btn-sm-outline">Next »</a>
    <?php endif; ?>
  </div>

  <div class="grid md:grid-cols-3 gap-6">
    <!-- Media display -->
    <div class="md:col-span-2">
      <div class="card p-2 shadow-sm">
        <?php if ($media['kind'] === 'image'): ?>
          <img src="/file/<?= (int)$media['id'] ?>"
               alt="Post #<?= (int)$media['id'] ?>"
               class="w-full rounded-sm"
               <?= $media['width'] ? 'width="' . (int)$media['width'] . '"' : '' ?>
               <?= $media['height'] ? 'height="' . (int)$media['height'] . '"' : '' ?>>
        <?php else: ?>
          <video controls preload="metadata" class="w-full rounded-sm"
                 poster="/thumb/<?= (int)$media['id'] ?>">
            <source src="/file/<?= (int)$media['id'] ?>" type="<?= $this->e($media['mime']) ?>">
            Your browser does not support video playback.
          </video>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sidebar info -->
    <div class="flex flex-col gap-4">
      <!-- Tags -->
      <div class="card p-4 shadow-sm">
        <h2 class="font-semibold mb-2">Tags</h2>
        <?php if (empty($media['tags'])): ?>
          <p class="text-sm text-muted-foreground">No tags.</p>
        <?php else: ?>
          <div class="flex flex-wrap gap-1">
            <?php foreach ($media['tags'] as $tag): ?>
              <a href="/t/<?= urlencode($tag) ?>" class="badge-outline hover:bg-accent text-xs"><?= $this->e($tag) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="card p-4 shadow-sm">
        <h2 class="font-semibold mb-2">Info</h2>
        <table class="table w-full text-sm">
          <tbody>
            <tr><td class="font-medium py-0.5 pr-2">ID</td><td class="py-0.5">#<?= (int)$media['id'] ?></td></tr>
            <tr><td class="font-medium py-0.5 pr-2">Type</td><td class="py-0.5"><?= $this->e($media['mime']) ?></td></tr>
            <tr><td class="font-medium py-0.5 pr-2">Size</td><td class="py-0.5"><?= number_format($media['size_bytes'] / 1024, 1) ?> KB</td></tr>
            <?php if ($media['width'] && $media['height']): ?>
              <tr><td class="font-medium py-0.5 pr-2">Dimensions</td><td class="py-0.5"><?= (int)$media['width'] ?> × <?= (int)$media['height'] ?></td></tr>
            <?php endif; ?>
            <?php if ($media['duration_seconds']): ?>
              <tr><td class="font-medium py-0.5 pr-2">Duration</td><td class="py-0.5"><?= number_format((float)$media['duration_seconds'], 1) ?>s</td></tr>
            <?php endif; ?>
            <tr><td class="font-medium py-0.5 pr-2">Uploaded</td><td class="py-0.5"><?= $this->e(substr($media['created_at'], 0, 10)) ?></td></tr>
            <?php if ($media['source']): ?>
              <tr><td class="font-medium py-0.5 pr-2">Source</td><td class="py-0.5"><a href="<?= $this->e($media['source']) ?>" target="_blank" rel="noopener" class="underline text-primary truncate block max-w-[140px]">link</a></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
        <a href="/file/<?= (int)$media['id'] ?>" download class="btn-sm-outline mt-3 inline-flex">Download</a>
      </div>

      <!-- API link -->
      <div class="card p-3 shadow-sm">
        <a href="/api/v1/media/<?= (int)$media['id'] ?>" class="text-sm text-primary underline" target="_blank">View JSON API</a>
      </div>
    </div>
  </div>
</div>
