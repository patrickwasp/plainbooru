<?php
// details_accordion.php — reusable collapsible details block
// $title:   string  — header label (default: 'Details')
// $rows:    array   — [ [label, value_raw_html], ... ]
// $actions: string  — raw HTML for action buttons (optional)
// $upward:  bool    — true = content renders above summary (sidebar pinned-bottom use)
// $class:   string  — extra classes on <details> (optional)
?>
<details class="group <?= !empty($upward) ? 'flex flex-col-reverse' : '' ?> <?= $this->e($class ?? '') ?>">
  <summary class="w-full focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] transition-all outline-none rounded-md cursor-pointer">
    <h2 class="flex flex-1 items-center justify-between gap-4 py-3 text-left text-sm font-semibold hover:underline">
      <?= $this->e($title ?? 'Details') ?>
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
           class="text-muted-foreground pointer-events-none shrink-0 transition-transform duration-200 <?= !empty($upward) ? 'rotate-180 group-open:rotate-0' : 'group-open:rotate-180' ?>">
        <path d="m6 9 6 6 6-6" />
      </svg>
    </h2>
  </summary>
  <section class="pt-3 pb-4">
    <?php if (!empty($rows)): ?>
      <table class="w-full text-xs">
        <tbody>
          <?php foreach ($rows as [$label, $value]): ?>
            <tr>
              <td class="font-medium py-0.5 pr-2 text-muted-foreground"><?= $this->e($label) ?></td>
              <td class="py-0.5"><?= $value ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <?php if (!empty($actions)): ?>
      <div class="mt-3"><?= $actions ?></div>
    <?php endif; ?>
  </section>
</details>
