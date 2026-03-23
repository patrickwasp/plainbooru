<?php
// admin/logs.php
/** @var array       $diag         */
/** @var array       $phpLimits    */
/** @var string|null $phpErrorLog  */
/** @var array       $phpLogLines  */
?>
<div class="max-w-5xl flex flex-col gap-6">

  <?= $this->partial('admin/nav', ['adminSection' => 'logs', 'currentUser' => $currentUser]) ?>

  <div>
    <h1 class="text-2xl font-bold">Logs &amp; Health</h1>
    <p class="text-sm text-muted-foreground mt-1">Server status, diagnostic checks, and recent log output.</p>
  </div>

  <!-- Server Health -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30">
      <h2 class="text-sm font-semibold">Server Health</h2>
    </div>
    <div class="px-6 py-6 grid gap-3">

      <?php foreach ([
          ['label' => 'GD extension',    'ok' => $diag['gd'],      'warn' => 'Image thumbnails will not work.'],
          ['label' => 'GD WebP support', 'ok' => $diag['gd_webp'], 'warn' => 'Thumbnails will be saved as JPEG instead of WebP.'],
          ['label' => 'exec()',          'ok' => $diag['exec_enabled'],       'warn' => 'ffmpeg cannot run — exec() is disabled.'],
          ['label' => 'shell_exec()',    'ok' => $diag['shell_exec_enabled'], 'warn' => 'ffmpeg path detection may fail.'],
      ] as $check): ?>
        <div class="flex items-start gap-3 text-sm">
          <?php if ($check['ok']): ?>
            <span class="text-green-600 font-mono mt-0.5">✓</span>
            <span class="text-muted-foreground"><?= $this->e($check['label']) ?></span>
          <?php else: ?>
            <span class="text-destructive font-mono mt-0.5">✗</span>
            <div>
              <span class="font-medium"><?= $this->e($check['label']) ?></span>
              <span class="text-muted-foreground"> — <?= $this->e($check['warn']) ?></span>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ([
          ['key' => 'ffmpeg',  'label' => 'ffmpeg',  'path' => $diag['ffmpeg_path'],  'version' => $diag['ffmpeg_version'],  'warn' => 'Video thumbnails will be a grey placeholder.'],
          ['key' => 'ffprobe', 'label' => 'ffprobe', 'path' => $diag['ffprobe_path'], 'version' => $diag['ffprobe_version'], 'warn' => 'Video duration will not be detected.'],
      ] as $check): ?>
        <div class="flex items-start gap-3 text-sm">
          <?php if ($check['version']): ?>
            <span class="text-green-600 font-mono mt-0.5">✓</span>
            <div>
              <span class="text-muted-foreground"><?= $this->e($check['label']) ?></span>
              <span class="text-xs text-muted-foreground/60 ml-2 font-mono"><?= $this->e($check['path']) ?></span>
              <p class="text-xs text-muted-foreground/60 font-mono"><?= $this->e($check['version']) ?></p>
            </div>
          <?php elseif ($check['path']): ?>
            <span class="text-yellow-600 font-mono mt-0.5">!</span>
            <div>
              <span class="font-medium"><?= $this->e($check['label']) ?></span>
              <span class="text-muted-foreground"> — found at <code><?= $this->e($check['path']) ?></code> but failed to execute. <?= $this->e($check['warn']) ?></span>
            </div>
          <?php else: ?>
            <span class="text-destructive font-mono mt-0.5">✗</span>
            <div>
              <span class="font-medium"><?= $this->e($check['label']) ?></span>
              <span class="text-muted-foreground"> — not found. Checked <code><?= $this->e($diag['bin_path']) ?></code> and system PATH. <?= $this->e($check['warn']) ?></span>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <?php if ($diag['open_basedir']): ?>
        <div class="flex items-start gap-3 text-sm">
          <span class="text-yellow-600 font-mono mt-0.5">!</span>
          <div>
            <span class="font-medium">open_basedir</span>
            <span class="text-muted-foreground"> is set: <code><?= $this->e($diag['open_basedir']) ?></code></span>
            <p class="text-xs text-muted-foreground mt-0.5">If ffmpeg is outside this path, PHP cannot access it.</p>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </section>

  <!-- PHP limits -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30">
      <h2 class="text-sm font-semibold">PHP Limits</h2>
    </div>
    <div class="px-6 py-4 grid gap-1 text-sm font-mono">
      <?php foreach ([
          'upload_max_filesize', 'post_max_size', 'memory_limit', 'max_execution_time',
      ] as $k): ?>
        <div class="flex gap-4">
          <span class="text-muted-foreground w-52"><?= $this->e($k) ?></span>
          <span><?= $this->e($phpLimits[$k] ?? ini_get($k) ?: '?') ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ffmpeg last error -->
  <?php if (!empty($diag['last_ffmpeg_error'])): ?>
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
      <h2 class="text-sm font-semibold">Last ffmpeg Error</h2>
      <span class="text-xs text-muted-foreground font-mono"><?= $this->e(sys_get_temp_dir() . '/pb_ffmpeg_last_err.txt') ?></span>
    </div>
    <pre class="px-6 py-4 text-xs font-mono text-muted-foreground whitespace-pre-wrap break-all overflow-x-auto max-h-64"><?= $this->e($diag['last_ffmpeg_error']) ?></pre>
  </section>
  <?php endif; ?>

  <!-- PHP error log -->
  <section class="card shadow-sm overflow-hidden p-0 gap-0">
    <div class="px-6 py-4 border-b border-border bg-muted/30 flex items-center justify-between">
      <h2 class="text-sm font-semibold">PHP Error Log</h2>
      <span class="text-xs text-muted-foreground font-mono"><?= $this->e($phpErrorLog ?? 'path unknown') ?></span>
    </div>
    <?php if (empty($phpLogLines)): ?>
      <p class="px-6 py-4 text-sm text-muted-foreground">
        <?php if (!$phpErrorLog): ?>
          <code>error_log</code> is not configured in php.ini.
        <?php elseif (!file_exists($phpErrorLog)): ?>
          Log file does not exist: <code><?= $this->e($phpErrorLog) ?></code>
        <?php elseif (!is_readable($phpErrorLog)): ?>
          Log file is not readable: <code><?= $this->e($phpErrorLog) ?></code>
        <?php else: ?>
          Log file is empty.
        <?php endif; ?>
      </p>
    <?php else: ?>
      <pre class="px-6 py-4 text-xs font-mono text-muted-foreground whitespace-pre-wrap break-all overflow-x-auto max-h-96"><?= $this->e(implode("\n", $phpLogLines)) ?></pre>
    <?php endif; ?>
  </section>

</div>
