<?php
require_once __DIR__ . '/includes/functions.php';

unset($_SESSION['user_id'], $_SESSION['full_name'], $_SESSION['cart']);
session_regenerate_id(true);

redirect('login.php');
