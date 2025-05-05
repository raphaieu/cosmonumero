<?php
/**
 * Integração com a API da OpenAI para interpretações numerológicas
 */
// API key is injected from environment by api/api.php using Dotenv

/**
 * Chamar o assistente da OpenAI
 *
 * @param string $fullName Nome completo
 * @param string $birthDate Data de nascimento
 * @param array $numerologyData Dados numerológicos calculados
 * @param string $api_key Chave da API OpenAI
 * @return array Resposta do assistente
 * @throws Exception
 */
function callOpenAIAssistant($fullName, $birthDate, $numerologyData, $api_key) {

    $lifePathNumber = $numerologyData['lifePathNumber'];
    $destinyNumber = $numerologyData['destinyNumber'];
    $personalYearNumber = $numerologyData['personalYearNumber'];

    // URL da API
    $url = 'https://api.openai.com/v1/chat/completions';

    $systemInstructions = <<<EOT
You are a cosmic numerology expert with an inspiring, accessible, and spiritual tone. Based on the provided data (full name, birth date, and current date), generate a complete numerological analysis in Portuguese, structured with the following sections:

1. **Caminho de Vida**: Calculate the Life Path Number using the Pythagorean system and explain its meaning (up to 255 words) in terms of purpose and challenges.  IMPORTANT: Do not reduce master numbers (11, 22, 33) to a single digit. If the calculation results in 11, 22, or 33, keep it as is and explain the significance of this master number.
2. **Talentos e Forças**: Identify (in up to 125 words) which natural talents and strengths the individual may be underestimating, based on the Life Path.
3. **Número de Destino**: Calculate the Destiny Number using the Pythagorean system and describe (in up to 255 words) the major life lessons suggested. IMPORTANT: Do not reduce master numbers (11, 22, 33) to a single digit. If the calculation results in 11, 22, or 33, keep it as is and explain the significance of this master number.
4. **Ano Pessoal**: Calculate the current Personal Year by adding the day and month of birth to the current year, then reduce to a single digit (1-9). Even if the sum results in 11, 22, or 33, these should be reduced to a single digit for Personal Year calculations. Explain (in up to 125 words) the dominant energy to focus on until the next cycle.
5. **Desafios e Oportunidades**: Based on the Life Path and Personal Year, indicate (in up to 150 words) the main active challenge and opportunity.
6. **Ritual Diário**: Create a simple daily ritual or exercise (max 150 words) based on the Life Path to align with one's purpose.

Format the response with clear section titles in Portuguese. Avoid redundancy, and keep the content concise, practical, and motivating.

Always use the PYTHAGOREAN NUMEROLOGY SYSTEM for all calculations and respond in Brazilian Portuguese.
EOT;

    // Data atual
    $currentDate = date('Y-m-d');

    // Preparar prompt para o assistente
    $prompt = "Nome completo: {$fullName}, nascido(a) em {$birthDate} e hoje é {$currentDate}.";

    // Dados da requisição
    $data = [
        'model' => 'gpt-4.1',
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemInstructions
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => 0.7
    ];

    try {
        // Inicializar cURL
        $ch = curl_init($url);

        // Configurar opções do cURL
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ]);

        // Executar requisição
        $response = curl_exec($ch);

        // Verificar erros
        if (curl_errno($ch)) {
            curl_close($ch);
            throw new Exception('Erro na requisição cURL: ' . curl_error($ch));
        }

        // Obter código de status HTTP
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Fechar cURL
        curl_close($ch);

        // Decodificar resposta
        $result = json_decode($response, true);

        // Verificar código de status HTTP
        if ($http_code !== 200) {
            $error_message = $result['error']['message'] ?? 'Erro desconhecido';
            throw new Exception('Erro na API da OpenAI: ' . $error_message);
        }

        // Verificar se a resposta foi bem-sucedida
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('Formato de resposta inválido da API da OpenAI');
        }

        // Retornar o conteúdo da resposta
        $response = $result['choices'][0]['message']['content'];

        return parseOpenAIResponse($response);
    } catch (Exception $e) {
        logOpenAIError($e->getMessage());
        throw new Exception($e->getMessage());
    }
}

/**
 * Processar a resposta da OpenAI e extrair as interpretações em um array.
 *
 * @param string $response Resposta da OpenAI
 * @return array Interpretações extraídas
 */
