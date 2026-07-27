<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

$articleAllowedCategories = category_slugs_by_template('news');
$articleKindLabel = 'News';
$articleShowValidMeta = false;

require dirname(__DIR__) . '/includes/partials/article_page.php';
