<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';

$pdo = connect_db();
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$order = null;
$customer_custom_data = [];
$order_status_logs = [];
$error_message = '';

if (!$order_id) {
    $error_message = "ID do pedido inválido ou não fornecido.";
} else {
    try {
        $sql = "SELECT o.*,
                       p.name as product_name, p.slug as product_slug,
                       c.name as client_name, c.email as client_email, c.phone as client_phone, c.cpf_cnpj as client_cpf_cnpj
                FROM orders o
                LEFT JOIN products p ON o.product_id = p.id
                LEFT JOIN clients c ON o.client_id = c.id
                WHERE o.id = :order_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            $error_message = "Pedido não encontrado (#{$order_id}).";
        } else {
            // Decode customer_data JSON
            if (!empty($order['customer_data'])) {
                $decoded_data = json_decode($order['customer_data'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $customer_custom_data = $decoded_data;
                } else {
                    $customer_custom_data = ['json_error' => 'Dados do cliente (JSON) malformados ou ilegíveis. Conteúdo: ' . htmlspecialchars($order['customer_data'])];
                }
            }

            // Fetch order status logs
            $stmt_logs = $pdo->prepare("SELECT * FROM order_status_logs WHERE order_id = :order_id ORDER BY created_at ASC");
            $stmt_logs->bindParam(':order_id', $order_id, PDO::PARAM_INT);
            $stmt_logs->execute();
            $order_status_logs = $stmt_logs->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error_message = "Erro ao buscar detalhes do pedido: " . $e->getMessage();
    }
}

// Fetch defined custom field labels for display
$defined_custom_fields = [];
if ($pdo) { // Check if PDO connection was successful
    try {
        $stmt_fields = $pdo->query("SELECT field_name, field_label FROM checkout_fields WHERE status = 1 ORDER BY sort_order ASC");
        $defined_custom_fields = $stmt_fields->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        // Log this error or display a less critical message, as this is auxiliary info
        // error_log("Could not fetch custom field definitions: " . $e->getMessage());
    }
}


?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pedido #<?php echo htmlspecialchars($order_id ?? ''); ?> - <?php echo SITE_NAME; ?></title>
     <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 20px; background-color: #f8f9fa; color: #212529; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.075); max-width: 800px; margin: 30px auto; }
        h1 { border-bottom: 1px solid #dee2e6; padding-bottom:10px; color:#495057; font-size: 1.8em; }
        h2 { margin-top:0; font-size:1.3em; color:#495057; border-bottom:1px dashed #e0e0e0; padding-bottom:8px; margin-bottom:15px;}
        .details-section { margin-bottom: 25px; padding:15px; border:1px solid #e0e0e0; border-radius:5px; background-color: #fdfdfd; }
        .details-section p { margin: 8px 0; line-height: 1.6; font-size: .95rem;}
        .details-section strong { display:inline-block; min-width:160px; color: #343a40; }
        .message.error { background-color: #f8d7da; color: #721c24; padding: .75rem 1.25rem; border: 1px solid #f5c6cb; border-radius:.25rem; text-align:center; margin-bottom: 1rem;}
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        table.status-logs { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.status-logs th, table.status-logs td { border: 1px solid #dee2e6; padding: .6rem; text-align: left; font-size: .9rem;}
        table.status-logs th { background-color: #e9ecef; font-weight: 600;}
    </style>
</head>
<body>
    <div class="container">
        <h1>Detalhes do Pedido #<?php echo htmlspecialchars($order_id ?? 'Inválido'); ?></h1>
        <p><a href="orders_list.php">&laquo; Voltar para Lista de Pedidos</a></p>

        <?php if ($error_message): ?>
            <p class="message error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif ($order): ?>
            <div class="details-section">
                <h2>Informações Gerais do Pedido</h2>
                <p><strong>ID do Pedido:</strong> #<?php echo htmlspecialchars($order['id']); ?></p>
                <p><strong>Data do Pedido:</strong> <?php echo date('d/m/Y H:i:s', strtotime($order['created_at'])); ?></p>
                <p><strong>Última Atualização:</strong> <?php echo date('d/m/Y H:i:s', strtotime($order['updated_at'])); ?></p>
                <p><strong>Status:</strong> <span style="font-weight:bold; color: <?php echo ($order['order_status'] === 'Pago' || $order['order_status'] === 'Concluído') ? 'green' : (($order['order_status'] === 'Cancelado' || $order['order_status'] === 'Falha') ? 'red' : 'orange'); ?>;"><?php echo htmlspecialchars($order['order_status']); ?></span></p>
                <p><strong>Total do Pedido:</strong> R$ <?php echo number_format($order['order_total'], 2, ',', '.'); ?></p>
                <p><strong>Método de Pagamento:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? 'N/A'); ?></p>
                <p><strong>ID da Cobrança Efi:</strong> <?php echo htmlspecialchars($order['efi_charge_id'] ?? 'N/A'); ?></p>
            </div>

            <div class="details-section">
                <h2>Informações do Cliente</h2>
                <p><strong>Nome:</strong> <?php echo htmlspecialchars($order['client_name'] ?? 'N/A'); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['client_email'] ?? 'N/A'); ?></p>
                <p><strong>Telefone:</strong> <?php echo htmlspecialchars($order['client_phone'] ?? 'N/A'); ?></p>
                <p><strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($order['client_cpf_cnpj'] ?? 'N/A'); ?></p>
            </div>

            <div class="details-section">
                <h2>Informações do Produto Adquirido</h2>
                <p><strong>Nome:</strong> <?php echo htmlspecialchars($order['product_name'] ?? 'Produto não encontrado ou removido'); ?></p>
                <?php if (isset($order['product_slug'])): ?>
                <p><strong>Link do Produto:</strong> <a href="<?php echo BASE_URL . '/checkout/' . htmlspecialchars($order['product_slug']); ?>" target="_blank"><?php echo BASE_URL . '/checkout/' . htmlspecialchars($order['product_slug']); ?></a></p>
                <?php endif; ?>
            </div>

            <?php if (!empty($customer_custom_data)): ?>
            <div class="details-section">
                <h2>Dados Adicionais Fornecidos no Checkout</h2>
                <?php if(isset($customer_custom_data['json_error'])): ?>
                     <p style="color:red;"><?php echo htmlspecialchars($customer_custom_data['json_error']); ?></p>
                <?php else: ?>
                    <?php foreach ($customer_custom_data as \$key => \$value):
                        // Use the label from defined_custom_fields if available, otherwise format the key
                        \$display_label = $defined_custom_fields[\$key] ?? ucfirst(str_replace('_', ' ', \$key));
                    ?>
                        <p><strong><?php echo htmlspecialchars(\$display_label); ?>:</strong> <?php echo htmlspecialchars(is_array(\$value) ? implode(', ', \$value) : \$value); ?></p>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($order_status_logs)): ?>
            <div class="details-section">
                <h2>Histórico de Status do Pedido</h2>
                <table class="status-logs">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Status Anterior</th>
                            <th>Novo Status</th>
                            <th>Alterado Por</th>
                            <th>Detalhes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_status_logs as \$log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i:s', strtotime(\$log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars(\$log['previous_status'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(\$log['new_status']); ?></td>
                            <td><?php echo htmlspecialchars(\$log['changed_by']); ?></td>
                            <td><?php echo nl2br(htmlspecialchars(\$log['details'] ?? '')); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>


        <?php else: ?>
             <?php if (!$error_message): // Only show this if no specific error message was already set ?>
                <p>Nenhuma informação de pedido para exibir. Verifique o ID do pedido.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
