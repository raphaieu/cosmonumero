<?php
/**
 * API principal da aplicação
 * Ponto de entrada para todas as requisições
 */

// Configurações de debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Define o caminho base do projeto
define('BASE_PATH', realpath(__DIR__ . '/..'));

// Log básico para debug
global $logDir;
$logDir = BASE_PATH . '/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents($logDir . '/debug.log', date('Y-m-d H:i:s') . " - API chamada - " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);

// Registrar corpo da requisição
$input = file_get_contents('php://input');
file_put_contents($logDir . '/debug.log', date('Y-m-d H:i:s') . " - Dados recebidos: " . $input . "\n", FILE_APPEND);

// Configuração de cabeçalhos
header('Content-Type: application/json');

// Definir zona de tempo
date_default_timezone_set('America/Sao_Paulo');

// Incluir arquivos necessários
require_once __DIR__ . '/checkout/mercadopago.php';
require_once __DIR__ . '/numerology/calculator.php';
require_once __DIR__ . '/numerology/openai.php';
require_once __DIR__ . '/pdf/generator.php';
require_once __DIR__ . '/database/database.php';

// Verificar método de requisição
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Método não permitido', 405);
}

// Obter e decodificar os dados JSON enviados
$json_data = $input;
$data = json_decode($json_data, true);

// Verificar se os dados foram decodificados corretamente
if (!$data) {
    sendErrorResponse('Dados inválidos', 400);
}

// Verificar se a ação foi especificada
if (!isset($data['action'])) {
    sendErrorResponse('Ação não especificada', 400);
}

// Criar diretórios necessários se não existirem
initializeDirectories();

// Processar com base na ação
try {
    switch ($data['action']) {
        case 'createPayment':
            createPayment($data, $mp_access_token);
            break;
        case 'verifyPayment':
            verifyPayment($data, $mp_access_token);
            break;
        case 'getNumerologyResults':
            getNumerologyResults($data, $openai_api_key, $openai_assistant_id);
            break;
        case 'sendEmail':
            sendEmailWithPDF($data);
            break;
        case 'generatePDF':
            generateAndDownloadPDF($data);
            break;
        case 'getTestResults': // Para testes sem pagamento
            getTestResults($data, $openai_api_key, $openai_assistant_id);
            break;
        case 'test':
            echo json_encode([
                'success' => true,
                'message' => 'API funcionando corretamente',
                'timestamp' => date('Y-m-d H:i:s'),
                'php_version' => phpversion(),
                'extensions' => get_loaded_extensions()
            ]);
            exit;
            break;
        default:
            sendErrorResponse('Ação desconhecida', 400);
    }
} catch (Exception $e) {
    logError($e->getMessage());
    sendErrorResponse('Erro ao processar requisição: ' . $e->getMessage());
}

/**
 * Criar preferência de pagamento no Mercado Pago
 *
 * @param array $data Dados da requisição
 * @param string $mp_access_token Token de acesso do Mercado Pago
 * @return void
 */
