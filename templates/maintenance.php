<?php // maintenance.php ?>
<div class="flex flex-col items-center justify-center min-h-[50vh] gap-4 text-center px-4">
  <div class="text-6xl font-bold text-muted-foreground/30">503</div>
  <h1 class="text-2xl font-bold">Under Maintenance</h1>
  <p class="text-muted-foreground max-w-sm"><?= $this->e($message ?? 'Site is under maintenance. Please check back soon.') ?></p>
</div>
