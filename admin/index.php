<?php
require_once 'auth_check.php'; // Secure this page
require_once '../config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <style>
        body { font-family: sans-serif; margin: 20px; }
        .header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 10px; border-bottom: 1px solid #ccc; }
        .header a { text-decoration: none; padding: 8px 15px; background-color: #007bff; color: white; border-radius: 4px; }
        .header a:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Admin Dashboard</h1>
        <a href="logout.php">Logout</a>
    </div>
    <p>Bem-vindo, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>!</p>
    <p>Esta é a área administrativa.</p>

    <h2>Gerenciamento</h2>
    <ul>
        <li><a href="products_list.php">Gerenciar Produtos</a></li>
        <li><a href="orders_list.php">Ver Pedidos</a></li>
        <li><a href="settings_checkout.php">Configurações do Checkout</a></li>
        <li><a href="custom_fields.php">Campos Personalizados</a></li>
        <li><a href="settings_email.php">Configurações de E-mail</a></li>
        <!-- Adicionar mais links conforme as funcionalidades são desenvolvidas -->
    </ul>

</body>
</html>
