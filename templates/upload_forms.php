<?php
// upload_forms.php — two upload form options (select files + directory picker)
// $action        : string  — form POST action URL
// $button_files  : string  — submit label for the files form
// $button_dir    : string  — submit label for the directory form
$button_files = $button_files ?? 'Upload';
$button_dir   = $button_dir   ?? 'Upload Directory';
?>
<div class="divide-y divide-border">

  <!-- Option 1: Multi-select files -->
  <div class="p-5">
    <p class="text-sm font-medium mb-0.5">Select files</p>
    <p class="text-xs text-muted-foreground mb-4">Hold Ctrl / Cmd to select multiple files.</p>
    <form action="<?= $this->e($action) ?>" method="post" enctype="multipart/form-data" class="flex flex-col gap-4">
      <?= $this->csrfInput() ?>
      <div class="rounded-lg border-2 border-dashed border-border p-6 flex flex-col items-center gap-3 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
        <input
          type="file"
          name="files[]"
          multiple
          required
          accept="image/*,video/mp4,video/webm"
          class="text-sm text-foreground cursor-pointer max-w-xs
            file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0
            file:text-xs file:font-semibold file:cursor-pointer
            file:bg-primary file:text-primary-foreground
            hover:file:opacity-90">
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">
          Tags
          <span class="text-xs font-normal text-muted-foreground ml-1">optional — applied to all files in this batch</span>
        </label>
        <input type="text" name="tags" placeholder="tag1, tag2, tag3" class="input h-9 text-sm">
      </div>
      <div>
        <button type="submit" class="btn-sm-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1.5 -mt-px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          <?= $this->e($button_files) ?>
        </button>
      </div>
    </form>
  </div>

  <!-- Option 2: Directory -->
  <div class="p-5">
    <p class="text-sm font-medium mb-0.5">Upload from a directory</p>
    <p class="text-xs text-muted-foreground mb-4">Opens a folder picker. All supported files inside are uploaded. Unsupported types are skipped by the server.</p>
    <form action="<?= $this->e($action) ?>" method="post" enctype="multipart/form-data" class="flex flex-col gap-4">
      <?= $this->csrfInput() ?>
      <div class="rounded-lg border-2 border-dashed border-border p-6 flex flex-col items-center gap-3 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-muted-foreground"><path d="M20 20a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.9a2 2 0 0 1-1.69-.9L9.6 3.9A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2Z"/></svg>
        <div>
          <p class="text-sm font-medium">Choose a directory</p>
          <p class="text-xs text-muted-foreground mt-0.5">After picking, the browser shows how many files were found</p>
        </div>
        <input
          type="file"
          name="files[]"
          multiple
          webkitdirectory
          class="text-sm text-foreground cursor-pointer max-w-xs
            file:mr-2 file:py-1 file:px-3 file:rounded-md file:border-0
            file:text-xs file:font-semibold file:cursor-pointer
            file:bg-primary file:text-primary-foreground
            hover:file:opacity-90">
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">
          Tags
          <span class="text-xs font-normal text-muted-foreground ml-1">optional — applied to all files</span>
        </label>
        <input type="text" name="tags" placeholder="tag1, tag2, tag3" class="input h-9 text-sm">
      </div>
      <div>
        <button type="submit" class="btn-sm-primary">
          <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1.5 -mt-px"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
          <?= $this->e($button_dir) ?>
        </button>
      </div>
    </form>
  </div>

</div>
