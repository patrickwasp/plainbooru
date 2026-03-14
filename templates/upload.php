<?php // upload.php ?>
<div class="max-w-lg mx-auto">
  <h1 class="text-2xl font-bold mb-4">Upload Media</h1>

  <?php if (!empty($error)): ?>
    <div class="alert alert-destructive mb-4">
      <span><?= $this->e($error) ?></span>
    </div>
  <?php endif; ?>

  <div class="card p-6 shadow-sm">
    <form action="/upload" method="post" enctype="multipart/form-data" class="flex flex-col gap-4">

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="file">
          File <span class="text-destructive">*</span>
          <span class="text-xs text-muted-foreground ml-1">Image or video, max 50 MB</span>
        </label>
        <input type="file" id="file" name="file" required accept="image/*,video/mp4,video/webm"
               class="input">
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="tags">
          Tags
          <span class="text-xs text-muted-foreground ml-1">Space or comma separated</span>
        </label>
        <input type="text" id="tags" name="tags" placeholder="cat cute blue_eyes"
               class="input">
      </div>

      <div class="flex flex-col gap-1">
        <label class="text-sm font-medium" for="source">Source URL</label>
        <input type="url" id="source" name="source" placeholder="https://example.com/original"
               class="input">
      </div>

      <button type="submit" class="btn-primary w-full">Upload</button>
    </form>
  </div>
</div>
