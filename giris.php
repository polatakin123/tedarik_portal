<?php
// giris.php - Kullanıcı giriş sayfası
require_once 'config.php';

// Eğer kullanıcı zaten giriş yapmışsa yönlendir
if (isset($_SESSION['giris']) && $_SESSION['giris'] === true) {
    if ($_SESSION['rol'] === 'Admin' || $_SESSION['rol'] === 'Sorumlu') {
        header("Location: admin/index.php");
    } else if ($_SESSION['rol'] === 'Tedarikci') {
        header("Location: tedarikci/index.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

$hatalar = [];
$giris = '';
$sifre = '';

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $giris = trim($_POST['giris'] ?? '');
    $sifre = trim($_POST['sifre'] ?? '');
    
    // Validasyon
    if (empty($giris)) {
        $hatalar[] = "Kullanıcı adı veya e-posta adresi boş bırakılamaz";
    }
    
    if (empty($sifre)) {
        $hatalar[] = "Şifre boş bırakılamaz";
    }
    
    // Hata yoksa giriş işlemi
    if (empty($hatalar)) {
        try {
            // Kullanıcı adı veya e-posta ile sorgula
            $giris_sql = "SELECT * FROM kullanicilar WHERE kullanici_adi = ? OR email = ?";
            $giris_stmt = $db->prepare($giris_sql);
            $giris_stmt->execute([$giris, $giris]);
            $kullanici = $giris_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($kullanici && password_verify($sifre, $kullanici['sifre'])) {
                // Giriş başarılı
                $_SESSION['giris'] = true;
                $_SESSION['kullanici_id'] = $kullanici['id'];
                $_SESSION['kullanici_adi'] = $kullanici['kullanici_adi'];
                $_SESSION['ad_soyad'] = $kullanici['ad_soyad'];
                $_SESSION['rol'] = $kullanici['rol'];
                
                // Rolüne göre yönlendir
                if ($kullanici['rol'] === 'Admin') {
                    header("Location: admin/index.php");
                } else if ($kullanici['rol'] === 'Sorumlu') {
                    header("Location: sorumlu/index.php");
                } else if ($kullanici['rol'] === 'Tedarikci') {
                    header("Location: tedarikci/index.php");
                } else {
                    header("Location: index.php");
                }
                exit;
            } else {
                $hatalar[] = "Kullanıcı adı/e-posta veya şifre hatalı";
            }
        } catch (PDOException $e) {
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Tema rengini veritabanından al
$tema_renk_sql = "SELECT tema_renk FROM ayarlar WHERE id = 1";
$tema_renk_stmt = $db->prepare($tema_renk_sql);
$tema_renk_stmt->execute();
$tema_renk = $tema_renk_stmt->fetchColumn() ?: '#4e73df';

// Site başlığını veritabanından al
$site_basligi_sql = "SELECT site_basligi FROM ayarlar WHERE id = 1";
$site_basligi_stmt = $db->prepare($site_basligi_sql);
$site_basligi_stmt->execute();
$site_basligi = $site_basligi_stmt->fetchColumn() ?: 'Tedarik Portalı';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - <?= guvenli($site_basligi) ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bs-primary: <?= $tema_renk ?>;
            --bs-primary-rgb: <?= implode(', ', sscanf($tema_renk, "#%02x%02x%02x")) ?>;
        }
        
        body {
            background-color: #f8f9fc;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 15px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .card-header {
            background-color: var(--bs-primary);
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }
        
        .card-body {
            padding: 2rem;
        }
        
        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
        }
        
        .btn-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            opacity: 0.9;
        }
        
        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0"><?= guvenli($site_basligi) ?></h4>
                <p class="mb-0">Tedarikçi ve Sipariş Yönetim Sistemi</p>
            </div>
            <div class="card-body">
                <?php if (!empty($hatalar)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($hatalar as $hata): ?>
                                <li><?= $hata ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="post" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="giris" class="form-label">Kullanıcı Adı veya E-posta</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="giris" name="giris" value="<?= guvenli($giris) ?>" required>
                            <div class="invalid-feedback">Kullanıcı adı veya e-posta adresi gereklidir.</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="sifre" class="form-label">Şifre</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="sifre" name="sifre" required>
                            <div class="invalid-feedback">Şifre gereklidir.</div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Giriş Yap</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Form doğrulama için Bootstrap validation
        (function () {
            'use strict'
            
            // Tüm formları seçin
            var forms = document.querySelectorAll('.needs-validation')
            
            // Doğrulama yapmak için döngü
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        
                        form.classList.add('was-validated')
                    }, false)
                })
        })()
    </script>
</body>
</html> 