<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

auth_logout();
redirect('/admin/login.php');
