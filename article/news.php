<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$articleAllowedCategories = CS_NEWS_CATEGORIES;
$articleKindLabel = 'News';
$articleShowValidMeta = false;

require dirname(__DIR__) . '/includes/partials/article_page.php';
