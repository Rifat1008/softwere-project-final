<?php
require_once __DIR__ . '/includes/functions.php';

unset($_SESSION['seller_id'], $_SESSION['seller_name']);
session_regenerate_id(true);

redirect('seller_login.php');
