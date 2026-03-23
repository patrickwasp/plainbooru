<?php
/** @var array $target */
/** @var array $uploads */
/** @var array $logEntries */
/** @var array $currentUser */
?>
<div class="max-w-5xl flex flex-col gap-6">

  <?= $this->partial('admin/nav', ['adminSection' => 'users', 'currentUser' => $currentUser]) ?>

  <div class="flex items-center gap-3">
    <a href="/admin/users" class="text-sm text-muted-foreground hover:text-foreground">← Users</a>
    <span class="text-muted-foreground">/</span>
    <h1 class="text-2xl font-bold"><?= $this->e($target['username']) ?></h1>
    <span class="badge-outline text-xs"><?= $this->e($target['role']) ?></span>
    <?php if (!empty($target['banned_at'])): ?>
      <span class="badge text-xs bg-destructive/10 text-destructive border border-destructive/30">Banned</span>
    <?php endif; ?>
  </div>

  <!-- User info -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30">
      <h2 class="text-sm font-semibold">Details</h2>
    </div>
    <div class="px-6 py-4 grid gap-1 text-sm font-mono">
      <div class="flex gap-4">
        <span class="text-muted-foreground w-24">ID</span>
        <span><?= (int)$target['id'] ?></span>
      </div>
      <div class="flex gap-4">
        <span class="text-muted-foreground w-24">Joined</span>
        <span><?= $this->e(substr($target['created_at'], 0, 10)) ?></span>
      </div>
      <?php if (!empty($target['banned_at'])): ?>
      <div class="flex gap-4">
        <span class="text-muted-foreground w-24">Banned</span>
        <span><?= $this->e(substr($target['banned_at'], 0, 10)) ?><?= $target['ban_reason'] ? ' — ' . $this->e($target['ban_reason']) : '' ?></span>
      </div>
      <?php endif; ?>
      <div class="flex gap-4">
        <span class="text-muted-foreground w-24">Uploads</span>
        <span><?= count($uploads) ?><?= count($uploads) >= 100 ? '+' : '' ?></span>
      </div>
      <div class="flex gap-4 items-center">
        <span class="text-muted-foreground w-24">Moderation</span>
        <?php $needsMod = (int)($target['requires_moderation'] ?? 1) === 1; ?>
        <form action="/admin/users/<?= (int)$target['id'] ?>/moderation" method="post" class="flex items-center gap-2">
          <?= $this->csrfInput() ?>
          <input type="hidden" name="enabled" value="<?= $needsMod ? '0' : '1' ?>">
          <span class="<?= $needsMod ? 'text-amber-600 dark:text-amber-400' : 'text-green-600 dark:text-green-400' ?>">
            <?= $needsMod ? 'Required' : 'Trusted' ?>
          </span>
          <button type="submit" class="btn-sm-outline text-xs h-6 px-2">
            <?= $needsMod ? 'Mark as trusted' : 'Require moderation' ?>
          </button>
        </form>
      </div>
    </div>
  </section>

  <!-- Uploads -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
      <h2 class="text-sm font-semibold">Uploads</h2>
      <span class="text-xs text-muted-foreground"><?= count($uploads) ?><?= count($uploads) >= 100 ? '+ (showing latest 100)' : '' ?></span>
    </div>
    <?php if (empty($uploads)): ?>
      <p class="px-6 py-4 text-sm text-muted-foreground">No uploads.</p>
    <?php else: ?>
      <div class="grid grid-cols-[repeat(auto-fill,minmax(8rem,1fr))] gap-2 p-4">
        <?php foreach ($uploads as $m): ?>
          <a href="/m/<?= (int)$m['id'] ?>" class="group flex flex-col gap-1 rounded border border-border hover:border-foreground/30 overflow-hidden bg-muted/20" target="_blank">
            <div class="aspect-square bg-muted relative overflow-hidden">
              <img src="/thumb/<?= (int)$m['id'] ?>" alt=""
                   class="w-full h-full object-cover"
                   loading="lazy">
              <?php if ($m['kind'] === 'video'): ?>
                <span class="absolute bottom-1 right-1 text-white text-xs leading-none drop-shadow">▶</span>
              <?php endif; ?>
            </div>
            <div class="px-1.5 pb-1.5 min-w-0">
              <p class="text-xs text-muted-foreground truncate">#<?= (int)$m['id'] ?></p>
              <p class="text-xs truncate leading-tight"><?= $this->e($m['original_name']) ?></p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Activity log -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
      <h2 class="text-sm font-semibold">Activity Log</h2>
      <span class="text-xs text-muted-foreground"><?= count($logEntries) ?><?= count($logEntries) >= 200 ? '+ (showing latest 200)' : '' ?></span>
    </div>
    <?php if (empty($logEntries)): ?>
      <p class="px-6 py-4 text-sm text-muted-foreground">No recorded activity.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
            <th class="px-4 py-3 text-left">When</th>
            <th class="px-4 py-3 text-left">Action</th>
            <th class="px-4 py-3 text-left">Target</th>
            <th class="px-4 py-3 text-left">Details</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <?php foreach ($logEntries as $e): ?>
            <tr>
              <td class="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap"><?= $this->e(substr($e['created_at'], 0, 16)) ?></td>
              <td class="px-4 py-2"><code class="text-xs"><?= $this->e($e['action']) ?></code></td>
              <td class="px-4 py-2 font-mono text-xs">
                <?php
                  // Make media: and pool: targets into links
                  $t = $e['target'];
                  if (preg_match('/^media:(\d+)/', $t, $mm)) {
                      echo '<a href="/m/' . (int)$mm[1] . '" class="hover:underline" target="_blank">' . $this->e($t) . '</a>';
                  } elseif (preg_match('/^pool:(\d+)/', $t, $mm)) {
                      echo '<a href="/pools/' . (int)$mm[1] . '" class="hover:underline" target="_blank">' . $this->e($t) . '</a>';
                  } else {
                      echo $this->e($t);
                  }
                ?>
              </td>
              <td class="px-4 py-2 text-xs text-muted-foreground"><?= $this->e($e['details'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

</div>
