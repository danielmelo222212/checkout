<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php'; // For any helper functions if needed

$pdo = connect_db();
$feedback_message = '';
$message_type = '';

if (isset($_GET['message'])) {
    $feedback_message = urldecode($_GET['message']);
    // Determine message type based on a potential 'message_type' GET param or infer it
    if (isset($_GET['message_type'])) {
        $message_type = $_GET['message_type'] === 'error' ? 'error' : 'success';
    } else {
        $message_type = (stripos($feedback_message, 'erro') !== false || stripos($feedback_message, 'falha') !== false) ? 'error' : 'success';
    }
}


// --- Filtering ---
$filter_product_id = filter_input(INPUT_GET, 'filter_product_id', FILTER_VALIDATE_INT);
$filter_status = trim($_GET['filter_status'] ?? '');
$filter_date_start = trim($_GET['filter_date_start'] ?? '');
$filter_date_end = trim($_GET['filter_date_end'] ?? '');

$where_clauses = [];
$params = [];

if ($filter_product_id) {
    $where_clauses[] = "o.product_id = :product_id";
    $params[':product_id'] = $filter_product_id;
}
if (!empty($filter_status)) {
    $where_clauses[] = "o.status = :status";
    $params[':status'] = $filter_status;
}
if (!empty($filter_date_start)) {
    // Validate date format if necessary, though type="date" helps
    $date_start_obj = DateTime::createFromFormat('Y-m-d', $filter_date_start);
    if ($date_start_obj) {
        $where_clauses[] = "o.created_at >= :date_start";
        $params[':date_start'] = $date_start_obj->format('Y-m-d') . ' 00:00:00';
    } else {
        // Handle invalid date format for start date
        if (!empty($feedback_message)) $feedback_message .= "<br>";
        $feedback_message .= "Formato de Data Início inválido. Use AAAA-MM-DD.";
        $message_type = 'error';
        $filter_date_start = ''; // Clear invalid date
    }
}
if (!empty($filter_date_end)) {
    $date_end_obj = DateTime::createFromFormat('Y-m-d', $filter_date_end);
    if ($date_end_obj) {
        $where_clauses[] = "o.created_at <= :date_end";
        $params[':date_end'] = $date_end_obj->format('Y-m-d') . ' 23:59:59';
    } else {
        if (!empty($feedback_message)) $feedback_message .= "<br>";
        $feedback_message .= "Formato de Data Fim inválido. Use AAAA-MM-DD.";
        $message_type = 'error';
        $filter_date_end = ''; // Clear invalid date
    }
}


$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = "WHERE " . implode(" AND ", $where_clauses);
}

