<?php
// debug_bitrix_field.php
header('Content-Type: text/html; charset=utf-8');

$webhookUrl = "https://gvprint.bitrix24.ru/rest/10/33sjwnbap09wrl0j/";
$dealId = 402;

echo "<h1>Диагностика поля UF_CRM_1770816063 в сделке #$dealId</h1>";

// 1. Получаем сделку
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.deal.get.json");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['id' => $dealId]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
curl_close($ch);

$deal = json_decode($response, true);
$files = $deal['result']['UF_CRM_1770816063'] ?? [];

echo "<h2>1. Сырые данные из Битрикс24:</h2>";
echo "<pre>";
print_r($files);
echo "</pre>";

echo "<h2>2. Анализ формата:</h2>";
if (empty($files)) {
    echo "<p style='color:blue'>Поле пустое (нет файлов)</p>";
} else {
    foreach ($files as $index => $file) {
        echo "<h3>Файл #" . ($index + 1) . "</h3>";
        echo "<pre>";
        echo "Тип: " . gettype($file) . "\n";
        if (is_array($file)) {
            echo "Ключи: " . implode(", ", array_keys($file)) . "\n";
            echo "JSON: " . json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
        echo "</pre>";
    }
}

echo "<h2>3. Тест отправки файла с сохранением</h2>";
echo "<p>Скопируйте этот код для теста:</p>";
echo "<pre>";
$testData = [
    'id' => $dealId,
    'fields' => [
        'UF_CRM_1770816063' => [
            ['fileData' => ['test.txt', base64_encode('test content')]]
        ]
    ]
];
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo "</pre>";
?>