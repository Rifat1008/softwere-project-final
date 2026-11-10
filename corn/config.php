<?php
// ============================================================
//  Upohar - main settings. Edit this file only.
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'upohar');

define('SITE_NAME', 'Upohar');

// Google AI Studio key for the AI gift suggester (api/ai.php).
// Never commit this file to GitHub with a real key inside.
define('GEMINI_API_KEY', 'PASTE_YOUR_NEW_KEY_HERE');
define('GEMINI_MODEL',   'gemini-2.5-flash');

// Seller plans: max products per plan (null = unlimited)
define('PLAN_LIMITS', ['free' => 5, 'silver' => 15, 'gold' => null]);

date_default_timezone_set('Asia/Dhaka');
