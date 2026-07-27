<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$articleAllowedCategories = CS_WEATHER_CATEGORIES;
$articleKindLabel = 'Weather';
$articleShowValidMeta = true;

require dirname(__DIR__) . '/includes/partials/article_page.php';
