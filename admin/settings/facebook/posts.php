<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/bootstrap.php';
auth_require();
redirect('/admin/facebook/posts.php');
