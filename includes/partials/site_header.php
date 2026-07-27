<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$mainFeedActive = $path === '/' || $path === '/index.php';
?>
  <header class="site-header">
    <nav class="site-nav" aria-label="Primary">
      <a class="nav-link<?= $mainFeedActive ? ' is-active' : '' ?>" href="/">MAIN FEED</a>
      <a class="nav-link nav-link--placeholder" href="#other-feeds" title="Coming soon">OTHER FEEDS <span aria-hidden="true">▾</span></a>
      <a class="nav-link nav-link--placeholder" href="#maps" title="Coming soon">MAPS</a>
    </nav>
  </header>
