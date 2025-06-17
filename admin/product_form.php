<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php'; // For slugify function

$pdo = connect_db();
$product = [
    'id' => '',
    'name' => '',
    'slug' => '',
    'description' => '',
    'price' => '',
    'product_type' => 'Outros',
    'file_path' => '',
    'cover_image_path' => '',
    'tags' => '',
    'status' => 1
];
$page_title = 'Adicionar Novo Produto';
$form_action = 'product_actions.php?action=add';
$error_message = $_GET['error'] ?? '';
$success_message = $_GET['success'] ?? '';


if (isset($_GET['id'])) {
    $product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($product_id) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
            $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($product_data) {
                $product = $product_data;
                $page_title = 'Editar Produto: ' . htmlspecialchars($product['name']);
                $form_action = 'product_actions.php?action=edit&id=' . $product_id;
            } else {
                header("Location: products_list.php?error=" . urlencode('Produto não encontrado.'));
                exit;
            }
        } catch (PDOException $e) {
            die("Erro ao buscar produto: " . $e->getMessage());
        }
    } else {
        header("Location: products_list.php?error=" . urlencode('ID de produto inválido.'));
        exit;
    }
}

// Product types - can be moved to config or DB later
$product_types = ['Script', 'Ebook', 'Vídeo', 'Imagem', 'PDF', 'Curso Online', 'Outros'];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <style>
        /* Basic temp styles */
        body { font-family: sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); max-width: 800px; margin: auto; }
        h1 { border-bottom: 1px solid #ccc; padding-bottom:10px; }
        label { display: block; margin-top: 15px; font-weight: bold; }
        input[type="text"], input[type="number"], input[type="file"], textarea, select {
            width: calc(100% - 22px); padding: 10px; margin-top: 5px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;
        }
        textarea { min-height: 100px; }
        button[type="submit"] { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px; }
        button[type="submit"]:hover { background-color: #0056b3; }
        .current-file, .current-image { margin-top: 5px; font-size: 0.9em; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo $page_title; ?></h1>
        <a href="products_list.php">&laquo; Voltar para Lista de Produtos</a>

        <?php if ($error_message): ?>
            <p class="message error"><?php echo htmlspecialchars(urldecode($error_message)); ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p class="message success"><?php echo htmlspecialchars(urldecode($success_message)); ?></p>
        <?php endif; ?>

        <form action="<?php echo $form_action; ?>" method="POST" enctype="multipart/form-data">
            <?php if (isset($product['id']) && !empty($product['id'])): ?>
                <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
            <?php endif; ?>
            <div>
                <label for="name">Nome do Produto:</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>

            <div>
                <label for="description">Descrição:</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div>
                <label for="price">Preço (BRL):</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>

            <div>
                <label for="product_type">Tipo de Produto:</label>
                <select id="product_type" name="product_type">
                    <?php foreach ($product_types as $type): ?>
                        <option value="<?php echo $type; ?>" <?php echo ($product['product_type'] == $type) ? 'selected' : ''; ?>>
                            <?php echo $type; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="file_path_external">Arquivo do Produto (Upload abaixo OU Link Externo aqui):</label>
                <input type="text" id="file_path_external" name="file_path_external" placeholder="https://exemplo.com/link/para/arquivo" value="<?php echo (filter_var($product['file_path'], FILTER_VALIDATE_URL)) ? htmlspecialchars($product['file_path']) : ''; ?>">
                <small>Preencha o campo acima para link externo OU use o campo abaixo para upload.</small><br>

                <label for="product_file" style="margin-top:10px;">Upload de Arquivo do Produto:</label>
                <input type="file" id="product_file" name="product_file">
                <?php if (!empty($product['file_path'])): ?>
                    <?php if (filter_var($product['file_path'], FILTER_VALIDATE_URL)): ?>
                        <p class="current-file">Link externo atual: <a href="<?php echo htmlspecialchars($product['file_path']); ?>" target="_blank"><?php echo htmlspecialchars($product['file_path']); ?></a></p>
                    <?php else: ?>
                        <p class="current-file">Arquivo atual: <?php echo htmlspecialchars(basename($product['file_path'])); ?> (Deixe o upload em branco para manter o atual)</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div>
                <label for="cover_image_file">Imagem de Capa (Opcional):</label>
                <input type="file" id="cover_image_file" name="cover_image_file">
                <?php if (!empty($product['cover_image_path'])): ?>
                    <p class="current-image">
                        Imagem atual: <img src="../<?php echo htmlspecialchars($product['cover_image_path']); ?>" alt="Capa" style="max-width: 100px; max-height: 100px; vertical-align: middle;">
                        (Deixe o upload em branco para manter a atual)
                    </p>
                <?php endif; ?>
            </div>

            <div>
                <label for="tags">Tags (separadas por vírgula):</label>
                <input type="text" id="tags" name="tags" value="<?php echo htmlspecialchars($product['tags']); ?>" placeholder="Ebook, Script, Marketing">
            </div>

            <div>
                <label for="slug">Slug (URL amigável - será gerado automaticamente do nome se deixado em branco):</label>
                <input type="text" id="slug" name="slug" value="<?php echo htmlspecialchars($product['slug']); ?>" placeholder="Ex: meu-produto-digital">
            </div>

            <div>
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="1" <?php echo ($product['status'] == 1) ? 'selected' : ''; ?>>Ativo</option>
                    <option value="0" <?php echo ($product['status'] == 0) ? 'selected' : ''; ?>>Inativo</option>
                </select>
            </div>

            <button type="submit">Salvar Produto</button>
        </form>
    </div>
</body>
</html>
