<?php
/**
 * Webhook para receber notificações do Mercado Pago
 */

// Configurações
header('Content-Type: application/json');

// Incluir arquivos necessários
require_once './mercadopago.php';

// Definir log
$logFile = __DIR__ . '/../../logs/webhook.log';

// Registrar recebimento
logWebhook('Webhook recebido: ' . file_get_contents('php://input'));

// Obter e decodificar os dados JSON enviados
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Verificar se os dados foram decodificados corretamente
if (!$data) {
    logWebhook('Dados inválidos: não foi possível decodificar JSON');
    http_response_code(400);
    echo json_encode(['error' => 'Dados inválidos']);
    exit;
}

try {
    // Verificar e adaptar para diferentes formatos de notificação
    if (isset($data['action']) && ($data['action'] == 'payment.created' || $data['action'] == 'payment.updated')) {
        // Formato de webhook moderno
        $id = $data['data']['id'];
        logWebhook('Formato webhook novo detectado, payment_id: ' . $id);
        processPaymentNotification($id);
    } elseif (isset($data['resource']) && is_numeric($data['resource'])) {
        // Formato webhook mais simples
        $id = $data['resource'];
        logWebhook('Formato webhook simples detectado, payment_id: ' . $id);
        processPaymentNotification($id);
    } elseif (isset($data['topic']) && $data['topic'] == 'payment' && isset($data['resource'])) {
        // Formato de recurso de pagamento
        $id = $data['resource'];
        logWebhook('Formato webhook de recurso de pagamento detectado, payment_id: ' . $id);
        processPaymentNotification($id);
    } elseif (isset($data['topic']) && $data['topic'] == 'merchant_order' && isset($data['resource'])) {
        // Notificação de ordem de comerciante - não processamos, apenas logamos
        logWebhook('Notificação de merchant_order recebida, ignorando');
        http_response_code(200);
        echo json_encode(['status' => 'ignored']);
        exit;
    } else {
        logWebhook('Formato de notificação não reconhecido: ' . json_encode($data));
        http_response_code(200); // Retornar 200 para evitar reenvios
        echo json_encode(['status' => 'ignored']);
        exit;
    }

    // Responder com sucesso
    http_response_code(200);
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    logWebhook('Erro ao processar notificação: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao processar notificação']);
    exit;
}

/**
 * Processar notificação de pagamento
 *
 * @param string $payment_id ID do pagamento
 * @return void
 */
function processPaymentNotification($payment_id) {
    global $mp_access_token;

    logWebhook('Processando notificação de pagamento: ' . $payment_id);

    // Obter detalhes do pagamento
    $payment_details = getMercadoPagoPaymentDetails($payment_id, $mp_access_token);

    // Verificar status do pagamento
    if ($payment_details['status'] !== 'approved') {
        logWebhook('Pagamento não aprovado: ' . $payment_details['status']);
        return;
    }

    // Obter referência externa
    $external_reference = $payment_details['external_reference'] ?? '';

    if (empty($external_reference)) {
        logWebhook('Referência externa não encontrada');
        return;
    }

    // Buscar dados da transação
    $transaction_file = __DIR__ . '/../../temp/' . $external_reference . '.json';

    if (!file_exists($transaction_file)) {
        logWebhook('Arquivo de transação não encontrado: ' . $transaction_file);
        return;
    }

    $transaction_data = json_decode(file_get_contents($transaction_file), true);

    if (!$transaction_data) {
        logWebhook('Não foi possível decodificar dados da transação');
        return;
    }

    // Atualizar status do pagamento
    updatePaymentStatus($external_reference, $payment_id, 'approved', $payment_details);

    logWebhook('Pagamento processado com sucesso: ' . $payment_id);
}

/**
 * Registrar log de webhook
 *
 * @param string $message Mensagem para o log
 * @return void
 */
function logWebhook($message) {
    global $logFile;

    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}