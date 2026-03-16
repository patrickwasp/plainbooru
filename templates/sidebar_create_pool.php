<?php
/** @var string|null $error */
?>
<div class="px-5 py-4 flex flex-col gap-3 border-b border-border">
  <h2 class="font-semibold text-sm">Create Pool</h2>
  <?php if (!empty($error)): ?>
    <?= $this->partial('alert_error', ['error' => $error, 'class' => 'py-2 text-sm']) ?>
  <?php endif; ?>
  <form action="/pools" method="post" class="flex flex-col gap-2">
    <?= $this->csrfInput() ?>
    <input type="text" name="name" required placeholder="Name" class="input h-8 text-sm">
    <input type="text" name="description" placeholder="Description (optional)" class="input h-8 text-sm">
    <select name="visibility" class="input h-8 text-sm">
      <option value="public">Public</option>
      <option value="private">Private</option>
    </select>
    <button type="submit" class="btn-sm-primary">Create</button>
  </form>
</div>
