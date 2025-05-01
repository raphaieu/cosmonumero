<?php
/**
 * Funções para interagir com o banco de dados SQLite
 */

/**
 * Obter conexão com o banco de dados
 *
 * @return SQLite3 Conexão com o banco de dados
 */
function getDatabase() {
    $dbFile = __DIR__ . '/../../database/numerology.db';
    $dbDir = dirname($dbFile);

    // Criar diretório se não existir
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0755, true);
    }

    // Criar conexão
    $db = new SQLite3($dbFile);

    // Criar tabelas
    createTables($db);

    return $db;
}

/**
 * Criar tabelas no banco de dados
 *
 * @param SQLite3 $db Conexão com o banco de dados
 * @return void
 */
function createTables($db) {
    // Tabela de transações
    $db->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        external_reference TEXT NOT NULL,
        preference_id TEXT NOT NULL,
        payment_id TEXT,
        customer_name TEXT NOT NULL,
        birth_date TEXT NOT NULL,
        amount REAL NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        created_at TEXT NOT NULL,
        updated_at TEXT
    )");

    // Tabela de análises numerológicas
    $db->exec("CREATE TABLE IF NOT EXISTS numerology_results (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id INTEGER NOT NULL,
        life_path_number TEXT NOT NULL,
        destiny_number TEXT NOT NULL,
        personal_year_number TEXT NOT NULL,
        life_path_meaning TEXT NOT NULL,
        life_path_talents TEXT NOT NULL,
        destiny_meaning TEXT NOT NULL,
        personal_year_meaning TEXT NOT NULL,
        current_challenges TEXT NOT NULL,
        current_opportunities TEXT NOT NULL,
        daily_ritual TEXT NOT NULL,
        created_at TEXT NOT NULL,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id)
    )");

    // Tabela de contatos
    $db->exec("CREATE TABLE IF NOT EXISTS contacts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        transaction_id INTEGER NOT NULL,
        email TEXT NOT NULL,
        phone TEXT,
        created_at TEXT NOT NULL,
        FOREIGN KEY (transaction_id) REFERENCES transactions(id)
    )");

    // Índices
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_external_reference ON transactions(external_reference)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_transactions_payment_id ON transactions(payment_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_contacts_email ON contacts(email)");
}

/**
 * Salvar transação no banco de dados
 *
 * @param array $data Dados da transação
 * @return int ID da transação
 */
function saveTransaction($data) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("INSERT INTO transactions 
            (external_reference, preference_id, customer_name, birth_date, amount, status, created_at) 
            VALUES 
            (:external_reference, :preference_id, :customer_name, :birth_date, :amount, :status, :created_at)");

        // Vincular parâmetros
        $stmt->bindValue(':external_reference', $data['external_reference'], SQLITE3_TEXT);
        $stmt->bindValue(':preference_id', $data['preference_id'], SQLITE3_TEXT);
        $stmt->bindValue(':customer_name', $data['customer_name'], SQLITE3_TEXT);
        $stmt->bindValue(':birth_date', $data['birth_date'], SQLITE3_TEXT);
        $stmt->bindValue(':amount', $data['amount'], SQLITE3_FLOAT);
        $stmt->bindValue(':status', $data['status'] ?? 'pending', SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Obter ID da transação
        $transaction_id = $db->lastInsertRowID();

        // Fechar banco de dados
        $db->close();

        return $transaction_id;
    } catch (Exception $e) {
        // Log de erro
        logDatabaseError('Erro ao salvar transação: ' . $e->getMessage());

        // Em caso de erro, salvar em arquivo
        $file = __DIR__ . '/../../temp/' . $data['external_reference'] . '.json';
        file_put_contents($file, json_encode($data));

        return 0;
    }
}

/**
 * Atualizar status de transação
 *
 * @param string $external_reference Referência externa
 * @param string $payment_id ID do pagamento
 * @param string $status Status do pagamento
 * @return bool Sucesso da operação
 */
