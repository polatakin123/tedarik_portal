<?php
// giris.php - Giriş sayfası
require 'config.php';

// Zaten giriş yapmış kullanıcı varsa ana sayfaya yönlendir
if (isset($_SESSION['giris']) && $_SESSION['giris'] === true) {
    // Rol bazlı yönlendirme
    switch ($_SESSION['rol']) {
        case 'Admin':
            header("Location: admin/index.php");
            break;
        case 'Sorumlu':
            header("Location: sorumlu/index.php");
            break;
        case 'Tedarikci':
            header("Location: tedarikci/index.php");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}

$hata = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $kullanici_adi = $_POST['kullanici_adi'] ?? '';
    $sifre = $_POST['sifre'] ?? '';
    
    if ($kullanici_adi && $sifre) {
        $stmt = $db->prepare("SELECT id, kullanici_adi, sifre, ad_soyad, rol FROM kullanicilar WHERE kullanici_adi = ?");
        $stmt->execute([$kullanici_adi]);
        
        if ($kullanici = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($sifre, $kullanici['sifre'])) {
                // Oturum bilgilerini ayarla
                $_SESSION['giris'] = true;
                $_SESSION['kullanici_id'] = $kullanici['id'];
                $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
                $_SESSION['ad_soyad'] = $kullanici['ad_soyad'];
                $_SESSION['rol'] = $kullanici['rol'];
                
                // Son giriş zamanını güncelle
                $update = $db->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?");
                $update->execute([$kullanici['id']]);
                
                // Rol bazlı yönlendirme
                switch ($kullanici['rol']) {
                    case 'Admin':
                        header("Location: admin/index.php");
                        break;
                    case 'Sorumlu':
                        header("Location: sorumlu/index.php");
                        break;
                    case 'Tedarikci':
                        header("Location: tedarikci/index.php");
                        break;
                    default:
                        header("Location: index.php");
                }
                exit;
            }
        }
        $hata = 'Kullanıcı adı veya şifre hatalı!';
    } else {
        $hata = 'Lütfen tüm alanları doldurun!';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tedarik Portalı - Giriş</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #1a3a5f;
            --secondary-color: #36404a;
            --light-color: #f2f4f8;
            --border-color: #d8dbe0;
        }
        
        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Arial, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 0 15px;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.25rem;
            overflow: hidden;
        }
        
        .card-header {
            background: var(--primary-color);
            color: white;
            padding: 1.5rem;
            border-radius: 0 !important;
            text-align: center;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--secondary-color);
        }
        
        .form-control {
            border-color: var(--border-color);
            padding: 0.75rem 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(26, 58, 95, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem;
            font-weight: 500;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: #122a47;
            border-color: #122a47;
        }
        
        .copyright {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .forgot-password {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .forgot-password:hover {
            text-decoration: underline;
        }
        
        .logo {
            max-width: 200px;
            margin: 0 auto 1.5rem;
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <img src="assets/img/logo.png" alt="Tedarik Portalı" class="logo">
        </div>
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Tedarik Portalı</h4>
            </div>
            <div class="card-body">
                <?php if ($hata): ?>
                    <div class="alert alert-danger"><?= guvenli($hata) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="kullanici_adi" class="form-label">Kullanıcı Adı</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" required>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="sifre" class="form-label">Şifre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" id="sifre" name="sifre" required>
                        </div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">GİRİŞ YAP</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <a href="sifremi_unuttum.php" class="forgot-password">Şifremi Unuttum</a>
                </div>
            </div>
        </div>
        <div class="copyright">
            &copy; <?= date('Y') ?> Tedarik Portalı - Tüm hakları saklıdır.
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 