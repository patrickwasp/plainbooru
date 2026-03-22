<?php
/**
 * Pagination nav partial.
 *
 * @var int    $page       Current page number.
 * @var int    $totalPages Total number of pages.
 * @var string $base       URL prefix that already includes all filter query params and ends
 *                         with either '?' or '&' so 'page=N' can be appended directly.
 *                         Examples: '/?'  '/pools?'  '/search?tags=foo&'
 * @var string $class      Optional extra wrapper class (default: 'mt-6').
 */
?>
<?php if ($totalPages > 1): ?>
  <?php if ($compact ?? false): ?>
  <nav role="navigation" aria-label="pagination" class="flex md:hidden w-full justify-center <?= $class ?? 'mt-6' ?>">
    <ul class="flex flex-row items-center gap-2">
      <li>
        <?php if ($page > 1): ?>
          <a href="<?= $base ?>page=<?= $page - 1 ?>" class="btn-icon-ghost" aria-label="Previous">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </a>
        <?php else: ?>
          <span class="btn-icon-ghost opacity-50 cursor-default" aria-disabled="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          </span>
        <?php endif; ?>
      </li>
<li>
        <?php if ($page < $totalPages): ?>
          <a href="<?= $base ?>page=<?= $page + 1 ?>" class="btn-icon-ghost" aria-label="Next">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </a>
        <?php else: ?>
          <span class="btn-icon-ghost opacity-50 cursor-default" aria-disabled="true">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
          </span>
        <?php endif; ?>
      </li>
    </ul>
  </nav>
  <nav role="navigation" aria-label="pagination" class="hidden md:flex w-full justify-center <?= $class ?? 'mt-6' ?>">
  <?php else: ?>
  <nav role="navigation" aria-label="pagination" class="mx-auto flex w-full justify-center <?= $class ?? 'mt-6' ?>">
  <?php endif; ?>
    <ul class="flex flex-row items-center gap-1">
      <li>
        <?php if ($page > 1): ?>
          <a href="<?= $base ?>page=<?= $page - 1 ?>" class="btn-ghost">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
            Previous
          </a>
        <?php else: ?>
          <span class="btn-ghost opacity-50 cursor-default">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6" /></svg>
            Previous
          </span>
        <?php endif; ?>
      </li>

      <?php
        // Show up to 5 page numbers centred around current page
        $window = 2;
        $start  = max(1, $page - $window);
        $end    = min($totalPages, $page + $window);
        // Pad window if near the edges
        if ($page - $window < 1)           $end   = min($totalPages, $end + (1 - ($page - $window)));
        if ($page + $window > $totalPages) $start = max(1, $start - (($page + $window) - $totalPages));

        if ($start > 1): ?>
          <li><a href="<?= $base ?>page=1" class="btn-icon-ghost">1</a></li>
          <?php if ($start > 2): ?>
            <li>
              <div class="size-9 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /><circle cx="5" cy="12" r="1" /></svg>
              </div>
            </li>
          <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li>
            <?php if ($i === $page): ?>
              <a href="<?= $base ?>page=<?= $i ?>" class="btn-icon-outline" aria-current="page"><?= $i ?></a>
            <?php else: ?>
              <a href="<?= $base ?>page=<?= $i ?>" class="btn-icon-ghost"><?= $i ?></a>
            <?php endif; ?>
          </li>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
          <?php if ($end < $totalPages - 1): ?>
            <li>
              <div class="size-9 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-4 shrink-0"><circle cx="12" cy="12" r="1" /><circle cx="19" cy="12" r="1" /><circle cx="5" cy="12" r="1" /></svg>
              </div>
            </li>
          <?php endif; ?>
          <li><a href="<?= $base ?>page=<?= $totalPages ?>" class="btn-icon-ghost"><?= $totalPages ?></a></li>
        <?php endif; ?>

      <li>
        <?php if ($page < $totalPages): ?>
          <a href="<?= $base ?>page=<?= $page + 1 ?>" class="btn-ghost">
            Next
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg>
          </a>
        <?php else: ?>
          <span class="btn-ghost opacity-50 cursor-default">
            Next
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6" /></svg>
          </span>
        <?php endif; ?>
      </li>
    </ul>
  </nav>
<?php endif; ?>
