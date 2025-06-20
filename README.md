# Zimbra Password Redirect for Roundcube

**Zimbra ile Roundcube entegre çalışan sistemlerde**, eğer kullanıcıdan "Şifre değiştirilmeli" (`password must be changed`) uyarısı gelirse, bu eklenti devreye girer ve kullanıcıyı özel bir şifre değiştirme sayfasına yönlendirir.

Kullanıcı, şifresini belirledikten sonra Roundcube'a normal şekilde giriş yapabilir.

---

## ✨ Özellikler

- Zimbra'dan gelen `password must be changed` hatasını tespit eder.
- Roundcube login sayfasından özel bir URL'ye yönlendirme yapar.
- Kullanıcı dostu, sade ve esnek yapı.
- İsteğe bağlı olarak SOAP üzerinden şifre değişimiyle entegre edilebilir.

---

## 🔧 Kurulum

### 1. Eklentiyi Roundcube’a ekleyin

```bash
cd /var/www/html/roundcube/plugins
git clone https://github.com/kullaniciadi/zimbra_password_redirect.git

---
/var/www/html/roundcube/config/config.inc.php içinde:


$config['plugins'] = ['zimbra_password_redirect'];
Eğer başka eklentileriniz de varsa aynı dizi içinde virgülle ekleyin.

---
Apache Alias ve dizin erişimi ayarları
Şifre değiştirme sayfanızı Roundcube dışında /sifremi-unuttum dizinine yönlendirmek için Apache yapılandırmanıza aşağıdaki tanımı ekleyin:

🗂 Dosya Yolu: /etc/httpd/conf.d/sifremi-unuttum.conf


Alias /sifremi-unuttum /var/www/html/sifremi-unuttum

<Directory /var/www/html/sifremi-unuttum>
    Options Indexes FollowSymLinks
    AllowOverride None
    Require all granted
</Directory>
Değişiklikten sonra Apache servisini yeniden yükleyin:


sudo systemctl reload httpd
