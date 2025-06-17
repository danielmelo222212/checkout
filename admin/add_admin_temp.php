<?php
require_once '../config/config.php';
require_once '../core/database.php';

// --- IMPORTANT ---
// THIS SCRIPT IS FOR INITIAL SETUP ONLY.
// DELETE IT OR SECURE IT PROPERLY AFTER CREATING THE FIRST ADMIN USER.
// DO NOT LEAVE THIS ACCESSIBLE ON A PRODUCTION SERVER.
// --- IMPORTANT ---

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? ''; // Optional

    if (!empty($username) && !empty($password)) {
        $pdo = connect_db();
        if ($pdo) {
            // Check if username already exists
            $stmt_check = $pdo->prepare("SELECT id FROM admin_users WHERE username = :username");
            $stmt_check->bindParam(':username', $username);
            $stmt_check->execute();

            if ($stmt_check->fetch()) {
                $message = "Erro: Nome de usuário '$username' já existe.";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password_hash, email) VALUES (:username, :password_hash, :email)");
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password_hash', $password_hash);
                    $stmt->bindParam(':email', $email);
                    $stmt->execute();
                    $message = "Administrador '$username' criado com sucesso! <strong style='color:red;'>Delete este script (add_admin_temp.php) agora.</strong>";
                } catch (PDOException $e) {
                    $message = "Erro ao criar administrador: " . $e->getMessage();
                }
            }
        } else {
            $message = "Erro: Não foi possível conectar ao banco de dados.";
        }
    } else {
        $message = "Por favor, preencha o nome de usuário e a senha.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Criar Administrador Inicial</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .container { max-width: 500px; margin: auto; background: #f9f9f9; padding: 20px; border-radius: 5px; box-shadow: 0 0 5px rgba(0,0,0,0.1); }
        label { display: block; margin-top: 10px; }
        input[type="text"], input[type="password"], input[type="email"] { width: calc(100% - 22px); padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 3px; }
        button { padding: 10px 15px; background-color: #28a745; color: white; border: none; border-radius: 3px; cursor: pointer; margin-top: 15px; }
        button:hover { background-color: #218838; }
        .message { margin-top: 15px; padding: 10px; border-radius: 3px; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Criar Administrador Inicial</h2>
        <p style="color: red; font-weight: bold;">AVISO: Este script é para configuração inicial. Remova-o ou proteja-o adequadamente após o uso!</p>

        <?php if ($message): ?>
            <div class="message <?php echo (strpos($message, 'sucesso') !== false || strpos($message, 'Success') !== false) ? 'success' : 'error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="add_admin_temp.php" method="POST">
            <div>
                <label for="username">Usuário:</label>
                <input type="text" id="username" name="username" required>
            </div>
            <div>
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="email">Email (opcional):</label>
                <input type="email" id="email" name="email">
            </div>
            <button type="submit">Criar Administrador</button>
        </form>
        <p style="margin-top: 20px;">Após criar o usuário, acesse <a href="login.php">login.php</a> para entrar.</p>
    </div>
</body>
</html>