function parseOpenAIResponse($response) {
    $map = [
        'Caminho de Vida' => 'lifePathMeaning',
        'Talentos e Forças' => 'lifePathTalents',
        'Número de Destino' => 'destinyMeaning',
        'Ano Pessoal' => 'personalYearMeaning',
        'Desafios e Oportunidades' => 'currentChallenges',
        'Ritual Diário' => 'dailyRitual'
    ];

    $interpretations = array_fill_keys(array_values($map), '');

    // Captura todas as seções com título Markdown e seus conteúdos
    preg_match_all('/###\s*(.+?)\s*\n+(.+?)(?=(\n###|$))/s', $response, $matches, PREG_SET_ORDER);

    foreach ($matches as $match) {
        $title = trim($match[1]);
        $content = trim($match[2]);

        // Remover marcações Markdown
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content); // Remove **negrito**
        $content = preg_replace('/\*(.*?)\*/', '$1', $content);     // Remove *itálico*
        $content = preg_replace('/---+/', '', $content);            // Remove linhas horizontais
        $content = preg_replace('/\n\s*\n/', "\n", $content);       // Remove linhas extras
        $content = trim($content);                                  // Remove espaços extras

        if (isset($map[$title])) {
            $interpretations[$map[$title]] = $content;
        } elseif ($title === 'Desafios e Oportunidades') {
            // Dividir desafio e oportunidade
            $split = preg_split('/(?<=\.)\s+(?=A oportunidade|A principal oportunidade|A grande oportunidade)/i', $content, 2);
            $interpretations['currentChallenges'] = trim($split[0]);
            if (isset($split[1])) {
                $interpretations['currentOpportunities'] = trim($split[1]);
            }
        }
    }

    return $interpretations;
}

/**
 * Obter interpretação de fallback para um campo específico
 *
 * @param string $field Campo da interpretação
 * @param array $numerologyData Dados numerológicos calculados
 * @return string Interpretação de fallback
 */
function getFallbackInterpretation($field, $numerologyData) {
    $lifePathNumber = $numerologyData['lifePathNumber'];
    $destinyNumber = $numerologyData['destinyNumber'];
    $personalYearNumber = $numerologyData['personalYearNumber'];

    // Fallbacks básicos para cada campo
    $fallbacks = [
        'lifePathMeaning' => "O Caminho de Vida {$lifePathNumber} representa seu propósito principal nesta existência. Este número revela os talentos inatos e desafios que você enfrentará para alcançar seu maior potencial. Ele serve como um guia para entender sua missão de vida e as lições que você veio aprender.",
        'lifePathTalents' => "Com o Caminho de Vida {$lifePathNumber}, você possui talentos naturais que podem estar adormecidos ou subestimados. Desenvolver e expressar esses dons é essencial para seu crescimento pessoal e para cumprir seu propósito maior.",
        'destinyMeaning' => "Seu Número de Destino {$destinyNumber} revela as grandes lições que você veio aprender nesta vida. Ele aponta para qualidades e habilidades que deve desenvolver para alcançar seu potencial máximo e realizar sua missão de vida.",
        'personalYearMeaning' => "Você está em um Ano Pessoal {$personalYearNumber}, que traz uma energia específica para o período atual até seu próximo aniversário. Esta vibração influencia as experiências, oportunidades e desafios que encontrará neste ciclo.",
        'currentChallenges' => "A combinação do seu Caminho de Vida {$lifePathNumber} com seu Ano Pessoal {$personalYearNumber} apresenta desafios específicos neste momento. Estar consciente deles ajuda a navegar este período com mais sabedoria e menos resistência.",
        'currentOpportunities' => "A interação entre seu Caminho de Vida {$lifePathNumber} e Ano Pessoal {$personalYearNumber} cria oportunidades únicas neste momento. Reconhecê-las e aproveitá-las pode acelerar seu crescimento e trazer realizações significativas.",
        'dailyRitual' => "Um ritual diário baseado no seu Caminho de Vida {$lifePathNumber} pode ajudar a mantê-lo alinhado com seu propósito maior. Dedique alguns minutos por dia para esta prática e observe como ela potencializa sua energia natural."
    ];

    return $fallbacks[$field] ?? "Informação não disponível.";
}

/**
 * Obter todas as interpretações de fallback
 *
 * @param array $numerologyData Dados numerológicos calculados
 * @return array Interpretações de fallback
 */
function getFallbackInterpretations($numerologyData) {
    $interpretations = [
        'lifePathMeaning' => getFallbackInterpretation('lifePathMeaning', $numerologyData),
        'lifePathTalents' => getFallbackInterpretation('lifePathTalents', $numerologyData),
        'destinyMeaning' => getFallbackInterpretation('destinyMeaning', $numerologyData),
        'personalYearMeaning' => getFallbackInterpretation('personalYearMeaning', $numerologyData),
        'currentChallenges' => getFallbackInterpretation('currentChallenges', $numerologyData),
        'currentOpportunities' => getFallbackInterpretation('currentOpportunities', $numerologyData),
        'dailyRitual' => getFallbackInterpretation('dailyRitual', $numerologyData)
    ];

    return $interpretations;
}

/**
 * Registrar erro da OpenAI
 *
 * @param string $message Mensagem de erro
 * @return void
 */
function logOpenAIError($message) {
    $logDir = __DIR__ . '/../../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/openai_error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
