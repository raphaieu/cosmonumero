<?php
/**
 * Página de pagamento pendente após pagamento no Mercado Pago
 * Redireciona o usuário de volta à aplicação frontend
 */
// Carregar variáveis de ambiente
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->safeLoad();
$mp_base_url = rtrim($_ENV['MP_BASE_URL'] ?? '', '/');

// Capturar parâmetros
$payment_id = $_GET['payment_id'] ?? '';
$external_reference = $_GET['external_reference'] ?? '';
$status = $_GET['status'] ?? '';

// Log de callback (opcional)
$logFile = __DIR__ . '/../../logs/checkout.log';
if (!is_dir(dirname($logFile))) { mkdir(dirname($logFile), 0755, true); }
file_put_contents($logFile, "[".date('Y-m-d H:i:s')."] Pending: payment_id=$payment_id, external_reference=$external_reference, status=$status\n", FILE_APPEND);

// Redirecionar para a aplicação (frontend) com parâmetros
$redirect_url = $mp_base_url . '?status=' . urlencode($status)
    . '&payment_id=' . urlencode($payment_id)
    . '&external_reference=' . urlencode($external_reference);
header('Location: ' . $redirect_url);
exit;