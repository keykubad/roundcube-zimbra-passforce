<?php
header('Content-Type: application/json');

function json_response($success, $message) {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Geçersiz istek.');
}

// reCAPTCHA doğrulaması
$recaptcha_secret = 'SECRET_KEYİNİ_BURAYA_YAZ';
$recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

if (!$recaptcha_response) {
    json_response(false, 'reCAPTCHA doğrulaması yapılmadı.');
}

$verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$recaptcha_secret}&response={$recaptcha_response}");
$captcha_success = json_decode($verify);

if (!$captcha_success->success) {
    json_response(false, 'reCAPTCHA doğrulaması başarısız.');
}

// Form verileri
$email = trim($_POST['mail'] ?? '');
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($email) || empty($current_password) || empty($new_password) || empty($confirm_password)) {
    json_response(false, 'Tüm alanları doldurmanız gereklidir.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Geçerli bir email adresi giriniz.');
}

if ($new_password !== $confirm_password) {
    json_response(false, 'Yeni parolalar eşleşmiyor.');
}

// LDAP ayarları
$ldap_host = "ldap://demo.kamueposta.com";
$ldap_port = 389;
$ldap_base_dn = "dc=demo,dc=kamueposta,dc=com";
$admin_dn = "uid=zimbra,cn=admins,cn=zimbra";
$admin_password = "sifreniz";

// Kullanıcıyı bulmak için admin olarak bağlan
$ldap_conn = ldap_connect($ldap_host, $ldap_port);
if (!$ldap_conn) json_response(false, "LDAP bağlantı hatası.");
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

if (!@ldap_bind($ldap_conn, $admin_dn, $admin_password)) {
    json_response(false, "Admin ile LDAP bağlantısı başarısız.");
}

$filter = "(mail=$email)";
$search = ldap_search($ldap_conn, $ldap_base_dn, $filter);
if (!$search) {
    json_response(false, "LDAP arama hatası.");
}

$entries = ldap_get_entries($ldap_conn, $search);
if ($entries['count'] == 0) {
    json_response(false, "Kullanıcı bulunamadı.");
}

$user_dn = $entries[0]['dn'];
ldap_unbind($ldap_conn);

// Kullanıcının mevcut şifresini doğrula
$ldap_conn = ldap_connect($ldap_host, $ldap_port);
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);

if (!@ldap_bind($ldap_conn, $user_dn, $current_password)) {
    json_response(false, "Mevcut şifreniz hatalı.");
}
ldap_unbind($ldap_conn);

// Admin ile tekrar bağlan ve şifreyi değiştir
$ldap_conn = ldap_connect($ldap_host, $ldap_port);
ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
if (!@ldap_bind($ldap_conn, $admin_dn, $admin_password)) {
    json_response(false, "Admin olarak yeniden bağlanılamadı.");
}

$hashed_password = generateSSHA($new_password);
$update = [
    'userPassword' => [$hashed_password],
    'zimbraPasswordMustChange' => ['FALSE']
];

if (ldap_modify($ldap_conn, $user_dn, $update)) {
    json_response(true, "Şifreniz başarıyla değiştirildi. 15 dakika içinde yeni şifrenizle giriş yapabilirsiniz.");
} else {
    json_response(false, "Şifre güncellenemedi: " . ldap_error($ldap_conn));
}

ldap_close($ldap_conn);

// SSHA hash fonksiyonu
function generateSSHA($password) {
    $salt = openssl_random_pseudo_bytes(4);
    $hash = sha1($password . $salt, true);
    return "{SSHA}" . base64_encode($hash . $salt);
}
