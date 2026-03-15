<div class="w-full max-w-sm mx-auto">

  <?php if (isset($error) && $error): ?>
    <div class="mb-4 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
      <?= $this->e($error) ?>
    </div>
  <?php endif; ?>

  <div class="rounded-lg border bg-card p-8 shadow-sm">
    <div class="mb-6">
      <h1 class="text-lg font-semibold">Create account</h1>
    </div>

    <form class="form grid gap-5" action="/signup" method="post">

      <div class="grid gap-2">
        <label for="username">Username</label>
        <input
          id="username"
          type="text"
          name="username"
          required
          autofocus
          autocomplete="username"
          minlength="3"
          maxlength="30"
          pattern="[a-zA-Z0-9_-]+"
          title="Letters, numbers, underscores, and hyphens only"
          value="<?= $this->e($username ?? '') ?>"
        >
        <p class="text-muted-foreground text-sm">3–30 characters. Letters, numbers, _ and - only.</p>
      </div>

      <div class="grid gap-2">
        <label for="password">Password</label>
        <input
          id="password"
          type="password"
          name="password"
          required
          autocomplete="new-password"
          minlength="8"
        >
        <p class="text-muted-foreground text-sm">At least 8 characters.</p>
      </div>

      <div class="grid gap-2">
        <label for="confirm">Confirm password</label>
        <input
          id="confirm"
          type="password"
          name="confirm"
          required
          autocomplete="new-password"
          minlength="8"
        >
      </div>

      <button type="submit" class="btn-primary">Create account</button>
    </form>

    <p class="mt-5 text-sm text-muted-foreground">
      Already have an account? <a href="/login" class="underline hover:text-foreground">Log in</a>
    </p>
  </div>

</div>
