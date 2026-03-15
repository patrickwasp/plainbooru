<div class="w-full max-w-sm mx-auto">

  <?php if (isset($error) && $error): ?>
    <div class="mb-4 rounded-md border border-destructive/40 bg-destructive/10 px-4 py-3 text-sm text-destructive">
      <?= $this->e($error) ?>
    </div>
  <?php endif; ?>

  <div class="rounded-lg border bg-card p-8 shadow-sm">
    <div class="mb-6">
      <h1 class="text-lg font-semibold">Log in</h1>
    </div>

    <form class="form grid gap-5" action="/login" method="post">
      <input type="hidden" name="next" value="<?= $this->e($next ?? '/') ?>">

      <div class="grid gap-2">
        <label for="username">Username</label>
        <input
          id="username"
          type="text"
          name="username"
          required
          autofocus
          autocomplete="username"
        >
      </div>

      <div class="grid gap-2">
        <label for="password">Password</label>
        <input
          id="password"
          type="password"
          name="password"
          required
          autocomplete="current-password"
        >
      </div>

      <button type="submit" class="btn-primary">Log in</button>
    </form>

    <p class="mt-5 text-sm text-muted-foreground">
      No account? <a href="/signup" class="underline hover:text-foreground">Sign up</a>
    </p>
  </div>

</div>
