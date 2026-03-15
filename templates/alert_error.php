<?php
// alert_error.php — destructive error alert partial
// $error: string (escaped)
// $class: string (optional extra CSS classes)
?>
<div class="alert-destructive<?= !empty($class) ? ' ' . $class : '' ?>">
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
  <h2><?= $this->e($error) ?></h2>
</div>
