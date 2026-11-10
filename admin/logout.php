<?php
require_once __DIR__ . '/../includes/functions.php';
unset($_SESSION['admin_id'], $_SESSION['admin_name']);
session_regenerate_id(true);
redirect('login.php');
