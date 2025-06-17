<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php'; // Might need for some utility

$pdo = connect_db();
$feedback_message = '';
$message_type = ''; // 'success' or 'error'

// Define default settings keys we expect
$setting_keys = [
    'checkout_name',
    'post_purchase_message',
    'default_currency', // Will be BRL, mostly for display
    'min_order_value',
    'efi_client_id_sandbox',
    'efi_client_secret_sandbox',
    'efi_client_id_production',
    'efi_client_secret_production',
    'efi_sandbox_mode', // 'true' or 'false' string
    'efi_certificate_path_sandbox', // Path relative to project root for .p12 file
    'efi_certificate_path_production' // Path relative to project root for .p12 file
];

// Initialize settings array with defaults or empty values
$settings = array_fill_keys($setting_keys, '');
$settings['default_currency'] = 'BRL'; // Hardcoded for now
$settings['efi_sandbox_mode'] = 'true'; // Default to sandbox

// Load existing settings from database
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (array_key_exists($row['setting_key'], $settings)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    $feedback_message = "Erro ao carregar configurações: " . $e->getMessage();
    $message_type = 'error';
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted_settings = [];
    foreach ($setting_keys as $key) {
        if ($key === 'efi_sandbox_mode') {
            $submitted_settings[$key] = isset($_POST[$key]) ? 'true' : 'false';
        } elseif (isset($_POST[$key])) {
            $submitted_settings[$key] = trim($_POST[$key]);
        } else {
            // For keys not in POST (e.g. if a text field is cleared), ensure they are set to empty or a default
            $submitted_settings[$key] = ($key === 'min_order_value') ? '0.00' : '';
        }
    }

    // Validate min_order_value
    if (isset($submitted_settings['min_order_value'])) {
        if (!is_numeric($submitted_settings['min_order_value']) || $submitted_settings['min_order_value'] < 0) {
            $feedback_message = "Valor mínimo do pedido deve ser um número positivo.";
            $message_type = 'error';
            // Restore original value on error to prevent saving invalid input
            $submitted_settings['min_order_value'] = $settings['min_order_value'];
        } elseif (empty($submitted_settings['min_order_value'])) {
             $submitted_settings['min_order_value'] = '0.00'; // Default if explicitly empty
        }
    }

    // Handle certificate uploads
    $upload_dir_certs_absolute = '../config/certs/'; // Relative to this script's location for move_uploaded_file
    $upload_dir_certs_relative_to_root = 'config/certs/'; // For storing in DB

    if (!file_exists($upload_dir_certs_absolute)) {
        if (!mkdir($upload_dir_certs_absolute, 0775, true)) {
            $feedback_message = "Falha ao criar diretório de certificados: " . $upload_dir_certs_absolute;
            $message_type = 'error';
        }
    }

    if ($message_type !== 'error') { // Proceed if directory creation was fine
        foreach (['sandbox', 'production'] as $env) {
            $file_input_name = "efi_certificate_file_{$env}";
            $setting_key_path = "efi_certificate_path_{$env}";

            // Assume old path initially
            $submitted_settings[$setting_key_path] = $settings[$setting_key_path];

            if (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] == UPLOAD_ERR_OK) {
                $original_filename = $_FILES[$file_input_name]['name'];
                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

                if ($file_extension !== 'p12'){
                    $feedback_message = "Certificado para {$env} deve ser um arquivo .p12.";
                    $message_type = 'error';
                    continue; // Stop processing this file, keep old path
                }

                $unique_filename = "efi_cert_{$env}_" . uniqid() . ".p12";
                $destination_absolute = $upload_dir_certs_absolute . $unique_filename;
                $destination_relative_for_db = $upload_dir_certs_relative_to_root . $unique_filename;

                $max_cert_size = 100 * 1024; // 100KB
                if ($_FILES[$file_input_name]['size'] > $max_cert_size) {
                    $feedback_message = "Arquivo de certificado {$env} excede o tamanho máximo permitido (100KB).";
                    $message_type = 'error';
                    continue;
                }

                if (move_uploaded_file($_FILES[$file_input_name]['tmp_name'], $destination_absolute)) {
                    $submitted_settings[$setting_key_path] = $destination_relative_for_db;

                    // Delete old certificate if a new one is uploaded and old one exists and is different
                    if ($settings[$setting_key_path] &&
                        $settings[$setting_key_path] !== $submitted_settings[$setting_key_path] &&
                        file_exists('../' . $settings[$setting_key_path])) { // Old path is relative from root
                        // unlink('../' . $settings[$setting_key_path]); // Commented out for safety
                    }
                } else {
                    $feedback_message = "Falha ao mover o arquivo de certificado {$env}. Verifique permissões.";
                    $message_type = 'error';
                    // On failure, ensure we keep the old path for this environment
                    $submitted_settings[$setting_key_path] = $settings[$setting_key_path];
                }
            } elseif (isset($_FILES[$file_input_name]) && $_FILES[$file_input_name]['error'] != UPLOAD_ERR_NO_FILE) {
                // File was provided but an error occurred (not UPLOAD_ERR_OK and not UPLOAD_ERR_NO_FILE)
                $feedback_message = "Erro no upload do certificado {$env}. Código: " . $_FILES[$file_input_name]['error'];
                $message_type = 'error';
                $submitted_settings[$setting_key_path] = $settings[$setting_key_path]; // Keep old path
            }
            // If UPLOAD_ERR_NO_FILE, it means no new file was chosen, so $settings[$setting_key_path] (already set) is correct.
        }
    }


    if (empty($message_type) || $message_type === 'success') { // Proceed only if no new errors
        try {
            $pdo->beginTransaction();
            // Use REPLACE INTO or INSERT ... ON DUPLICATE KEY UPDATE for settings table
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

            foreach ($submitted_settings as $key => $value) {
                if (in_array($key, $setting_keys)) {
                    $stmt->bindParam(':key', $key);
                    $stmt->bindParam(':value', $value);
                    $stmt->execute();
                }
            }
            $pdo->commit();
            $feedback_message = "Configurações salvas com sucesso!";
            $message_type = 'success';
            // Reload settings to show updated values
            foreach ($submitted_settings as $key => $value) {
                if (array_key_exists($key, $settings)) {
                    $settings[$key] = $value;
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $feedback_message = "Erro ao salvar configurações no banco: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações Globais do Checkout - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
        h1, h2, h3 { border-bottom: 1px solid #ccc; padding-bottom:10px; color: #333; }
        h2.section-title { margin-top: 30px; margin-bottom:10px; font-size: 1.2em; border-bottom: 1px solid #eee; padding-bottom:5px;}
        h3.section-title { font-size:1.1em; margin-top:20px; border-bottom: none;}
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="password"], textarea, select {
            width: 100%; padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        input[type="checkbox"] { margin-top: 10px; margin-right: 5px; vertical-align: middle;}
        .current-file { margin-top: 5px; font-size: 0.9em; color: #555; }
        button[type="submit"] { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; }
        button[type="submit"]:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align:center; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 15px; }
        .form-group small { color: #6c757d; display: block; margin-top: 3px;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Configurações Globais do Checkout</h1>
        <a href="index.php">&laquo; Voltar ao Dashboard</a>

        <?php if ($feedback_message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>

        <form action="settings_checkout.php" method="POST" enctype="multipart/form-data">

            <h2 class="section-title">Geral</h2>
            <div class="form-group">
                <label for="checkout_name">Nome do Checkout (Loja/Site):</label>
                <input type="text" id="checkout_name" name="checkout_name" value="<?php echo htmlspecialchars($settings['checkout_name']); ?>">
            </div>
            <div class="form-group">
                <label for="post_purchase_message">Mensagem Pós-Compra:</label>
                <textarea id="post_purchase_message" name="post_purchase_message" rows="3"><?php echo htmlspecialchars($settings['post_purchase_message']); ?></textarea>
            </div>
            <div class="form-group">
                <label for="default_currency">Moeda Padrão:</label>
                <input type="text" id="default_currency" name="default_currency" value="BRL" readonly>
            </div>
            <div class="form-group">
                <label for="min_order_value">Limite Mínimo de Valor por Pedido (0 para nenhum):</label>
                <input type="number" id="min_order_value" name="min_order_value" step="0.01" min="0" value="<?php echo htmlspecialchars($settings['min_order_value']); ?>">
            </div>

            <h2 class="section-title">Configuração Efi API</h2>
            <div class="form-group">
                <label for="efi_sandbox_mode">
                    <input type="checkbox" id="efi_sandbox_mode" name="efi_sandbox_mode" value="true" <?php echo ($settings['efi_sandbox_mode'] === 'true') ? 'checked' : ''; ?>>
                    Modo Sandbox (Homologação)
                </label>
                <small>Marque para usar o ambiente de testes da Efi. Desmarque para ambiente de produção.</small>
            </div>

            <h3 class="section-title">Credenciais de Homologação (Sandbox)</h3>
            <div class="form-group">
                <label for="efi_client_id_sandbox">Client ID (Sandbox):</label>
                <input type="text" id="efi_client_id_sandbox" name="efi_client_id_sandbox" value="<?php echo htmlspecialchars($settings['efi_client_id_sandbox']); ?>">
            </div>
            <div class="form-group">
                <label for="efi_client_secret_sandbox">Client Secret (Sandbox):</label>
                <input type="password" id="efi_client_secret_sandbox" name="efi_client_secret_sandbox" value="<?php echo htmlspecialchars($settings['efi_client_secret_sandbox']); ?>">
            </div>
             <div class="form-group">
                <label for="efi_certificate_file_sandbox">Certificado .p12 (Sandbox):</label>
                <input type="file" id="efi_certificate_file_sandbox" name="efi_certificate_file_sandbox" accept=".p12">
                <?php if (!empty($settings['efi_certificate_path_sandbox'])): ?>
                    <p class="current-file">Certificado atual (Sandbox): <?php echo htmlspecialchars(basename($settings['efi_certificate_path_sandbox'])); ?> (Caminho: <?php echo htmlspecialchars($settings['efi_certificate_path_sandbox']); ?>)</p>
                <?php else: ?>
                    <p class="current-file">Nenhum certificado de Sandbox configurado.</p>
                <?php endif; ?>
                <small>Faça upload do seu arquivo de certificado .p12 para o ambiente de homologação.</small>
            </div>

            <h3 class="section-title">Credenciais de Produção</h3>
            <div class="form-group">
                <label for="efi_client_id_production">Client ID (Produção):</label>
                <input type="text" id="efi_client_id_production" name="efi_client_id_production" value="<?php echo htmlspecialchars($settings['efi_client_id_production']); ?>">
            </div>
            <div class="form-group">
                <label for="efi_client_secret_production">Client Secret (Produção):</label>
                <input type="password" id="efi_client_secret_production" name="efi_client_secret_production" value="<?php echo htmlspecialchars($settings['efi_client_secret_production']); ?>">
            </div>
            <div class="form-group">
                <label for="efi_certificate_file_production">Certificado .p12 (Produção):</label>
                <input type="file" id="efi_certificate_file_production" name="efi_certificate_file_production" accept=".p12">
                 <?php if (!empty($settings['efi_certificate_path_production'])): ?>
                    <p class="current-file">Certificado atual (Produção): <?php echo htmlspecialchars(basename($settings['efi_certificate_path_production'])); ?> (Caminho: <?php echo htmlspecialchars($settings['efi_certificate_path_production']); ?>)</p>
                <?php else: ?>
                    <p class="current-file">Nenhum certificado de Produção configurado.</p>
                <?php endif; ?>
                <small>Faça upload do seu arquivo de certificado .p12 para o ambiente de produção.</small>
            </div>

            <button type="submit">Salvar Configurações</button>
        </form>
    </div>
</body>
</html>
