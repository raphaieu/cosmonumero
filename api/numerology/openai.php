<?php
/**
 * Integração com a API da OpenAI para interpretações numerológicas
 */
$openai_api_key = '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';
$openai_assistant_id = 'asst_CA8Yo9SaiNhBZVRcCJXQZX0I';

/**
 * Obter interpretações numerológicas via OpenAI
 *
 * @param string $fullName Nome completo
 * @param string $birthDate Data de nascimento
 * @param array $numerologyData Dados numerológicos calculados
 * @param string $api_key Chave da API OpenAI
 * @param string $assistant_id ID do assistente OpenAI
 * @return array Interpretações
 */
function getNumerologyInterpretations($fullName, $birthDate, $numerologyData, $api_key, $assistant_id) {
    try {
        // Extrair números
        $lifePathNumber = $numerologyData['lifePathNumber'];
        $destinyNumber = $numerologyData['destinyNumber'];
        $personalYearNumber = $numerologyData['personalYearNumber'];

        // Chamar a API da OpenAI
        $response = callOpenAIAssistant(
            $fullName,
            $birthDate,
            $lifePathNumber,
            $destinyNumber,
            $personalYearNumber,
            $api_key,
            $assistant_id
        );

        // Processar resposta
        $interpretations = parseOpenAIResponse($response);

        // Se alguma interpretação estiver vazia, usar fallback
        foreach ($interpretations as $key => $value) {
            if (empty($value)) {
                $interpretations[$key] = getFallbackInterpretation($key, $numerologyData);
            }
        }

        return $interpretations;
    } catch (Exception $e) {
        // Log do erro
        logOpenAIError($e->getMessage());

        // Usar interpretações de fallback
        return getFallbackInterpretations($numerologyData);
    }
}

/**
 * Chamar o assistente da OpenAI
 *
 * @param string $fullName Nome completo
 * @param string $birthDate Data de nascimento
 * @param int $lifePathNumber Número do caminho de vida
 * @param int $destinyNumber Número de destino
 * @param int $personalYearNumber Número do ano pessoal
 * @param string $api_key Chave da API OpenAI
 * @param string $assistant_id ID do assistente OpenAI
 * @return string Resposta do assistente
 */
function callOpenAIAssistant($fullName, $birthDate, $lifePathNumber, $destinyNumber, $personalYearNumber, $api_key, $assistant_id) {
    // Para fins de teste, vamos usar a API de Chat Completion em vez do assistente
    // Em produção, você deve usar a API de Assistants

    // URL da API
    $url = 'https://api.openai.com/v1/chat/completions';

    // Data atual
    $currentDate = date('Y-m-d');

    // Preparar prompt para o assistente
    $prompt = "Gere uma análise numerológica completa para {$fullName}, nascido(a) em {$birthDate}. A data atual é {$currentDate}.

Dados numerológicos:
- Número do Caminho de Vida: {$lifePathNumber}
- Número de Destino: {$destinyNumber}
- Número do Ano Pessoal: {$personalYearNumber}

Por favor, forneça:

1. **Caminho de Vida**: Explique o significado do Número do Caminho de Vida {$lifePathNumber} em termos de propósito e desafios (máximo 100 palavras).

2. **Talentos e Forças**: Identifique quais talentos e forças naturais a pessoa pode estar subestimando, com base no Caminho de Vida {$lifePathNumber} (máximo 80 palavras).

3. **Número de Destino**: Descreva as grandes lições de vida sugeridas pelo Número de Destino {$destinyNumber} (máximo 100 palavras).

4. **Ano Pessoal**: Explique a energia dominante do Ano Pessoal {$personalYearNumber} a ser focada até o próximo ciclo (máximo 80 palavras).

5. **Desafios**: Com base no Caminho de Vida {$lifePathNumber} e Ano Pessoal {$personalYearNumber}, indique o principal desafio ativo para esta pessoa agora (máximo 100 palavras).

6. **Oportunidades**: Com base no Caminho de Vida {$lifePathNumber} e Ano Pessoal {$personalYearNumber}, indique a principal oportunidade ativa para esta pessoa agora (máximo 100 palavras).

7. **Ritual Diário**: Crie um ritual ou exercício diário simples baseado no Caminho de Vida {$lifePathNumber} para alinhamento com o propósito (máximo 80 palavras).

Forneça respostas concisas, práticas e motivadoras, respeitando os limites de palavras indicados. Divida cada resposta em seções claras.";

    // Dados da requisição
    $data = [
        'model' => 'gpt-4-turbo',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'Você é um especialista em numerologia cósmica, com um tom inspirador, acessível e espiritual. Forneça informações precisas, respeitosas e úteis sobre o significado dos números na vida das pessoas.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.7
    ];

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
        $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Erro desconhecido';
        throw new Exception('Erro na API da OpenAI: ' . $error_message);
    }

    // Verificar se a resposta foi bem-sucedida
    if (!isset($result['choices'][0]['message']['content'])) {
        throw new Exception('Formato de resposta inválido da API da OpenAI');
    }

    // Retornar o conteúdo da resposta
    return $result['choices'][0]['message']['content'];
}

/**
 * Processar a resposta da OpenAI e extrair as interpretações
 *
 * @param string $response Resposta da OpenAI
 * @return array Interpretações extraídas
 */
function parseOpenAIResponse($response) {
    // Inicializar array de interpretações
    $interpretations = [
        'lifePathMeaning' => '',
        'lifePathTalents' => '',
        'destinyMeaning' => '',
        'personalYearMeaning' => '',
        'currentChallenges' => '',
        'currentOpportunities' => '',
        'dailyRitual' => ''
    ];

    // Quebrar a resposta em seções
    $sections = preg_split('/\r?\n\r?\n/', $response);

    // Flag para rastrear a seção atual
    $currentSection = null;

    foreach ($sections as $section) {
        $section = trim($section);

        // Verificar em qual seção estamos
        if (preg_match('/^[\d\.\s]*Caminho de Vida:/i', $section)) {
            $currentSection = 'lifePathMeaning';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Caminho de Vida:\s*/i', '', $section);
        } elseif (preg_match('/^[\d\.\s]*Talentos e Forças:/i', $section)) {
            $currentSection = 'lifePathTalents';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Talentos e Forças:\s*/i', '', $section);
        } elseif (preg_match('/^[\d\.\s]*Número de Destino:/i', $section)) {
            $currentSection = 'destinyMeaning';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Número de Destino:\s*/i', '', $section);
        } elseif (preg_match('/^[\d\.\s]*Ano Pessoal:/i', $section)) {
            $currentSection = 'personalYearMeaning';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Ano Pessoal:\s*/i', '', $section);
        } elseif (preg_match('/^[\d\.\s]*Desafios:/i', $section)) {
            $currentSection = 'currentChallenges';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Desafios:\s*/i', '', $section);
        } elseif (preg_match('/^[\d\.\s]*Oportunidades:/i', $section)) {
            $currentSection = 'currentOpportunities';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Oportunidades:\s*/i', '', $section);
        } elseif (preg_match('/^[\d\.\s]*Ritual Diário:/i', $section)) {
            $currentSection = 'dailyRitual';
            $interpretations[$currentSection] = preg_replace('/^[\d\.\s]*Ritual Diário:\s*/i', '', $section);
        } elseif ($currentSection !== null && !empty($section)) {
            // Adicionar à seção atual se não for um cabeçalho e a seção não estiver vazia
            $interpretations[$currentSection] .= "\n\n" . $section;
        }
    }

    // Limpar cada interpretação
    foreach ($interpretations as $key => $value) {
        $interpretations[$key] = trim($value);
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
