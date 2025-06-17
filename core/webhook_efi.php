<?php
// core/webhook_efi.php
// Endpoint to receive payment status notifications from Efi

// It's crucial to include Composer's autoloader if the Efi SDK relies on it.
// Assuming it's in the vendor directory at the project root.
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    error_log("Efi Webhook Error: vendor/autoload.php not found. Composer dependencies missing.");
    http_response_code(500);
    echo "Erro interno: Dependências ausentes.";
    exit;
}

require_once __DIR__ . '/../config/config.php'; // Defines DB_HOST etc., SITE_NAME
require_once __DIR__ . '/database.php'; // For connect_db()
require_once __DIR__ . '/functions.php';   // For any helper functions

// --- Efi SDK Integration Placeholder ---
// Load Efi SDK and necessary classes
// use Efi\EfiPay; // Example, adjust based on actual SDK structure
// use Efi\Exception\EfiException; // Example for SDK exceptions

// --- Retrieve Efi API Credentials from Database ---
function get_efi_credentials($pdo) {
    $credentials = [];
    $db_settings_raw = [];

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'efi_%'");
        $db_settings_raw = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        error_log("Efi Webhook DB Error: Failed to fetch Efi settings: " . $e->getMessage());
        return null; // Critical error, cannot proceed
    }

    $is_sandbox = (isset($db_settings_raw['efi_sandbox_mode']) && $db_settings_raw['efi_sandbox_mode'] === 'true');

    $credentials['sandbox'] = $is_sandbox;
    $id_key = $is_sandbox ? 'efi_client_id_sandbox' : 'efi_client_id_production';
    $secret_key = $is_sandbox ? 'efi_client_secret_sandbox' : 'efi_client_secret_production';
    $cert_path_key = $is_sandbox ? 'efi_certificate_path_sandbox' : 'efi_certificate_path_production';

    $credentials['client_id'] = $db_settings_raw[$id_key] ?? '';
    $credentials['client_secret'] = $db_settings_raw[$secret_key] ?? '';

    // Certificate path is stored relative to project root (e.g., config/certs/file.p12)
    // The SDK might need an absolute path.
    $relative_cert_path = $db_settings_raw[$cert_path_key] ?? '';
    if (!empty($relative_cert_path)) {
        $absolute_cert_path = __DIR__ . '/../' . $relative_cert_path;
        if (file_exists($absolute_cert_path)) {
            $credentials['certificate'] = $absolute_cert_path;
        } else {
            error_log("Efi Webhook Config Error: Certificate file not found at specified path: " . $absolute_cert_path . " (configured as: " . $relative_cert_path . ")");
            $credentials['certificate'] = null;
        }
    } else {
        $credentials['certificate'] = null;
    }

    if (empty($credentials['client_id']) || empty($credentials['client_secret'])) {
        error_log("Efi Webhook Config Error: Efi Client ID or Client Secret is not configured for the current mode (" . ($is_sandbox ? "Sandbox" : "Production") . ").");
        return null; // Missing essential credentials
    }
    // Certificate might be optional for some SDK operations but required for others (like Pix with mTLS)
    if (empty($credentials['certificate'])) {
         error_log("Efi Webhook Config Warning: Efi certificate is not configured or not found for the current mode. This may be required for Pix or other operations.");
    }
    return $credentials;
}


// --- Main Webhook Logic ---
$raw_notification_data = file_get_contents('php://input');
$notification_headers = getallheaders();

// Log the raw request for debugging (conditional logging is better for production)
// Consider logging to a specific file or using a more advanced logging library.
if (defined('EFI_WEBHOOK_DEBUG') && EFI_WEBHOOK_DEBUG === true) {
    error_log("Efi Webhook Received Raw Data: " . $raw_notification_data);
    error_log("Efi Webhook Received Headers: " . json_encode($notification_headers));
}

if (empty($raw_notification_data)) {
    http_response_code(400); // Bad Request
    echo "Nenhuma notificação recebida.";
    error_log("Efi Webhook Error: Empty notification data received.");
    exit;
}

$pdo = connect_db();
if (!$pdo) {
    http_response_code(500); // Internal Server Error
    echo "Erro crítico: Falha na conexão com o banco de dados.";
    error_log("Efi Webhook Error: Database connection failed.");
    exit;
}

$efi_credentials = get_efi_credentials($pdo);
if (!$efi_credentials) {
    http_response_code(500);
    echo "Erro crítico: Configuração da API Efi ausente ou incompleta no sistema.";
    // Error already logged by get_efi_credentials
    exit;
}

