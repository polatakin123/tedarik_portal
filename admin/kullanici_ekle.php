<?php
// admin/kullanici_ekle.php - Kullanıcı ekleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = isset($_POST['ad_soyad']) ? trim($_POST['ad_soyad']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $kullanici_adi = isset($_POST['kullanici_adi']) ? trim($_POST['kullanici_adi']) : '';
    $sifre = isset($_POST['sifre']) ? trim($_POST['sifre']) : '';
    $sifre_tekrar = isset($_POST['sifre_tekrar']) ? trim($_POST['sifre_tekrar']) : '';
    $telefon = isset($_POST['telefon']) ? trim($_POST['telefon']) : '';
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $firma_id = isset($_POST['firma_id']) && !empty($_POST['firma_id']) ? intval($_POST['firma_id']) : null;
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Eklenen bilgilerin kontrolü
    $hatalar = [];
    
    if (empty($ad_soyad)) {
        $hatalar[] = "Ad Soyad alanı boş bırakılamaz.";
    }
    
    if (empty($email)) {
        $hatalar[] = "E-posta alanı boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = "Geçerli bir e-posta adresi giriniz.";
    } else {
        // E-posta adresi benzersiz mi kontrol et
        $kontrol = $db->prepare("SELECT COUNT(*) FROM kullanicilar WHERE email = ?");
        $kontrol->execute([$email]);
        if ($kontrol->fetchColumn() > 0) {
            $hatalar[] = "Bu e-posta adresi zaten kullanılmaktadır.";
        }
    }
    
    if (empty($kullanici_adi)) {
        $hatalar[] = "Kullanıcı adı boş bırakılamaz.";
    } else {
        // Kullanıcı adı benzersiz mi kontrol et
        $kontrol = $db->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?");
        $kontrol->execute([$kullanici_adi]);
        if ($kontrol->fetchColumn() > 0) {
            $hatalar[] = "Bu kullanıcı adı zaten kullanılmaktadır.";
        }
    }
    
    if (empty($sifre)) {
        $hatalar[] = "Şifre alanı boş bırakılamaz.";
    } elseif (strlen($sifre) < 6) {
        $hatalar[] = "Şifre en az 6 karakter olmalıdır.";
    } elseif ($sifre !== $sifre_tekrar) {
        $hatalar[] = "Girilen şifreler uyuşmuyor.";
    }
    
    if (empty($rol)) {
        $hatalar[] = "Kullanıcı rolü seçilmelidir.";
    }
    
    // Rol Tedarikci ise firma_id zorunlu
    if ($rol === 'Tedarikci' && empty($firma_id)) {
        $hatalar[] = "Tedarikçi kullanıcılar için firma seçilmelidir.";
    }
    
    // Hata yoksa kullanıcıyı ekle
    if (empty($hatalar)) {
        try {
            $db->beginTransaction();
            
            // Şifreyi hash'le
            $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO kullanicilar (ad_soyad, email, kullanici_adi, sifre, telefon, rol, firma_id, aktif, son_giris, olusturma_tarihi) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([$ad_soyad, $email, $kullanici_adi, $hashed_password, $telefon, $rol, $firma_id, $aktif]);
            
            $kullanici_id = $db->lastInsertId();
            
            // Eğer tedarikçi kullanıcısı ise, tedarikçi ile ilişkilendir
            if ($rol === 'Tedarikci' && !empty($firma_id)) {
                $iliski_sql = "INSERT INTO kullanici_tedarikci_iliskileri (kullanici_id, tedarikci_id) VALUES (?, ?)";
                $iliski_stmt = $db->prepare($iliski_sql);
                $iliski_stmt->execute([$kullanici_id, $firma_id]);
            }
            
            $db->commit();
            
            // Başarılı mesajı ile yönlendir
            $mesaj = "Kullanıcı başarıyla eklendi.";
            header("Location: kullanicilar.php?mesaj=" . urlencode($mesaj));
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Tedarikçileri getir
$tedarikciler_sql = "SELECT id, firma_adi, firma_kodu FROM tedarikciler WHERE aktif = 1 ORDER BY firma_adi ASC";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısını al
$kullanici_id = $_SESSION['kullanici_id'];
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);

// Son 5 bildirimi al
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$kullanici_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Kullanıcı Ekle - Admin Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #4e73df;
            transition: all 0.3s;
            width: 250px;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: 0.5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        main {
            margin-left: 250px;
            padding: 2rem;
            padding-top: 70px;
            transition: all 0.3s;
        }
        .navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 99;
            background-color: #fff !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s;
            height: 60px;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        .badge-notification {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar col-md-3 col-lg-2 d-md-block text-white">
        <div class="pt-3 text-center mb-4">
            <h4>Tedarik Portalı</h4>
            <p>Admin Paneli</p>
        </div>
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siparisler.php">
                        <i class="bi bi-list-check"></i> Siparişler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tedarikciler.php">
                        <i class="bi bi-building"></i> Tedarikçiler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="sorumlular.php">
                        <i class="bi bi-people"></i> Sorumlular
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projeler.php">
                        <i class="bi bi-diagram-3"></i> Projeler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="kullanicilar.php">
                        <i class="bi bi-person-badge"></i> Kullanıcılar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="raporlar.php">
                        <i class="bi bi-graph-up"></i> Raporlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ayarlar.php">
                        <i class="bi bi-gear"></i> Ayarlar
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="../cikis.php">
                        <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownBildirim" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if ($okunmamis_bildirim_sayisi > 0): ?>
                                <span class="badge rounded-pill bg-danger badge-notification"><?= $okunmamis_bildirim_sayisi ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownBildirim">
                            <?php if (count($bildirimler) > 0): ?>
                                <?php foreach ($bildirimler as $bildirim): ?>
                                    <li>
                                        <a class="dropdown-item" href="bildirim_goruntule.php?id=<?= $bildirim['id'] ?>">
                                            <?= guvenli(mb_substr($bildirim['mesaj'], 0, 50)) ?>...
                                            <div class="small text-muted"><?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="bildirimler.php">Tüm Bildirimleri Gör</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item text-center" href="#">Bildirim bulunmamaktadır</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownProfil" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= guvenli($_SESSION['ad_soyad']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownProfil">
                            <li><a class="dropdown-item" href="profil.php">Profilim</a></li>
                            <li><a class="dropdown-item" href="ayarlar.php">Ayarlar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../cikis.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Ana İçerik -->
    <main>
        <div class="container-fluid">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Yeni Kullanıcı Ekle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="kullanicilar.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Kullanıcılara Dön
                    </a>
                </div>
            </div>

            <?php if (!empty($hatalar)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($hatalar as $hata): ?>
                            <li><?= guvenli($hata) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Kullanıcı Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ad_soyad" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" value="<?= isset($ad_soyad) ? guvenli($ad_soyad) : '' ?>" required>
                                <div class="invalid-feedback">Ad Soyad alanı zorunludur.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? guvenli($email) : '' ?>" required>
                                <div class="invalid-feedback">Geçerli bir e-posta adresi giriniz.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="kullanici_adi" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" value="<?= isset($kullanici_adi) ? guvenli($kullanici_adi) : '' ?>" required>
                                <div class="invalid-feedback">Kullanıcı adı zorunludur.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon" value="<?= isset($telefon) ? guvenli($telefon) : '' ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sifre" class="form-label">Şifre <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="sifre" name="sifre" required minlength="6">
                                <div class="form-text">Şifre en az 6 karakter içermelidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="sifre_tekrar" class="form-label">Şifre (Tekrar) <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" required minlength="6">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rol" class="form-label">Kullanıcı Rolü <span class="text-danger">*</span></label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="" selected disabled>-- Rol Seçin --</option>
                                    <option value="Admin" <?= (isset($rol) && $rol == 'Admin') ? 'selected' : '' ?>>Admin</option>
                                    <option value="Sorumlu" <?= (isset($rol) && $rol == 'Sorumlu') ? 'selected' : '' ?>>Sorumlu</option>
                                    <option value="Tedarikci" <?= (isset($rol) && $rol == 'Tedarikci') ? 'selected' : '' ?>>Tedarikçi</option>
                                </select>
                                <div class="invalid-feedback">Lütfen bir rol seçin.</div>
                            </div>
                            <div class="col-md-6 tedarikci-alani d-none">
                                <label for="firma_id" class="form-label">Tedarikçi Firma <span class="text-danger">*</span></label>
                                <select class="form-select" id="firma_id" name="firma_id">
                                    <option value="">-- Firma Seçin --</option>
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <option value="<?= $tedarikci['id'] ?>" <?= (isset($firma_id) && $firma_id == $tedarikci['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Tedarikçi firma seçilmelidir.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="aktif" name="aktif" value="1" <?= (!isset($aktif) || $aktif == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="aktif">Kullanıcı Aktif</label>
                            </div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-person-plus"></i> Kullanıcıyı Ekle
                                </button>
                                <a href="kullanicilar.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> İptal
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validasyonu
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Tedarikçi alanını göster/gizle
        document.getElementById('rol').addEventListener('change', function() {
            var tedarikciAlani = document.querySelector('.tedarikci-alani');
            var firmaSelect = document.getElementById('firma_id');
            
            if (this.value === 'Tedarikci') {
                tedarikciAlani.classList.remove('d-none');
                firmaSelect.setAttribute('required', '');
            } else {
                tedarikciAlani.classList.add('d-none');
                firmaSelect.removeAttribute('required');
                firmaSelect.value = '';
            }
        });
        
        // Sayfa yüklendiğinde rol değerine göre tedarikçi alanını kontrol et
        document.addEventListener('DOMContentLoaded', function() {
            var rol = document.getElementById('rol').value;
            var tedarikciAlani = document.querySelector('.tedarikci-alani');
            var firmaSelect = document.getElementById('firma_id');
            
            if (rol === 'Tedarikci') {
                tedarikciAlani.classList.remove('d-none');
                firmaSelect.setAttribute('required', '');
            }
        });
        
        // Şifre eşleşme kontrolü
        document.getElementById('sifre_tekrar').addEventListener('input', function() {
            var sifre = document.getElementById('sifre').value;
            var sifreTekrar = this.value;
            
            if (sifre !== sifreTekrar) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 