<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php';

$pdo = connect_db();
$feedback_message = '';
$message_type = '';

if (isset($_GET['message'])) {
    $feedback_message = urldecode($_GET['message']);
    $message_type = (isset($_GET['type']) && $_GET['type'] === 'error') ? 'error' : 'success';
}


// Handle Actions (Add, Edit, Delete) from POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $template_id = isset($_POST['template_id']) ? filter_input(INPUT_POST, 'template_id', FILTER_VALIDATE_INT) : null;

    $name = trim($_POST['name'] ?? ''); // Internal name/slug
    $subject = trim($_POST['subject'] ?? '');
    $body_html = $_POST['body_html'] ?? ''; // HTML content, don't trim aggressively here, allow leading/trailing spaces if intended by user
    $placeholders = trim($_POST['placeholders'] ?? ''); // Comma-separated

    try {
        if ($action === 'add' || $action === 'edit') {
            if (empty($name) && $action === 'add') { // Name is only editable on add
                 throw new Exception("Nome Interno do Template é obrigatório.");
            }
            if (empty($subject) || empty($body_html)) {
                 throw new Exception("Assunto e Corpo do E-mail são obrigatórios.");
            }

            if ($action === 'add' && !preg_match('/^[a-z0-9_]+$/', $name)) {
                throw new Exception("Nome Interno do Template deve conter apenas letras minúsculas, números e underscores (_). Ex: `confirmacao_pedido`.");
            }

            if ($action === 'add') {
                // Check for duplicate name on add
                $stmt_check = $pdo->prepare("SELECT id FROM email_templates WHERE name = :name");
                $stmt_check->bindParam(':name', $name);
                $stmt_check->execute();
                if ($stmt_check->fetch()) {
                    throw new Exception("O Nome Interno do Template '{$name}' já existe. Escolha um nome único.");
                }

                $sql = "INSERT INTO email_templates (name, subject, body_html, placeholders, created_at, updated_at)
                        VALUES (:name, :subject, :body_html, :placeholders, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $feedback_message = "Template de e-mail adicionado com sucesso!";
            } else if ($action === 'edit' && $template_id) {
                // Name (slug) is not editable, so we only update other fields
                $sql = "UPDATE email_templates SET subject = :subject, body_html = :body_html, placeholders = :placeholders, updated_at = NOW()
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $template_id, PDO::PARAM_INT);
                $feedback_message = "Template de e-mail atualizado com sucesso!";
            } else {
                throw new Exception("Ação inválida ou ID do template ausente.");
            }

            if ($action === 'add') { // Only bind name for add action
                $stmt->bindParam(':name', $name);
            }
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':body_html', $body_html);
            $stmt->bindParam(':placeholders', $placeholders);
            $stmt->execute();
            $message_type = 'success';

        } elseif ($action === 'delete' && $template_id) {
            $stmt = $pdo->prepare("DELETE FROM email_templates WHERE id = :id");
            $stmt->bindParam(':id', $template_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $feedback_message = "Template de e-mail excluído com sucesso!";
                $message_type = 'success';
            } else {
                $feedback_message = "Erro: Template não encontrado ou já excluído.";
                $message_type = 'error';
            }
        } else {
             throw new Exception("Ação desconhecida.");
        }
        header("Location: email_templates.php?message=" . urlencode($feedback_message) . "&type=" . $message_type);
        exit;

    } catch (PDOException $e) {
        $feedback_message = "Erro de banco de dados: " . $e->getMessage();
        $message_type = 'error';
        if (($e->getCode() == '23000' || $e->getCode() == 23000) && stripos($e->getMessage(), 'UNIQUE constraint failed: email_templates.name') !== false) {
             $feedback_message = "Erro: O Nome Interno do Template '{$name}' já existe. Escolha um nome único.";
        }
    } catch (Exception $e) {
        $feedback_message = "Erro: " . $e->getMessage();
        $message_type = 'error';
    }
}


// Fetch existing template for editing if an edit_id is present in GET
$editing_template = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt_edit = $pdo->prepare("SELECT * FROM email_templates WHERE id = :id");
        $stmt_edit->bindParam(':id', $edit_id, PDO::PARAM_INT);
        $stmt_edit->execute();
        $editing_template = $stmt_edit->fetch(PDO::FETCH_ASSOC);
        if (!$editing_template) {
            $feedback_message = "Erro: Template para edição não encontrado.";
            $message_type = 'error';
        }
    }
}

// Fetch all templates for listing
$email_templates = [];
try {
    $stmt_list = $pdo->query("SELECT id, name, subject FROM email_templates ORDER BY name ASC");
    $email_templates = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($feedback_message)) {
      $feedback_message = "Erro ao carregar templates: " . $e->getMessage();
      $message_type = 'error';
    }
}

