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
        $stmt = $db->prepare("SELECT id, kullanici_adi, sifre, ad_soyad, rol FROM kullanicilar WHERE kullanici_adi = ? AND aktif = 1");
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
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 100px auto;
        }
        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background: linear-gradient(to right, #4e73df, #224abe);
            color: white;
            padding: 20px;
            border-radius: 5px 5px 0 0 !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #224abe;
            border-color: #224abe;
        }
        .logo {
            max-width: 200px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="text-center mb-4">
            <img src="assets/img/logo.png" alt="Logo" class="logo">
        </div>
        <div class="card">
            <div class="card-header text-center">
                <h4 class="mb-0">Tedarik Portalı Giriş</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($hata): ?>
                    <div class="alert alert-danger"><?= guvenli($hata) ?></div>
                <?php endif; ?>
                
                <form method="post">
                    <div class="mb-3">
                        <label for="kullanici_adi" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" required>
                    </div>
                    <div class="mb-3">
                        <label for="sifre" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="sifre" name="sifre" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Giriş Yap</button>
                    </div>
                </form>
                
                <div class="text-center mt-3">
                    <a href="sifremi_unuttum.php">Şifremi Unuttum</a>
                </div>
            </div>
        </div>
        <div class="text-center mt-3 text-muted small">
            &copy; <?= date('Y') ?> Tedarik Portalı. Tüm hakları saklıdır.
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 