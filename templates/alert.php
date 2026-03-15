<?php
// alert.php — info/empty-state alert partial
// $title: string (escaped)
// $body:  string (raw HTML — caller is responsible for escaping dynamic values)
// $class: string (optional extra CSS classes)
?>
<div class="alert<?= !empty($class) ? ' ' . $class : '' ?>">
  <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
  <h2><?= $this->e($title) ?></h2>
  <section><?= $body ?></section>
</div>
