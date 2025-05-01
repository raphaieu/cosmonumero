<?php
/**
 * Página de falha após tentativa de pagamento no Mercado Pago
 */

// Capturar parâmetros
$payment_id = $_GET['payment_id'] ?? '';
$external_reference = $_GET['external_reference'] ?? '';
$status = $_GET['status'] ?? '';

// Registrar recebimento
$logFile = __DIR__ . '/../../logs/checkout.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
$timestamp = date('Y-m-d H:i:s');
$log_message = "[$timestamp] Failure callback: payment_id=$payment_id, external_reference=$external_reference, status=$status\n";
file_put_contents($logFile, $log_message, FILE_APPEND);

// Redirecionar para a página principal com parâmetros de falha
$redirect_url = '/cosmonumero/?status=' . urlencode($status) . '&payment_id=' . urlencode($payment_id) . '&external_reference=' . urlencode($external_reference);
header('Location: ' . $redirect_url);
exit;