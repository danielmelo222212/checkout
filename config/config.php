<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'your_db_name');

// Efi API Configuration (Sandbox - Homologação)
define('EFI_CLIENT_ID', 'YOUR_EFI_CLIENT_ID_HOMOLOGACAO');
define('EFI_CLIENT_SECRET', 'YOUR_EFI_CLIENT_SECRET_HOMOLOGACAO');
define('EFI_CERTIFICATE_PATH', __DIR__ . '/certs/efi_certificate.p12'); // Path to your .p12 certificate
define('EFI_SANDBOX', true); // true for Sandbox, false for Production

// PHPMailer SMTP Configuration
define('SMTP_HOST', 'your_smtp_host');
define('SMTP_USER', 'your_smtp_username');
define('SMTP_PASS', 'your_smtp_password');
define('SMTP_PORT', 587); // Or 465, 25, etc.
define('SMTP_SECURE', 'tls'); // 'tls' or 'ssl'

// Site Configuration
define('SITE_NAME', 'My Digital Store');
define('BASE_URL', 'http://localhost/your_project_folder'); // Change this to your actual base URL
define('DEFAULT_CURRENCY', 'BRL');
?>
