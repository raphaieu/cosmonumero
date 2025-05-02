<?php
// Configurações de debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Teste da API Numerologia</h1>";

// Verificar versão do PHP
echo "<p>Versão do PHP: " . phpversion() . "</p>";

// Verificar extensões
$required_extensions = ['curl', 'json', 'pdo_sqlite', 'sqlite3', 'mbstring'];
echo "<h2>Extensões requeridas:</h2>";
echo "<ul>";
foreach ($required_extensions as $ext) {
    $loaded = extension_loaded($ext);
    echo "<li>" . $ext . ": " . ($loaded ? "✅ Carregada" : "❌ Não carregada") . "</li>";
}
echo "</ul>";

// Testar permissões de diretórios
$directories = [
    'logs' => './logs',
    'temp' => './temp',
    'pdfs' => './pdfs',
    'database' => './database'
];

echo "<h2>Permissões de diretórios:</h2>";
echo "<ul>";
foreach ($directories as $name => $dir) {
    $writable = is_writable($dir);
    echo "<li>" . $name . ": " . ($writable ? "✅ Gravável" : "❌ Não gravável") . "</li>";
}
echo "</ul>";

// Testar chamada à API
echo "<h2>Teste de API:</h2>";
try {
    $data = array(
        'action' => 'test',
        'testData' => 'Este é um teste'
    );
    
    $ch = curl_init('https://ckao.in/cosmonumero/api/api.php');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    
    echo "<p>Código HTTP: " . $info['http_code'] . "</p>";
    
    if ($error) {
        echo "<p>Erro cURL: " . $error . "</p>";
    }
    
    echo "<p>Resposta: <pre>" . htmlspecialchars($response) . "</pre></p>";
    
    curl_close($ch);
} catch (Exception $e) {
    echo "<p>Exceção: " . $e->getMessage() . "</p>";
}

// Adicionar código de teste
echo '<h2>Adicionar ao api.php:</h2>';
echo '<pre>
case \'test\':
    echo json_encode([
        \'success\' => true,
        \'message\' => \'API funcionando corretamente\',
        \'timestamp\' => date(\'Y-m-d H:i:s\'),
        \'php_version\' => phpversion(),
        \'extensions\' => get_loaded_extensions()
    ]);
    exit;
    break;
</pre>';
