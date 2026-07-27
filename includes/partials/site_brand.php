<?php

declare(strict_types=1);

/** @var string $brandTitle */
$brandTitle = $brandTitle ?? 'NCST MAIN FEED';
?>
  <div class="brand">
    <img class="brand__logo" src="/assets/img/ncst-logo.png" alt="" width="48" height="48">
    <h1 class="brand__title"><?= e($brandTitle) ?></h1>
  </div>
