<?php
/**
 * API principal da aplicação
 * Ponto de entrada para todas as requisições
 */
// Autoload and environment config
require_once __DIR__ . '/../vendor/autoload.php';
// Secure session settings
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

// Generate CSRF token if missing
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
// Provide CSRF token via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    if ($action === 'getCsrfToken') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'csrfToken' => $_SESSION['csrf_token']]);
        exit;
    }
    if ($action === 'getConfig') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'config' => [
                'csrfToken'    => $_SESSION['csrf_token'],
                'mpPublicKey'  => ($_ENV['MP_PUBLIC_KEY'] ?? 'APP_USR-20845c57-b2d8-4550-9789-3e4b309d92d2'),
                'apiEndpoint'  => 'api/api.php',
                'mpBaseUrl'    => ($_ENV['MP_BASE_URL'] ?? 'https://ckao.in/cosmonumero/')
            ]
        ]);
        exit;
    }
}
// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Environment variables
$env = $_ENV['ENV'] ?? 'production';
$openai_api_key = $_ENV['OPENAI_API_KEY'] ?? '';
$mp_access_token = $_ENV['MP_ACCESS_TOKEN'] ?? '';
$mp_base_url = rtrim($_ENV['MP_BASE_URL'] ?? '', '/');
$openai_model = $_ENV['OPENAI_MODEL'] ?? 'gpt-4.1';

// Configure error display
if ($env === 'production') {
    ini_set('display_errors', 0);
    error_reporting(0);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

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

// Include necessary modules
require_once __DIR__ . '/checkout/mercadopago.php';
require_once __DIR__ . '/numerology/calculator.php';
require_once __DIR__ . '/numerology/openai.php';
require_once __DIR__ . '/pdf/generator.php';
require_once __DIR__ . '/database/database.php';
/**
 * Simple rate limiter: max $maxRequests per $minutes for each IP
 * Stores counters in temp directory
 */
function checkRateLimit(int $maxRequests = 60, int $minutes = 1) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = BASE_PATH . "/temp/ratelimit_" . md5($ip) . ".json";
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0755, true);
    }
    $now = time();
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        $count = $data['count'] ?? 0;
        $start = $data['start'] ?? $now;
        if ($now - $start < $minutes * 60) {
            if ($count >= $maxRequests) {
                sendErrorResponse('Too many requests. Rate limit exceeded.', 429);
            }
            $count++;
        } else {
            $count = 1;
            $start = $now;
        }
    } else {
        $count = 1;
        $start = $now;
    }
    file_put_contents($file, json_encode(['count' => $count, 'start' => $start]));
}
// Helper: valid date in expected format
function validateDate(string $date, string $format = 'Y-m-d'): bool {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

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

// Verify CSRF token for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
        sendErrorResponse('CSRF token inválido', 403);
    }
}
// Criar diretórios necessários se não existirem
initializeDirectories();

