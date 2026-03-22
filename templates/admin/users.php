<?php
/** @var array $users */
/** @var array $currentUser */
$roles = ['user', 'trusted', 'moderator', 'admin'];
?>
<div class="max-w-5xl flex flex-col gap-6">

  <?= $this->partial('admin/nav', ['adminSection' => 'users', 'currentUser' => $currentUser]) ?>

  <div>
    <h1 class="text-2xl font-bold">Users</h1>
    <p class="text-sm text-muted-foreground mt-1"><?= count($users) ?> total</p>
  </div>

  <div class="card overflow-hidden p-0 gap-0 shadow-sm">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-border bg-muted/30 text-xs font-semibold uppercase tracking-wider text-muted-foreground">
          <th class="px-4 py-3 text-left w-10">#</th>
          <th class="px-4 py-3 text-left">Username</th>
          <th class="px-4 py-3 text-left">Role</th>
          <th class="px-4 py-3 text-left">Joined</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-left">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-border">
        <?php foreach ($users as $u): ?>
          <?php
            $isBanned = !empty($u['banned_at']);
            $isSelf   = (int)$u['id'] === (int)$currentUser['id'];
          ?>
          <tr class="<?= $isBanned ? 'opacity-50' : '' ?>">
            <td class="px-4 py-3 text-muted-foreground"><?= (int)$u['id'] ?></td>
            <td class="px-4 py-3 font-medium">
              <?= $this->e($u['username']) ?>
              <?php if ($isSelf): ?>
                <span class="text-xs text-muted-foreground">(you)</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <span class="badge-outline text-xs"><?= $this->e($u['role']) ?></span>
            </td>
            <td class="px-4 py-3 text-muted-foreground"><?= $this->e(substr($u['created_at'], 0, 10)) ?></td>
            <td class="px-4 py-3">
              <?php if ($isBanned): ?>
                <span class="badge text-xs bg-destructive/10 text-destructive border border-destructive/30">Banned</span>
              <?php else: ?>
                <span class="text-xs text-muted-foreground">Active</span>
              <?php endif; ?>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2 flex-wrap">

                <?php if (!$isBanned && !$isSelf): ?>
                  <!-- Change role -->
                  <form action="/admin/users/<?= (int)$u['id'] ?>/role" method="post" class="flex items-center gap-1">
                    <?= $this->csrfInput() ?>
                    <select name="role" class="input h-7 text-xs py-0 px-2 w-28">
                      <?php foreach ($roles as $r): ?>
                        <option value="<?= $this->e($r) ?>"<?= $r === $u['role'] ? ' selected' : '' ?>><?= $this->e($r) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-sm-outline text-xs h-7 px-2">Set</button>
                  </form>

                  <!-- Ban -->
                  <form action="/admin/users/<?= (int)$u['id'] ?>/ban" method="post" class="flex items-center gap-1">
                    <?= $this->csrfInput() ?>
                    <input type="text" name="reason" placeholder="Reason (optional)"
                           class="input h-7 text-xs py-0 px-2 w-36">
                    <button type="submit" class="btn-sm-destructive text-xs h-7 px-2">Ban</button>
                  </form>
                <?php endif; ?>

              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

</div>
