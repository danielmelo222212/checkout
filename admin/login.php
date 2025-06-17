<?php
session_start();
require_once '../config/config.php'; // Adjust path as needed
require_once '../core/database.php'; // We'll create this helper soon

// If already logged in, redirect to a dashboard page (e.g., admin/index.php)
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Por favor, preencha o usuário e a senha.';
    } else {
        // Connect to DB (simple procedural example, consider a class later)
        $pdo = connect_db(); // Assumes connect_db() is in database.php

        if ($pdo) {
            try {
                $stmt = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username');
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin_user && password_verify($password, $admin_user['password_hash'])) {
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user_id'] = $admin_user['id'];
                    $_SESSION['admin_username'] = $admin_user['username'];
                    header('Location: index.php'); // Redirect to admin dashboard
                    exit;
                } else {
                    $error_message = 'Usuário ou senha inválidos.';
                }
            } catch (PDOException $e) {
                $error_message = 'Erro no banco de dados: ' . $e->getMessage(); // Log this error properly in a real app
            }
        } else {
            $error_message = 'Não foi possível conectar ao banco de dados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - <?php echo SITE_NAME; ?></title>
    <!-- Basic styling - consider Tailwind CSS later as per requirements -->
    <style>
        body { font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f0f0f0; margin: 0; }
        .login-container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 300px; }
        .login-container h2 { text-align: center; margin-bottom: 20px; }
        .login-container label { display: block; margin-bottom: 5px; }
        .login-container input[type="text"],
        .login-container input[type="password"] { width: 95%; padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
        .login-container button { width: 100%; padding: 10px; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .login-container button:hover { background-color: #0056b3; }
        .error-message { color: red; margin-bottom: 10px; text-align: center; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Admin Login</h2>
        <?php if (!empty($error_message)): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="login.php" method="POST">
            <div>
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
