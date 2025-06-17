<?php
require_once 'auth_check.php';
require_once '../config/config.php';
require_once '../core/database.php';
require_once '../core/functions.php'; // For slugify and handle_upload

$pdo = connect_db();
$action = $_GET['action'] ?? '';
// Correctly get product_id from POST for edit, or GET for other actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $product_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
} elseif (isset($_GET['id'])) {
    $product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
} else {
    $product_id = null;
}


// Define upload directories (relative to project root for consistency in DB)
define('PRODUCT_FILES_PROTECTED_DIR_RELATIVE', 'uploads/protected/');
define('PRODUCT_COVERS_PUBLIC_DIR_RELATIVE', 'uploads/public/product_covers/');

// Absolute paths for file operations (relative to this script's location)
define('PRODUCT_FILES_PROTECTED_DIR_ABSOLUTE', '../uploads/protected/');
define('PRODUCT_COVERS_PUBLIC_DIR_ABSOLUTE', '../uploads/public/product_covers/');


// --- Helper function to redirect with message ---
function redirect_with_message($url, $message, $type = 'success') {
    $param_type = ($type === 'success') ? 'success' : 'error';
    header("Location: {$url}?{$param_type}=" . urlencode($message));
    exit;
}

// --- ADD or EDIT Product ---
if (($_action === 'add' || $action === 'edit') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $product_type = $_POST['product_type'] ?? 'Outros';
    $file_path_external = filter_input(INPUT_POST, 'file_path_external', FILTER_SANITIZE_URL);
    $tags = trim($_POST['tags'] ?? '');
    $status = filter_input(INPUT_POST, 'status', FILTER_VALIDATE_INT);
    $slug_input = trim($_POST['slug'] ?? '');

    // Validate required fields
    if (empty($name) || $price === null || $price === false || $price < 0 || ($status !== 0 && $status !== 1)) {
        $error_url = 'product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : "");
        redirect_with_message($error_url, 'Preencha todos os campos obrigatórios corretamente (Nome, Preço, Status).', 'error');
    }

    $slug = !empty($slug_input) ? slugify($slug_input) : slugify($name);

    // File handling
    $current_file_path_db = ''; // Path stored in DB
    $current_cover_image_path_db = ''; // Path stored in DB

    if ($action === 'edit' && $product_id) {
        $stmt_current = $pdo->prepare("SELECT file_path, cover_image_path FROM products WHERE id = :id");
        $stmt_current->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt_current->execute();
        $existing_product = $stmt_current->fetch(PDO::FETCH_ASSOC);
        if ($existing_product) {
            $current_file_path_db = $existing_product['file_path'];
            $current_cover_image_path_db = $existing_product['cover_image_path'];
        } else {
             redirect_with_message('products_list.php', 'Produto não encontrado para edição.', 'error');
        }
    }

    $final_file_path_for_db = $current_file_path_db;
    $final_cover_image_path_for_db = $current_cover_image_path_db;

    // Handle Product File Upload or External Link
    if (!empty($file_path_external)) {
        if (!filter_var($file_path_external, FILTER_VALIDATE_URL)) {
            redirect_with_message('product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : ""), 'Link externo do produto inválido.', 'error');
        }
        $final_file_path_for_db = $file_path_external;
        // If there was an uploaded file before and now it's an external link, consider deleting the old file
        if ($action === 'edit' && $current_file_path_db && !filter_var($current_file_path_db, FILTER_VALIDATE_URL) && file_exists(PRODUCT_FILES_PROTECTED_DIR_ABSOLUTE . basename($current_file_path_db))) {
            // @unlink(PRODUCT_FILES_PROTECTED_DIR_ABSOLUTE . basename($current_file_path_db)); // Suppress error if file not found
        }
    } elseif (isset($_FILES['product_file']) && $_FILES['product_file']['error'] == UPLOAD_ERR_OK) {
        $allowed_product_types = ['application/zip', 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'video/mp4', 'text/plain', 'application/octet-stream', 'application/x-rar-compressed', 'application/epub+zip'];
        $upload_result = handle_upload($_FILES['product_file'], PRODUCT_FILES_PROTECTED_DIR_ABSOLUTE, $allowed_product_types, 200 * 1024 * 1024); // Max 200MB

        if (is_array($upload_result) && isset($upload_result['error'])) {
            redirect_with_message('product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : ""), 'Erro no upload do arquivo do produto: ' . $upload_result['error'], 'error');
        }
        // handle_upload returns path relative to project root, but $upload_dir was absolute.
        // We need to make sure it's relative from project root for DB.
        $final_file_path_for_db = PRODUCT_FILES_PROTECTED_DIR_RELATIVE . basename($upload_result);

        if ($action === 'edit' && $current_file_path_db && $current_file_path_db != $final_file_path_for_db && !filter_var($current_file_path_db, FILTER_VALIDATE_URL) && file_exists(PRODUCT_FILES_PROTECTED_DIR_ABSOLUTE . basename($current_file_path_db))) {
            // @unlink(PRODUCT_FILES_PROTECTED_DIR_ABSOLUTE . basename($current_file_path_db));
        }
    }


    // Handle Cover Image Upload
    if (isset($_FILES['cover_image_file']) && $_FILES['cover_image_file']['error'] == UPLOAD_ERR_OK) {
        $allowed_image_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $upload_cover_result = handle_upload($_FILES['cover_image_file'], PRODUCT_COVERS_PUBLIC_DIR_ABSOLUTE, $allowed_image_types, 5 * 1024 * 1024); // Max 5MB

        if (is_array($upload_cover_result) && isset($upload_cover_result['error'])) {
             redirect_with_message('product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : ""), 'Erro no upload da imagem de capa: ' . $upload_cover_result['error'], 'error');
        }
        $final_cover_image_path_for_db = PRODUCT_COVERS_PUBLIC_DIR_RELATIVE . basename($upload_cover_result);

        if ($action === 'edit' && $current_cover_image_path_db && $current_cover_image_path_db != $final_cover_image_path_for_db && file_exists(PRODUCT_COVERS_PUBLIC_DIR_ABSOLUTE . basename($current_cover_image_path_db))) {
            // @unlink(PRODUCT_COVERS_PUBLIC_DIR_ABSOLUTE . basename($current_cover_image_path_db));
        }
    }

    // Check if slug is unique (if new or changed)
    $check_slug_sql = "SELECT id FROM products WHERE slug = :slug" . ($action === 'edit' && $product_id ? " AND id != :id" : "");
    $stmt_slug = $pdo->prepare($check_slug_sql);
    $stmt_slug->bindParam(':slug', $slug);
    if ($action === 'edit' && $product_id) {
        $stmt_slug->bindParam(':id', $product_id, PDO::PARAM_INT);
    }
    $stmt_slug->execute();
    if ($stmt_slug->fetch()) {
        $original_slug_for_error = $slug;
        $slug .= '-' . substr(uniqid(), -3); // Append unique part if slug exists
         redirect_with_message(
            'product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : ""),
            "O slug '$original_slug_for_error' (gerado de '$slug_input' ou '$name') já existe. Foi ajustado para '$slug'. Verifique ou altere manualmente.",
            'error'
        );
    }

    try {
        if ($action === 'add') {
            $sql = "INSERT INTO products (name, slug, description, price, product_type, file_path, cover_image_path, tags, status, created_at, updated_at)
                    VALUES (:name, :slug, :description, :price, :product_type, :file_path, :cover_image_path, :tags, :status, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
        } elseif ($action === 'edit' && $product_id) {
            $sql = "UPDATE products SET name = :name, slug = :slug, description = :description, price = :price, product_type = :product_type,
                    file_path = :file_path, cover_image_path = :cover_image_path, tags = :tags, status = :status, updated_at = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        } else {
            throw new Exception("Ação inválida ou ID do produto ausente para ". $action);
        }

        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':slug', $slug);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':product_type', $product_type);
        $stmt->bindParam(':file_path', $final_file_path_for_db);
        $stmt->bindParam(':cover_image_path', $final_cover_image_path_for_db);
        $stmt->bindParam(':tags', $tags);
        $stmt->bindParam(':status', $status, PDO::PARAM_INT);
        $stmt->execute();

        $message = ($action === 'add') ? 'Produto adicionado com sucesso!' : 'Produto atualizado com sucesso!';
        redirect_with_message('products_list.php', $message, 'success');

    } catch (PDOException $e) {
        redirect_with_message('product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : ""), 'Erro no banco de dados: ' . $e->getMessage(), 'error');
    } catch (Exception $e) {
        redirect_with_message('product_form.php' . ($action === 'edit' && $product_id ? "?id={$product_id}" : ""), 'Erro: ' . $e->getMessage(), 'error');
    }
}

// --- DELETE Product ---
elseif ($action === 'delete' && $product_id) {
    try {
        $stmt_get = $pdo->prepare("SELECT file_path, cover_image_path FROM products WHERE id = :id");
        $stmt_get->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt_get->execute();
        $product_files = $stmt_get->fetch(PDO::FETCH_ASSOC);

        // Before deleting from DB, attempt to delete files (paths are relative to project root)
        if ($product_files) {
            if (!empty($product_files['file_path']) && !filter_var($product_files['file_path'], FILTER_VALIDATE_URL) && file_exists('../' . $product_files['file_path'])) {
                // @unlink('../' . $product_files['file_path']); // Be careful with this path
            }
            if (!empty($product_files['cover_image_path']) && file_exists('../' . $product_files['cover_image_path'])) {
                 // @unlink('../' . $product_files['cover_image_path']);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            redirect_with_message('products_list.php', 'Produto excluído com sucesso!', 'success');
        } else {
            redirect_with_message('products_list.php', 'Produto não encontrado ou já excluído.', 'error');
        }
    } catch (PDOException $e) {
        if ($e->getCode() == '23000') { // Integrity constraint violation
             redirect_with_message('products_list.php', 'Erro: Este produto não pode ser excluído pois está associado a pedidos existentes ou outros dados.', 'error');
        } else {
             redirect_with_message('products_list.php', 'Erro ao excluir produto: ' . $e->getMessage(), 'error');
        }
    }
}

// --- TOGGLE Product Status ---
elseif ($action === 'toggle_status' && $product_id) {
    try {
        // Check current status to provide a more descriptive message, though not strictly necessary for toggle
        $stmt_current_status = $pdo->prepare("SELECT status FROM products WHERE id = :id");
        $stmt_current_status->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt_current_status->execute();
        $current_status_result = $stmt_current_status->fetchColumn();

        if ($current_status_result === false) {
             redirect_with_message('products_list.php', 'Produto não encontrado.', 'error');
        }

        $new_status = 1 - $current_status_result; // Toggle logic
        $stmt = $pdo->prepare("UPDATE products SET status = :new_status, updated_at = NOW() WHERE id = :id");
        $stmt->bindParam(':new_status', $new_status, PDO::PARAM_INT);
        $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
        $stmt->execute();

        $status_message = ($new_status == 1) ? 'ativado' : 'desativado';
        redirect_with_message('products_list.php', "Status do produto alterado para {$status_message} com sucesso!", 'success');

    } catch (PDOException $e) {
        redirect_with_message('products_list.php', 'Erro ao alterar status do produto: ' . $e->getMessage(), 'error');
    }
}
else {
    redirect_with_message('products_list.php', 'Ação inválida ou parâmetros ausentes.', 'error');
}

?>
