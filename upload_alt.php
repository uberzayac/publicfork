<?php
// upload_alt.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

function writeLog($message) {
    $logFile = 'bitrix_upload.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("=== Начало обработки запроса (альтернативный метод) ===");

try {
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    
    if (!$data) {
        throw new Exception('Не удалось декодировать JSON');
    }
    
    if (empty($data['image'])) {
        throw new Exception('Отсутствует изображение');
    }
    
    if (empty($data['deal_id'])) {
        throw new Exception('Не указан ID сделки');
    }
    
    // Декодируем изображение
    $img = $data['image'];
    $img = str_replace('data:image/png;base64,', '', $img);
    $img = str_replace(' ', '+', $img);
    $img = base64_decode($img);
    
    if (!$img) {
        throw new Exception('Не удалось декодировать изображение');
    }
    
    // Создаем временный файл
    $tempDir = sys_get_temp_dir();
    $fileName = 'screenshot_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.png';
    $filePath = $tempDir . DIRECTORY_SEPARATOR . $fileName;
    
    file_put_contents($filePath, $img);
    
    // === СПЕЦИАЛЬНЫЙ МЕТОД ДЛЯ ФАЙЛОВ ===
    $webhookUrl = "https://gvprint.bitrix24.ru/rest/10/33sjwnbap09wrl0j/";
    $fileUploadUrl = $webhookUrl . "crm.item.files.json";
    
    // Формат для crm.item.files
    $postData = [
        'entityTypeId' => 2,  // Сделка
        'id' => (int)$data['deal_id'],
        'fieldName' => 'UF_CRM_1770816063',
        'fileData' => [
            $fileName,
            base64_encode(file_get_contents($filePath))
        ]
    ];
    
    writeLog("Отправка через crm.item.files");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fileUploadUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    // Удаляем временный файл
    if (file_exists($filePath)) {
        unlink($filePath);
    }
    
    writeLog("HTTP код ответа: $httpCode");
    writeLog("Ответ от Битрикс24: " . $response);
    
    if ($curlError) {
        throw new Exception('Ошибка CURL: ' . $curlError);
    }
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['error'])) {
        throw new Exception('Ошибка Битрикс24: ' . ($responseData['error_description'] ?? $responseData['error']));
    }
    
    echo json_encode([
        'success' => true,
        'result' => $responseData,
        'message' => 'Файл успешно загружен в сделку #' . $data['deal_id']
    ]);
    
    writeLog("✅ Успешное завершение");
    
} catch (Exception $e) {
    writeLog("❌ Ошибка: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

writeLog("=== Конец обработки запроса ===\n");
?>