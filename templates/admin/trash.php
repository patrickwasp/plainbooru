<?php
// admin/trash.php
/** @var array  $deletedMedia        */
/** @var int    $mediaTotal          */
/** @var int    $mediaPage           */
/** @var int    $mediaTotalPages     */
/** @var array  $deletedPools        */
/** @var int    $poolsTotal          */
/** @var int    $poolsPage           */
/** @var int    $poolsTotalPages     */
/** @var array  $deletedTags         */
/** @var int    $tagsTotal           */
/** @var int    $tagPage             */
/** @var int    $tagsTotalPages      */
/** @var array  $deletedComments     */
/** @var int    $commentsTotal       */
/** @var int    $commentPage         */
/** @var int    $commentsTotalPages  */
?>
<div class="max-w-5xl flex flex-col gap-10">

  <div>
    <h1 class="text-2xl font-bold">Trash</h1>
    <p class="text-sm text-muted-foreground mt-1">Soft-deleted items. Restore to bring back, or purge to permanently delete.</p>
  </div>

  <!-- Deleted Media -->
  <section class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Deleted Media <span class="text-sm font-normal text-muted-foreground">(<?= (int)$mediaTotal ?>)</span></h2>
    </div>

    <?php if (empty($deletedMedia)): ?>
      <p class="text-sm text-muted-foreground">No deleted media.</p>
    <?php else: ?>
      <div class="card shadow-sm overflow-hidden p-0 gap-0">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-left">
              <th class="px-4 py-2 font-medium w-10">#</th>
              <th class="px-4 py-2 font-medium">Name</th>
              <th class="px-4 py-2 font-medium">Kind</th>
              <th class="px-4 py-2 font-medium">Deleted at</th>
              <th class="px-4 py-2 font-medium">Deleted by</th>
              <th class="px-4 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <?php foreach ($deletedMedia as $m): ?>
              <tr class="hover:bg-muted/20">
                <td class="px-4 py-2 text-muted-foreground"><?= (int)$m['id'] ?></td>
                <td class="px-4 py-2 font-mono text-xs truncate max-w-xs"><?= $this->e($m['original_name']) ?></td>
                <td class="px-4 py-2 text-xs">
                  <span class="badge-outline"><?= $this->e($m['kind']) ?></span>
                </td>
                <td class="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap"><?= $this->e(substr($m['deleted_at'], 0, 16)) ?></td>
                <td class="px-4 py-2 text-xs">
                  <?php if (!empty($m['deleted_by_username'])): ?>
                    <a href="/u/<?= urlencode($m['deleted_by_username']) ?>" class="hover:underline"><?= $this->e($m['deleted_by_username']) ?></a>
                  <?php else: ?>
                    <span class="text-muted-foreground italic">unknown</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <form action="/admin/trash/media/<?= (int)$m['id'] ?>/restore" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-outline text-xs h-7 px-2">Restore</button>
                    </form>
                    <form action="/admin/trash/media/<?= (int)$m['id'] ?>/purge" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-destructive text-xs h-7 px-2"
                              onclick="return confirm('Permanently delete this media and its files? This cannot be undone.')">Purge</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($mediaTotalPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($mediaPage > 1): ?>
            <a href="/admin/trash?mp=<?= $mediaPage - 1 ?>&pp=<?= (int)$poolsPage ?>&tp=<?= (int)$tagPage ?>&cp=<?= (int)$commentPage ?>" class="btn-sm-outline">Previous</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= (int)$mediaPage ?> of <?= (int)$mediaTotalPages ?></span>
          <?php if ($mediaPage < $mediaTotalPages): ?>
            <a href="/admin/trash?mp=<?= $mediaPage + 1 ?>&pp=<?= (int)$poolsPage ?>&tp=<?= (int)$tagPage ?>&cp=<?= (int)$commentPage ?>" class="btn-sm-outline">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Deleted Pools -->
  <section class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Deleted Pools <span class="text-sm font-normal text-muted-foreground">(<?= (int)$poolsTotal ?>)</span></h2>
    </div>

    <?php if (empty($deletedPools)): ?>
      <p class="text-sm text-muted-foreground">No deleted pools.</p>
    <?php else: ?>
      <div class="card shadow-sm overflow-hidden p-0 gap-0">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-left">
              <th class="px-4 py-2 font-medium w-10">#</th>
              <th class="px-4 py-2 font-medium">Name</th>
              <th class="px-4 py-2 font-medium">Deleted at</th>
              <th class="px-4 py-2 font-medium">Deleted by</th>
              <th class="px-4 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <?php foreach ($deletedPools as $p): ?>
              <tr class="hover:bg-muted/20">
                <td class="px-4 py-2 text-muted-foreground"><?= (int)$p['id'] ?></td>
                <td class="px-4 py-2 font-medium"><?= $this->e($p['name']) ?></td>
                <td class="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap"><?= $this->e(substr($p['deleted_at'], 0, 16)) ?></td>
                <td class="px-4 py-2 text-xs">
                  <?php if (!empty($p['deleted_by_username'])): ?>
                    <a href="/u/<?= urlencode($p['deleted_by_username']) ?>" class="hover:underline"><?= $this->e($p['deleted_by_username']) ?></a>
                  <?php else: ?>
                    <span class="text-muted-foreground italic">unknown</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <form action="/admin/trash/pools/<?= (int)$p['id'] ?>/restore" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-outline text-xs h-7 px-2">Restore</button>
                    </form>
                    <form action="/admin/trash/pools/<?= (int)$p['id'] ?>/purge" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-destructive text-xs h-7 px-2"
                              onclick="return confirm('Permanently delete this pool? This cannot be undone.')">Purge</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($poolsTotalPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($poolsPage > 1): ?>
            <a href="/admin/trash?mp=<?= (int)$mediaPage ?>&pp=<?= $poolsPage - 1 ?>&tp=<?= (int)$tagPage ?>&cp=<?= (int)$commentPage ?>" class="btn-sm-outline">Previous</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= (int)$poolsPage ?> of <?= (int)$poolsTotalPages ?></span>
          <?php if ($poolsPage < $poolsTotalPages): ?>
            <a href="/admin/trash?mp=<?= (int)$mediaPage ?>&pp=<?= $poolsPage + 1 ?>&tp=<?= (int)$tagPage ?>&cp=<?= (int)$commentPage ?>" class="btn-sm-outline">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Deleted Tags -->
  <section class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Deleted Tags <span class="text-sm font-normal text-muted-foreground">(<?= (int)$tagsTotal ?>)</span></h2>
    </div>

    <?php if (empty($deletedTags)): ?>
      <p class="text-sm text-muted-foreground">No deleted tags.</p>
    <?php else: ?>
      <div class="card shadow-sm overflow-hidden p-0 gap-0">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-left">
              <th class="px-4 py-2 font-medium">Name</th>
              <th class="px-4 py-2 font-medium">Media</th>
              <th class="px-4 py-2 font-medium">Deleted at</th>
              <th class="px-4 py-2 font-medium">Deleted by</th>
              <th class="px-4 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <?php foreach ($deletedTags as $t): ?>
              <tr class="hover:bg-muted/20">
                <td class="px-4 py-2 font-mono text-xs"><?= $this->e($t['name']) ?></td>
                <td class="px-4 py-2 text-xs text-muted-foreground"><?= (int)$t['media_count'] ?></td>
                <td class="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap"><?= $this->e(substr($t['deleted_at'], 0, 16)) ?></td>
                <td class="px-4 py-2 text-xs">
                  <?php if (!empty($t['deleted_by_username'])): ?>
                    <a href="/u/<?= urlencode($t['deleted_by_username']) ?>" class="hover:underline"><?= $this->e($t['deleted_by_username']) ?></a>
                  <?php else: ?>
                    <span class="text-muted-foreground italic">unknown</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <form action="/admin/trash/tags/<?= urlencode($t['name']) ?>/restore" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-outline text-xs h-7 px-2">Restore</button>
                    </form>
                    <form action="/admin/trash/tags/<?= urlencode($t['name']) ?>/purge" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-destructive text-xs h-7 px-2"
                              onclick="return confirm('Permanently delete tag &quot;<?= $this->e(addslashes($t['name'])) ?>&quot; and remove it from all media? This cannot be undone.')">Purge</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($tagsTotalPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($tagPage > 1): ?>
            <a href="/admin/trash?mp=<?= (int)$mediaPage ?>&pp=<?= (int)$poolsPage ?>&tp=<?= $tagPage - 1 ?>&cp=<?= (int)$commentPage ?>" class="btn-sm-outline">Previous</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= (int)$tagPage ?> of <?= (int)$tagsTotalPages ?></span>
          <?php if ($tagPage < $tagsTotalPages): ?>
            <a href="/admin/trash?mp=<?= (int)$mediaPage ?>&pp=<?= (int)$poolsPage ?>&tp=<?= $tagPage + 1 ?>&cp=<?= (int)$commentPage ?>" class="btn-sm-outline">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

  <!-- Deleted Comments -->
  <section class="flex flex-col gap-4">
    <div class="flex items-center justify-between">
      <h2 class="text-lg font-semibold">Deleted Comments <span class="text-sm font-normal text-muted-foreground">(<?= (int)$commentsTotal ?>)</span></h2>
    </div>

    <?php if (empty($deletedComments)): ?>
      <p class="text-sm text-muted-foreground">No deleted comments.</p>
    <?php else: ?>
      <div class="card shadow-sm overflow-hidden p-0 gap-0">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border bg-muted/30 text-left">
              <th class="px-4 py-2 font-medium w-10">#</th>
              <th class="px-4 py-2 font-medium">Author</th>
              <th class="px-4 py-2 font-medium">Body</th>
              <th class="px-4 py-2 font-medium">Media</th>
              <th class="px-4 py-2 font-medium">Deleted at</th>
              <th class="px-4 py-2 font-medium">Deleted by</th>
              <th class="px-4 py-2 font-medium">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <?php foreach ($deletedComments as $c): ?>
              <tr class="hover:bg-muted/20">
                <td class="px-4 py-2 text-muted-foreground"><?= (int)$c['id'] ?></td>
                <td class="px-4 py-2 text-xs">
                  <?php if (!empty($c['username'])): ?>
                    <a href="/u/<?= urlencode($c['username']) ?>" class="hover:underline"><?= $this->e($c['username']) ?></a>
                  <?php else: ?>
                    <span class="text-muted-foreground italic">anonymous</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2 text-xs text-muted-foreground max-w-xs truncate"><?= $this->e(mb_strimwidth($c['body'], 0, 80, '…')) ?></td>
                <td class="px-4 py-2 text-xs">
                  <a href="/m/<?= (int)$c['media_id'] ?>" class="hover:underline text-muted-foreground">#<?= (int)$c['media_id'] ?></a>
                </td>
                <td class="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap"><?= $this->e(substr($c['deleted_at'], 0, 16)) ?></td>
                <td class="px-4 py-2 text-xs">
                  <?php if (!empty($c['deleted_by_username'])): ?>
                    <a href="/u/<?= urlencode($c['deleted_by_username']) ?>" class="hover:underline"><?= $this->e($c['deleted_by_username']) ?></a>
                  <?php else: ?>
                    <span class="text-muted-foreground italic">unknown</span>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-2">
                  <div class="flex items-center gap-2">
                    <form action="/admin/trash/comments/<?= (int)$c['id'] ?>/restore" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-outline text-xs h-7 px-2">Restore</button>
                    </form>
                    <form action="/admin/trash/comments/<?= (int)$c['id'] ?>/purge" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="btn-sm-destructive text-xs h-7 px-2"
                              onclick="return confirm('Permanently delete this comment? This cannot be undone.')">Purge</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($commentsTotalPages > 1): ?>
        <div class="flex items-center gap-2 text-sm">
          <?php if ($commentPage > 1): ?>
            <a href="/admin/trash?mp=<?= (int)$mediaPage ?>&pp=<?= (int)$poolsPage ?>&tp=<?= (int)$tagPage ?>&cp=<?= $commentPage - 1 ?>" class="btn-sm-outline">Previous</a>
          <?php endif; ?>
          <span class="text-muted-foreground">Page <?= (int)$commentPage ?> of <?= (int)$commentsTotalPages ?></span>
          <?php if ($commentPage < $commentsTotalPages): ?>
            <a href="/admin/trash?mp=<?= (int)$mediaPage ?>&pp=<?= (int)$poolsPage ?>&tp=<?= (int)$tagPage ?>&cp=<?= $commentPage + 1 ?>" class="btn-sm-outline">Next</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </section>

</div>
