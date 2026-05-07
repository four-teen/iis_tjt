<?php

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to('administrator/index.php');
}

redirect_to('login.php');
