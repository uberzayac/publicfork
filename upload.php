<?php
// Включаем отображение ошибок ТОЛЬКО для диагностики
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Отключаем вывод ошибок в браузер, чтобы они не ломали JSON
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Обработка preflight запросов CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

function writeLog($message) {
    $logFile = __DIR__ . '/bitrix_upload.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

// Перехватываем все ошибки и предупреждения
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    writeLog("PHP Ошибка: [$errno] $errstr в $errfile на строке $errline");
    return true;
});

// Регистрируем функцию для обработки фатальных ошибок
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        writeLog("Фатальная ошибка: " . json_encode($error));
        
        ob_clean();
        echo json_encode([
            'success' => false,
            'error' => 'Внутренняя ошибка сервера: ' . $error['message']
        ]);
    }
});

writeLog("=== Начало обработки запроса ===");
writeLog("Метод: " . $_SERVER['REQUEST_METHOD']);
writeLog("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'не указан'));

try {
    // Получаем входящие данные
    $json = file_get_contents("php://input");
    if (!$json) {
        throw new Exception('Пустой запрос');
    }
    
    writeLog("Получены данные: " . substr($json, 0, 200) . "...");
    
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception('Не удалось декодировать JSON: ' . json_last_error_msg());
    }
    
    writeLog("Данные получены: deal_id=" . ($data['deal_id'] ?? 'не указан'));
    
    // Проверяем наличие обязательных полей
    if (empty($data['image'])) {
        throw new Exception('Отсутствует изображение');
    }
    
    if (empty($data['deal_id'])) {
        throw new Exception('Не указан ID сделки');
    }
    
    $dealId = (int)$data['deal_id'];
    $entityTypeId = 2; // 2 = Сделка
    
    // Декодируем изображение
    $img = $data['image'];
    
    // Удаляем префикс data:image/png;base64,
    if (strpos($img, 'data:image/png;base64,') === 0) {
        $img = substr($img, strlen('data:image/png;base64,'));
    }
    
    // Заменяем пробелы на + для корректного декодирования
    $img = str_replace(' ', '+', $img);
    $img = base64_decode($img, true);
    
    if ($img === false) {
        throw new Exception('Не удалось декодировать base64 изображение');
    }
    
    writeLog("Изображение декодировано, размер: " . strlen($img) . " байт");
    
    // Создаем временную директорию, если её нет
    $tempDir = __DIR__ . '/temp';
    if (!file_exists($tempDir)) {
        if (!mkdir($tempDir, 0777, true)) {
            throw new Exception('Не удалось создать временную директорию');
        }
        writeLog("Создана временная директория: $tempDir");
    }
    
    // Проверяем права на запись
    if (!is_writable($tempDir)) {
        throw new Exception('Нет прав на запись во временную директорию: ' . $tempDir);
    }
    
    // Создаем временный файл
    $fileName = 'screenshot_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.png';
    $filePath = $tempDir . '/' . $fileName;
    
    writeLog("Создание временного файла: $filePath");
    
    if (file_put_contents($filePath, $img) === false) {
        throw new Exception('Не удалось сохранить временный файл (проверьте права на запись)');
    }
    
    // Проверяем размер файла
    $fileSize = filesize($filePath);
    writeLog("Размер файла: $fileSize байт");
    
    if ($fileSize === 0) {
        throw new Exception('Сохраненный файл пустой');
    }
    
    // ========== ПОЛУЧАЕМ ID СУЩЕСТВУЮЩИХ ФАЙЛОВ ЧЕРЕЗ crm.item.list ==========
    $webhookUrl = "https://gvprint.bitrix24.ru/rest/10/33sjwnbap09wrl0j/";
    
    // Имена полей в camelCase
    $fileFieldName = 'ufCrm_1770816063'; // Поле для файлов
    $idsFieldName = 'ufCrm_1771236840';   // Поле для хранения ID
    
    // Формируем запрос как в примере
    $getData = [
        'entityTypeId' => $entityTypeId,
        'select' => [
            $fileFieldName // Только поле с файлами
        ],
        'filter' => [
            'id' => $dealId
        ]
    ];
    
    writeLog("Запрос ID существующих файлов через crm.item.list");
    writeLog("Параметры: " . json_encode($getData, JSON_UNESCAPED_UNICODE));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.list.json");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($getData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script)');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Ошибка CURL при получении файлов: ' . $curlError);
    }
    
    if ($httpCode != 200) {
        writeLog("HTTP код ответа при получении файлов: $httpCode");
        writeLog("Ответ: " . $response);
        throw new Exception("Не удалось получить файлы, HTTP код: $httpCode");
    }
    
    $fileInfo = json_decode($response, true);
    
    if (isset($fileInfo['error'])) {
        throw new Exception('Ошибка получения файлов: ' . ($fileInfo['error_description'] ?? $fileInfo['error']));
    }
    
    writeLog("Ответ от Битрикс24: " . json_encode($fileInfo, JSON_UNESCAPED_UNICODE));
    
    // ========== ИЗВЛЕКАЕМ ID СУЩЕСТВУЮЩИХ ФАЙЛОВ ==========
    $existingFileIds = [];
    
    if (isset($fileInfo['result']['items'][0][$fileFieldName])) {
        $files = $fileInfo['result']['items'][0][$fileFieldName];
        
        foreach ($files as $file) {
            if (isset($file['id'])) {
                $existingFileIds[] = (int)$file['id'];
                writeLog("Найден существующий файл ID: " . $file['id']);
            }
        }
    }
    
    writeLog("Всего найдено ID файлов: " . count($existingFileIds));
    
    // ========== ФОРМИРУЕМ МАССИВ ДЛЯ ОБНОВЛЕНИЯ ==========
    $filesToUpdate = [];
    
    // Добавляем существующие файлы (в формате ['id' => ID])
    foreach ($existingFileIds as $fileId) {
        $filesToUpdate[] = ['id' => $fileId];
    }
    
    // Добавляем новый файл
    $newFileContent = base64_encode($img);
    $filesToUpdate[] = [$fileName, $newFileContent];
    writeLog("Добавлен новый файл: $fileName");
    
    writeLog("Всего элементов для отправки: " . count($filesToUpdate));
    
    // ========== ОТПРАВЛЯЕМ ОБНОВЛЕНИЕ ==========
    $updateData = [
        'entityTypeId' => $entityTypeId,
        'id' => $dealId,
        'fields' => [
            $fileFieldName => $filesToUpdate
        ]
    ];
    
    writeLog("Отправка обновления сделки #$dealId через crm.item.update");
    writeLog("Данные: " . json_encode($updateData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.update.json");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($updateData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; PHP Script)');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    
    curl_close($ch);
    
    if ($curlError) {
        throw new Exception('Ошибка CURL при обновлении: ' . $curlError);
    }
    
    if ($httpCode != 200) {
        writeLog("HTTP код ответа при обновлении: $httpCode");
        writeLog("Ответ: " . $response);
        throw new Exception("Не удалось обновить сделку, HTTP код: $httpCode");
    }
    
    writeLog("Ответ от Битрикс24: " . $response);
    
    $responseData = json_decode($response, true);
    
    if (isset($responseData['error'])) {
        throw new Exception('Ошибка Битрикс24: ' . ($responseData['error_description'] ?? $responseData['error']));
    }
    
    // ========== ПОЛУЧАЕМ ID НОВОГО ФАЙЛА ==========
    // После загрузки, получаем обновленные данные
    writeLog("Получение обновленных данных для определения ID нового файла");
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.list.json");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($getData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $updatedResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $newFileId = null;
    
    if ($httpCode == 200) {
        $updatedInfo = json_decode($updatedResponse, true);
        
        if (isset($updatedInfo['result']['items'][0][$fileFieldName])) {
            $updatedFiles = $updatedInfo['result']['items'][0][$fileFieldName];
            
            // Ищем ID нового файла (которого не было в старом списке)
            foreach ($updatedFiles as $file) {
                if (isset($file['id']) && !in_array($file['id'], $existingFileIds)) {
                    $newFileId = $file['id'];
                    writeLog("Найден ID нового файла: " . $newFileId);
                    break;
                }
            }
        }
    } else {
        writeLog("Не удалось получить обновленные данные, HTTP код: $httpCode");
    }
    
    // ========== ОБНОВЛЯЕМ ПОЛЕ С ID ==========
    if ($newFileId) {
        // Получаем текущее значение поля с ID
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.list.json");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'entityTypeId' => $entityTypeId,
            'select' => [$idsFieldName],
            'filter' => ['id' => $dealId]
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $idsResponse = curl_exec($ch);
        curl_close($ch);
        
        $idsInfo = json_decode($idsResponse, true);
        $existingIdsString = $idsInfo['result']['items'][0][$idsFieldName] ?? '';
        
        writeLog("Текущее значение $idsFieldName: '" . $existingIdsString . "'");
        
        // Разбираем существующие ID
        $existingIds = [];
        if (!empty($existingIdsString) && $existingIdsString !== 'Array' && $existingIdsString !== 'array') {
            $existingIds = array_map('trim', explode(',', $existingIdsString));
            $existingIds = array_filter($existingIds, function($value) {
                return $value !== '' && is_numeric($value);
            });
            $existingIds = array_map('intval', $existingIds);
        }
        
        // Добавляем новый ID
        if (!in_array($newFileId, $existingIds)) {
            $existingIds[] = $newFileId;
        }
        
        sort($existingIds);
        
        $newIdsString = implode(', ', $existingIds);
        
        writeLog("Новое значение $idsFieldName: " . $newIdsString);
        
        $idFieldData = [
            'entityTypeId' => $entityTypeId,
            'id' => $dealId,
            'fields' => [
                $idsFieldName => $newIdsString
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl . "crm.item.update.json");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($idFieldData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $idFieldResponse = curl_exec($ch);
        $idFieldHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($idFieldHttpCode == 200) {
            writeLog("✅ ID файлов успешно сохранены в поле $idsFieldName");
        } else {
            writeLog("⚠️ Не удалось сохранить ID файлов, HTTP код: $idFieldHttpCode");
            writeLog("Ответ: " . $idFieldResponse);
        }
    } else {
        writeLog("⚠️ Не удалось определить ID нового файла");
    }
    
    // Удаляем временный файл
    if (file_exists($filePath)) {
        unlink($filePath);
        writeLog("Временный файл удален");
    }
    
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'result' => $responseData,
        'message' => "Файл успешно добавлен в сделку #$dealId",
        'file_count' => count($filesToUpdate),
        'new_file_id' => $newFileId,
        'existing_ids' => $existingFileIds
    ]);
    
    writeLog("✅ Успешное завершение");
    
} catch (Exception $e) {
    writeLog("❌ Ошибка: " . $e->getMessage());
    writeLog("Файл: " . $e->getFile() . " строка " . $e->getLine());
    
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
        writeLog("Временный файл удален после ошибки");
    }
    
    ob_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

writeLog("=== Конец обработки запроса ===\n");
?>