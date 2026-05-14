<?php

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect_to(role_home_path(current_user()['role'] ?? 'Administrator'));
}

redirect_to('login.php');
