<?php
// yetki_yok.php - Yetkisiz erişim sayfası
require 'config.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yetkisiz Erişim - Tedarik Portalı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            max-width: 500px;
            text-align: center;
        }
        .error-code {
            font-size: 8rem;
            font-weight: 700;
            color: #d9534f;
            margin-bottom: 0;
            line-height: 1;
        }
        .error-message {
            font-size: 2rem;
            font-weight: 500;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="container error-container">
        <div class="card border-danger">
            <div class="card-body">
                <h1 class="error-code">403</h1>
                <p class="error-message">Yetkisiz Erişim</p>
                <p class="mb-4">Bu sayfayı görüntülemek için yeterli yetkiniz bulunmamaktadır.</p>
                
                <?php if (isset($_SESSION['kullanici_id'])): ?>
                    <?php if ($_SESSION['rol'] == 'Admin'): ?>
                        <a href="admin/index.php" class="btn btn-primary">Admin Paneline Dön</a>
                    <?php elseif ($_SESSION['rol'] == 'Sorumlu'): ?>
                        <a href="sorumlu/index.php" class="btn btn-primary">Sorumlu Paneline Dön</a>
                    <?php elseif ($_SESSION['rol'] == 'Tedarikci'): ?>
                        <a href="tedarikci/index.php" class="btn btn-primary">Tedarikçi Paneline Dön</a>
                    <?php else: ?>
                        <a href="index.php" class="btn btn-primary">Ana Sayfaya Dön</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="giris.php" class="btn btn-primary">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 