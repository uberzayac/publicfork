<?php
// test_webhook.php
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Тестирование вебхука Битрикс24</h1>";

$webhookUrl = "https://gvprint.bitrix24.ru/rest/10/33sjwnbap09wrl0j/";
$dealId = 402; // ID вашей тестовой сделки

// Тест 1: Получение сделки
echo "<h2>1. Проверка получения сделки #$dealId</h2>";

$getData = [
    'entityTypeId' => 2,
    'select' => ['ID', 'TITLE', 'UF_CRM_1770816063'],
    'filter' => ['id' => $dealId]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.list.json");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($getData));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

echo "HTTP код: $httpCode<br>";
if ($curlError) {
    echo "Ошибка CURL: $curlError<br>";
} else {
    echo "Ответ: <pre>" . htmlspecialchars(json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
}

// Тест 2: Попытка обновления с тестовым файлом
echo "<h2>2. Тест обновления с тестовым файлом</h2>";

// Создаем тестовый файл
$testContent = "Тестовый файл для проверки " . date('Y-m-d H:i:s');
$testFileName = "test_" . date('Y-m-d_H-i-s') . ".txt";
$testFileBase64 = base64_encode($testContent);

$updateData = [
    'entityTypeId' => 2,
    'id' => $dealId,
    'fields' => [
        'UF_CRM_1770816063' => [
            [$testFileName, $testFileBase64] // Просто добавляем файл без сохранения старых
        ]
    ]
];

echo "Отправка данных: <pre>" . htmlspecialchars(json_encode($updateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.update.json");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($updateData));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

echo "HTTP код: $httpCode<br>";
if ($curlError) {
    echo "Ошибка CURL: $curlError<br>";
} else {
    echo "Ответ: <pre>" . htmlspecialchars(json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
}

// Тест 3: Проверка прав вебхука
echo "<h2>3. Проверка прав вебхука</h2>";
echo "Попробуйте открыть в браузере: <a href='$webhookUrl' target='_blank'>$webhookUrl</a><br>";
echo "Должен быть ответ с описанием доступных методов.<br>";
?>