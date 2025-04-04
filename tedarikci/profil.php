<?php
// tedarikci/profil.php - Tedarikçi profil sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Tedarikçi bilgilerini al
$kullanici_id = $_SESSION['kullanici_id'];
$tedarikci_sql = "SELECT t.* FROM tedarikciler t 
                 WHERE t.id = (SELECT tedarikci_id FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?)";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute([$kullanici_id]);
$tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

// Eğer kullanıcı_tedarikci_iliskileri tablosu yoksa, kullanıcı rolünden tedarikçi bilgilerini al
if (!$tedarikci) {
    // Alternatif sorgu - direkt kullanıcı ID'sine göre tedarikçi bul (eğer standart isimler kullanılıyorsa)
    $tedarikci_sql = "SELECT t.* FROM tedarikciler t
                    INNER JOIN kullanicilar k ON t.email = k.email
                    WHERE k.id = ?";
    $tedarikci_stmt = $db->prepare($tedarikci_sql);
    $tedarikci_stmt->execute([$kullanici_id]);
    $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
}

// Yine bulamazsak kullanıcı adını kontrol edelim 
if (!$tedarikci) {
    $kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
    $kullanici_stmt = $db->prepare($kullanici_sql);
    $kullanici_stmt->execute([$kullanici_id]);
    $kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($kullanici) {
        $tedarikci_sql = "SELECT * FROM tedarikciler WHERE email = ? OR firma_adi LIKE ?";
        $tedarikci_stmt = $db->prepare($tedarikci_sql);
        $tedarikci_stmt->execute([$kullanici['email'], '%' . $kullanici['ad_soyad'] . '%']);
        $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$tedarikci) {
    // Bu kullanıcı için tedarikçi kaydı bulunamadı
    header("Location: ../yetki_yok.php");
    exit;
}

// Kullanıcı bilgilerini al
$kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
$kullanici_stmt = $db->prepare($kullanici_sql);
$kullanici_stmt->execute([$kullanici_id]);
$kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);

// Mesaj değişkenleri
$mesaj = '';
$hata = '';
$sifre_mesaj = '';
$sifre_hata = '';