// --- Notification Validation (CRITICAL STEP - REPLACE WITH EFI SDK) ---
// ** THIS SECTION REQUIRES ACTUAL EFI SDK IMPLEMENTATION FOR SECURITY **
// The following is a conceptual placeholder.
/*
try {
    $options = [
        'client_id' => $efi_credentials['client_id'],
        'client_secret' => $efi_credentials['client_secret'],
        'sandbox' => $efi_credentials['sandbox'], // true or false
        'certificate' => $efi_credentials['certificate'], // Absolute path to .p12 file
    ];
    // $efiPay = new Efi\EfiPay($options); // Or however the SDK is initialized

    // $notification = $efiPay->notification()->getNotification(['token' => $_POST['notification']]); // Example for POST with token
    // Or, if Efi sends JSON directly and uses signature validation:
    // $isValid = $efiPay->webhookHandler()->validateSignature($raw_notification_data, $notification_headers['X-Efi-Signature'] ?? ''); // Conceptual
    // if (!$isValid) {
    //     http_response_code(403); // Forbidden
    //     error_log("Efi Webhook Error: Invalid notification signature.");
    //     echo "Notificação inválida.";
    //     exit;
    // }
    // $payload = json_decode($raw_notification_data, true); // If validated, decode the raw data
    // if (json_last_error() !== JSON_ERROR_NONE) { throw new Exception("Invalid JSON payload after validation."); }

} catch (EfiException $e) { // Catch SDK specific exceptions
    http_response_code(400); // Or 500 depending on the error
    error_log("Efi Webhook SDK Error: " . $e->getMessage() . " (Code: " . $e->getCode() . ")");
    echo "Erro ao validar notificação com SDK Efi.";
    exit;
} catch (Exception $e) {
    http_response_code(500);
    error_log("Efi Webhook Processing Error: " . $e->getMessage());
    echo "Erro interno ao processar notificação.";
    exit;
}
*/
// --- END EFI SDK VALIDATION PLACEHOLDER ---


// --- MOCK PAYLOAD (REMOVE THIS SECTION AFTER INTEGRATING EFI SDK VALIDATION) ---
// This is a MOCK payload for development purposes.
// Replace this with the actual payload obtained from the validated Efi notification.
$payload = json_decode($raw_notification_data, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    error_log("Efi Webhook Error: Invalid JSON received (mock payload section): " . $raw_notification_data);
    echo "JSON inválido.";
    exit;
}
// --- END MOCK PAYLOAD ---


$efi_charge_id = null;
$new_status_from_efi = null;
$event_details_for_log = json_encode($payload);

// Adapt this logic based on the ACTUAL Efi webhook payload structure
if (isset($payload['identificadorPagamento'])) { // Example from some Efi docs (older?)
    $efi_charge_id = $payload['identificadorPagamento'];
    $new_status_from_efi = $payload['tipoPagamento'] ?? ($payload['status']['atual'] ?? null); // Guessing structure
}
// Example for a PIX specific update (often an array of pix notifications):
elseif (isset($payload['pix'][0]['txid']) && isset($payload['pix'][0]['status'])) {
    $efi_charge_id = $payload['pix'][0]['txid'];
    $new_status_from_efi = $payload['pix'][0]['status'];
}
// Example for charge status update:
elseif (isset($payload['notificationType']) && $payload['notificationType'] === 'chargeStatus' && isset($payload['chargeId']) && isset($payload['newStatus'])) {
    $efi_charge_id = $payload['chargeId'];
    $new_status_from_efi = $payload['newStatus'];
}
// Add more conditions if Efi has different payload structures for different events/payment types.


if (empty($efi_charge_id) || $new_status_from_efi === null) {
    http_response_code(400);
    echo "Dados da notificação incompletos ou não reconhecidos.";
    error_log("Efi Webhook Error: Incomplete/unrecognized payload. Charge ID or Status missing. Payload: " . $event_details_for_log);
    exit;
}

