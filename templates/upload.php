<?php // upload.php ?>
<div class="max-w-lg mx-auto">

  <?php if (!empty($error)): ?>
    <?= $this->partial('alert_error', ['error' => $error, 'class' => 'mb-4']) ?>
  <?php endif; ?>

  <div class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="flex items-center gap-2 px-5 py-3 border-b border-border bg-muted/30">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground shrink-0"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
      <h1 class="text-sm font-semibold">Upload Media</h1>
      <span class="ml-auto text-xs text-muted-foreground">JPEG · PNG · GIF · WebP · MP4 · WebM</span>
    </div>
    <?= $this->partial('upload_forms', [
        'action'       => '/upload',
        'button_files' => 'Upload',
        'button_dir'   => 'Upload Directory',
    ]) ?>
  </div>

</div>
