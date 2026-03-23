<?php
/** @var string $tab */
/** @var int    $page */
/** @var int    $page_size */
/** @var array  $pendingMedia */
/** @var array  $pendingComments */
/** @var array  $pendingTags */
/** @var array  $currentUser */

$mediaTotal    = $pendingMedia['total']    ?? 0;
$commentTotal  = $pendingComments['total'] ?? 0;
$tagTotal      = $pendingTags['total']     ?? 0;

$mediaPages   = max(1, (int)ceil($mediaTotal   / max(1, $page_size)));
$commentPages = max(1, (int)ceil($commentTotal / max(1, $page_size)));
$tagPages     = max(1, (int)ceil($tagTotal     / max(1, $page_size)));
?>
<div class="max-w-5xl flex flex-col gap-6">

  <?= $this->partial('admin/nav', ['adminSection' => 'queue', 'currentUser' => $currentUser]) ?>

  <div>
    <h1 class="text-2xl font-bold">Moderation Queue</h1>
    <p class="text-sm text-muted-foreground mt-1">
      <?= $mediaTotal ?> media · <?= $commentTotal ?> comments · <?= $tagTotal ?> tags pending
    </p>
  </div>

  <!-- Tab bar -->
  <div class="flex gap-1 border-b border-border -mt-2">
    <?php foreach ([
        ['media',    'Media',    $mediaTotal],
        ['comments', 'Comments', $commentTotal],
        ['tags',     'Tags',     $tagTotal],
    ] as [$key, $label, $count]): ?>
      <a href="?tab=<?= $key ?>"
         class="px-4 py-2 text-sm font-medium border-b-2 -mb-px <?= $tab === $key
             ? 'border-primary text-foreground'
             : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border' ?>">
        <?= $label ?>
        <?php if ($count > 0): ?>
          <span class="ml-1 badge text-xs bg-destructive/10 text-destructive border border-destructive/30"><?= $count ?></span>
        <?php endif; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <?php if ($tab === 'media'): ?>
    <!-- ── Pending media ──────────────────────────────────────────────── -->
    <?php if (empty($pendingMedia['results'])): ?>
      <p class="text-sm text-muted-foreground">No pending media.</p>
    <?php else: ?>
      <div class="flex flex-col gap-3">
        <?php foreach ($pendingMedia['results'] as $m): ?>
          <?php $lb = 'qlb-' . (int)$m['id']; ?>
          <div class="card p-0 overflow-hidden gap-0 shadow-sm">
            <div class="flex gap-4 p-4 items-center">

              <!-- Thumbnail — click opens lightbox -->
              <a href="#<?= $lb ?>" class="shrink-0 block w-24 h-24 rounded overflow-hidden bg-muted cursor-zoom-in relative">
                <img src="/thumb/<?= (int)$m['id'] ?>"
                     alt="Post #<?= (int)$m['id'] ?>"
                     class="w-full h-full object-cover">
                <?php if ($m['kind'] === 'video'): ?>
                  <span class="absolute bottom-1 right-1 badge text-xs bg-black/70 text-white border-0">▶</span>
                <?php endif; ?>
              </a>

              <!-- Meta -->
              <div class="flex-1 min-w-0 flex flex-col gap-1 text-sm">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-medium">#<?= (int)$m['id'] ?></span>
                  <span class="text-muted-foreground"><?= $this->e($m['original_name']) ?></span>
                  <span class="badge-outline text-xs"><?= $this->e($m['mime']) ?></span>
                </div>
                <div class="text-xs text-muted-foreground flex gap-3 flex-wrap">
                  <span>By <strong><?= $this->e($m['uploader_username'] ?? 'anon') ?></strong></span>
                  <span><?= $this->formatBytes((int)$m['size_bytes']) ?></span>
                  <?php if ($m['width'] && $m['height']): ?>
                    <span><?= (int)$m['width'] ?> × <?= (int)$m['height'] ?></span>
                  <?php endif; ?>
                  <span>Submitted <?= $this->e(substr($m['pending_at'], 0, 10)) ?></span>
                </div>
              </div>

              <!-- Actions -->
              <div class="flex gap-2 shrink-0 pr-2">
                <form action="/admin/queue/media/<?= (int)$m['id'] ?>/approve" method="post">
                  <?= $this->csrfInput() ?>
                  <button type="submit" class="btn-sm-outline text-xs text-green-600 border-green-600/40 hover:bg-green-50 dark:hover:bg-green-900/20">Approve</button>
                </form>
                <form action="/admin/queue/media/<?= (int)$m['id'] ?>/reject" method="post">
                  <?= $this->csrfInput() ?>
                  <button type="submit" class="btn-sm-destructive text-xs">Reject</button>
                </form>
              </div>
            </div>
          </div>

          <!-- CSS-only lightbox -->
          <?php if ($m['kind'] === 'image'): ?>
          <div id="<?= $lb ?>" class="lightbox">
            <a href="#" aria-label="Close">
              <img src="/file/<?= (int)$m['id'] ?>" alt="Post #<?= (int)$m['id'] ?> full size">
            </a>
          </div>
          <?php endif; ?>

        <?php endforeach; ?>
      </div>

      <?php if ($mediaPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($page > 1): ?>
            <a href="?tab=media&page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= $page ?> of <?= $mediaPages ?></span>
          <?php if ($page < $mediaPages): ?>
            <a href="?tab=media&page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  <?php elseif ($tab === 'comments'): ?>
    <!-- ── Pending comments ───────────────────────────────────────────── -->
    <?php if (empty($pendingComments['results'])): ?>
      <p class="text-sm text-muted-foreground">No pending comments.</p>
    <?php else: ?>
      <div class="card overflow-hidden p-0 gap-0 shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              <th class="px-4 py-3 text-left">User</th>
              <th class="px-4 py-3 text-left">Media</th>
              <th class="px-4 py-3 text-left">Comment</th>
              <th class="px-4 py-3 text-left">Submitted</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <?php foreach ($pendingComments['results'] as $c): ?>
              <tr>
                <td class="px-4 py-3"><?= $this->e($c['username'] ?? 'anon') ?></td>
                <td class="px-4 py-3">
                  <a href="/m/<?= (int)$c['media_id'] ?>" class="hover:underline text-muted-foreground">#<?= (int)$c['media_id'] ?></a>
                </td>
                <td class="px-4 py-3 max-w-xs">
                  <span class="line-clamp-2"><?= $this->e($c['body']) ?></span>
                </td>
                <td class="px-4 py-3 text-muted-foreground text-xs"><?= $this->e(substr($c['pending_at'], 0, 10)) ?></td>
                <td class="px-4 py-3">
                  <div class="flex gap-1">
                    <form action="/admin/queue/comments/<?= (int)$c['id'] ?>/approve" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-outline text-xs text-green-600 border-green-600/40 hover:bg-green-50 dark:hover:bg-green-900/20">Approve</button>
                    </form>
                    <form action="/admin/queue/comments/<?= (int)$c['id'] ?>/reject" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-destructive text-xs">Reject</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($commentPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($page > 1): ?>
            <a href="?tab=comments&page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= $page ?> of <?= $commentPages ?></span>
          <?php if ($page < $commentPages): ?>
            <a href="?tab=comments&page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  <?php else: ?>
    <!-- ── Pending tags ───────────────────────────────────────────────── -->
    <?php if (empty($pendingTags['results'])): ?>
      <p class="text-sm text-muted-foreground">No pending tags.</p>
    <?php else: ?>
      <div class="card overflow-hidden p-0 gap-0 shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
              <th class="px-4 py-3 text-left">Tag</th>
              <th class="px-4 py-3 text-left">Media</th>
              <th class="px-4 py-3 text-left">User</th>
              <th class="px-4 py-3 text-left">Submitted</th>
              <th class="px-4 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <?php foreach ($pendingTags['results'] as $tq): ?>
              <tr>
                <td class="px-4 py-3 font-mono"><?= $this->e($tq['tag']) ?></td>
                <td class="px-4 py-3">
                  <a href="/m/<?= (int)$tq['media_id'] ?>" class="hover:underline text-muted-foreground">#<?= (int)$tq['media_id'] ?></a>
                  <span class="text-xs text-muted-foreground block"><?= $this->e(substr($tq['original_name'] ?? '', 0, 24)) ?></span>
                </td>
                <td class="px-4 py-3"><?= $this->e($tq['submitter_username'] ?? 'anon') ?></td>
                <td class="px-4 py-3 text-muted-foreground text-xs"><?= $this->e(substr($tq['created_at'], 0, 10)) ?></td>
                <td class="px-4 py-3">
                  <div class="flex gap-1">
                    <form action="/admin/queue/tags/<?= (int)$tq['id'] ?>/approve" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-outline text-xs text-green-600 border-green-600/40 hover:bg-green-50 dark:hover:bg-green-900/20">Approve</button>
                    </form>
                    <form action="/admin/queue/tags/<?= (int)$tq['id'] ?>/reject" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-destructive text-xs">Reject</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if ($tagPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($page > 1): ?>
            <a href="?tab=tags&page=<?= $page - 1 ?>" class="btn-sm-outline">← Prev</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= $page ?> of <?= $tagPages ?></span>
          <?php if ($page < $tagPages): ?>
            <a href="?tab=tags&page=<?= $page + 1 ?>" class="btn-sm-outline">Next →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>

</div>
