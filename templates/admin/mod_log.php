<?php
// admin/mod_log.php
?>
<div class="max-w-4xl flex flex-col gap-6">

  <div>
    <h1 class="text-2xl font-bold">Moderation Log</h1>
    <p class="text-sm text-muted-foreground mt-1"><?= (int)$total ?> total entries.</p>
  </div>

  <?php if (empty($entries)): ?>
    <p class="text-sm text-muted-foreground">No log entries yet.</p>
  <?php else: ?>
    <div class="card shadow-sm overflow-hidden p-0 gap-0">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-border bg-muted/30 text-left">
            <th class="px-4 py-2 font-medium">When</th>
            <th class="px-4 py-2 font-medium">Moderator</th>
            <th class="px-4 py-2 font-medium">Action</th>
            <th class="px-4 py-2 font-medium">Target</th>
            <th class="px-4 py-2 font-medium">Details</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-border">
          <?php foreach ($entries as $e): ?>
            <tr class="hover:bg-muted/20">
              <td class="px-4 py-2 text-xs text-muted-foreground whitespace-nowrap"><?= $this->e(substr($e['created_at'], 0, 16)) ?></td>
              <td class="px-4 py-2">
                <?php if ($e['mod_username']): ?>
                  <a href="/u/<?= urlencode($e['mod_username']) ?>" class="hover:underline"><?= $this->e($e['mod_username']) ?></a>
                <?php else: ?>
                  <span class="text-muted-foreground italic">deleted</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-2"><code class="text-xs"><?= $this->e($e['action']) ?></code></td>
              <td class="px-4 py-2 text-xs font-mono"><?= $this->e($e['target']) ?></td>
              <td class="px-4 py-2 text-xs text-muted-foreground"><?= $this->e($e['details'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?= $this->partial('pagination', ['page' => $page, 'totalPages' => $totalPages, 'base' => '/admin/mod-log?', 'class' => '']) ?>
  <?php endif; ?>

</div>
