<?php

declare(strict_types=1);

/**
 * Open Graph + Twitter card meta tags.
 *
 * Expects optional $og resolved array from settings_resolve_og(), or builds site defaults.
 *
 * @var array{title?: string, description?: string, site_name?: string, type?: string, image_url?: ?string, url?: string}|null $og
 */

$og = $og ?? settings_resolve_og();
$ogTitle = (string) ($og['title'] ?? 'NCST Main Feed');
$ogDescription = (string) ($og['description'] ?? '');
$ogSiteName = (string) ($og['site_name'] ?? 'Newnan Coweta Scanner Traffic');
$ogType = (string) ($og['type'] ?? 'website');
$ogUrl = (string) ($og['url'] ?? site_url());
$ogImage = isset($og['image_url']) && is_string($og['image_url']) && $og['image_url'] !== ''
    ? $og['image_url']
    : null;
?>
  <meta property="og:title" content="<?= e($ogTitle) ?>">
  <meta property="og:description" content="<?= e($ogDescription) ?>">
  <meta property="og:type" content="<?= e($ogType) ?>">
  <meta property="og:url" content="<?= e($ogUrl) ?>">
  <meta property="og:site_name" content="<?= e($ogSiteName) ?>">
<?php if ($ogImage !== null): ?>
  <meta property="og:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
  <meta name="twitter:card" content="<?= $ogImage !== null ? 'summary_large_image' : 'summary' ?>">
  <meta name="twitter:title" content="<?= e($ogTitle) ?>">
  <meta name="twitter:description" content="<?= e($ogDescription) ?>">
<?php if ($ogImage !== null): ?>
  <meta name="twitter:image" content="<?= e($ogImage) ?>">
<?php endif; ?>