function createPayment($data, $mp_access_token) {
    // Verificar se os dados necessários foram enviados
    global $logDir;
    if (!isset($data['amount']) || !isset($data['description']) || !isset($data['formData'])) {
        sendErrorResponse('Dados insuficientes para criar pagamento');
    }

    // Extrair dados
    $amount = $data['amount'];
    $description = $data['description'];
    $fullName = $data['formData']['fullName'];
    $birthDate = $data['formData']['birthDate'];

    // Gerar referência externa única
    $external_reference = 'NUM-' . uniqid();

    try {
        // Criar preferência de pagamento
        $result = createMercadoPagoPreference(
            $description,
            $amount,
            $fullName,
            $birthDate,
            $external_reference,
            $mp_access_token
        );

        // Salvar dados da transação
        $transaction_data = [
            'preference_id' => $result['id'],
            'external_reference' => $external_reference,
            'amount' => $amount,
            'description' => $description,
            'customer_name' => $fullName,
            'birth_date' => $birthDate,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Salvar no banco de dados (ou em arquivo para testes)
        $temp_file = BASE_PATH . '/temp/' . $external_reference . '.json';
        file_put_contents($temp_file, json_encode($transaction_data));

        // Retornar dados da preferência
        sendSuccessResponse([
            'preferenceId' => $result['id'],
            'init_point' => $result['init_point']
        ]);
    } catch (Exception $e) {
        file_put_contents($logDir . '/error.log', date('Y-m-d H:i:s') . " - Erro ao criar pagamento: " . $e->getMessage() . "\n", FILE_APPEND);
        sendErrorResponse('Erro ao criar pagamento: ' . $e->getMessage());
    }
}

function verifyPayment($data, $mp_access_token) {
    // Verificar dados
    if (!isset($data['paymentId'])) {
        sendErrorResponse('ID de pagamento não especificado');
    }
    
    $paymentId = $data['paymentId'];
    $externalReference = $data['externalReference'] ?? '';
    
    try {
        // Verificar se temos status salvo localmente primeiro (mais rápido)
        $statusFound = false;
        $paymentApproved = false;
        
        if ($externalReference) {
            $statusFile = BASE_PATH . '/temp/' . $externalReference . '_status.json';
            if (file_exists($statusFile)) {
                $statusData = json_decode(file_get_contents($statusFile), true);
                if ($statusData && isset($statusData['status'])) {
                    $statusFound = true;
                    $paymentApproved = ($statusData['status'] === 'approved');
                    
                    // Log
                    logError("Status encontrado localmente para {$paymentId}: {$statusData['status']}");
                    
                    // Se aprovado, podemos retornar imediatamente
                    if ($paymentApproved) {
                        sendSuccessResponse([
                            'paymentId' => $paymentId,
                            'status' => 'approved',
                            'paymentApproved' => true,
                            'source' => 'local'
                        ]);
                        return;
                    }
                }
            }
        }
        
        // Se não temos status local ou não está aprovado, verificar na API
        $payment = getMercadoPagoPaymentDetails($paymentId, $mp_access_token);
        
        // Verificar status
        $status = $payment['status'] ?? 'unknown';
        $paymentApproved = ($status === 'approved');
        
        // Se aprovado, salvar status localmente
        if ($paymentApproved && $externalReference) {
            updatePaymentStatus($externalReference, $paymentId, 'approved', $payment);
        }
        
        // Retornar resultado
        sendSuccessResponse([
            'paymentId' => $paymentId,
            'status' => $status,
            'paymentApproved' => $paymentApproved,
            'source' => 'api'
        ]);
    } catch (Exception $e) {
        logError("Erro ao verificar pagamento {$paymentId}: " . $e->getMessage());
        sendErrorResponse('Erro ao verificar pagamento: ' . $e->getMessage());
    }
}

/**
 * Obter resultados da análise numerológica após pagamento
 *
 * @param array $data Dados da requisição
 * @param string $openai_api_key Chave da API OpenAI
 * @param string $openai_assistant_id ID do assistente OpenAI
 * @return void
 */
function getNumerologyResults($data, $openai_api_key, $openai_assistant_id) {
    // Verificar se os dados necessários foram enviados
    global $logDir;
    if (!isset($data['paymentId']) || !isset($data['formData'])) {
        sendErrorResponse('Dados insuficientes para processar análise');
    }

    $paymentId = $data['paymentId'];
    $externalReference = $data['externalReference'] ?? null;
    $formData = $data['formData'];

    // Para testes, vamos assumir que o pagamento foi aprovado
    // Em produção, verificar o status do pagamento

    // Processar análise numerológica
    $fullName = $formData['fullName'];
    $birthDate = $formData['birthDate'];

    try {
        // Calcular os números numerológicos
        $numerologyData = calculateNumerology($fullName, $birthDate);

        // Obter interpretações
        // Para testes, usaremos interpretações simplificadas
        $interpretations = [
            'lifePathMeaning' => "Seu Caminho de Vida {$numerologyData['lifePathNumber']} representa seu propósito nesta existência. Este número revela os talentos e desafios que você enfrentará.",
            'lifePathTalents' => "Com o Caminho de Vida {$numerologyData['lifePathNumber']}, você possui talentos naturais que podem estar adormecidos.",
            'destinyMeaning' => "Seu Número de Destino {$numerologyData['destinyNumber']} revela as grandes lições que você veio aprender nesta vida.",
            'personalYearMeaning' => "Você está em um Ano Pessoal {$numerologyData['personalYearNumber']}, que traz uma energia específica para o período atual.",
            'currentChallenges' => "A combinação do seu Caminho de Vida {$numerologyData['lifePathNumber']} com seu Ano Pessoal {$numerologyData['personalYearNumber']} apresenta desafios específicos.",
            'currentOpportunities' => "A interação entre seu Caminho de Vida {$numerologyData['lifePathNumber']} e Ano Pessoal {$numerologyData['personalYearNumber']} cria oportunidades únicas.",
            'dailyRitual' => "Um ritual diário baseado no seu Caminho de Vida {$numerologyData['lifePathNumber']} pode ajudar a mantê-lo alinhado com seu propósito."
        ];

        // Resultados completos
        $results = array_merge($numerologyData, $interpretations);

        // Salvar os resultados
        $temp_file = BASE_PATH . '/temp/results_' . $paymentId . '.json';
        file_put_contents($temp_file, json_encode($results));

        // Retornar resultados
        sendSuccessResponse([
            'results' => $results
        ]);
    } catch (Exception $e) {
        file_put_contents($logDir . '/error.log', date('Y-m-d H:i:s') . " - Erro ao processar análise: " . $e->getMessage() . "\n", FILE_APPEND);
        sendErrorResponse('Erro ao processar análise: ' . $e->getMessage());
    }
}

/**
 * Obter resultados de teste (sem pagamento)
 *
 * @param array $data Dados da requisição
 * @param string $openai_api_key Chave da API OpenAI
 * @param string $openai_assistant_id ID do assistente OpenAI
 * @return void
 */
function getTestResults($data, $openai_api_key, $openai_assistant_id) {
    // Verificar se os dados necessários foram enviados
    global $logDir;
    if (!isset($data['formData'])) {
        sendErrorResponse('Dados insuficientes para processar análise de teste');
    }

    // Extrair dados
    $fullName = $data['formData']['fullName'];
    $birthDate = $data['formData']['birthDate'];

    try {
        // Calcular os números numerológicos
        $numerologyData = calculateNumerology($fullName, $birthDate);

        // Obter interpretações simplificadas para teste
        $interpretations = [
            'lifePathMeaning' => "Seu Caminho de Vida {$numerologyData['lifePathNumber']} representa seu propósito nesta existência. Este número revela os talentos e desafios que você enfrentará.",
            'lifePathTalents' => "Com o Caminho de Vida {$numerologyData['lifePathNumber']}, você possui talentos naturais que podem estar adormecidos.",
            'destinyMeaning' => "Seu Número de Destino {$numerologyData['destinyNumber']} revela as grandes lições que você veio aprender nesta vida.",
            'personalYearMeaning' => "Você está em um Ano Pessoal {$numerologyData['personalYearNumber']}, que traz uma energia específica para o período atual.",
            'currentChallenges' => "A combinação do seu Caminho de Vida {$numerologyData['lifePathNumber']} com seu Ano Pessoal {$numerologyData['personalYearNumber']} apresenta desafios específicos.",
            'currentOpportunities' => "A interação entre seu Caminho de Vida {$numerologyData['lifePathNumber']} e Ano Pessoal {$numerologyData['personalYearNumber']} cria oportunidades únicas.",
            'dailyRitual' => "Um ritual diário baseado no seu Caminho de Vida {$numerologyData['lifePathNumber']} pode ajudar a mantê-lo alinhado com seu propósito."
        ];

        // Resultados completos
        $results = array_merge($numerologyData, $interpretations);

        // Retornar resultados
        sendSuccessResponse([
            'results' => $results
        ]);
    } catch (Exception $e) {
        file_put_contents($logDir . '/error.log', date('Y-m-d H:i:s') . " - Erro ao processar análise de teste: " . $e->getMessage() . "\n", FILE_APPEND);
        sendErrorResponse('Erro ao processar análise de teste: ' . $e->getMessage());
    }
}

/**
 * Enviar resposta de erro
 *
 * @param string $message Mensagem de erro
 * @param int $code Código HTTP
 * @return void
 */
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}