// Profil güncelleme formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'profil_guncelle') {
    $yeni_ad_soyad = $_POST['ad_soyad'] ?? '';
    $yeni_email = $_POST['email'] ?? '';
    $yeni_telefon = $_POST['telefon'] ?? '';
    
    if (empty($yeni_ad_soyad) || empty($yeni_email)) {
        $hata = "Ad Soyad ve E-posta alanları zorunludur!";
    } else {
        try {
            // Kullanıcı bilgilerini güncelle
            $guncelle_sql = "UPDATE kullanicilar SET 
                           ad_soyad = ?, 
                           email = ?, 
                           telefon = ? 
                           WHERE id = ?";
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_stmt->execute([
                $yeni_ad_soyad,
                $yeni_email,
                $yeni_telefon,
                $kullanici_id
            ]);
            
            // Tedarikçi bilgilerini güncelle (e-posta ile bağlantılıysa)
            if ($tedarikci['email'] === $kullanici['email']) {
                $tedarikci_guncelle_sql = "UPDATE tedarikciler SET 
                                         email = ?, 
                                         telefon = ? 
                                         WHERE id = ?";
                $tedarikci_guncelle_stmt = $db->prepare($tedarikci_guncelle_sql);
                $tedarikci_guncelle_stmt->execute([
                    $yeni_email,
                    $yeni_telefon,
                    $tedarikci['id']
                ]);
            }
            
            // Session bilgilerini güncelle
            $_SESSION['ad_soyad'] = $yeni_ad_soyad;
            $_SESSION['email'] = $yeni_email;
            
            // Güncel bilgileri al
            $kullanici_stmt->execute([$kullanici_id]);
            $kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);
            
            $mesaj = "Profil bilgileriniz başarıyla güncellendi.";
        } catch (Exception $e) {
            $hata = "Profil güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Şifre değiştirme formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sifre_degistir') {
    $mevcut_sifre = $_POST['mevcut_sifre'] ?? '';
    $yeni_sifre = $_POST['yeni_sifre'] ?? '';
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'] ?? '';
    
    if (empty($mevcut_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
        $sifre_hata = "Tüm şifre alanları zorunludur!";
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $sifre_hata = "Yeni şifreler eşleşmiyor!";
    } elseif (strlen($yeni_sifre) < 6) {
        $sifre_hata = "Yeni şifre en az 6 karakter olmalıdır!";
    } else {
        // Mevcut şifreyi doğrula
        if (password_verify($mevcut_sifre, $kullanici['sifre'])) {
            try {
                // Şifreyi güncelle
                $hash_sifre = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                $sifre_guncelle_sql = "UPDATE kullanicilar SET sifre = ? WHERE id = ?";
                $sifre_guncelle_stmt = $db->prepare($sifre_guncelle_sql);
                $sifre_guncelle_stmt->execute([$hash_sifre, $kullanici_id]);
                
                $sifre_mesaj = "Şifreniz başarıyla güncellendi.";
            } catch (Exception $e) {
                $sifre_hata = "Şifre güncellenirken bir hata oluştu: " . $e->getMessage();
            }
        } else {
            $sifre_hata = "Mevcut şifre doğru değil!";
        }
    }
}

// Firma bilgileri güncelleme formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'firma_guncelle') {
    $firma_adi = $_POST['firma_adi'] ?? '';
    $firma_adres = $_POST['adres'] ?? '';
    $firma_vergi_no = $_POST['vergi_no'] ?? '';
    $firma_yetkili = $_POST['yetkili_kisi'] ?? '';
    
    if (empty($firma_adi)) {
        $hata = "Firma adı zorunludur!";
    } else {
        try {
            // Firma bilgilerini güncelle
            $firma_guncelle_sql = "UPDATE tedarikciler SET 
                                  firma_adi = ?, 
                                  adres = ?, 
                                  vergi_no = ?, 
                                  yetkili_kisi = ?, 
                                  guncelleme_tarihi = NOW() 
                                  WHERE id = ?";
            $firma_guncelle_stmt = $db->prepare($firma_guncelle_sql);
            $firma_guncelle_stmt->execute([
                $firma_adi,
                $firma_adres,
                $firma_vergi_no,
                $firma_yetkili,
                $tedarikci['id']
            ]);
            
            // Güncel bilgileri al
            $tedarikci_stmt->execute([$kullanici_id]);
            $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
            
            $mesaj = "Firma bilgileriniz başarıyla güncellendi.";
        } catch (Exception $e) {
            $hata = "Firma bilgileri güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Sorumluları getir
$sorumlular_sql = "SELECT k.id, k.ad_soyad, k.email, k.telefon 
                  FROM kullanicilar k
                  INNER JOIN sorumluluklar s ON k.id = s.sorumlu_id
                  WHERE s.tedarikci_id = ?";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute([$tedarikci['id']]);
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirimleri al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profilim - Tedarikçi Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #26c281;
            width: 204px;
        }
        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: 100vh;
            padding-top: 0.5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            font-weight: 500;
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
        .sidebar-heading {
            color: white;
            text-align: center;
            padding: 20px 0;
        }
        .sidebar-heading h4 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        main {
            margin-left: 204px;
            padding: 1.5rem;
        }
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 56px;
            background-color: #fff !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .navbar-toggler {
            padding: 0.25rem 0.75rem;
            font-size: 1.25rem;
            line-height: 1;
            background-color: transparent;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        .card {
            border: none;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            padding: 0.75rem 1.25rem;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .badge-notification {
            position: absolute;
            top: 0.2rem;
            right: 0.2rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-heading">
            <h4>Tedarik Portalı</h4>
            <p>Tedarikçi Paneli</p>
        </div>
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siparislerim.php">
                        <i class="bi bi-list-check"></i> Siparişlerim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siparis_guncelle.php">
                        <i class="bi bi-pencil-square"></i> Sipariş Güncelle
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="teslimatlarim.php">
                        <i class="bi bi-truck"></i> Teslimatlarım
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dokumanlar.php">
                        <i class="bi bi-file-earmark-text"></i> Dokümanlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="profil.php">
                        <i class="bi bi-person"></i> Profilim
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

    <!-- Ana içerik -->
    <main>
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
            <div class="container-fluid">
                <span class="navbar-brand mb-0 h1">Profilim</span>
                <div class="ms-auto d-flex">
                    <div class="dropdown me-3">
                        <a class="nav-link position-relative" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            <?php if ($okunmamis_bildirim_sayisi > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $okunmamis_bildirim_sayisi ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">Bildirimleri Gör</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['ad_soyad']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profil.php">Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../cikis.php">Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mesaj) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($hata)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($hata) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Kullanıcı Bilgileri -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-person-fill me-2"></i> Kullanıcı Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="kullanici_guncelle">
                            <div class="mb-3">
                                <label for="ad_soyad" class="form-label">Ad Soyad</label>
                                <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" value="<?= htmlspecialchars($kullanici['ad_soyad']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($kullanici['email']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="text" class="form-control" id="telefon" name="telefon" value="<?= htmlspecialchars($kullanici['telefon'] ?? '') ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Bilgileri Güncelle</button>
                        </form>
                    </div>
                </div>

                <!-- Şifre Değiştirme -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-lock-fill me-2"></i> Şifre Değiştir
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="sifre_degistir">
                            <div class="mb-3">
                                <label for="mevcut_sifre" class="form-label">Mevcut Şifre</label>
                                <input type="password" class="form-control" id="mevcut_sifre" name="mevcut_sifre" required>
                            </div>
                            <div class="mb-3">
                                <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                                <input type="password" class="form-control" id="yeni_sifre" name="yeni_sifre" 
                                    minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre Tekrar</label>
                                <input type="password" class="form-control" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" 
                                    minlength="8" required>
                            </div>
                            <div class="mb-3">
                                <div class="form-text">
                                    <ul>
                                        <li>Şifreniz en az 8 karakter uzunluğunda olmalıdır.</li>
                                        <li>En az bir büyük harf, bir küçük harf ve bir sayı içermelidir.</li>
                                    </ul>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-warning">Şifreyi Değiştir</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Firma Bilgileri -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-building me-2"></i> Firma Bilgileri
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="">
                            <input type="hidden" name="action" value="firma_guncelle">
                            <div class="mb-3">
                                <label for="firma_adi" class="form-label">Firma Adı</label>
                                <input type="text" class="form-control" id="firma_adi" name="firma_adi" value="<?= htmlspecialchars($tedarikci['firma_adi']) ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="yetkili_kisi" class="form-label">Yetkili Kişi</label>
                                <input type="text" class="form-control" id="yetkili_kisi" name="yetkili_kisi" value="<?= htmlspecialchars($tedarikci['yetkili_kisi'] ?? '') ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="vergi_no" class="form-label">Vergi No</label>
                                    <input type="text" class="form-control" id="vergi_no" name="vergi_no" value="<?= htmlspecialchars($tedarikci['vergi_no'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="firma_telefon" class="form-label">Firma Telefon</label>
                                    <input type="text" class="form-control" id="firma_telefon" name="firma_telefon" value="<?= htmlspecialchars($tedarikci['telefon'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="firma_email" class="form-label">Firma E-posta</label>
                                <input type="email" class="form-control" id="firma_email" name="firma_email" value="<?= htmlspecialchars($tedarikci['email'] ?? '') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="firma_adres" class="form-label">Adres</label>
                                <textarea class="form-control" id="firma_adres" name="firma_adres" rows="3"><?= htmlspecialchars($tedarikci['adres'] ?? '') ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="web_sitesi" class="form-label">Web Sitesi</label>
                                <input type="url" class="form-control" id="web_sitesi" name="web_sitesi" value="<?= htmlspecialchars($tedarikci['web_sitesi'] ?? '') ?>" placeholder="https://...">
                            </div>
                            <div class="mb-3">
                                <label for="aciklama" class="form-label">Ek Bilgiler</label>
                                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= htmlspecialchars($tedarikci['aciklama'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-success">Firma Bilgilerini Güncelle</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 