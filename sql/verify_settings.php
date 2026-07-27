<?php

declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$cats = categories_all();
echo 'categories: ' . count($cats) . PHP_EOL;
echo 'filters: ' . implode(',', cs_filter_category_slugs()) . PHP_EOL;
echo 'FIRE template: ' . category_template('FIRE') . ' color: ' . category_color('FIRE') . PHP_EOL;
$og = settings_og_defaults();
echo 'og title: ' . $og['title'] . PHP_EOL;
$resolved = settings_resolve_og(null, site_url());
echo 'resolved type: ' . $resolved['type'] . PHP_EOL;
echo "ok\n";
