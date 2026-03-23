<?php // post.php ?>
<?php $lb = 'lb-' . (int)$media['id']; ?>
<div class="flex-1 min-w-0 overflow-y-auto flex flex-col">

    <?php if ($is_pending ?? false): ?>
    <div class="border-b border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400 text-sm px-6 py-2 shrink-0">
      This post is awaiting moderation and is only visible to you and moderators.
      <?php if ($can_moderate ?? false): ?>
        <a href="/admin/queue" class="underline ml-1">Review queue</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Media: centered, takes as much height as available -->
    <div class="flex items-center justify-center p-6 h-[calc(100dvh-3.5rem)] shrink-0">
      <?php if ($media['kind'] === 'image'): ?>
        <a href="#<?= $lb ?>" class="block max-w-full cursor-zoom-in">
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

    <!-- Comments -->
    <div class="w-full">
      <?= $this->partial('post_comments', [
          'media'        => $media,
          'comments'     => $comments,
          'can_comment'  => $can_comment,
          'currentUser'  => $currentUser,
      ]) ?>
    </div>

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