// --- Fetching Orders ---
$orders = [];
$products_for_filter = [];
try {
    $sql = "SELECT o.id as order_id, o.order_total, o.status as order_status, o.created_at as order_date,
                   p.name as product_name, p.id as product_id,
                   c.name as client_name, c.email as client_email
            FROM orders o
            LEFT JOIN products p ON o.product_id = p.id
            LEFT JOIN clients c ON o.client_id = c.id
            $sql_where
            ORDER BY o.created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch products for filter dropdown
    $stmt_products = $pdo->query("SELECT id, name FROM products ORDER BY name ASC");
    $products_for_filter = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if (empty($feedback_message)) { // Show this error only if no other error message is set
        $feedback_message = "Erro ao buscar pedidos: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Define possible order statuses (can be moved to a config or helper)
$order_statuses = [
    'Aguardando Pagamento', 'Processando', 'Pago', 'Cancelado', 'Reembolsado', 'Falha', 'Em Análise', 'Concluído'
];


// --- CSV Export ---
if (isset($_GET['export_csv'])) {
    // Re-fetch orders with current filters specifically for export, to ensure data consistency
    // This is important if the $orders array was somehow modified or if there's a lot of data
    // For simplicity here, we'll use the already fetched $orders if available.
    // In a real high-traffic app, consider a dedicated export query or background job.

    if (!empty($orders)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=pedidos_' . date('Y-m-d_H-i') . '.csv');
        $output = fopen('php://output', 'w');

        // Header row
        fputcsv($output, ['ID Pedido', 'Cliente', 'Email Cliente', 'Produto', 'Total (R$)', 'Status', 'Data']);

        foreach ($orders as $order) {
            fputcsv($output, [
                $order['order_id'],
                $order['client_name'],
                $order['client_email'],
                $order['product_name'],
                number_format($order['order_total'], 2, ',', '.'),
                $order['order_status'],
                date('d/m/Y H:i', strtotime($order['order_date']))
            ]);
        }
        fclose($output);
        exit;
    } else {
        // Avoids empty CSV if no orders match filter for export
        // Redirect back with an error message
        $query_params_for_redirect = $_GET; // Start with current GET params
        unset($query_params_for_redirect['export_csv']); // Remove export trigger
        $redirect_url = 'orders_list.php?' . http_build_query($query_params_for_redirect);
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&') . 'message=' . urlencode("Nenhum pedido encontrado para exportar com os filtros atuais.") . '&message_type=error';
        header('Location: ' . $redirect_url);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listagem de Pedidos - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; margin: 20px; background-color: #f8f9fa; color: #212529; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.075); max-width: 1200px; margin: 30px auto;}
        h1 { border-bottom: 1px solid #dee2e6; padding-bottom:10px; color:#495057; font-size: 1.8em;}
        .message { padding: .75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: .25rem; text-align:center; }
        .success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .filters { background-color: #fdfdfd; padding: 15px; border: 1px solid #e0e0e0; border-radius: 5px; margin-bottom: 20px; display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-end; }
        .filters .form-group { display: flex; flex-direction: column; min-width:180px; }
        .filters label { font-weight: 600; margin-bottom: 5px; font-size: 0.85em; color:#495057; }
        .filters input[type="date"], .filters select, .filters button, .filters a.btn-clear {
            padding: .5rem .75rem; border: 1px solid #ced4da; border-radius: .25rem; font-size: .9rem;
        }
        .filters button { background-color: #007bff; color: white; cursor: pointer; }
        .filters button:hover { background-color: #0056b3; }
        .filters a.btn-clear { background-color: #6c757d; color:white; text-decoration:none; display:inline-block; text-align:center;}
        .filters a.btn-clear:hover { background-color: #5a6268;}
        .export-btn { background-color: #28a745; color:white; padding: .5rem .75rem; text-decoration:none; border-radius:.25rem; font-size: .9rem; margin-left:auto;} /* Pushes to the right */
        .export-btn:hover { background-color: #218838; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background-color:white; }
        th, td { border: 1px solid #dee2e6; padding: .75rem; text-align: left; font-size: .9rem; vertical-align:middle;}
        th { background-color: #e9ecef; font-weight: 600;}
        .action-links a { margin-right: 10px; text-decoration: none; color:#007bff; font-size: .85rem;}
        .action-links a:hover { text-decoration:underline; }
        .no-orders { text-align: center; padding: 20px; color: #6c757d; background-color: #fdfdfd; border:1px solid #e0e0e0; border-radius:5px;}
        a { color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Listagem de Pedidos/Compras</h1>
        <p><a href="index.php">&laquo; Voltar ao Dashboard</a></p>

        <?php if ($feedback_message): ?>
            <p class="message <?php echo $message_type; ?>"><?php echo htmlspecialchars($feedback_message); ?></p>
        <?php endif; ?>

        <form method="GET" action="orders_list.php" class="filters">
            <div class="form-group">
                <label for="filter_product_id">Produto:</label>
                <select id="filter_product_id" name="filter_product_id">
                    <option value="">Todos os Produtos</option>
                    <?php foreach ($products_for_filter as $product): ?>
                        <option value="<?php echo $product['id']; ?>" <?php echo ($filter_product_id == $product['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter_status">Status do Pagamento:</label>
                <select id="filter_status" name="filter_status">
                    <option value="">Todos os Status</option>
                    <?php foreach ($order_statuses as $status_key => $status_label): // Assuming $order_statuses can be key => label
                        $current_status_val = is_array($status_label) ? $status_key : $status_label; ?>
                        <option value="<?php echo htmlspecialchars($current_status_val); ?>" <?php echo ($filter_status == $current_status_val) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="filter_date_start">Data Início:</label>
                <input type="date" id="filter_date_start" name="filter_date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>">
            </div>
            <div class="form-group">
                <label for="filter_date_end">Data Fim:</label>
                <input type="date" id="filter_date_end" name="filter_date_end" value="<?php echo htmlspecialchars($filter_date_end); ?>">
            </div>
            <button type="submit">Filtrar</button>
            <a href="orders_list.php" class="btn-clear">Limpar Filtros</a>
            <?php
                $export_query_params = $_GET;
                $export_query_params['export_csv'] = '1';
                unset($export_query_params['message']);
                unset($export_query_params['message_type']);
            ?>
            <a href="orders_list.php?<?php echo http_build_query($export_query_params); ?>" class="export-btn">Exportar Seleção para CSV</a>
        </form>

        <?php if (empty($orders)): ?>
            <p class="no-orders">Nenhum pedido encontrado com os filtros selecionados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Pedido</th>
                        <th>Cliente</th>
                        <th>Email Cliente</th>
                        <th>Produto</th>
                        <th>Total (R$)</th>
                        <th>Status</th>
                        <th>Data</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                            <td><?php echo htmlspecialchars($order['client_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['client_email'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($order['product_name'] ?? 'Produto não encontrado'); ?></td>
                            <td><?php echo number_format($order['order_total'], 2, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($order['order_status']); ?></td>
                            <td><?php echo date('d/m/Y H:i', strtotime($order['order_date'])); ?></td>
                            <td class="action-links">
                                <a href="order_details.php?id=<?php echo $order['order_id']; ?>">Ver Detalhes</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
