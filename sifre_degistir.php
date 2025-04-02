<?php
// sifre_degistir.php - Şifre değiştirme sayfası
require_once 'config.php';
girisKontrol();

$mesaj = '';
$hata = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mevcut_sifre = trim($_POST['mevcut_sifre'] ?? '');
    $yeni_sifre = trim($_POST['yeni_sifre'] ?? '');
    $yeni_sifre_tekrar = trim($_POST['yeni_sifre_tekrar'] ?? '');
    
    // Alanların doldurulup doldurulmadığını kontrol et
    if (empty($mevcut_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
        $hata = "Lütfen tüm alanları doldurun.";
    } 
    // Yeni şifrelerin eşleştiğini kontrol et
    elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $hata = "Yeni şifreler eşleşmiyor.";
    }
    // Şifre güvenliği (en az 8 karakter)
    elseif (strlen($yeni_sifre) < 8) {
        $hata = "Yeni şifre en az 8 karakter uzunluğunda olmalıdır.";
    } 
    else {
        // Kullanıcının mevcut şifresini doğrula
        $kullanici_id = $_SESSION['kullanici_id'];
        $sql = "SELECT sifre FROM kullanicilar WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$kullanici_id]);
        $kullanici = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($kullanici && password_verify($mevcut_sifre, $kullanici['sifre'])) {
            // Yeni şifreyi hashle
            
            
            // Şifreyi güncelle
            $sql = "UPDATE kullanicilar SET sifre = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([$hash_sifre, $kullanici_id]);
            
            if ($result) {
                $mesaj = "Şifreniz başarıyla değiştirildi.";
                
                // Admin, sorumlu veya tedarikçi olmasına göre yönlendirme yapabilirsiniz
                // header("Location: index.php");
                // exit;
            } else {
                $hata = "Şifre değiştirme sırasında bir hata oluştu. Lütfen tekrar deneyin.";
            }
        } else {
            $hata = "Mevcut şifre yanlış.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Değiştir - Tedarik Portalı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 2rem;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #3756a4;
            border-color: #3756a4;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h4 class="m-0">Şifre Değiştir</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($mesaj)): ?>
                            <div class="alert alert-success"><?= $mesaj ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($hata)): ?>
                            <div class="alert alert-danger"><?= $hata ?></div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <div class="mb-3">
                                <label for="mevcut_sifre" class="form-label">Mevcut Şifre</label>
                                <input type="password" class="form-control" id="mevcut_sifre" name="mevcut_sifre" required>
                            </div>
                            <div class="mb-3">
                                <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                                <input type="password" class="form-control" id="yeni_sifre" name="yeni_sifre" required>
                                <div class="form-text">Şifreniz en az 8 karakter uzunluğunda olmalıdır.</div>
                            </div>
                            <div class="mb-3">
                                <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password" class="form-control" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Şifreyi Değiştir</button>
                                <a href="index.php" class="btn btn-secondary">Geri Dön</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 