// Rate limiting: max 60 requests per minute per IP
checkRateLimit();
// Processar com base na ação
try {
    switch ($data['action']) {
        case 'createPayment':
            // Input validation
            if (!isset($data['amount'], $data['description'], $data['formData']['fullName'], $data['formData']['birthDate'])
                || !is_numeric($data['amount'])
                || !is_string($data['description'])
                || !is_string($data['formData']['fullName'])
                || !validateDate($data['formData']['birthDate'], 'Y-m-d')
            ) {
                sendErrorResponse('Dados inválidos para criação de pagamento', 400);
            }
            // sanitize input
            $data['amount'] = (float) $data['amount'];
            $data['description'] = trim($data['description']);
            $data['formData']['fullName'] = trim($data['formData']['fullName']);
            createPayment($data, $mp_access_token);
            break;
        case 'verifyPayment':
            // Input validation
            if (!isset($data['paymentId']) || !is_string($data['paymentId'])) {
                sendErrorResponse('ID de pagamento inválido', 400);
            }
            $data['externalReference'] = isset($data['externalReference']) && is_string($data['externalReference']) ? $data['externalReference'] : null;
            verifyPayment($data, $mp_access_token);
            break;
        case 'getNumerologyResults':
            // Input validation
            if (!isset($data['paymentId'], $data['formData']['fullName'], $data['formData']['birthDate'])
                || !is_string($data['paymentId'])
                || !is_string($data['formData']['fullName'])
                || !validateDate($data['formData']['birthDate'], 'Y-m-d')
            ) {
                sendErrorResponse('Dados inválidos para análise numerológica', 400);
            }
            getNumerologyResults($data, $openai_api_key);
            break;
        case 'sendEmail':
            sendEmailWithPDF($data);
            break;
        case 'generatePDF':
            generateAndDownloadPDF($data);
            break;
        case 'getTestResults': // Para testes sem pagamento
            // Input validation
            if (!isset($data['formData']['fullName'], $data['formData']['birthDate'])
                || !is_string($data['formData']['fullName'])
                || !validateDate($data['formData']['birthDate'], 'Y-m-d')
            ) {
                sendErrorResponse('Dados inválidos para análise de teste', 400);
            }
            getTestResults($data, $openai_api_key);
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
 * @return void
 */
function getNumerologyResults($data, $openai_api_key) {
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
 * @return void
 */
function getTestResults($data, $openai_api_key) {
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

        $interpretations = callOpenAIAssistant($fullName, $birthDate, $numerologyData, $openai_api_key);
        // Resultados completos
        $results = array_merge($numerologyData, $interpretations);
        file_put_contents($logDir . '/debug.log', date('Y-m-d H:i:s') . " - Resultado: " . json_encode($results) . "\n", FILE_APPEND);
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
 * @param array $requestData Dados da requisição
 * @return void
 */
function generateAndDownloadPDF($requestData) {
    // Verificar se temos os dados necessários
    if (!isset($requestData['formData']) || !isset($requestData['results'])) {
        header('Content-Type: application/json');
        sendErrorResponse(['message' => 'Dados insuficientes para gerar o PDF']);
        return;
    }

    // Extrair dados do request
    $formData = $requestData['formData'];
    $results = $requestData['results'];

    $name = $formData['fullName'];
    $birthdate = $formData['birthDate'];

    // Obter os números principais
    $lifePathNumber = $results['lifePathNumber'] ?? '';
    $destinyNumber = $results['destinyNumber'] ?? '';
    $personalYearNumber = $results['personalYearNumber'] ?? '';

    // Análises
    $analysis = [
        'lifePathMeaning' => $results['lifePathMeaning'] ?? '',
        'lifePathTalents' => $results['lifePathTalents'] ?? '',
        'destinyMeaning' => $results['destinyMeaning'] ?? '',
        'personalYearMeaning' => $results['personalYearMeaning'] ?? '',
        'currentChallenges' => $results['currentChallenges'] ?? '',
        'currentOpportunities' => $results['currentOpportunities'] ?? '',
        'dailyRitual' => $results['dailyRitual'] ?? ''
    ];

    // Incluir a biblioteca TCPDF
    require_once __DIR__ . '/../vendor/autoload.php';

    // Criar nova instância de TCPDF
    $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Remover cabeçalho e rodapé padrão
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);

    // Configurar cores e fontes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetFont('helvetica', '', 11);

    // Adicionar página
    $pdf->AddPage();

    // Definir fundo da página com cor escura
    $pdf->SetFillColor(15, 23, 42); // Cor azul escuro similar ao fundo
    $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');

    // Estilos para o PDF
    $titleStyle = 'color: #9333EA; font-size: 20pt; font-weight: bold; text-align: center; line-height: 1.5;';
    $subtitleStyle = 'color: #CBD5E1; font-size: 12pt; text-align: center; line-height: 1.5;';
    $nameStyle = 'color: #9333EA; font-size: 16pt; font-weight: bold; text-align: center; line-height: 1.5;';
    $birthdateStyle = 'color: #94A3B8; font-size: 12pt; text-align: center; line-height: 1.5;';
    $sectionTitleStyle = 'color: #9333EA; font-size: 14pt; font-weight: bold; line-height: 1.5;';
    $contentStyle = 'color: #E2E8F0; font-size: 11pt; line-height: 1.5;';
    $footerStyle = 'color: #94A3B8; font-size: 8pt; text-align: center; font-style: italic;';

    // Título principal
    $pdf->writeHTML('<h1 style="' . $titleStyle . '">Numerologia Cósmica</h1>', true, false, true, false, 'C');
    $pdf->writeHTML('<p style="' . $subtitleStyle . '">Descubra seu propósito, desafios e oportunidades através dos números que regem sua vida</p>', true, false, true, false, 'C');

    // Espaço
    $pdf->Ln(10);

    // Nome e data de nascimento
    $pdf->writeHTML('<h2 style="' . $nameStyle . '">' . $name . '</h2>', true, false, true, false, 'C');
    $birthdate_formatted = date('d/m/Y', strtotime($birthdate));
    $pdf->writeHTML('<p style="' . $birthdateStyle . '">' . $birthdate_formatted . '</p>', true, false, true, false, 'C');

    // Espaço
    $pdf->Ln(10);

    // Função para criar um círculo com número
    function addCircleWithNumber($pdf, $number, $x, $y, $r = 8) {
        $pdf->SetFillColor(147, 51, 234); // Cor roxa
        $pdf->Circle($x, $y, $r, 0, 360, 'F');
        $pdf->SetTextColor(255, 255, 255); // Texto branco
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetXY($x - $r, $y - $r/2);
        $pdf->Cell(2*$r, $r, $number, 0, 0, 'C');
        $pdf->SetTextColor(0, 0, 0); // Restaurar cor do texto
        $pdf->SetFont('helvetica', '', 11);
    }

    // Adicionar método Circle se não existir no TCPDF
    if (!method_exists($pdf, 'Circle')) {
        $pdf->Circle = function($x, $y, $r, $angstart = 0, $angend = 360, $style = 'D', $line_style = array(), $fill_color = array(), $nc = 8) use ($pdf) {
            $pdf->Ellipse($x, $y, $r, $r, 0, $angstart, $angend, $style, $line_style, $fill_color, $nc);
        };
    }

    // Seção: Caminho de Vida
    if (!empty($analysis['lifePathMeaning'])) {
        // Posição atual
        $curY = $pdf->GetY() + 10;

        // Adicionar círculo com número
        addCircleWithNumber($pdf, $lifePathNumber, 25, $curY);

        // Título da seção
        $pdf->SetXY(35, $curY - 5);
        $pdf->writeHTML('<span style="' . $sectionTitleStyle . '">Número do Caminho de Vida</span>', true, false, true, false);

        // Conteúdo
        $pdf->SetXY(15, $curY + 5);
        $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['lifePathMeaning'] . '</div>', true, false, true, false);

        // Atualizar posição Y
        $pdf->SetY($pdf->GetY() + 10);
    }

    // Seção: Talentos e Forças
    if (!empty($analysis['lifePathTalents'])) {
        // Posição atual
        $curY = $pdf->GetY() + 10;

        // Título da seção
        $pdf->SetXY(25, $curY - 5);
        $pdf->writeHTML('<span style="' . $sectionTitleStyle . '">Talentos e Forças Naturais</span>', true, false, true, false);

        // Conteúdo
        $pdf->SetXY(15, $curY + 5);
        $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['lifePathTalents'] . '</div>', true, false, true, false);

        // Atualizar posição Y
        $pdf->SetY($pdf->GetY() + 10);
    }

    // Seção: Número de Destino
    if (!empty($analysis['destinyMeaning'])) {

        // Posição atual
        $curY = $pdf->GetY() + 10;

        // Adicionar círculo com número
        addCircleWithNumber($pdf, $destinyNumber, 25, $curY);

        // Título da seção
        $pdf->SetXY(35, $curY - 5);
        $pdf->writeHTML('<span style="' . $sectionTitleStyle . '">Número de Destino</span>', true, false, true, false);

        // Conteúdo
        $pdf->SetXY(15, $curY + 5);
        $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['destinyMeaning'] . '</div>', true, false, true, false);

        // Atualizar posição Y
        $pdf->SetY($pdf->GetY() + 10);
    }

    // Adicionar nova página se necessário
    if ($pdf->GetY() > 200) {
        $pdf->AddPage();
        // Definir fundo da nova página
        $pdf->SetFillColor(15, 23, 42);
        $pdf->Rect(0, 0, $pdf->getPageWidth(), $pdf->getPageHeight(), 'F');
    }

    // Seção: Ano Pessoal
    if (!empty($analysis['personalYearMeaning'])) {

        // Posição atual
        $curY = $pdf->GetY() + 10;

        // Adicionar círculo com número
        addCircleWithNumber($pdf, $personalYearNumber, 25, $curY);

        // Título da seção
        $pdf->SetXY(35, $curY - 5);
        $pdf->writeHTML('<span style="' . $sectionTitleStyle . '">Ano Pessoal</span>', true, false, true, false);

        // Conteúdo
        $pdf->SetXY(15, $curY + 5);
        $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['personalYearMeaning'] . '</div>', true, false, true, false);

        // Atualizar posição Y
        $pdf->SetY($pdf->GetY() + 10);
    }

    // Seção: Desafios e Oportunidades
    if (!empty($analysis['currentChallenges'])) {

        // Posição atual
        $curY = $pdf->GetY() + 10;

        // Título da seção
        $pdf->SetXY(25, $curY - 5);
        $pdf->writeHTML('<span style="' . $sectionTitleStyle . '">Principais Desafios e Oportunidades</span>', true, false, true, false);

        // Conteúdo
        $pdf->SetXY(15, $curY + 5);
        $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['currentChallenges'] . '</div>', true, false, true, false);

        // Adicionar oportunidades se existirem
        if (!empty($analysis['currentOpportunities'])) {
            $pdf->SetY($pdf->GetY() + 5);
            $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['currentOpportunities'] . '</div>', true, false, true, false);
        }

        // Atualizar posição Y
        $pdf->SetY($pdf->GetY() + 10);
    }

    // Seção: Ritual Diário
    if (!empty($analysis['dailyRitual'])) {

        // Posição atual
        $curY = $pdf->GetY() + 10;

        // Título da seção
        $pdf->SetXY(25, $curY - 5);
        $pdf->writeHTML('<span style="' . $sectionTitleStyle . '">Ritual Diário Recomendado</span>', true, false, true, false);

        // Conteúdo
        $pdf->SetXY(15, $curY + 5);
        $pdf->writeHTML('<div style="' . $contentStyle . '">' . $analysis['dailyRitual'] . '</div>', true, false, true, false);
    }

    // Rodapé
    $pdf->SetY(-15);
    $pdf->writeHTML('<p style="' . $footerStyle . '">© ' . date('Y') . ' CosmoNúmeroAI. Todos os direitos reservados. https://ckao.in/cosmonumero/</p>', true, false, true, false, 'C');

    // Gerar o nome do arquivo
    $filename = 'Numerologia_' . preg_replace('/[^a-zA-Z0-9]/', '_', $name) . '.pdf';

    // Enviar para o navegador
    $pdf->Output($filename, 'D');
    exit;
}