function updateTransactionStatus($external_reference, $payment_id, $status) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("UPDATE transactions 
            SET payment_id = :payment_id, status = :status, updated_at = :updated_at 
            WHERE external_reference = :external_reference");

        // Vincular parâmetros
        $stmt->bindValue(':payment_id', $payment_id, SQLITE3_TEXT);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':updated_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
        $stmt->bindValue(':external_reference', $external_reference, SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Verificar resultado
        $changes = $db->changes();

        // Fechar banco de dados
        $db->close();

        return $changes > 0;
    } catch (Exception $e) {
        // Log de erro
        logError('Erro ao atualizar status de transação: ' . $e->getMessage());

        // Em caso de erro, salvar em arquivo
        $file = __DIR__ . '/../../temp/' . $external_reference . '_status.json';
        $data = [
            'external_reference' => $external_reference,
            'payment_id' => $payment_id,
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($file, json_encode($data));

        return false;
    }
}

/**
 * Salvar resultados de análise numerológica
 *
 * @param int $transaction_id ID da transação
 * @param array $results Resultados da análise
 * @return int ID dos resultados
 */
function saveNumerologyResults($transaction_id, $results) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("INSERT INTO numerology_results 
            (transaction_id, life_path_number, destiny_number, personal_year_number, 
            life_path_meaning, life_path_talents, destiny_meaning, personal_year_meaning, 
            current_challenges, current_opportunities, daily_ritual, created_at) 
            VALUES 
            (:transaction_id, :life_path_number, :destiny_number, :personal_year_number, 
            :life_path_meaning, :life_path_talents, :destiny_meaning, :personal_year_meaning, 
            :current_challenges, :current_opportunities, :daily_ritual, :created_at)");

        // Vincular parâmetros
        $stmt->bindValue(':transaction_id', $transaction_id, SQLITE3_INTEGER);
        $stmt->bindValue(':life_path_number', $results['lifePathNumber'], SQLITE3_TEXT);
        $stmt->bindValue(':destiny_number', $results['destinyNumber'], SQLITE3_TEXT);
        $stmt->bindValue(':personal_year_number', $results['personalYearNumber'], SQLITE3_TEXT);
        $stmt->bindValue(':life_path_meaning', $results['lifePathMeaning'], SQLITE3_TEXT);
        $stmt->bindValue(':life_path_talents', $results['lifePathTalents'], SQLITE3_TEXT);
        $stmt->bindValue(':destiny_meaning', $results['destinyMeaning'], SQLITE3_TEXT);
        $stmt->bindValue(':personal_year_meaning', $results['personalYearMeaning'], SQLITE3_TEXT);
        $stmt->bindValue(':current_challenges', $results['currentChallenges'], SQLITE3_TEXT);
        $stmt->bindValue(':current_opportunities', $results['currentOpportunities'], SQLITE3_TEXT);
        $stmt->bindValue(':daily_ritual', $results['dailyRitual'], SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Obter ID dos resultados
        $results_id = $db->lastInsertRowID();

        // Fechar banco de dados
        $db->close();

        return $results_id;
    } catch (Exception $e) {
        // Log de erro
        logError('Erro ao salvar resultados: ' . $e->getMessage());

        // Em caso de erro, salvar em arquivo
        $file = __DIR__ . '/../../temp/results_' . $transaction_id . '.json';
        file_put_contents($file, json_encode($results));

        return 0;
    }
}

/**
 * Salvar informações de contato
 *
 * @param int $transaction_id ID da transação
 * @param string $email E-mail
 * @param string $phone Telefone
 * @return int ID do contato
 */
function saveContact($transaction_id, $email, $phone = null) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("INSERT INTO contacts 
            (transaction_id, email, phone, created_at) 
            VALUES 
            (:transaction_id, :email, :phone, :created_at)");

        // Vincular parâmetros
        $stmt->bindValue(':transaction_id', $transaction_id, SQLITE3_INTEGER);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':phone', $phone, SQLITE3_TEXT);
        $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Obter ID do contato
        $contact_id = $db->lastInsertRowID();

        // Fechar banco de dados
        $db->close();

        return $contact_id;
    } catch (Exception $e) {
        // Log de erro
        logDatabaseError('Erro ao salvar contato: ' . $e->getMessage());

        // Em caso de erro, salvar em arquivo
        $file = __DIR__ . '/../../temp/contact_' . $transaction_id . '.json';
        $data = [
            'transaction_id' => $transaction_id,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s')
        ];
        file_put_contents($file, json_encode($data));

        return 0;
    }
}

