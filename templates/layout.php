<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $this->e($title ?? 'plainbooru') ?></title>
  <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="min-h-full bg-background text-foreground flex flex-col">

  <!-- Navbar -->
  <header class="sticky top-0 z-50 w-full border-b border-border bg-background/95 backdrop-blur">
    <div class="container mx-auto flex h-14 max-w-7xl items-center gap-4 px-4">
      <a href="/" class="text-xl font-bold text-primary">plainbooru</a>
      <nav class="hidden md:flex gap-1 flex-1">
        <a href="/" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Home</a>
        <a href="/upload" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Upload</a>
        <a href="/search" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Search</a>
        <a href="/tags" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Tags</a>
        <a href="/pools" class="btn-ghost text-sm px-3 py-1 rounded-md hover:bg-accent">Pools</a>
      </nav>
      <form action="/search" method="get" class="flex gap-1 ml-auto">
        <input type="text" name="q" placeholder="Search…" class="input w-36 md:w-48 h-8 text-sm px-3">
        <button type="submit" class="btn-sm-primary">Go</button>
      </form>
    </div>
  </header>

  <!-- Mobile nav -->
  <div class="flex md:hidden gap-1 px-2 py-1 border-b border-border bg-background overflow-x-auto">
    <a href="/" class="text-xs px-2 py-1 rounded hover:bg-accent">Home</a>
    <a href="/upload" class="text-xs px-2 py-1 rounded hover:bg-accent">Upload</a>
    <a href="/search" class="text-xs px-2 py-1 rounded hover:bg-accent">Search</a>
    <a href="/tags" class="text-xs px-2 py-1 rounded hover:bg-accent">Tags</a>
    <a href="/pools" class="text-xs px-2 py-1 rounded hover:bg-accent">Pools</a>
  </div>

  <!-- Content -->
  <main class="flex-1 container mx-auto px-4 py-6 max-w-7xl">
    <?= $content ?>
  </main>

  <!-- Footer -->
  <footer class="border-t border-border bg-background py-4 text-center text-sm text-muted-foreground">
    plainbooru · zero JavaScript · PHP + SQLite
  </footer>

</body>
</html>
