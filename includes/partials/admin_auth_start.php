<?php

declare(strict_types=1);

/**
 * Shared auth page chrome (login / forgot / reset).
 *
 * @var string $authPageTitle
 * @var string $authHeading optional; unused in partial
 */

$authPageTitle = $authPageTitle ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($authPageTitle) ?> — NCST</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body admin-body--auth">
  <div class="admin-auth">
    <div class="admin-auth__brand">
      <span class="admin-auth__mark" aria-hidden="true">NC</span>
      <span class="admin-auth__brand-text">Newnan Coweta Scanner Traffic</span>
    </div>