/**
 * Buscar transação por referência externa
 *
 * @param string $external_reference Referência externa
 * @return array|null Dados da transação
 */
function getTransactionByExternalReference($external_reference) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("SELECT * FROM transactions WHERE external_reference = :external_reference");

        // Vincular parâmetros
        $stmt->bindValue(':external_reference', $external_reference, SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Obter dados
        $transaction = $result->fetchArray(SQLITE3_ASSOC);

        // Fechar banco de dados
        $db->close();

        return $transaction ?: null;
    } catch (Exception $e) {
        // Log de erro
        logDatabaseError('Erro ao buscar transação: ' . $e->getMessage());

        // Em caso de erro, tentar buscar em arquivo
        $file = __DIR__ . '/../../temp/' . $external_reference . '.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }

        return null;
    }
}

/**
 * Buscar transação por ID de pagamento
 *
 * @param string $payment_id ID do pagamento
 * @return array|null Dados da transação
 */
function getTransactionByPaymentId($payment_id) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("SELECT * FROM transactions WHERE payment_id = :payment_id");

        // Vincular parâmetros
        $stmt->bindValue(':payment_id', $payment_id, SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Obter dados
        $transaction = $result->fetchArray(SQLITE3_ASSOC);

        // Fechar banco de dados
        $db->close();

        return $transaction ?: null;
    } catch (Exception $e) {
        // Log de erro
        logDatabaseError('Erro ao buscar transação: ' . $e->getMessage());

        // Em caso de erro, tentar buscar em arquivo
        $files = glob(__DIR__ . '/../../temp/*_status.json');
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['payment_id']) && $data['payment_id'] === $payment_id) {
                return $data;
            }
        }

        return null;
    }
}

/**
 * Buscar resultados de análise numerológica por ID de transação
 *
 * @param int $transaction_id ID da transação
 * @return array|null Resultados da análise
 */
function getNumerologyResultsByTransactionId($transaction_id) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("SELECT * FROM numerology_results WHERE transaction_id = :transaction_id");

        // Vincular parâmetros
        $stmt->bindValue(':transaction_id', $transaction_id, SQLITE3_INTEGER);

        // Executar consulta
        $result = $stmt->execute();

        // Obter dados
        $numerology_results = $result->fetchArray(SQLITE3_ASSOC);

        // Fechar banco de dados
        $db->close();

        return $numerology_results ?: null;
    } catch (Exception $e) {
        // Log de erro
        logDatabaseError('Erro ao buscar resultados: ' . $e->getMessage());

        // Em caso de erro, tentar buscar em arquivo
        $file = __DIR__ . '/../../temp/results_' . $transaction_id . '.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }

        return null;
    }
}

/**
 * Verificar se um e-mail já está cadastrado
 *
 * @param string $email E-mail
 * @return bool True se o e-mail já existe, false caso contrário
 */
function emailExists($email) {
    try {
        $db = getDatabase();

        // Preparar consulta
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM contacts WHERE email = :email");

        // Vincular parâmetros
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);

        // Executar consulta
        $result = $stmt->execute();

        // Obter resultado
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];

        // Fechar banco de dados
        $db->close();

        return $count > 0;
    } catch (Exception $e) {
        // Log de erro
        logDatabaseError('Erro ao verificar e-mail: ' . $e->getMessage());

        // Em caso de erro, retornar false
        return false;
    }
}

/**
 * Registrar erro no log
 *
 * @param string $message Mensagem de erro
 * @return void
 */
function logDatabaseError($message) {
    $logDir = __DIR__ . '/../../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/database_error.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}
