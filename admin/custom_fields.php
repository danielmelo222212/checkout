<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php';

$pdo = connect_db();
$feedback_message = '';
$message_type = '';

// Use a more reliable way to determine message type from GET if redirected
if (isset($_GET['message'])) {
    $feedback_message = urldecode($_GET['message']);
    if (isset($_GET['type'])) {
        $message_type = $_GET['type'] === 'success' ? 'success' : 'error';
    } else {
        // Basic inference if type is not explicitly set
        $message_type = (stripos($feedback_message, 'erro') !== false || stripos($feedback_message, 'falha') !== false) ? 'error' : 'success';
    }
}


// Handle Actions (Add, Edit, Delete, Toggle Status) from POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $field_id = isset($_POST['field_id']) ? filter_input(INPUT_POST, 'field_id', FILTER_VALIDATE_INT) : null;

    // Sanitize and retrieve form data
    $field_name = isset($_POST['field_name']) ? trim($_POST['field_name']) : '';
    $field_label = trim($_POST['field_label'] ?? '');
    $field_type = $_POST['field_type'] ?? '';
    $is_required = isset($_POST['is_required']) ? 1 : 0;
    $sort_order = filter_input(INPUT_POST, 'sort_order', FILTER_VALIDATE_INT);
    if ($sort_order === false || $sort_order === null) $sort_order = 0; // Default to 0 if not provided or invalid
    $status = isset($_POST['status']) ? 1 : 0;


    try {
        if ($action === 'add' || $action === 'edit') {
            if (empty($field_name) && $action === 'add') { // field_name is only editable on add
                 throw new Exception("Nome do Campo (interno) é obrigatório.");
            }
            if (empty($field_label) || empty($field_type)) {
                 throw new Exception("Label do Campo e Tipo são obrigatórios.");
            }

            if ($action === 'add' && !preg_match('/^[a-z0-9_]+$/', $field_name)) {
                throw new Exception("Nome do Campo (interno) deve conter apenas letras minúsculas, números e underscores (_).");
            }

            if ($action === 'add') {
                // Check for duplicate field_name on add
                $stmt_check = $pdo->prepare("SELECT id FROM checkout_fields WHERE field_name = :field_name");
                $stmt_check->bindParam(':field_name', $field_name);
                $stmt_check->execute();
                if ($stmt_check->fetch()) {
                    throw new Exception("O Nome do Campo (interno) '{$field_name}' já existe. Escolha um nome único.");
                }

                $sql = "INSERT INTO checkout_fields (field_name, field_label, field_type, is_required, sort_order, status, created_at, updated_at)
                        VALUES (:field_name, :field_label, :field_type, :is_required, :sort_order, :status, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $feedback_message = "Campo personalizado adicionado com sucesso!";
            } else if ($action === 'edit' && $field_id) {
                // field_name is not editable, so we don't update it
                $sql = "UPDATE checkout_fields SET field_label = :field_label, field_type = :field_type,
                        is_required = :is_required, sort_order = :sort_order, status = :status, updated_at = NOW()
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':id', $field_id, PDO::PARAM_INT);
                $feedback_message = "Campo personalizado atualizado com sucesso!";
            } else {
                throw new Exception("Ação inválida ou ID do campo ausente.");
            }

            // Bind common parameters
            if ($action === 'add') { // Only bind field_name for add action
                $stmt->bindParam(':field_name', $field_name);
            }
            $stmt->bindParam(':field_label', $field_label);
            $stmt->bindParam(':field_type', $field_type);
            $stmt->bindParam(':is_required', $is_required, PDO::PARAM_INT);
            $stmt->bindParam(':sort_order', $sort_order, PDO::PARAM_INT);
            $stmt->bindParam(':status', $status, PDO::PARAM_INT);
            $stmt->execute();
            $message_type = 'success';

        } elseif ($action === 'delete' && $field_id) {
            $stmt = $pdo->prepare("DELETE FROM checkout_fields WHERE id = :id");
            $stmt->bindParam(':id', $field_id, PDO::PARAM_INT);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                $feedback_message = "Campo personalizado excluído com sucesso!";
                $message_type = 'success';
            } else {
                $feedback_message = "Erro: Campo não encontrado ou já excluído.";
                $message_type = 'error';
            }
        }
        header("Location: custom_fields.php?message=" . urlencode($feedback_message) . "&type=" . $message_type);
        exit;

    } catch (PDOException $e) {
        $feedback_message = "Erro de banco de dados: " . $e->getMessage();
        $message_type = 'error';
        // Specific check for duplicate field_name if DB throws unique constraint error (e.g., SQLite or stricter MySQL setup)
        if (($e->getCode() == '23000' || $e->getCode() == 23000) && stripos($e->getMessage(), 'UNIQUE constraint failed: checkout_fields.field_name') !== false) {
             $feedback_message = "Erro: O Nome do Campo (interno) '{$field_name}' já existe. Escolha um nome único.";
        }
    } catch (Exception $e) {
        $feedback_message = "Erro: " . $e->getMessage();
        $message_type = 'error';
    }
}