// Map Efi status to your system's status (CRUCIAL - CHECK EFI DOCS)
$status_mapping = [
    // Efi Pix Statuses (from current Efi docs for `consultarStatusPix`)
    'ATIVA' => 'Aguardando Pagamento',
    'CONCLUIDA' => 'Pago',
    'REMOVIDA_PELO_PSP' => 'Cancelado',
    'REMOVIDA_PELO_USUARIO_RECEBEDOR' => 'Cancelado',
    'EM_PROCESSAMENTO' => 'Processando', // If Efi sends this for Pix

    // Efi Boleto/Charge Statuses (These are examples, verify with Efi documentation)
    'NOVA' => 'Aguardando Pagamento',        // Boleto novo
    'AGUARDANDO' => 'Aguardando Pagamento', // Boleto aguardando
    'PAGO' => 'Pago',                       // Boleto pago
    'NAO_PAGO' => 'Aguardando Pagamento',     // Boleto não pago (pode virar Cancelado/Expirado)
    'CANCELADO' => 'Cancelado',             // Boleto/Cobrança cancelada
    'EXPIRADO' => 'Cancelado',              // Boleto expirado (treat as Cancelado)
    'DEVOLVIDO' => 'Reembolsado',           // Para Pix, se houver devolução
    // Generic status that might come from other charge types
    'NEW' => 'Aguardando Pagamento',
    'WAITING' => 'Aguardando Pagamento',
    'PAID' => 'Pago',
    'UNPAID' => 'Aguardando Pagamento',
    'CANCELED' => 'Cancelado',
    'EXPIRED' => 'Cancelado',
    'REFUNDED' => 'Reembolsado',
    // Add all relevant Efi statuses and their corresponding local status
];

$standardized_efi_status = strtoupper($new_status_from_efi); // Standardize for mapping
$local_order_status = $status_mapping[$standardized_efi_status] ?? null;

if (empty($local_order_status)) {
    http_response_code(200);
    echo "Status Efi '{$new_status_from_efi}' não mapeado ou não requer ação no sistema.";
    error_log("Efi Webhook Info: Efi status '{$new_status_from_efi}' (Charge ID '{$efi_charge_id}') not mapped or no action needed.");
    exit;
}


try {
    $pdo->beginTransaction();

    $stmt_order = $pdo->prepare("SELECT id, status, client_id, product_id FROM orders WHERE efi_charge_id = :efi_charge_id");
    $stmt_order->bindParam(':efi_charge_id', $efi_charge_id);
    $stmt_order->execute();
    $order = $stmt_order->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo "Pedido não encontrado para o ID de cobrança Efi fornecido.";
        error_log("Efi Webhook Warning: Order not found for efi_charge_id '{$efi_charge_id}'. Payload: " . $event_details_for_log);
        exit;
    }

    $current_order_status = $order['status'];
    $order_id = $order['id'];

    if ($current_order_status !== $local_order_status) {
        $stmt_update = $pdo->prepare("UPDATE orders SET status = :new_status, updated_at = NOW() WHERE id = :order_id");
        $stmt_update->bindParam(':new_status', $local_order_status);
        $stmt_update->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt_update->execute();

        $stmt_log = $pdo->prepare("INSERT INTO order_status_logs (order_id, previous_status, new_status, changed_by, details)
                                    VALUES (:order_id, :previous_status, :new_status, :changed_by, :details)");
        $changed_by = 'Webhook Efi';
        $stmt_log->bindParam(':order_id', $order_id, PDO::PARAM_INT);
        $stmt_log->bindParam(':previous_status', $current_order_status);
        $stmt_log->bindParam(':new_status', $local_order_status);
        $stmt_log->bindParam(':changed_by', $changed_by);
        $stmt_log->bindParam(':details', $event_details_for_log);
        $stmt_log->execute();

        if ($local_order_status === 'Pago' && $current_order_status !== 'Pago') {
            // TODO: Trigger email sending (order confirmation, product access details)
            // Example: send_order_confirmation_email($pdo, $order_id);
            // Example: grant_product_access($pdo, $order['client_id'], $order['product_id'], $order_id);
            error_log("Efi Webhook: Order ID {$order_id} updated to 'Pago'. Trigger post-payment actions.");
        }
    } else {
        error_log("Efi Webhook Info: Status for order ID {$order_id} ('{$local_order_status}') is already up-to-date. No change made for charge '{$efi_charge_id}'.");
    }

    $pdo->commit();
    http_response_code(200);
    echo "Notificação processada com sucesso.";
    // error_log("Efi Webhook Success: Notification for charge '{$efi_charge_id}' (Order ID {$order_id}) processed. New status: {$local_order_status}.");

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "Erro interno do servidor (DB) ao processar a notificação.";
    error_log("Efi Webhook PDOException: " . $e->getMessage() . " for charge '{$efi_charge_id}'. Payload: " . $event_details_for_log);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo "Erro geral ao processar a notificação.";
    error_log("Efi Webhook Exception: " . $e->getMessage() . " for charge '{$efi_charge_id}'. Payload: " . $event_details_for_log);
}

exit;
?>
