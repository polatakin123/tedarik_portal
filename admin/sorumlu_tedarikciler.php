<?php
// admin/sorumlu_tedarikciler.php - Sorumlu tedarikçi ilişkileri yönetim sayfası
require_once '../config.php';
adminYetkisiKontrol();

$hatalar = [];
$mesajlar = [];

// Tedarikçi ID mi yoksa sorumlu ID mi belirtilmiş kontrol et
$tedarikci_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$sorumlu_id = isset($_GET['sorumlu_id']) ? intval($_GET['sorumlu_id']) : null;

// Bu sayfanın iki farklı modu var:
// 1. Tedarikçiye sorumlu atama (tedarikci_id)
// 2. Sorumluya tedarikçi atama (sorumlu_id)

if ($tedarikci_id) {
    // Tedarikçi bilgilerini getir
    $tedarikci_sql = "SELECT * FROM tedarikciler WHERE id = ?";
    $tedarikci_stmt = $db->prepare($tedarikci_sql);
    $tedarikci_stmt->execute([$tedarikci_id]);
    $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tedarikci) {
        header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi bulunamadı"));
        exit;
    }

    $sayfa_basligi = "Tedarikçi Sorumluları: " . $tedarikci['firma_adi'];
    $geri_donulecek_sayfa = "tedarikci_detay.php?id=" . $tedarikci_id;
    $mod = "tedarikci";
} elseif ($sorumlu_id) {
    // Sorumlu kullanıcı bilgilerini getir
    $sorumlu_sql = "SELECT * FROM kullanicilar WHERE id = ? AND rol = 'Sorumlu'";
    $sorumlu_stmt = $db->prepare($sorumlu_sql);
    $sorumlu_stmt->execute([$sorumlu_id]);
    $sorumlu = $sorumlu_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$sorumlu) {
        header("Location: sorumlular.php?hata=" . urlencode("Sorumlu kullanıcı bulunamadı"));
        exit;
    }

    $sayfa_basligi = "Sorumlu Tedarikçileri: " . $sorumlu['ad_soyad'];
    $geri_donulecek_sayfa = "kullanici_detay.php?id=" . $sorumlu_id;
    $mod = "sorumlu";
} else {
    header("Location: sorumlular.php?hata=" . urlencode("Tedarikçi veya sorumlu ID belirtilmedi"));
    exit;
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['sorumluluk_ekle'])) {
        if ($mod == "tedarikci") {
            $yeni_sorumlu_id = intval($_POST['sorumlu_id'] ?? 0);
            
            if (empty($yeni_sorumlu_id)) {
                $hatalar[] = "Lütfen bir sorumlu seçin";
            } else {
                // Bu sorumlu zaten bu tedarikçiye atanmış mı kontrol et
                $kontrol_sql = "SELECT COUNT(*) FROM sorumluluklar WHERE tedarikci_id = ? AND sorumlu_id = ?";
                $kontrol_stmt = $db->prepare($kontrol_sql);
                $kontrol_stmt->execute([$tedarikci_id, $yeni_sorumlu_id]);
                $var_mi = $kontrol_stmt->fetchColumn();

                if ($var_mi) {
                    $hatalar[] = "Bu sorumlu zaten bu tedarikçiye atanmış";
                } else {
                    try {
                        // Sorumlu atama işlemi
                        $ekle_sql = "INSERT INTO sorumluluklar (tedarikci_id, sorumlu_id, olusturma_tarihi) VALUES (?, ?, NOW())";
                        $ekle_stmt = $db->prepare($ekle_sql);
                        $ekle_sonuc = $ekle_stmt->execute([$tedarikci_id, $yeni_sorumlu_id]);

                        if ($ekle_sonuc) {
                            $mesajlar[] = "Sorumlu başarıyla tedarikçiye atandı";
                            
                            // Sorumlu kullanıcı bilgilerini al
                            $sorumlu_ad_sql = "SELECT ad_soyad FROM kullanicilar WHERE id = ?";
                            $sorumlu_ad_stmt = $db->prepare($sorumlu_ad_sql);
                            $sorumlu_ad_stmt->execute([$yeni_sorumlu_id]);
                            $sorumlu_ad = $sorumlu_ad_stmt->fetchColumn();
                            
                            // Bildirim oluştur (sorumlu kullanıcıya)
                            $bildirim_mesaji = "'" . $tedarikci['firma_adi'] . "' tedarikçisi sorumluluğunuza atandı.";
                            bildirimOlustur($db, $yeni_sorumlu_id, $bildirim_mesaji);
                        } else {
                            $hatalar[] = "Sorumluluk atanırken bir hata oluştu";
                        }
                    } catch (PDOException $e) {
                        $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
                    }
                }
            }
        } elseif ($mod == "sorumlu") {
            $yeni_tedarikci_id = intval($_POST['tedarikci_id'] ?? 0);
            
            if (empty($yeni_tedarikci_id)) {
                $hatalar[] = "Lütfen bir tedarikçi seçin";
            } else {
                // Bu tedarikçi zaten bu sorumluya atanmış mı kontrol et
                $kontrol_sql = "SELECT COUNT(*) FROM sorumluluklar WHERE tedarikci_id = ? AND sorumlu_id = ?";
                $kontrol_stmt = $db->prepare($kontrol_sql);
                $kontrol_stmt->execute([$yeni_tedarikci_id, $sorumlu_id]);
                $var_mi = $kontrol_stmt->fetchColumn();

                if ($var_mi) {
                    $hatalar[] = "Bu tedarikçi zaten bu sorumluya atanmış";
                } else {
                    try {
                        // Tedarikçi atama işlemi
                        $ekle_sql = "INSERT INTO sorumluluklar (tedarikci_id, sorumlu_id, olusturma_tarihi) VALUES (?, ?, NOW())";
                        $ekle_stmt = $db->prepare($ekle_sql);
                        $ekle_sonuc = $ekle_stmt->execute([$yeni_tedarikci_id, $sorumlu_id]);

                        if ($ekle_sonuc) {
                            $mesajlar[] = "Tedarikçi başarıyla sorumluya atandı";
                            
                            // Tedarikçi bilgilerini al
                            $tedarikci_ad_sql = "SELECT firma_adi FROM tedarikciler WHERE id = ?";
                            $tedarikci_ad_stmt = $db->prepare($tedarikci_ad_sql);
                            $tedarikci_ad_stmt->execute([$yeni_tedarikci_id]);
                            $tedarikci_adi = $tedarikci_ad_stmt->fetchColumn();
                            
                            // Bildirim oluştur (sorumlu kullanıcıya)
                            $bildirim_mesaji = "'" . $tedarikci_adi . "' tedarikçisi sorumluluğunuza atandı.";
                            bildirimOlustur($db, $sorumlu_id, $bildirim_mesaji);
                        } else {
                            $hatalar[] = "Tedarikçi atanırken bir hata oluştu";
                        }
                    } catch (PDOException $e) {
                        $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
                    }
                }
            }
        }
    } elseif (isset($_POST['sorumluluk_kaldir'])) {
        $sorumluluk_id = intval($_POST['sorumluluk_id'] ?? 0);
        
        if (empty($sorumluluk_id)) {
            $hatalar[] = "Sorumluluk ID belirtilmedi";
        } else {
            try {
                // Silmeden önce kayıtları alalım
                $bilgi_sql = "SELECT s.tedarikci_id, s.sorumlu_id, t.firma_adi, k.ad_soyad 
                             FROM sorumluluklar s
                             JOIN tedarikciler t ON s.tedarikci_id = t.id
                             JOIN kullanicilar k ON s.sorumlu_id = k.id
                             WHERE s.id = ?";
                $bilgi_stmt = $db->prepare($bilgi_sql);
                $bilgi_stmt->execute([$sorumluluk_id]);
                $bilgi = $bilgi_stmt->fetch(PDO::FETCH_ASSOC);

                // Sorumluluk silme işlemi
                $sil_sql = "DELETE FROM sorumluluklar WHERE id = ?";
                $sil_stmt = $db->prepare($sil_sql);
                $sil_sonuc = $sil_stmt->execute([$sorumluluk_id]);

                if ($sil_sonuc) {
                    $mesajlar[] = "Sorumluluk başarıyla kaldırıldı";
                    
                    // Bildirim oluştur (sorumlu kullanıcıya)
                    if ($bilgi) {
                        $bildirim_mesaji = "'" . $bilgi['firma_adi'] . "' tedarikçisi sorumluluğunuzdan kaldırıldı.";
                        bildirimOlustur($db, $bilgi['sorumlu_id'], $bildirim_mesaji);
                    }
                } else {
                    $hatalar[] = "Sorumluluk kaldırılırken bir hata oluştu";
                }
            } catch (PDOException $e) {
                $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
            }
        }
    }
}

