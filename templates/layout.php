<?php $isDark = ($_COOKIE['theme'] ?? '') === 'dark'; ?>
<!doctype html>
<html lang="en" class="h-full<?= $isDark ? ' dark' : '' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $this->e($title ?? 'plainbooru') ?></title>
  <link rel="stylesheet" href="/assets/app.css?v=<?= filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/app.css') ?>">
</head>
<body class="<?= isset($sidebar) ? 'h-full overflow-hidden' : 'min-h-full' ?> bg-background text-foreground flex flex-col <?= $this->e($bodyClass ?? '') ?>">

  <!-- Navbar -->
  <header class="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur">
    <div class="container mx-auto flex h-14 max-w-7xl items-center gap-4 px-4">
      <a href="/" class="text-xl font-bold">plainbooru</a>
      <nav class="hidden md:flex gap-1 flex-1 items-center">
        <a href="/tags" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Tags</a>
        <a href="/pools" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Pools</a>
        <a href="/upload" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Upload</a>
        <?php if ($currentUser ?? null): ?>
          <details class="dropdown">
            <summary class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent cursor-pointer list-none"><?= $this->e($currentUser['username']) ?></summary>
            <ul>
              <li><a href="/settings">Settings</a></li>
              <li>
                <form action="/logout" method="post">
                  <button type="submit">Log out</button>
                </form>
              </li>
            </ul>
          </details>
        <?php else: ?>
          <a href="/login" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Log in</a>
        <?php endif; ?>
      </nav>
      <form action="/search" method="get" class="flex gap-1 ml-auto">
        <input type="text" name="tags" placeholder="Search tags" value="<?= $this->e(is_string($tags ?? null) ? $tags : '') ?>" class="input w-44 md:w-64 h-8 text-sm px-3">
        <button type="submit" class="btn-sm-primary">Go</button>
      </form>
      <form action="/theme" method="post">
        <input type="hidden" name="theme" value="<?= $isDark ? 'light' : 'dark' ?>">
        <input type="hidden" name="return" value="<?= $this->e($_SERVER['REQUEST_URI'] ?? '/') ?>">
        <button type="submit" class="btn-ghost h-8 w-8 flex items-center justify-center rounded-md" title="Toggle dark mode">
          <?php if ($isDark): ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>
          <?php else: ?>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
          <?php endif; ?>
        </button>
      </form>
    </div>
  </header>

  <!-- Mobile nav -->
  <div class="flex md:hidden gap-1 px-2 border-b border-border bg-background overflow-x-auto sticky top-14 z-40 h-10 items-center">
    <a href="/tags" class="text-xs px-2 py-1 rounded hover:bg-accent">Tags</a>
    <a href="/pools" class="text-xs px-2 py-1 rounded hover:bg-accent">Pools</a>
    <a href="/upload" class="text-xs px-2 py-1 rounded hover:bg-accent">Upload</a>
    <span class="flex-1"></span>
    <?php if ($currentUser ?? null): ?>
      <details class="dropdown">
        <summary class="text-xs px-2 py-1 rounded hover:bg-accent cursor-pointer list-none"><?= $this->e($currentUser['username']) ?></summary>
        <ul dir="rtl">
          <li><a href="/settings">Settings</a></li>
          <li>
            <form action="/logout" method="post">
              <button type="submit">Log out</button>
            </form>
          </li>
        </ul>
      </details>
    <?php else: ?>
      <a href="/login" class="text-xs px-2 py-1 rounded hover:bg-accent">Log in</a>
    <?php endif; ?>
  </div>

  <!-- Content -->
  <?php if (isset($sidebar)): ?>
  <main class="flex-1 min-h-0 flex overflow-hidden">
    <div class="flex-1 min-w-0 overflow-y-auto px-4 py-6">
      <?= $content ?>
    </div>
    <aside class="w-64 shrink-0 border-l border-border flex flex-col overflow-y-auto">
      <?= $sidebar ?>
    </aside>
  </main>
  <?php else: ?>
  <main class="<?= $this->e($mainClass ?? 'flex-1 container mx-auto px-4 py-6 max-w-7xl') ?>">
    <?= $content ?>
  </main>
  <?php endif; ?>


</body>
</html>
