<?php
// post_comments.php — comment list + form partial
// Variables: $media (array), $comments (array), $can_comment (bool), $currentUser (?array)
$isMod = in_array($currentUser['role'] ?? '', ['moderator', 'admin'], true);
?>
<section id="comments" class="flex flex-col border-t border-border">

  <div class="w-full max-w-2xl mx-auto">

    <div class="px-6 pt-5 pb-3">
      <h2 class="text-sm font-semibold text-foreground">
        Comments
        <span class="font-normal text-muted-foreground">(<?= count($comments) ?>)</span>
      </h2>
    </div>

    <?php if (!empty($comments)): ?>
      <div class="divide-y divide-border">
        <?php foreach ($comments as $c): ?>
          <?php
            $canDelete = $currentUser && (
                ($c['user_id'] !== null && (int)$c['user_id'] === (int)$currentUser['id'])
                || $isMod
            );
            $initial = strtoupper(substr($c['username'] ?? 'A', 0, 1));
          ?>
          <div class="px-6 py-4 flex gap-3">
            <div class="w-8 h-8 rounded-full bg-accent ring-1 ring-border flex items-center justify-center text-xs font-semibold shrink-0 text-muted-foreground select-none">
              <?= $this->e($initial) ?>
            </div>
            <div class="flex-1 min-w-0 flex flex-col gap-1.5">
              <div class="flex items-baseline gap-2">
                <?php if ($c['username']): ?>
                  <a href="/u/<?= urlencode($c['username']) ?>" class="text-sm font-medium hover:underline"><?= $this->e($c['username']) ?></a>
                <?php else: ?>
                  <span class="text-sm font-medium text-muted-foreground italic">anonymous</span>
                <?php endif; ?>
                <span class="text-xs text-muted-foreground"><?= $this->e(substr($c['created_at'], 0, 10)) ?></span>
                <?php if ($canDelete): ?>
                  <form action="/m/<?= (int)$media['id'] ?>/comments/<?= (int)$c['id'] ?>/delete" method="post" class="ml-auto">
                    <?= $this->csrfInput() ?>
                    <button type="submit" class="text-xs text-muted-foreground hover:text-destructive">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
              <p class="text-sm whitespace-pre-wrap break-words"><?= $this->e($c['body']) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="px-6 py-3 text-sm text-muted-foreground">No comments yet.</p>
    <?php endif; ?>

    <?php if ($can_comment): ?>
      <div class="px-6 py-5 border-t border-border">
        <form action="/m/<?= (int)$media['id'] ?>/comments" method="post" class="grid gap-3">
          <?= $this->csrfInput() ?>
          <textarea name="body" rows="3" maxlength="2000" placeholder="Add a comment…" required class="textarea"></textarea>
          <div class="flex justify-end">
            <button type="submit" class="btn-sm-primary">Post comment</button>
          </div>
        </form>
      </div>
    <?php elseif (!$currentUser): ?>
      <div class="px-6 py-5 border-t border-border">
        <p class="text-sm text-muted-foreground">
          <a href="/login?next=/m/<?= (int)$media['id'] ?>" class="underline text-foreground">Log in</a> to comment.
        </p>
      </div>
    <?php endif; ?>

  </div>

</section>
