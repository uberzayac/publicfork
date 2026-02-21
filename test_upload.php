<?php
// test_upload.php
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Диагностика загрузки файлов</h1>";

// Проверяем версию PHP
echo "<h2>1. Информация о сервере</h2>";
echo "PHP версия: " . phpversion() . "<br>";
echo "Максимальный размер POST: " . ini_get('post_max_size') . "<br>";
echo "Максимальный размер файла: " . ini_get('upload_max_filesize') . "<br>";
echo "Максимальное время выполнения: " . ini_get('max_execution_time') . " сек<br>";

// Проверяем директорию temp
$tempDir = __DIR__ . '/temp';
echo "<h2>2. Проверка временной директории</h2>";
echo "Путь: $tempDir<br>";

if (!file_exists($tempDir)) {
    if (mkdir($tempDir, 0777, true)) {
        echo "✅ Директория создана<br>";
    } else {
        echo "❌ Не удалось создать директорию<br>";
    }
} else {
    echo "✅ Директория существует<br>";
    echo "Права: " . substr(sprintf('%o', fileperms($tempDir)), -4) . "<br>";
    echo "Доступна для записи: " . (is_writable($tempDir) ? '✅ Да' : '❌ Нет') . "<br>";
}

// Проверяем соединение с Битрикс24
echo "<h2>3. Проверка соединения с Битрикс24</h2>";
$webhookUrl = "https://gvprint.bitrix24.ru/rest/10/33sjwnbap09wrl0j/";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.deal.list.json");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['select' => ['id', 'title'], 'limit' => 1]));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

if ($curlError) {
    echo "❌ Ошибка CURL: $curlError<br>";
} else {
    echo "HTTP код: $httpCode<br>";
    if ($httpCode == 200) {
        echo "✅ Соединение с Битрикс24 работает<br>";
        $data = json_decode($response, true);
        if (isset($data['result'])) {
            echo "✅ API отвечает корректно<br>";
        }
    } else {
        echo "❌ Ошибка соединения<br>";
        echo "Ответ: " . htmlspecialchars($response) . "<br>";
    }
}

// Проверяем лог-файл
echo "<h2>4. Проверка лог-файла</h2>";
$logFile = __DIR__ . '/bitrix_upload.log';
echo "Путь к логу: $logFile<br>";

if (file_exists($logFile)) {
    echo "✅ Лог-файл существует<br>";
    echo "Размер: " . filesize($logFile) . " байт<br>";
    echo "Права: " . substr(sprintf('%o', fileperms($logFile)), -4) . "<br>";
    
    // Показываем последние 20 строк лога
    echo "<h3>Последние записи в логе:</h3>";
    $lines = file($logFile);
    $lastLines = array_slice($lines, -20);
    echo "<pre>";
    foreach ($lastLines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>";
} else {
    echo "❌ Лог-файл не найден<br>";
    
    // Пробуем создать лог-файл
    if (file_put_contents($logFile, "[TEST] " . date('Y-m-d H:i:s') . " Тестовый лог\n")) {
        echo "✅ Лог-файл создан<br>";
    } else {
        echo "❌ Не удалось создать лог-файл<br>";
    }
}
?>