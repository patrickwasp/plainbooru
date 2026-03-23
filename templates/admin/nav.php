<?php
/**
 * Admin sub-navigation.
 * @var string $adminSection  — active section: 'users' | 'settings' | 'mod-log' | 'trash'
 * @var array  $currentUser
 */
$role    = $currentUser['role'] ?? '';
$isAdmin = $role === 'admin';
$isMod   = in_array($role, ['moderator', 'admin'], true);

$links = [];
if ($isAdmin) {
    $links[] = ['href' => '/admin/users',    'label' => 'Users',     'key' => 'users'];
    $links[] = ['href' => '/admin/settings', 'label' => 'Settings',  'key' => 'settings'];
}
if ($isMod) {
    $links[] = ['href' => '/admin/queue',    'label' => 'Queue',     'key' => 'queue'];
    $links[] = ['href' => '/admin/mod-log',  'label' => 'Mod Log',   'key' => 'mod-log'];
    $links[] = ['href' => '/admin/trash',    'label' => 'Trash',     'key' => 'trash'];
}
?>
<nav class="flex gap-1 border-b border-border mb-6 -mt-2">
  <?php foreach ($links as $link): ?>
    <?php $active = ($adminSection ?? '') === $link['key']; ?>
    <a href="<?= $this->e($link['href']) ?>"
       class="px-4 py-2 text-sm font-medium border-b-2 -mb-px <?= $active
           ? 'border-primary text-foreground'
           : 'border-transparent text-muted-foreground hover:text-foreground hover:border-border' ?>">
      <?= $this->e($link['label']) ?>
    </a>
  <?php endforeach; ?>
</nav>
