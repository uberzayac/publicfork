<?php
// test_bitrix_upload.php
$webhookUrl = "https://gvprint.bitrix24.ru/rest/10/33sjwnbap09wrl0j/crm.deal.update.json";

// Создаем тестовый файл
$testContent = "Test file content";
$fileName = "test_" . time() . ".txt";
$fileBase64 = base64_encode($testContent);

$sendData = [
    "id" => 402, // ID вашей сделки
    "fields" => [
        "UF_CRM_1770816063" => [
            "fileData" => [
                $fileName,
                $fileBase64
            ]
        ]
    ]
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $webhookUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sendData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
curl_close($ch);

echo "Response: " . $response;
?>