// Mevcut sorumlulukları getir
if ($mod == "tedarikci") {
    $sorumluluklar_sql = "SELECT s.id, s.tedarikci_id, s.sorumlu_id, s.olusturma_tarihi, 
                         k.ad_soyad, k.email, k.telefon
                         FROM sorumluluklar s
                         JOIN kullanicilar k ON s.sorumlu_id = k.id
                         WHERE s.tedarikci_id = ?
                         ORDER BY k.ad_soyad";
    $sorumluluklar_stmt = $db->prepare($sorumluluklar_sql);
    $sorumluluklar_stmt->execute([$tedarikci_id]);
    $sorumluluklar = $sorumluluklar_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Atanmamış sorumluları getir
    $atanmamis_sorumlular_sql = "SELECT k.id, k.ad_soyad, k.email
                               FROM kullanicilar k
                               WHERE k.rol = 'Sorumlu' AND k.aktif = 1
                               AND k.id NOT IN (
                                   SELECT s.sorumlu_id FROM sorumluluklar s WHERE s.tedarikci_id = ?
                               )
                               ORDER BY k.ad_soyad";
    $atanmamis_sorumlular_stmt = $db->prepare($atanmamis_sorumlular_sql);
    $atanmamis_sorumlular_stmt->execute([$tedarikci_id]);
    $atanabilecekler = $atanmamis_sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($mod == "sorumlu") {
    $sorumluluklar_sql = "SELECT s.id, s.tedarikci_id, s.sorumlu_id, s.olusturma_tarihi, 
                         t.firma_adi, t.firma_kodu, t.aktif
                         FROM sorumluluklar s
                         JOIN tedarikciler t ON s.tedarikci_id = t.id
                         WHERE s.sorumlu_id = ?
                         ORDER BY t.firma_adi";
    $sorumluluklar_stmt = $db->prepare($sorumluluklar_sql);
    $sorumluluklar_stmt->execute([$sorumlu_id]);
    $sorumluluklar = $sorumluluklar_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Atanmamış tedarikçileri getir
    $atanmamis_tedarikciler_sql = "SELECT t.id, t.firma_adi, t.firma_kodu
                                 FROM tedarikciler t
                                 WHERE t.aktif = 1
                                 AND t.id NOT IN (
                                     SELECT s.tedarikci_id FROM sorumluluklar s WHERE s.sorumlu_id = ?
                                 )
                                 ORDER BY t.firma_adi";
    $atanmamis_tedarikciler_stmt = $db->prepare($atanmamis_tedarikciler_sql);
    $atanmamis_tedarikciler_stmt->execute([$sorumlu_id]);
    $atanabilecekler = $atanmamis_tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);
}

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
    <title><?= guvenli($sayfa_basligi) ?> - Admin Paneli</title>
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
            background-color: #f8f9fc !important;
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
                    <a class="nav-link <?= $mod == 'tedarikci' ? 'active' : '' ?>" href="tedarikciler.php">
                        <i class="bi bi-building"></i> Tedarikçiler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $mod == 'sorumlu' ? 'active' : '' ?>" href="sorumlular.php">
                        <i class="bi bi-people"></i> Sorumlular
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projeler.php">
                        <i class="bi bi-diagram-3"></i> Projeler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kullanicilar.php">
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
                <h1 class="h2"><?= guvenli($sayfa_basligi) ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?= $geri_donulecek_sayfa ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Geri Dön
                    </a>
                </div>
            </div>

            <!-- Hata ve mesaj bildirimleri -->
            <?php if (!empty($hatalar)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Hata!</strong>
                    <ul class="mb-0">
                        <?php foreach ($hatalar as $hata): ?>
                            <li><?= guvenli($hata) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($mesajlar)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($mesajlar as $mesaj): ?>
                            <li><?= guvenli($mesaj) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <!-- Mevcut İlişkiler Kartı -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php if ($mod == "tedarikci"): ?>
                                    Atanmış Sorumlular
                                <?php else: ?>
                                    Atanmış Tedarikçiler
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($sorumluluklar) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <?php if ($mod == "tedarikci"): ?>
                                                    <th>Sorumlu Adı</th>
                                                    <th>E-posta</th>
                                                    <th>Telefon</th>
                                                <?php else: ?>
                                                    <th>Firma Adı</th>
                                                    <th>Firma Kodu</th>
                                                    <th>Durum</th>
                                                <?php endif; ?>
                                                <th>Atanma Tarihi</th>
                                                <th>İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sorumluluklar as $sorumluluk): ?>
                                                <tr>
                                                    <?php if ($mod == "tedarikci"): ?>
                                                        <td><?= guvenli($sorumluluk['ad_soyad']) ?></td>
                                                        <td><?= guvenli($sorumluluk['email']) ?></td>
                                                        <td><?= !empty($sorumluluk['telefon']) ? guvenli($sorumluluk['telefon']) : '-' ?></td>
                                                    <?php else: ?>
                                                        <td><?= guvenli($sorumluluk['firma_adi']) ?></td>
                                                        <td><?= guvenli($sorumluluk['firma_kodu']) ?></td>
                                                        <td>
                                                            <span class="badge <?= $sorumluluk['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                                                <?= $sorumluluk['aktif'] ? 'Aktif' : 'Pasif' ?>
                                                            </span>
                                                        </td>
                                                    <?php endif; ?>
                                                    <td><?= date('d.m.Y', strtotime($sorumluluk['olusturma_tarihi'])) ?></td>
                                                    <td>
                                                        <form action="" method="post" class="d-inline" onsubmit="return confirm('Bu sorumluluğu kaldırmak istediğinizden emin misiniz?');">
                                                            <input type="hidden" name="sorumluluk_id" value="<?= $sorumluluk['id'] ?>">
                                                            <button type="submit" name="sorumluluk_kaldir" class="btn btn-sm btn-danger">
                                                                <i class="bi bi-trash"></i> Kaldır
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted my-3">
                                    <?php if ($mod == "tedarikci"): ?>
                                        Bu tedarikçiye henüz sorumlu atanmamış.
                                    <?php else: ?>
                                        Bu sorumluya henüz tedarikçi atanmamış.
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Yeni İlişki Ekleme Kartı -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <?php if ($mod == "tedarikci"): ?>
                                    Yeni Sorumlu Ekle
                                <?php else: ?>
                                    Yeni Tedarikçi Ekle
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($atanabilecekler) > 0): ?>
                                <form action="" method="post">
                                    <div class="mb-3">
                                        <?php if ($mod == "tedarikci"): ?>
                                            <label for="sorumlu_id" class="form-label">Sorumlu Seçin</label>
                                            <select class="form-select" id="sorumlu_id" name="sorumlu_id" required>
                                                <option value="">Sorumlu Seçin</option>
                                                <?php foreach ($atanabilecekler as $sorumlu): ?>
                                                    <option value="<?= $sorumlu['id'] ?>">
                                                        <?= guvenli($sorumlu['ad_soyad']) ?> (<?= guvenli($sorumlu['email']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php else: ?>
                                            <label for="tedarikci_id" class="form-label">Tedarikçi Seçin</label>
                                            <select class="form-select" id="tedarikci_id" name="tedarikci_id" required>
                                                <option value="">Tedarikçi Seçin</option>
                                                <?php foreach ($atanabilecekler as $tedarikci): ?>
                                                    <option value="<?= $tedarikci['id'] ?>">
                                                        <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-grid">
                                        <button type="submit" name="sorumluluk_ekle" class="btn btn-primary">
                                            <i class="bi bi-plus-circle"></i> 
                                            <?php if ($mod == "tedarikci"): ?>
                                                Sorumlu Ekle
                                            <?php else: ?>
                                                Tedarikçi Ekle
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <?php if ($mod == "tedarikci"): ?>
                                        Atanabilecek aktif sorumlu kullanıcı bulunmamaktadır. 
                                        <a href="kullanici_ekle.php?rol=Sorumlu" class="alert-link">Yeni sorumlu ekle</a>
                                    <?php else: ?>
                                        Atanabilecek aktif tedarikçi bulunmamaktadır. 
                                        <a href="tedarikci_ekle.php" class="alert-link">Yeni tedarikçi ekle</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Bilgi Kartı -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Bilgilendirme</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-0">
                                <?php if ($mod == "tedarikci"): ?>
                                    <p><i class="bi bi-info-circle me-2"></i> <strong><?= guvenli($tedarikci['firma_adi']) ?></strong> tedarikçisine birden fazla sorumlu atayabilirsiniz.</p>
                                    <p class="mb-0">Sorumlu atandığında, ilgili kullanıcıya otomatik bildirim gönderilir ve tedarikçiye ait siparişleri yönetme yetkisi verilir.</p>
                                <?php else: ?>
                                    <p><i class="bi bi-info-circle me-2"></i> <strong><?= guvenli($sorumlu['ad_soyad']) ?></strong> sorumlusuna birden fazla tedarikçi atayabilirsiniz.</p>
                                    <p class="mb-0">Tedarikçi atandığında, sorumlu kullanıcıya otomatik bildirim gönderilir ve ilgili tedarikçiye ait siparişleri yönetme yetkisi verilir.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 