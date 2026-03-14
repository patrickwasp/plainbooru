<?php // error.php ?>
<div class="flex flex-col items-center justify-center min-h-[50vh] gap-4">
  <div class="text-6xl font-bold text-muted-foreground/30"><?= (int)($code ?? 500) ?></div>
  <h1 class="text-2xl font-bold"><?= $this->e($title ?? 'Error') ?></h1>
  <p class="text-muted-foreground"><?= $this->e($message ?? 'An error occurred.') ?></p>
  <a href="/" class="btn-primary">Go Home</a>
</div>