// Default/common placeholders - can be extended
$common_placeholders = "{{nome_cliente}}, {{email_cliente}}, {{id_pedido}}, {{numero_pedido}}, {{data_pedido}}, {{total_pedido}}, {{nome_produto}}, {{link_produto}}, {{link_download}}, {{link_area_cliente}}, {{nome_site}}, {{link_site}}, {{instrucoes_pagamento}}, {{codigo_pix}}, {{link_boleto}}";

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Templates de E-mail - <?php echo defined('SITE_NAME') ? SITE_NAME : 'Admin'; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 20px; background-color: #f8f9fa; color: #212529; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.075); max-width: 900px; margin: 30px auto; }
        h1, h2 { border-bottom: 1px solid #dee2e6; padding-bottom:10px; color:#495057; }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.4em; margin-top: 30px;}
        label { display: block; margin-top: 12px; font-weight: 600; color: #495057; }
        input[type="text"], textarea {
            width: 100%; padding: .5rem .75rem; margin-top: .25rem; border: 1px solid #ced4da; border-radius: .25rem; box-sizing: border-box; font-size: .9rem;
        }
        textarea#body_html { min-height: 250px; font-family: monospace; font-size: 1em; }
        textarea#placeholders {min-height: 80px; font-size: .85em; color: #495057; background-color: #e9ecef; }
        button[type="submit"], .btn-action { padding: .6rem 1.2rem; color: white; border: none; border-radius: .25rem; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 5px; font-size: .9rem; }
        button[type="submit"] { background-color: #007bff; margin-top:15px;}
        button[type="submit"]:hover { background-color: #0056b3; }
        .btn-edit { background-color: #ffc107; color:black; }
        .btn-delete { background-color: #dc3545; }
        .btn-action:hover { opacity:0.85; }
        .message { padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; text-align:center; }
        .success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: white; }
        th, td { border: 1px solid #dee2e6; padding: .75rem; text-align: left; vertical-align: middle; font-size: .9rem;}
        th { background-color: #e9ecef; font-weight: 600; }
        .form-section { margin-bottom: 30px; padding:20px; border:1px solid #e0e0e0; border-radius:5px; background-color: #fdfdfd;}
        .form-group { margin-bottom:1rem; }
        .form-group small { color: #6c757d; display: block; margin-top: .25rem; font-size: .8em;}
        .placeholders-info { background-color: #e9ecef; padding: 10px; border-radius: 4px; font-size: 0.85em; margin-top:5px; border: 1px solid #ced4da;}
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerenciar Templates de E-mail</h1>
        <p><a href="index.php">&laquo; Voltar ao Dashboard</a> | <a href="settings_email.php">&laquo; Configurações SMTP</a></p>

        <?php if ($feedback_message && !isset($_GET['edit_id'])): // Show general messages, not when form is prefilled for edit ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>
        <?php if ($feedback_message && $editing_template && $message_type === 'error'): // Show error if trying to edit and error occurs ?>
             <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>


        <div class="form-section">
            <h2><?php echo $editing_template ? 'Editar Template: ' . htmlspecialchars($editing_template['name']) : 'Adicionar Novo Template'; ?></h2>
            <form action="email_templates.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $editing_template ? 'edit' : 'add'; ?>">
                <?php if ($editing_template): ?>
                    <input type="hidden" name="template_id" value="<?php echo $editing_template['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name">Nome Interno do Template (ex: `confirmacao_pedido`):</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editing_template['name'] ?? ''); ?>" required <?php echo $editing_template ? 'readonly' : ''; ?>>
                    <small>Apenas letras minúsculas, números e underscore (_). <strong>Não pode ser alterado após a criação.</strong> Este nome é usado no código para selecionar o template.</small>
                </div>
                <div class="form-group">
                    <label for="subject">Assunto do E-mail:</label>
                    <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($editing_template['subject'] ?? ''); ?>" required>
                    <small>Pode usar placeholders aqui também, ex: <code>Pedido #{{id_pedido}} confirmado!</code></small>
                </div>
                <div class="form-group">
                    <label for="body_html">Corpo do E-mail (HTML):</label>
                    <textarea id="body_html" name="body_html" rows="15" required><?php echo htmlspecialchars($editing_template['body_html'] ?? ''); ?></textarea>
                    <small>Use HTML para formatar seu e-mail. Insira os placeholders onde necessário.</small>
                </div>
                <div class="form-group">
                    <label for="placeholders">Placeholders Documentados para este Template (informativo):</label>
                    <textarea id="placeholders" name="placeholders" rows="3"><?php echo htmlspecialchars($editing_template['placeholders'] ?? ''); ?></textarea>
                    <small>Liste os placeholders que você usou neste template para referência futura, ex: <code>{{nome_cliente}}, {{id_pedido}}, {{link_download}}</code>. Este campo é apenas para sua organização.</small>
                </div>
                <div class="placeholders-info">
                    <strong>Referência de Placeholders Comuns:</strong><br>
                    <code><?php echo str_replace(',', ', ', htmlspecialchars($common_placeholders)); ?></code><br>
                    <small>Use-os em seu Assunto e Corpo do E-mail como <code>{{placeholder_name}}</code>. A disponibilidade exata de cada placeholder pode variar dependendo do contexto em que o e-mail é enviado (ex: e-mail de confirmação de pedido vs. e-mail de redefinição de senha).</small>
                </div>
                <button type="submit"><?php echo $editing_template ? 'Atualizar Template' : 'Adicionar Template'; ?></button>
                <?php if ($editing_template): ?>
                    <a href="email_templates.php" class="btn-action" style="background-color:#6c757d;">Cancelar Edição</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Templates de E-mail Existentes</h2>
        <?php if (empty($email_templates)): ?>
            <p>Nenhum template de e-mail cadastrado ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome Interno (Slug)</th>
                        <th>Assunto</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($email_templates as $template): ?>
                        <tr>
                            <td><code><?php echo htmlspecialchars($template['name']); ?></code></td>
                            <td><?php echo htmlspecialchars($template['subject']); ?></td>
                            <td>
                                <a href="email_templates.php?edit_id=<?php echo $template['id']; ?>" class="btn-action btn-edit">Editar</a>
                                <form action="email_templates.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir o template \'<?php echo htmlspecialchars(addslashes($template['name'])); ?>\'? Esta ação não pode ser desfeita.');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete">Excluir</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
