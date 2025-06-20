<?php
header('Content-Type: application/json');

function json_response($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

// Hata günlüğü dosyası
$log_file = 'zimbra_soap_errors.log';
function log_error($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Geçersiz istek.');
}

// reCAPTCHA doğrulaması
$recaptcha_secret = 'secret key buraya gelecek';
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

if (!$recaptcha_response) {
    json_response(false, 'reCAPTCHA doğrulaması yapılmadı.');
}

$verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
$captcha_success = json_decode($verify);

if (!$captcha_success->success) {
    $error = $captcha_success->error_codes ?? 'Bilinmeyen hata';
    log_error("reCAPTCHA doğrulama hatası: " . json_encode($error));
    json_response(false, 'reCAPTCHA doğrulaması başarısız: ' . json_encode($error));
}

// Form verileri
$email = trim($_POST['mail'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($email) || empty($new_password) || empty($confirm_password)) {
    json_response(false, 'Email, yeni parola ve parola onayı alanları zorunludur.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Geçerli bir email adresi giriniz.');
}

if ($new_password !== $confirm_password) {
    json_response(false, 'Yeni parolalar eşleşmiyor.');
}

// Zimbra SOAP ayarları
$zimbra_server = 'https://siteniz.com:7071/service/admin/soap';
$admin_username = 'admin@siteniz.com';
$admin_password = 'sifreniz';

// cURL yardımcı fonksiyonu (JSON SOAP için)
function send_soap_request($url, $soap_request) {
    global $log_file;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($soap_request));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json; charset=utf-8',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Üretimde true yapın
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Üretimde true yapın
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = 'cURL hatası: ' . curl_error($ch);
        log_error($error . " | URL: $url | İstek: " . json_encode($soap_request));
        curl_close($ch);
        return ['success' => false, 'error' => $error];
    }
    curl_close($ch);
    log_error("SOAP isteği: $url | Yanıt: " . substr($response, 0, 1000)); // İlk 1000 karakteri logla
    return ['success' => true, 'response' => $response];
}

// Admin token alma (Admin AuthRequest)
$admin_soap_request = [
    'Body' => [
        'AuthRequest' => [
            'name' => $admin_username,
            'password' => $admin_password,
            '_jsns' => 'urn:zimbraAdmin'
        ]
    ],
    '_jsns' => 'urn:zimbraSoap'
];

$admin_result = send_soap_request($zimbra_server, $admin_soap_request);
if (!$admin_result['success']) {
    json_response(false, 'Admin SOAP isteği başarısız: ' . $admin_result['error']);
}

$admin_response = json_decode($admin_result['response'], true);
if (!$admin_response || !isset($admin_response['Body']['AuthResponse']['authToken'])) {
    $error_message = isset($admin_response['Body']['Fault']['Reason']['Text']) 
        ? $admin_response['Body']['Fault']['Reason']['Text'] 
        : 'Admin kimlik doğrulaması başarısız';
    log_error("Admin SOAP yanıtı: " . $admin_result['response']);
    json_response(false, $error_message);
}

$admin_token = is_array($admin_response['Body']['AuthResponse']['authToken']) 
    ? $admin_response['Body']['AuthResponse']['authToken'][0]['_content'] 
    : $admin_response['Body']['AuthResponse']['authToken'];

// Hesap varlığını kontrol et (GetAccountRequest)
$get_account_request = [
    'Header' => [
        'context' => [
            'authToken' => $admin_token,
            '_jsns' => 'urn:zimbra'
        ]
    ],
    'Body' => [
        'GetAccountRequest' => [
            'account' => [
                '_content' => $email,
                'by' => 'name'
            ],
            '_jsns' => 'urn:zimbraAdmin'
        ]
    ],
    '_jsns' => 'urn:zimbraSoap'
];

$get_account_result = send_soap_request($zimbra_server, $get_account_request);
if (!$get_account_result['success']) {
    json_response(false, 'Hesap kontrol isteği başarısız: ' . $get_account_result['error']);
}

$get_account_response = json_decode($get_account_result['response'], true);
if (!$get_account_response || isset($get_account_response['Body']['Fault'])) {
    $error_message = isset($get_account_response['Body']['Fault']['Reason']['Text']) 
        ? $get_account_response['Body']['Fault']['Reason']['Text'] 
        : 'Hesap bulunamadı';
    log_error("Hesap kontrol SOAP yanıtı: " . $get_account_result['response']);
    json_response(false, "Bu email adresine ait bir hesap bulunamadı: $email");
}

// Hesap UUID'sini al
$account_id = $get_account_response['Body']['GetAccountResponse']['account'][0]['id'];

// Şifre değiştirme (Admin yetkisiyle ModifyAccountRequest)
$modify_soap_request = [
    'Header' => [
        'context' => [
            'authToken' => $admin_token,
            '_jsns' => 'urn:zimbra'
        ]
    ],
    'Body' => [
        'ModifyAccountRequest' => [
            'id' => [
                '_content' => $account_id,
                'by' => 'id'
            ],
            'a' => [
                [
                    'n' => 'userPassword',
                    '_content' => $new_password
                ],
                [
                    'n' => 'zimbraPasswordMustChange',
                    '_content' => 'FALSE'
                ]
            ],
            '_jsns' => 'urn:zimbraAdmin'
        ]
    ],
    '_jsns' => 'urn:zimbraSoap'
];

$modify_result = send_soap_request($zimbra_server, $modify_soap_request);
if (!$modify_result['success']) {
    json_response(false, 'Şifre değiştirme isteği başarısız: ' . $modify_result['error']);
}

$modify_response = json_decode($modify_result['response'], true);
if (!$modify_response || isset($modify_response['Body']['Fault'])) {
    $error_message = isset($modify_response['Body']['Fault']['Reason']['Text']) 
        ? $modify_response['Body']['Fault']['Reason']['Text'] 
        : 'Bilinmeyen hata';
    log_error("Şifre değiştirme SOAP yanıtı: " . $modify_result['response']);
    json_response(false, 'Şifre güncellenemedi: ' . $error_message);
}

json_response(true, 'Şifreniz başarıyla değiştirildi. 15 dakika içinde yeni şifrenizle giriş yapabilirsiniz.');
?>