<?php
/** @var array $m  Media item row. Requires: id (int), kind (string, optional). */
?>
<a href="/m/<?= (int)$m['id'] ?>"
   class="relative block rounded overflow-hidden hover:opacity-90 transition-opacity group">
  <div class="aspect-square bg-muted overflow-hidden">
    <img src="/thumb/<?= (int)$m['id'] ?>" alt="Post #<?= (int)$m['id'] ?>"
         class="w-full h-full object-cover" loading="lazy">
  </div>
  <?php if (($m['kind'] ?? '') === 'video'): ?>
    <span class="absolute top-1 left-1 bg-black/65 text-white text-xs w-5 h-5 flex items-center justify-center rounded leading-none pointer-events-none">▶</span>
  <?php endif; ?>
</a>
