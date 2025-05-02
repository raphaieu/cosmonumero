<?php
/**
 * Integração com o Mercado Pago
 */
global $mp_access_token;
$mp_access_token = 'APP_USR-8427023500547057-050113-5f3c2441f8b60a7ac66fd3c0ee0cfc71-1901198';
/**
 * Criar preferência de pagamento no Mercado Pago
 *
 * @param string $description Descrição do item
 * @param float $amount Valor do pagamento
 * @param string $customer_name Nome do cliente
 * @param string $birth_date Data de nascimento
 * @param string $external_reference Referência externa
 * @param string $access_token Token de acesso do Mercado Pago
 * @return array Dados da preferência de pagamento
 */
function createMercadoPagoPreference($description, $amount, $customer_name, $birth_date, $external_reference, $access_token) {
    // URL da API
    $url = 'https://api.mercadopago.com/checkout/preferences';

    // URLs de callback
    $base_url = 'https://ckao.in/cosmonumero';

    // Dados da preferência
    $preference_data = [
        'items' => [
            [
                'title' => $description,
                'quantity' => 1,
                'currency_id' => 'BRL',
                'unit_price' => (float)$amount
            ]
        ],
        'payer' => [
            'name' => $customer_name,
            'identification' => [
                'type' => 'CPF',
                'number' => '00000000000' // Em produção, coletar CPF real
            ]
        ],
        'payment_methods' => [
            'excluded_payment_types' => [
                ['id' => 'ticket'],
                ['id' => 'atm']
            ],
            'installments' => 1
        ],
        'back_urls' => [
            'success' => $base_url . '/api/checkout/success.php',
            'failure' => $base_url . '/api/checkout/failure.php',
            'pending' => $base_url . '/api/checkout/pending.php'
        ],
        'notification_url' => $base_url . '/api/checkout/webhook.php',
        'auto_return' => 'approved',
        'external_reference' => $external_reference,
        'statement_descriptor' => 'Numerologia Cósmica',
        'metadata' => [
            'customer_name' => $customer_name,
            'birth_date' => $birth_date
        ]
    ];

    // Inicializar cURL
    $ch = curl_init($url);

    // Configurar opções do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);

    // Executar requisição
    $response = curl_exec($ch);

    // Verificar erros
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro na requisição cURL: ' . $error);
    }

    // Fechar cURL
    curl_close($ch);

    // Decodificar resposta
    $result = json_decode($response, true);

    // Verificar se a resposta foi bem-sucedida
    if (!isset($result['id'])) {
        if (isset($result['message'])) {
            throw new Exception('Erro na API do Mercado Pago: ' . $result['message']);
        } else {
            throw new Exception('Erro desconhecido na API do Mercado Pago');
        }
    }

    // Retornar dados da preferência
    return [
        'id' => $result['id'],
        'init_point' => $result['init_point'],
        'external_reference' => $external_reference
    ];
}

/**
 * Obter detalhes de um pagamento no Mercado Pago
 *
 * @param string $payment_id ID do pagamento
 * @param string $access_token Token de acesso do Mercado Pago
 * @return array Detalhes do pagamento
 */
function getMercadoPagoPaymentDetails($payment_id, $access_token) {
    // URL da API
    $url = 'https://api.mercadopago.com/v1/payments/' . $payment_id;

    // Inicializar cURL
    $ch = curl_init($url);

    // Configurar opções do cURL
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);

    // Executar requisição
    $response = curl_exec($ch);

    // Verificar erros
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Erro na requisição cURL: ' . $error);
    }

    // Fechar cURL
    curl_close($ch);

    // Decodificar resposta
    $result = json_decode($response, true);

    // Verificar se a resposta foi bem-sucedida
    if (!isset($result['id'])) {
        if (isset($result['message'])) {
            throw new Exception('Erro na API do Mercado Pago: ' . $result['message']);
        } else {
            throw new Exception('Erro desconhecido na API do Mercado Pago');
        }
    }

    return $result;
}

/**
 * Verificar se um pagamento foi aprovado
 *
 * @param string $payment_id ID do pagamento
 * @param string $access_token Token de acesso do Mercado Pago
 * @return bool True se aprovado, false caso contrário
 */
function isMercadoPagoPaymentApproved($payment_id, $access_token) {
    try {
        $payment_details = getMercadoPagoPaymentDetails($payment_id, $access_token);
        return $payment_details['status'] === 'approved';
    } catch (Exception $e) {
        logMercadoPagoError($e->getMessage());
        return false;
    }
}

/**
 * Atualizar status de pagamento
 *
 * @param string $external_reference Referência externa
 * @param string $payment_id ID do pagamento
 * @param string $status Status do pagamento
 * @param array $payment_details Detalhes do pagamento
 * @return void
 */
function updatePaymentStatus($external_reference, $payment_id, $status, $payment_details) {
    // Em produção, atualizar no banco de dados
    $status_file = __DIR__ . '/../../temp/' . $external_reference . '_status.json';
    $status_data = [
        'external_reference' => $external_reference,
        'payment_id' => $payment_id,
        'status' => $status,
        'payment_details' => $payment_details,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($status_file, json_encode($status_data));
}


/**
 * Registrar erro do Mercado Pago
 *
 * @param string $message Mensagem de erro
 * @return void
 */
function logMercadoPagoError($message) {
    $logDir = __DIR__ . '/../../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/mercadopago_error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
