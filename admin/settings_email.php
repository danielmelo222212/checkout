<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php'; // For any helper functions

// Ensure PHPMailer is available. It should be autoloaded if composer install has run.
// The `vendor` directory might need to be recreated in some sandbox environments.
if (file_exists('../vendor/autoload.php')) {
    require_once '../vendor/autoload.php';
} else {
    // Handle missing autoload more gracefully or ensure composer install is run prior.
    // For now, we'll let it fail if PHPMailer class is not found later,
    // as the environment should ideally persist the vendor directory.
    // Or die("Erro: PHPMailer não encontrado. Execute 'composer install'.");
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP; // Required for SMTP::DEBUG_SERVER
use PHPMailer\PHPMailer\Exception as PHPMailerException;

$pdo = connect_db();
$feedback_message = '';
$message_type = ''; // 'success' or 'error'

// Define default SMTP settings keys
$smtp_setting_keys = [
    'smtp_host',
    'smtp_user',
    'smtp_pass',
    'smtp_port',
    'smtp_secure', // 'tls', 'ssl', or 'none'
    'smtp_from_email',
    'smtp_from_name'
];

// Initialize settings array
$smtp_settings = array_fill_keys($smtp_setting_keys, '');

// Load existing SMTP settings from database
try {
    // Prepare statement for security, though keys are hardcoded here
    $placeholders = implode(',', array_fill(0, count($smtp_setting_keys), '?'));
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
    $stmt->execute($smtp_setting_keys); // Use actual keys for the IN clause values

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (array_key_exists($row['setting_key'], $smtp_settings)) {
            $smtp_settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    $feedback_message = "Erro ao carregar configurações SMTP: " . $e->getMessage();
    $message_type = 'error';
}

// Handle Save Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp_settings'])) {
    $submitted_settings = [];
    foreach ($smtp_setting_keys as $key) {
        // For password, only update if a new value is explicitly provided
        if ($key === 'smtp_pass' && empty($_POST[$key]) && !empty($smtp_settings['smtp_pass'])) {
            $submitted_settings[$key] = $smtp_settings['smtp_pass']; // Keep existing password
        } else {
            $submitted_settings[$key] = trim($_POST[$key] ?? '');
        }
    }

    // Basic validation
    if (empty($submitted_settings['smtp_host']) || empty($submitted_settings['smtp_port']) || empty($submitted_settings['smtp_from_email'])) {
        $feedback_message = "Host, Porta e E-mail do Remetente são obrigatórios.";
        $message_type = 'error';
    } elseif (!filter_var($submitted_settings['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
        $feedback_message = "E-mail do Remetente inválido.";
        $message_type = 'error';
    } elseif (!is_numeric($submitted_settings['smtp_port'])) {
        $feedback_message = "Porta SMTP deve ser um número.";
        $message_type = 'error';
    } else {
        try {
            $pdo->beginTransaction();
            $stmt_upsert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                                   ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");

            foreach ($submitted_settings as $key => $value) {
                if (in_array($key, $smtp_setting_keys)) {
                    $stmt_upsert->bindParam(':key', $key);
                    $stmt_upsert->bindParam(':value', $value);
                    $stmt_upsert->execute();
                }
            }
            $pdo->commit();
            $feedback_message = "Configurações SMTP salvas com sucesso!";
            $message_type = 'success';
            // Reload settings into the form
            $smtp_settings = $submitted_settings;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $feedback_message = "Erro ao salvar configurações SMTP: " . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Handle Test Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test_email'])) {
    $test_email_recipient = trim($_POST['test_email_recipient'] ?? '');
    if (empty($test_email_recipient) || !filter_var($test_email_recipient, FILTER_VALIDATE_EMAIL)) {
        $feedback_message = "Por favor, insira um e-mail de destinatário válido para o teste.";
        $message_type = 'error';
    } elseif (empty($smtp_settings['smtp_host']) || empty($smtp_settings['smtp_from_email'])) {
        // This check might be redundant if settings are always loaded, but good for safety
        $feedback_message = "Configure e salve as configurações SMTP antes de enviar um teste.";
        $message_type = 'error';
    } elseif (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $feedback_message = "Erro: PHPMailer não está disponível. Verifique a instalação (vendor/autoload.php).";
        $message_type = 'error';
    } else {
        $mail = new PHPMailer(true);
        try {
            //Server settings
            // $mail->SMTPDebug = SMTP::DEBUG_SERVER; // Enable verbose debug output for testing
            $mail->isSMTP();
            $mail->Host       = $smtp_settings['smtp_host'];
            $mail->SMTPAuth   = (!empty($smtp_settings['smtp_user'])); // Auth if user is set
            $mail->Username   = $smtp_settings['smtp_user'];
            $mail->Password   = $smtp_settings['smtp_pass'];

            if ($smtp_settings['smtp_secure'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($smtp_settings['smtp_secure'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                 $mail->SMTPSecure = false;
                 // For 'none', some servers require SMTPAutoTLS to be false if port is 25/587 and not using explicit TLS
                 if ($smtp_settings['smtp_port'] == 25 || $smtp_settings['smtp_port'] == 587) {
                    $mail->SMTPAutoTLS = false;
                 }
            }
            $mail->Port       = (int)$smtp_settings['smtp_port'];
            $mail->CharSet    = PHPMailer::CHARSET_UTF8;

            //Recipients
            $mail->setFrom($smtp_settings['smtp_from_email'], $smtp_settings['smtp_from_name'] ?: 'Sistema');
            $mail->addAddress($test_email_recipient);

            //Content
            $mail->isHTML(true);
            $mail->Subject = 'E-mail de Teste do Sistema - ' . (defined('SITE_NAME') ? SITE_NAME : 'Meu Site');
            $mail->Body    = "Olá,<br><br>Este é um e-mail de teste enviado a partir das configurações SMTP do seu sistema.<br>Se você recebeu esta mensagem, suas configurações SMTP parecem estar funcionando corretamente.<br><br>Detalhes da configuração usada para este envio:<br>Host: " . htmlspecialchars($smtp_settings['smtp_host']) . "<br>Porta: " . htmlspecialchars($smtp_settings['smtp_port']) . "<br>Segurança: " . htmlspecialchars($smtp_settings['smtp_secure']) . "<br>Usuário: " . htmlspecialchars($smtp_settings['smtp_user']) . "<br>Remetente: " . htmlspecialchars($smtp_settings['smtp_from_email']) . "<br><br>Obrigado!";
            $mail->AltBody = "Olá,\n\nEste é um e-mail de teste enviado a partir das configurações SMTP do seu sistema.\nSe você recebeu esta mensagem, suas configurações SMTP parecem estar funcionando corretamente.\n\nDetalhes:\nHost: " . $smtp_settings['smtp_host'] . "\nPorta: " . $smtp_settings['smtp_port'] . "\nSegurança: " . $smtp_settings['smtp_secure'] . "\nUsuário: " . $smtp_settings['smtp_user'] . "\nRemetente: " . $smtp_settings['smtp_from_email'] . "\n\nObrigado!";

            $mail->send();
            $feedback_message = 'E-mail de teste enviado com sucesso para ' . htmlspecialchars($test_email_recipient) . '!';
            $message_type = 'success';
        } catch (PHPMailerException $e) {
            $feedback_message = "Falha ao enviar e-mail de teste: " . $mail->ErrorInfo;
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
    <title>Configurações de E-mail (SMTP) - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin'; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 20px; background-color: #f8f9fa; color: #212529; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.075); max-width: 700px; margin: 30px auto; }
        h1, h2 { border-bottom: 1px solid #dee2e6; padding-bottom:10px; color:#495057; }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.4em; margin-top: 30px;}
        label { display: block; margin-top: 12px; font-weight: 600; color: #495057; }
        input[type="text"], input[type="number"], input[type="password"], input[type="email"], select {
            width: 100%; padding: .5rem .75rem; margin-top: .25rem; border: 1px solid #ced4da; border-radius: .25rem; box-sizing: border-box; font-size: .9rem;
        }
        button[type="submit"] { padding: .6rem 1.2rem; color: white; border: none; border-radius: .25rem; cursor: pointer; margin-top: 20px; font-size: .95rem; }
        .btn-save { background-color: #007bff; }
        .btn-save:hover { background-color: #0056b3; }
        .btn-test { background-color: #17a2b8; }
        .btn-test:hover { background-color: #117a8b; }
        .message { padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; text-align:center; }
        .success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .form-section { margin-bottom: 30px; padding:20px; border:1px solid #e0e0e0; border-radius:5px; background-color: #fdfdfd;}
        .form-group { margin-bottom:1rem; }
        .form-group small { color: #6c757d; display: block; margin-top: .25rem; font-size: .8em;}
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Configurações de E-mail (SMTP)</h1>
        <p><a href="index.php">&laquo; Voltar ao Dashboard</a> | <a href="email_templates.php">Gerenciar Templates de E-mail &raquo;</a></p>

        <?php if ($feedback_message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo nl2br(htmlspecialchars($feedback_message)); // Use nl2br for multi-line errors from PHPMailer, ensure $feedback_message is properly escaped before this point if it contains user input. Here, it's mostly system/PHPMailer messages. ?></p>
        <?php endif; ?>

        <div class="form-section">
            <h2>Configurar Servidor SMTP</h2>
            <form action="settings_email.php" method="POST">
                <div class="form-group">
                    <label for="smtp_host">Host SMTP:</label>
                    <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($smtp_settings['smtp_host']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="smtp_port">Porta SMTP:</label>
                    <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($smtp_settings['smtp_port']); ?>" required placeholder="Ex: 587">
                    <small>Comum: 587 (TLS), 465 (SSL), 25 (sem criptografia/TLS).</small>
                </div>
                <div class="form-group">
                    <label for="smtp_secure">Segurança SMTP:</label>
                    <select id="smtp_secure" name="smtp_secure">
                        <option value="none" <?php echo ($smtp_settings['smtp_secure'] == 'none') ? 'selected' : ''; ?>>Nenhuma / STARTTLS (Automático)</option>
                        <option value="tls" <?php echo ($smtp_settings['smtp_secure'] == 'tls') ? 'selected' : ''; ?>>TLS Explícito</option>
                        <option value="ssl" <?php echo ($smtp_settings['smtp_secure'] == 'ssl') ? 'selected' : ''; ?>>SSL Implícito</option>
                    </select>
                     <small>Para 'Nenhuma', PHPMailer tentará STARTTLS se o servidor suportar na porta 587. Se a porta for 25 e quiser TLS, use 'TLS Explícito'.</small>
                </div>
                <div class="form-group">
                    <label for="smtp_user">Usuário SMTP (geralmente o e-mail):</label>
                    <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($smtp_settings['smtp_user']); ?>" autocomplete="off">
                    <small>Deixe em branco se o servidor não requer autenticação.</small>
                </div>
                <div class="form-group">
                    <label for="smtp_pass">Senha SMTP:</label>
                    <input type="password" id="smtp_pass" name="smtp_pass" value="" autocomplete="new-password">
                    <small><?php echo !empty($smtp_settings['smtp_pass']) ? "Uma senha já está configurada. Deixe em branco para não alterar." : "Digite a senha SMTP."; ?></small>
                </div>
                <div class="form-group">
                    <label for="smtp_from_email">E-mail do Remetente (From Email):</label>
                    <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp_settings['smtp_from_email']); ?>" required placeholder="seu_email@seudominio.com">
                </div>
                 <div class="form-group">
                    <label for="smtp_from_name">Nome do Remetente (From Name):</label>
                    <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp_settings['smtp_from_name']); ?>" placeholder="<?php echo defined('SITE_NAME') ? SITE_NAME : 'Nome do Site'; ?>">
                </div>
                <button type="submit" name="save_smtp_settings" class="btn-save">Salvar Configurações SMTP</button>
            </form>
        </div>

        <div class="form-section">
            <h2>Testar Envio de E-mail</h2>
            <form action="settings_email.php" method="POST">
                <div class="form-group">
                    <label for="test_email_recipient">Enviar e-mail de teste para:</label>
                    <input type="email" id="test_email_recipient" name="test_email_recipient" required placeholder="destinatario@exemplo.com">
                </div>
                <button type="submit" name="send_test_email" class="btn-test">Enviar E-mail de Teste</button>
            </form>
        </div>
    </div>
</body>
</html>