/**
 * Enviar resposta de sucesso
 *
 * @param array $data Dados a serem enviados
 * @return void
 */
function sendSuccessResponse($data = []) {
    $response = array_merge(['success' => true], $data);
    echo json_encode($response);
    exit;
}

/**
 * Registrar erro no log
 *
 * @param string $message Mensagem de erro
 * @return void
 */
function logError($message) {
    global $logDir;

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

/**
 * Inicializar diretórios necessários
 *
 * @return void
 */
function initializeDirectories() {
    $dirs = [
        BASE_PATH . '/logs',
        BASE_PATH . '/temp',
        BASE_PATH . '/pdfs',
        BASE_PATH . '/database'
    ];

    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Enviar e-mail com PDF anexado
 *
 * @param array $data Dados da requisição
 * @return void
 */
function sendEmailWithPDF($data) {
    // Verificar dados
    if (!isset($data['formData']) || !isset($data['contactData']['email']) || !isset($data['results'])) {
        sendErrorResponse('Dados insuficientes para enviar e-mail');
    }

    // Extrair dados
    $fullName = $data['formData']['fullName'];
    $email = $data['contactData']['email'];
    $results = $data['results'];

    // Gerar PDF
    $pdfPath = generateNumerologyPDF($data);

    // Configurar email
    $to = $email;
    $subject = "Sua Análise Numerológica - {$fullName}";

    // Headers do email
    $headers = "From: contato@ckao.in\r\n";
    $headers .= "Reply-To: contato@ckao.in\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=boundary\r\n";

    // Corpo do email
    $message = "--boundary\r\n";
    $message .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
    $message .= "<html><body>";
    $message .= "<h1>Sua Análise Numerológica</h1>";
    $message .= "<p>Olá {$fullName},</p>";
    $message .= "<p>Segue em anexo sua análise numerológica completa.</p>";
    $message .= "<p>Agradecemos pela confiança!</p>";
    $message .= "<p>Atenciosamente,<br>Equipe Numerologia Cósmica</p>";
    $message .= "</body></html>\r\n";

    // Anexar PDF
    if (file_exists($pdfPath)) {
        $attachment = file_get_contents($pdfPath);
        $attachment = chunk_split(base64_encode($attachment));

        $message .= "--boundary\r\n";
        $message .= "Content-Type: application/pdf; name=\"Analise_Numerologica.pdf\"\r\n";
        $message .= "Content-Disposition: attachment; filename=\"Analise_Numerologica.pdf\"\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= $attachment . "\r\n";
        $message .= "--boundary--";
    }

    // Enviar email
    $success = mail($to, $subject, $message, $headers);

    if (!$success) {
        logError("Falha ao enviar email para {$email}");
        sendErrorResponse('Erro ao enviar e-mail');
    }

    // Registrar envio
    logError("Email enviado com sucesso para {$email}");

    // Resposta
    sendSuccessResponse([
        'message' => 'E-mail enviado com sucesso (simulação)'
    ]);
}

/**
 * Gerar e fazer download do PDF
 *
 * @param array $data Dados da requisição
 * @return void
 */
function generateAndDownloadPDF($data) {
    // Implementação simplificada para teste
    header('Content-Type: application/json');
    sendSuccessResponse([
        'message' => 'PDF gerado com sucesso (simulação)'
    ]);
}
