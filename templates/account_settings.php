<?php
/** @var array $currentUser */
/** @var string|null $error */
/** @var array|null $tokens  — set when viewing /settings/tokens */
$showTokens = isset($tokens);
?>
<div class="max-w-2xl flex flex-col gap-8">

  <div class="flex items-center gap-4">
    <h1 class="text-2xl font-bold">Account Settings</h1>
    <nav class="flex gap-2 text-sm">
      <a href="/settings/account" class="<?= !$showTokens ? 'font-semibold underline' : 'text-muted-foreground hover:text-foreground' ?>">Profile &amp; Password</a>
      <a href="/settings/tokens" class="<?= $showTokens ? 'font-semibold underline' : 'text-muted-foreground hover:text-foreground' ?>">API Tokens</a>
    </nav>
  </div>

  <?php if (!empty($error)): ?>
    <?= $this->partial('alert_error', ['error' => $error]) ?>
  <?php endif; ?>

  <?php if (!$showTokens): ?>

    <!-- Bio -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Profile</h2>
      </div>
      <form action="/settings/account" method="post" class="flex flex-col gap-4 p-5">
        <?= $this->csrfInput() ?>
        <input type="hidden" name="action" value="bio">

        <div class="grid gap-1.5">
          <label class="text-sm font-medium" for="bio">Bio</label>
          <textarea id="bio" name="bio" rows="4" maxlength="500"
                    class="input text-sm max-w-sm resize-y py-2"><?= $this->e($currentUser['bio'] ?? '') ?></textarea>
          <p class="text-xs text-muted-foreground">Up to 500 characters. Shown on your public profile.</p>
        </div>

        <div>
          <button type="submit" class="btn-sm-primary">Save bio</button>
        </div>
      </form>
    </section>

    <!-- Change password -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">Change Password</h2>
      </div>
      <form action="/settings/account" method="post" class="flex flex-col gap-4 p-5">
        <?= $this->csrfInput() ?>
        <input type="hidden" name="action" value="password">

        <div class="grid gap-1.5">
          <label class="text-sm font-medium" for="current_password">Current password</label>
          <input id="current_password" type="password" name="current_password" required
                 autocomplete="current-password" class="input h-9 text-sm max-w-sm">
        </div>

        <div class="grid gap-1.5">
          <label class="text-sm font-medium" for="new_password">New password</label>
          <input id="new_password" type="password" name="new_password" required
                 autocomplete="new-password" minlength="8" class="input h-9 text-sm max-w-sm">
          <p class="text-xs text-muted-foreground">At least 8 characters.</p>
        </div>

        <div class="grid gap-1.5">
          <label class="text-sm font-medium" for="confirm_password">Confirm new password</label>
          <input id="confirm_password" type="password" name="confirm_password" required
                 autocomplete="new-password" minlength="8" class="input h-9 text-sm max-w-sm">
        </div>

        <div>
          <button type="submit" class="btn-sm-primary">Update password</button>
        </div>
      </form>
    </section>

  <?php else: ?>

    <!-- API Tokens -->
    <section class="card shadow-sm overflow-hidden p-0 gap-0">
      <div class="px-5 py-3 border-b border-border bg-muted/30">
        <h2 class="text-sm font-semibold">API Tokens</h2>
      </div>
      <div class="p-5 flex flex-col gap-5">

        <p class="text-sm text-muted-foreground">
          Tokens authenticate API requests via <code>Authorization: Bearer &lt;token&gt;</code>.
          The raw token is shown once after creation — store it somewhere safe.
        </p>

        <?php if (!empty($tokens)): ?>
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-border text-left">
                <th class="pb-2 font-medium">Label</th>
                <th class="pb-2 font-medium">Created</th>
                <th class="pb-2 font-medium">Last used</th>
                <th class="pb-2"></th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border">
              <?php foreach ($tokens as $tok): ?>
                <tr>
                  <td class="py-2"><?= $this->e($tok['label']) ?></td>
                  <td class="py-2 text-xs text-muted-foreground"><?= $this->e(substr($tok['created_at'], 0, 10)) ?></td>
                  <td class="py-2 text-xs text-muted-foreground"><?= $tok['last_used_at'] ? $this->e(substr($tok['last_used_at'], 0, 10)) : '—' ?></td>
                  <td class="py-2 text-right">
                    <form action="/settings/tokens/<?= (int)$tok['id'] ?>/revoke" method="post">
                      <?= $this->csrfInput() ?>
                      <button type="submit" class="text-xs text-muted-foreground hover:text-destructive">Revoke</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="text-sm text-muted-foreground">No tokens yet.</p>
        <?php endif; ?>

        <!-- Create token -->
        <form action="/settings/tokens" method="post" class="flex gap-2 items-end pt-2 border-t border-border">
          <?= $this->csrfInput() ?>
          <div class="grid gap-1.5 flex-1 max-w-xs">
            <label class="text-sm font-medium" for="token_label">New token label</label>
            <input id="token_label" type="text" name="label" required maxlength="80"
                   placeholder="e.g. personal script" class="input h-9 text-sm">
          </div>
          <button type="submit" class="btn-sm-primary">Create token</button>
        </form>

      </div>
    </section>

  <?php endif; ?>

</div>
