<?php
// core/functions.php

function slugify($text, string $divider = '-') {
  // replace non letter or digits by divider
  $text = preg_replace('~[^\pL\d]+~u', $divider, $text);
  // transliterate
  $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
  // remove unwanted characters
  $text = preg_replace('~[^-\w]+~', '', $text);
  // trim
  $text = trim($text, $divider);
  // remove duplicate divider
  $text = preg_replace('~-+~', $divider, $text);
  // lowercase
  $text = strtolower($text);
  if (empty($text)) {
    return 'n-a-' . substr(md5(time()), 0, 6); // fallback for empty slugs
  }
  return $text;
}

/**
 * Handles file uploads.
 *
 * @param array $file_input The $_FILES['input_name'] array.
 * @param string $upload_dir The directory to upload to (e.g., 'uploads/protected/').
 * @param array $allowed_types Allowed MIME types.
 * @param int $max_size Maximum file size in bytes.
 * @return string|array Path to uploaded file on success, or an array ['error' => 'message'] on failure.
 */
function handle_upload($file_input, $upload_dir, $allowed_types = [], $max_size = 50000000) { // 50MB default
    if (!isset($file_input['error']) || is_array($file_input['error'])) {
        return ['error' => 'Parâmetros de upload inválidos.'];
    }

    switch ($file_input['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['error' => 'Nenhum arquivo enviado.']; // Not an error if file is optional
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['error' => 'Arquivo excede o tamanho máximo permitido.'];
        default:
            return ['error' => 'Erro desconhecido no upload. Código: ' . $file_input['error']];
    }

    if ($file_input['size'] > $max_size) {
        return ['error' => 'Arquivo excede o tamanho máximo permitido (' . ($max_size / 1024 / 1024) . 'MB).'];
    }

    // Check MIME type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $file_mime_type = $finfo->file($file_input['tmp_name']);
    if (!empty($allowed_types) && !in_array($file_mime_type, $allowed_types)) {
        return ['error' => 'Tipo de arquivo não permitido. Permitidos: ' . implode(', ', $allowed_types)];
    }

    // Sanitize filename and create unique name
    $file_extension = strtolower(pathinfo($file_input['name'], PATHINFO_EXTENSION));
    $safe_filename_base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file_input['name'], PATHINFO_FILENAME));
    $unique_filename = $safe_filename_base . '_' . uniqid() . '.' . $file_extension;
    $destination = rtrim($upload_dir, '/') . '/' . $unique_filename;

    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0775, true); // Create dir if not exists
    }

    if (!is_writable($upload_dir)){
        return ['error' => 'Diretório de upload não tem permissão de escrita: ' . $upload_dir];
    }

    if (!move_uploaded_file($file_input['tmp_name'], $destination)) {
        return ['error' => 'Falha ao mover o arquivo para o destino. Verifique as permissões.'];
    }

    return str_replace('../', '', $destination); // Return relative path from project root
}

?>
