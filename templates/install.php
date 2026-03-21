<div class="w-full max-w-md mx-auto">

  <div class="mb-6 text-center">
    <h1 class="text-2xl font-bold">Install plainbooru</h1>
    <p class="text-sm text-muted-foreground mt-1">Create your admin account to get started.</p>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="rounded-md border border-destructive/40 bg-destructive/10 text-destructive px-4 py-3 text-sm mb-4">
      <?php foreach ($errors as $err): ?>
        <p><?= $this->e($err) ?></p>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="rounded-lg border bg-card p-8 shadow-sm">

    <form class="form grid gap-5" action="/install" method="post">
      <?= $this->csrfInput() ?>

      <div class="grid gap-2">
        <label for="site_title">Site title</label>
        <input
          id="site_title"
          type="text"
          name="site_title"
          value="<?= $this->e($values['site_title'] ?? 'plainbooru') ?>"
          maxlength="100"
          autocomplete="off"
        >
      </div>

      <hr class="border-border">

      <div class="grid gap-2">
        <label for="admin_user">Admin username <span class="text-destructive">*</span></label>
        <input
          id="admin_user"
          type="text"
          name="admin_user"
          value="<?= $this->e($values['admin_user'] ?? '') ?>"
          required
          autofocus
          autocomplete="username"
          minlength="3"
          maxlength="30"
          pattern="[a-zA-Z0-9_-]+"
        >
      </div>

      <div class="grid gap-2">
        <label for="admin_pass">Admin password <span class="text-destructive">*</span></label>
        <input
          id="admin_pass"
          type="password"
          name="admin_pass"
          required
          autocomplete="new-password"
          minlength="8"
        >
        <p class="text-xs text-muted-foreground">Minimum 8 characters.</p>
      </div>

      <button type="submit" class="btn-primary">Install</button>
    </form>

  </div>

</div>