// Fetch existing field for editing if an edit_id is present in GET
$editing_field = null;
if (isset($_GET['edit_id'])) {
    $edit_id = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $pdo->prepare("SELECT * FROM checkout_fields WHERE id = :id");
        $stmt->bindParam(':id', $edit_id, PDO::PARAM_INT);
        $stmt->execute();
        $editing_field = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$editing_field) { // If field not found, redirect or show specific message
            $feedback_message = "Erro: Campo para edição não encontrado.";
            $message_type = 'error';
        }
    }
}

// Fetch all fields for listing
try {
    $stmt_list = $pdo->query("SELECT * FROM checkout_fields ORDER BY sort_order ASC, field_label ASC");
    $custom_fields = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    if (empty($feedback_message)) { // Show this error only if no other error message is set
        $feedback_message = "Erro ao carregar campos: " . $e->getMessage();
        $message_type = 'error';
    }
    $custom_fields = [];
}

$field_types = ['text' => 'Texto Simples', 'email' => 'Email', 'number' => 'Número', 'cpf_cnpj' => 'CPF/CNPJ', 'phone' => 'Telefone (com DDD)', 'checkbox' => 'Caixa de Seleção (Sim/Não)', 'textarea' => 'Área de Texto (múltiplas linhas)'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor de Campos Personalizados - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 20px; background-color: #f8f9fa; color: #212529; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.075); max-width: 900px; margin: 30px auto; }
        h1, h2 { border-bottom: 1px solid #dee2e6; padding-bottom:10px; color:#495057; }
        h1 { font-size: 1.8em; }
        h2 { font-size: 1.4em; margin-top: 30px;}
        label { display: block; margin-top: 12px; font-weight: 600; color: #495057; }
        input[type="text"], input[type="number"], select, textarea {
            width: 100%; padding: .5rem .75rem; margin-top: .25rem; border: 1px solid #ced4da; border-radius: .25rem; box-sizing: border-box; font-size: .9rem;
        }
        textarea { min-height: 80px; }
        input[type="checkbox"] { margin-right: 5px; vertical-align: middle; width: auto; }
        button[type="submit"], .btn-action { padding: .5rem 1rem; color: white; border: none; border-radius: .25rem; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 5px; font-size: .9rem; }
        button[type="submit"] { background-color: #007bff; margin-top:15px;}
        button[type="submit"]:hover { background-color: #0056b3; }
        .btn-edit { background-color: #ffc107; color:black; }
        .btn-delete { background-color: #dc3545; }
        .message { padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; text-align:center; }
        .success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color: white; }
        th, td { border: 1px solid #dee2e6; padding: .75rem; text-align: left; vertical-align: middle; font-size: .9rem;}
        th { background-color: #e9ecef; font-weight: 600; }
        .form-section { margin-bottom: 30px; padding:20px; border:1px solid #e0e0e0; border-radius:5px; background-color: #fdfdfd;}
        .form-group { margin-bottom:1rem; }
        .form-group small { color: #6c757d; display: block; margin-top: .25rem; font-size: .8em;}
        .actions-form-inline button { padding: .25rem .5rem; font-size: .8rem;}
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Editor de Campos Personalizados do Checkout</h1>
        <p><a href="index.php">&laquo; Voltar ao Dashboard</a></p>

        <?php if ($feedback_message && !isset($_GET['edit_id'])): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>
        <?php if ($feedback_message && $editing_field && $message_type === 'error'): // Show error if trying to edit and error occurs ?>
             <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>


        <div class="form-section">
            <h2><?php echo $editing_field ? 'Editar Campo: ' . htmlspecialchars($editing_field['field_label']) : 'Adicionar Novo Campo'; ?></h2>
            <form action="custom_fields.php" method="POST">
                <input type="hidden" name="action" value="<?php echo $editing_field ? 'edit' : 'add'; ?>">
                <?php if ($editing_field): ?>
                    <input type="hidden" name="field_id" value="<?php echo $editing_field['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="field_name">Nome do Campo (interno/identificador, ex: `cliente_telefone`):</label>
                    <input type="text" id="field_name" name="field_name" value="<?php echo htmlspecialchars($editing_field['field_name'] ?? ''); ?>" required <?php echo $editing_field ? 'readonly' : ''; ?>>
                    <small>Apenas letras minúsculas, números e underscore (_). Ex: `observacoes`, `data_nascimento`. <strong>Não pode ser alterado após a criação.</strong></small>
                </div>
                <div class="form-group">
                    <label for="field_label">Label do Campo (exibido ao usuário):</label>
                    <input type="text" id="field_label" name="field_label" value="<?php echo htmlspecialchars($editing_field['field_label'] ?? ''); ?>" required>
                    <small>Ex: "Observações Adicionais", "Data de Nascimento".</small>
                </div>
                <div class="form-group">
                    <label for="field_type">Tipo de Campo:</label>
                    <select id="field_type" name="field_type" required>
                        <?php foreach ($field_types as $type_key => $type_name): ?>
                            <option value="<?php echo $type_key; ?>" <?php echo (isset($editing_field['field_type']) && $editing_field['field_type'] == $type_key) ? 'selected' : ''; ?>>
                                <?php echo $type_name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="is_required">
                        <input type="checkbox" id="is_required" name="is_required" value="1" <?php echo (isset($editing_field['is_required']) && $editing_field['is_required'] == 1) ? 'checked' : ''; ?>>
                        Campo Obrigatório?
                    </label>
                </div>
                 <div class="form-group">
                    <label for="status">
                        <input type="checkbox" id="status" name="status" value="1" <?php echo (!isset($editing_field) || (isset($editing_field['status']) && $editing_field['status'] == 1)) ? 'checked' : ''; // Default to checked for new fields ?>>
                        Ativo?
                    </label>
                    <small>Campos inativos não serão exibidos no formulário de checkout.</small>
                </div>
                <div class="form-group">
                    <label for="sort_order">Ordem de Exibição (0, 1, 2...):</label>
                    <input type="number" id="sort_order" name="sort_order" value="<?php echo htmlspecialchars($editing_field['sort_order'] ?? '0'); ?>" min="0">
                    <small>Campos com menor número aparecem primeiro. Campos com mesma ordem serão ordenados pelo Label.</small>
                </div>
                <button type="submit"><?php echo $editing_field ? 'Atualizar Campo' : 'Adicionar Campo'; ?></button>
                <?php if ($editing_field): ?>
                    <a href="custom_fields.php" class="btn-action" style="background-color:#6c757d;">Cancelar Edição</a>
                <?php endif; ?>
            </form>
        </div>

        <h2>Campos Personalizados Existentes</h2>
        <?php if (empty($custom_fields)): ?>
            <p>Nenhum campo personalizado cadastrado ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Nome Interno</th>
                        <th>Tipo</th>
                        <th>Obrig.</th>
                        <th>Ordem</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($custom_fields as $field): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($field['field_label']); ?></td>
                            <td><code><?php echo htmlspecialchars($field['field_name']); ?></code></td>
                            <td><?php echo htmlspecialchars($field_types[$field['field_type']] ?? $field['field_type']); ?></td>
                            <td><?php echo $field['is_required'] ? 'Sim' : 'Não'; ?></td>
                            <td><?php echo htmlspecialchars($field['sort_order']); ?></td>
                            <td><?php echo $field['status'] ? '<span style="color:green;">Ativo</span>' : '<span style="color:red;">Inativo</span>'; ?></td>
                            <td>
                                <a href="custom_fields.php?edit_id=<?php echo $field['id']; ?>" class="btn-action btn-edit">Editar</a>
                                <form action="custom_fields.php" method="POST" style="display:inline;" onsubmit="return confirm('Tem certeza que deseja excluir o campo \'<?php echo htmlspecialchars(addslashes($field['field_label'])); ?>\'? <?php echo htmlspecialchars(addslashes("Atenção: Se este campo já foi usado em pedidos, os dados associados a ele no JSON de pedidos não serão removidos, mas o campo não será mais processado ou exibido.")); ?>');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="field_id" value="<?php echo $field['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete actions-form-inline">Excluir</button>
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
