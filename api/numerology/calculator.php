<?php
/**
 * Funções para cálculos numerológicos
 */

/**
 * Calcular números numerológicos
 *
 * @param string $fullName Nome completo
 * @param string $birthDate Data de nascimento
 * @return array Números numerológicos calculados
 */
function calculateNumerology($fullName, $birthDate) {
    // Converter data de nascimento
    $birthDateObj = new DateTime($birthDate);
    $currentYear = date('Y');

    // Calcular número do caminho de vida (soma de todos os dígitos da data de nascimento)
    $day = $birthDateObj->format('d');
    $month = $birthDateObj->format('m');
    $year = $birthDateObj->format('Y');

    $lifePathNumber = calculateLifePathNumber($day, $month, $year);

    // Calcular número de destino (valor numerológico de cada letra do nome)
    $destinyNumber = calculateDestinyNumber($fullName);

    // Calcular ano pessoal (soma do dia e mês de nascimento + ano atual)
    $personalYearNumber = calculatePersonalYearNumber($day, $month, $currentYear);

    return [
        'lifePathNumber' => $lifePathNumber,
        'destinyNumber' => $destinyNumber,
        'personalYearNumber' => $personalYearNumber
    ];
}

/**
 * Calcular número do caminho de vida
 *
 * @param string $day Dia de nascimento
 * @param string $month Mês de nascimento
 * @param string $year Ano de nascimento
 * @return int Número do caminho de vida
 */
function calculateLifePathNumber($day, $month, $year) {
    // Reduzir cada componente da data
    $dayNumber = reduceToSingleDigit($day);
    $monthNumber = reduceToSingleDigit($month);
    $yearNumber = reduceToSingleDigit($year);

    // Somar os componentes reduzidos
    $sum = $dayNumber + $monthNumber + $yearNumber;

    // Reduzir a soma para um único dígito ou número mestre
    return reduceToSingleDigitOrMaster($sum);
}

/**
 * Calcular número de destino
 *
 * @param string $fullName Nome completo
 * @return int Número de destino
 */
function calculateDestinyNumber($fullName) {
    // Converter nome para minúsculas e remover caracteres especiais
    $name = mb_strtolower($fullName, 'UTF-8');
    $name = transliterateToASCII($name);
    $name = preg_replace('/[^a-z ]/', '', $name);

    // Tabela de valores numerológicos para cada letra
    $letterValues = [
        'a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6, 'g' => 7, 'h' => 8, 'i' => 9,
        'j' => 1, 'k' => 2, 'l' => 3, 'm' => 4, 'n' => 5, 'o' => 6, 'p' => 7, 'q' => 8, 'r' => 9,
        's' => 1, 't' => 2, 'u' => 3, 'v' => 4, 'w' => 5, 'x' => 6, 'y' => 7, 'z' => 8
    ];

    // Calcular soma
    $sum = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        if (isset($letterValues[$name[$i]])) {
            $sum += $letterValues[$name[$i]];
        }
    }

    // Reduzir para um único dígito ou número mestre
    return reduceToSingleDigitOrMaster($sum);
}

/**
 * Calcular número do ano pessoal
 *
 * @param string $day Dia de nascimento
 * @param string $month Mês de nascimento
 * @param string $currentYear Ano atual
 * @return int Número do ano pessoal
 */
function calculatePersonalYearNumber($day, $month, $currentYear) {
    // Reduzir dia e mês
    $dayNumber = reduceToSingleDigit($day);
    $monthNumber = reduceToSingleDigit($month);

    // Reduzir ano atual
    $yearNumber = reduceToSingleDigit($currentYear);

    // Somar os componentes reduzidos
    $sum = $dayNumber + $monthNumber + $yearNumber;

    // Reduzir a soma para um único dígito (não usar números mestres para o ano pessoal)
    return reduceToSingleDigit($sum);
}

/**
 * Reduzir um número para um único dígito
 *
 * @param string $number Número a ser reduzido
 * @return int Valor de um único dígito
 */
function reduceToSingleDigit($number) {
    // Remover zeros à esquerda
    $number = ltrim($number, '0');

    // Se o número já for de um único dígito, retorná-lo
    if (strlen($number) === 1) {
        return (int)$number;
    }

    // Somar os dígitos
    $sum = 0;
    for ($i = 0; $i < strlen($number); $i++) {
        $sum += (int)$number[$i];
    }

    // Recursivamente reduzir até obter um único dígito
    return reduceToSingleDigit((string)$sum);
}

/**
 * Reduzir um número para um único dígito ou número mestre
 *
 * @param string $number Número a ser reduzido
 * @return int Valor de um único dígito ou número mestre
 */
function reduceToSingleDigitOrMaster($number) {
    // Remover zeros à esquerda
    $number = ltrim($number, '0');

    // Números mestres não são reduzidos
    $masterNumbers = [11, 22, 33];

    // Se o número já for de um único dígito, retorná-lo
    if (strlen($number) === 1) {
        return (int)$number;
    }

    // Se for um número mestre, retorná-lo
    if (strlen($number) === 2 && in_array((int)$number, $masterNumbers)) {
        return (int)$number;
    }

    // Somar os dígitos
    $sum = 0;
    for ($i = 0; $i < strlen($number); $i++) {
        $sum += (int)$number[$i];
    }

    // Verificar se a soma é um número mestre
    if (in_array($sum, $masterNumbers)) {
        return $sum;
    }

    // Recursivamente reduzir até obter um único dígito ou número mestre
    return reduceToSingleDigitOrMaster((string)$sum);
}

/**
 * Transliterar caracteres especiais para ASCII
 *
 * @param string $text Texto a ser transliterado
 * @return string Texto transliterado
 */
function transliterateToASCII($text) {
    $transliterationTable = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c', 'ñ' => 'n'
    ];

    return strtr($text, $transliterationTable);
}