<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';

$pdo = connect_db();
$message = $_GET['message'] ?? ''; // For success/error messages from actions
$error_message = $_GET['error'] ?? ''; // For error messages
$success_message = $_GET['success'] ?? ''; // For success messages


try {
    $stmt = $pdo->query("SELECT id, name, price, product_type, status, slug FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle error, e.g., log it and show a user-friendly message
    die("Erro ao buscar produtos: " . $e->getMessage());
}

function getStatusText($status) {
    return $status == 1 ? '<span style="color:green;">Ativo</span>' : '<span style="color:red;">Inativo</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Produtos - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/admin_style.css"> <!-- We'll create this later -->
    <style>
        /* Basic temp styles - replace with admin_style.css */
        body { font-family: sans-serif; margin: 20px; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 1px solid #ccc; padding-bottom:10px; }
        .action-links a { margin-right: 10px; text-decoration: none; }
        .btn-add { display: inline-block; padding: 10px 15px; background-color: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-bottom:20px; }
        .btn-add:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Gerenciar Produtos</h1>
        <a href="index.php">&laquo; Voltar ao Dashboard</a> |
        <a href="product_form.php" class="btn-add">Adicionar Novo Produto</a>

        <?php if ($message): /* Generic message for backward compatibility or simple cases */ ?>
            <p class="message <?php echo (strpos($message, 'sucesso') !== false || strpos($message, 'Sucesso') !== false || strpos($message, 'success') !== false) ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars(urldecode($message)); ?>
            </p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="message success"><?php echo htmlspecialchars(urldecode($success_message)); ?></p>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <p class="message error"><?php echo htmlspecialchars(urldecode($error_message)); ?></p>
        <?php endif; ?>


        <?php if (empty($products)): ?>
            <p>Nenhum produto cadastrado ainda.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Link Checkout</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td>R$ <?php echo number_format($product['price'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($product['product_type']); ?></td>
                            <td><?php echo getStatusText($product['status']); ?></td>
                            <td><?php echo BASE_URL . '/checkout/' . htmlspecialchars($product['slug']); ?></td>
                            <td class="action-links">
                                <a href="product_form.php?id=<?php echo $product['id']; ?>">Editar</a>
                                <a href="product_actions.php?action=delete&id=<?php echo $product['id']; ?>" onclick="return confirm('Tem certeza que deseja excluir este produto? Esta ação não pode ser desfeita.');">Excluir</a>
                                <a href="product_actions.php?action=toggle_status&id=<?php echo $product['id']; ?>">
                                    <?php echo $product['status'] == 1 ? 'Desativar' : 'Ativar'; ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
