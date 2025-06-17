-- ---
-- Table structure for admin_users
-- ---

DROP TABLE IF EXISTS `admin_users`;

CREATE TABLE `admin_users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- ---
-- Table structure for products
-- ---

DROP TABLE IF EXISTS `products`;

CREATE TABLE `products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `product_type` VARCHAR(50) NULL COMMENT 'E.g., Script, Ebook, Video, PDF, Other',
  `file_path` VARCHAR(255) NULL COMMENT 'Path to local file in uploads/protected or external link',
  `cover_image_path` VARCHAR(255) NULL COMMENT 'Path to cover image in uploads/images/',
  `tags` VARCHAR(255) NULL COMMENT 'Comma-separated tags',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- ---
-- Table structure for clients
-- ---

DROP TABLE IF EXISTS `clients`;

CREATE TABLE `clients` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `cpf_cnpj` VARCHAR(20) NULL, -- To store CPF or CNPJ
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- ---
-- Table structure for orders
-- ---

DROP TABLE IF EXISTS `orders`;

CREATE TABLE `orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `order_total` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50) NULL COMMENT 'E.g., pix, billet',
  `efi_charge_id` VARCHAR(255) NULL COMMENT 'Charge ID from Efi API',
  `status` VARCHAR(50) NOT NULL DEFAULT 'Aguardando Pagamento' COMMENT 'E.g., Aguardando Pagamento, Pago, Cancelado, Falha',
  `customer_data` TEXT NULL COMMENT 'JSON encoded customer data from checkout form',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
);

-- ---
-- Table structure for settings
-- ---

DROP TABLE IF EXISTS `settings`;

CREATE TABLE `settings` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(255) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Default settings (can be inserted via PHP later or manually)
-- INSERT INTO `settings` (setting_key, setting_value) VALUES
-- ('checkout_name', 'My Awesome Store'),
-- ('post_purchase_message', 'Thank you for your purchase!'),
-- ('default_currency', 'BRL'),
-- ('min_order_value', '0.00'),
-- ('efi_client_id', 'YOUR_EFI_CLIENT_ID'),
-- ('efi_client_secret', 'YOUR_EFI_CLIENT_SECRET'),
-- ('efi_certificate_path', '/path/to/your/certificate.p12'),
-- ('efi_sandbox_mode', 'true'),
-- ('smtp_host', 'smtp.example.com'),
-- ('smtp_user', 'user@example.com'),
-- ('smtp_pass', 'password'),
-- ('smtp_port', '587'),
-- ('smtp_secure', 'tls');


-- ---
-- Table structure for email_templates
-- ---

DROP TABLE IF EXISTS `email_templates`;

CREATE TABLE `email_templates` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL UNIQUE COMMENT 'E.g., order_confirmation, product_access, password_reset',
  `subject` VARCHAR(255) NOT NULL,
  `body_html` TEXT NOT NULL,
  `placeholders` TEXT NULL COMMENT 'Comma-separated list of available placeholders like {{client_name}}, {{order_id}}',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- ---
-- Table structure for checkout_fields
-- ---

DROP TABLE IF EXISTS `checkout_fields`;

CREATE TABLE `checkout_fields` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `field_name` VARCHAR(100) NOT NULL COMMENT 'E.g., customer_name, customer_email',
  `field_label` VARCHAR(255) NOT NULL,
  `field_type` VARCHAR(50) NOT NULL COMMENT 'E.g., text, email, number, cpf_cnpj, phone, checkbox',
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Active, 0=Inactive',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);

-- Default essential fields (can be inserted via PHP later or manually)
-- INSERT INTO `checkout_fields` (field_name, field_label, field_type, is_required, sort_order, status) VALUES
-- ('client_name', 'Nome Completo', 'text', 1, 1, 1),
-- ('client_email', 'E-mail', 'email', 1, 2, 1),
-- ('client_phone', 'Telefone (com DDD)', 'phone', 0, 3, 1),
-- ('client_cpf', 'CPF', 'cpf_cnpj', 0, 4, 1);


-- ---
-- Table structure for order_status_logs
-- ---

DROP TABLE IF EXISTS `order_status_logs`;

CREATE TABLE `order_status_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `previous_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` VARCHAR(100) DEFAULT 'Webhook Efi' COMMENT 'Can be Admin, System, Webhook Efi',
  `details` TEXT NULL COMMENT 'Additional details, e.g., webhook payload snippet',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
);

-- ---
-- Table structure for download_tokens
-- ---

DROP TABLE IF EXISTS `download_tokens`;

CREATE TABLE `download_tokens` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `token` VARCHAR(255) NOT NULL UNIQUE,
  `expires_at` TIMESTAMP NULL,
  `max_attempts` INT DEFAULT 3,
  `attempts_made` INT DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `is_used` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
);

SHOW TABLES;
