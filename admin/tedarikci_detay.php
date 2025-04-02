<?php
// admin/tedarikci_detay.php - Tedarikçi detay sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Tedarikçi ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi ID belirtilmedi"));
    exit;
}

$tedarikci_id = intval($_GET['id']);

// Tedarikçi bilgilerini getir
$tedarikci_sql = "SELECT * FROM tedarikciler WHERE id = ?";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute([$tedarikci_id]);
$tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

if (!$tedarikci) {
    header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi bulunamadı"));
    exit;
}

// Tedarikçi sorumlularını getir
$sorumlular_sql = "SELECT k.id, k.ad_soyad, k.email, k.telefon, k.son_giris
                  FROM kullanicilar k
                  INNER JOIN sorumluluklar s ON k.id = s.sorumlu_id
                  WHERE s.tedarikci_id = ? AND k.aktif = 1
                  ORDER BY k.ad_soyad";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute([$tedarikci_id]);
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi siparişlerini getir (son 10 sipariş)
$siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, p.proje_kodu, k.ad_soyad as sorumlu_adi
                  FROM siparisler s
                  LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                  LEFT JOIN projeler p ON s.proje_id = p.id
                  LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
                  WHERE s.tedarikci_id = ?
                  ORDER BY s.olusturma_tarihi DESC
                  LIMIT 10";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$tedarikci_id]);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi kullanıcılarını getir
$kullanicilar_sql = "SELECT k.id, k.ad_soyad, k.email, k.kullanici_adi, k.aktif, k.son_giris
                    FROM kullanicilar k
                    INNER JOIN kullanici_tedarikci_iliskileri kti ON k.id = kti.kullanici_id
                    WHERE kti.tedarikci_id = ?
                    ORDER BY k.ad_soyad";
$kullanicilar_stmt = $db->prepare($kullanicilar_sql);
$kullanicilar_stmt->execute([$tedarikci_id]);
$kullanicilar = $kullanicilar_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Sipariş durum istatistikleri
$siparis_istatistik_sql = "SELECT sd.durum_adi, COUNT(s.id) as sayi
                          FROM siparis_durumlari sd
                          LEFT JOIN siparisler s ON sd.id = s.durum_id AND s.tedarikci_id = ?
                          GROUP BY sd.id, sd.durum_adi
                          ORDER BY sd.id";
$siparis_istatistik_stmt = $db->prepare($siparis_istatistik_sql);
$siparis_istatistik_stmt->execute([$tedarikci_id]);
$siparis_istatistikleri = $siparis_istatistik_stmt->fetchAll(PDO::FETCH_ASSOC);

// Duruma göre renk sınıfı belirle
function durumRengiGetir($durum_id) {
    switch ($durum_id) {
        case 1: return "info"; // Açık
        case 2: return "primary"; // İşlemde
        case 3: return "warning"; // Beklemede
        case 4: return "success"; // Tamamlandı
        case 5: return "danger"; // İptal Edildi
        default: return "secondary";
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tedarikçi Detayı: <?= guvenli($tedarikci['firma_adi']) ?> - Admin Paneli</title>
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
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
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
        .btn-xs {
            padding: .125rem .25rem;
            font-size: .75rem;
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
                    <a class="nav-link active" href="tedarikciler.php">
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
                <h1 class="h2">
                    Tedarikçi: <?= guvenli($tedarikci['firma_adi']) ?>
                    <span class="badge <?= $tedarikci['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                        <?= $tedarikci['aktif'] ? 'Aktif' : 'Pasif' ?>
                    </span>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="tedarikciler.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Tedarikçilere Dön
                    </a>
                    <a href="tedarikci_duzenle.php?id=<?= $tedarikci_id ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-pencil"></i> Düzenle
                    </a>
                    <a href="tedarikci_sorumlular.php?id=<?= $tedarikci_id ?>" class="btn btn-sm btn-outline-warning me-2">
                        <i class="bi bi-person-check"></i> Sorumlu Ata
                    </a>
                    <a href="siparis_ekle.php?tedarikci_id=<?= $tedarikci_id ?>" class="btn btn-sm btn-outline-success">
                        <i class="bi bi-plus-circle"></i> Sipariş Oluştur
                    </a>
                </div>
            </div>

            <?php if (isset($_GET['mesaj'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= guvenli($_GET['mesaj']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['hata'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= guvenli($_GET['hata']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <!-- Tedarikçi Bilgileri Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Tedarikçi Bilgileri</h5>
                            <div>
                                <span class="badge <?= $tedarikci['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $tedarikci['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Firma Adı:</p>
                                    <p><?= guvenli($tedarikci['firma_adi']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Firma Kodu:</p>
                                    <p><?= guvenli($tedarikci['firma_kodu']) ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Yetkili Kişi:</p>
                                    <p><?= !empty($tedarikci['yetkili_kisi']) ? guvenli($tedarikci['yetkili_kisi']) : '-' ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Telefon:</p>
                                    <p><?= !empty($tedarikci['telefon']) ? guvenli($tedarikci['telefon']) : '-' ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">E-posta:</p>
                                    <p><?= !empty($tedarikci['email']) ? guvenli($tedarikci['email']) : '-' ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Kayıt Tarihi:</p>
                                    <p><?= date('d.m.Y', strtotime($tedarikci['olusturma_tarihi'])) ?></p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <p class="fw-bold mb-1">Adres:</p>
                                <p><?= !empty($tedarikci['adres']) ? nl2br(guvenli($tedarikci['adres'])) : '-' ?></p>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Vergi No:</p>
                                    <p><?= !empty($tedarikci['vergi_no']) ? guvenli($tedarikci['vergi_no']) : '-' ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Vergi Dairesi:</p>
                                    <p><?= !empty($tedarikci['vergi_dairesi']) ? guvenli($tedarikci['vergi_dairesi']) : '-' ?></p>
                                </div>
                            </div>
                            <?php if (!empty($tedarikci['aciklama'])): ?>
                                <div class="mb-3">
                                    <p class="fw-bold mb-1">Açıklama:</p>
                                    <p><?= nl2br(guvenli($tedarikci['aciklama'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sorumlular Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Sorumlular</h5>
                            <a href="tedarikci_sorumlular.php?id=<?= $tedarikci_id ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-person-plus"></i> Sorumlu Ata
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($sorumlular) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($sorumlular as $sorumlu): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= guvenli($sorumlu['ad_soyad']) ?></strong><br>
                                                    <small class="text-muted"><?= guvenli($sorumlu['email']) ?></small>
                                                    <?php if (!empty($sorumlu['telefon'])): ?>
                                                        <small class="text-muted d-block"><?= guvenli($sorumlu['telefon']) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <a href="kullanici_detay.php?id=<?= $sorumlu['id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-center text-muted my-3">Bu tedarikçi için henüz sorumlu atanmamış.</p>
                                <div class="text-center">
                                    <a href="tedarikci_sorumlular.php?id=<?= $tedarikci_id ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-person-plus"></i> Sorumlu Ata
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Sipariş İstatistikleri Kartı -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Sipariş İstatistikleri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <?php 
                                $toplam_siparis = 0;
                                foreach ($siparis_istatistikleri as $istatistik) {
                                    $toplam_siparis += $istatistik['sayi'];
                                }
                                ?>
                                <div class="col-md-12 mb-3">
                                    <h6 class="fw-bold">Toplam: <?= $toplam_siparis ?> Sipariş</h6>
                                </div>
                                <?php foreach ($siparis_istatistikleri as $index => $istatistik): ?>
                                    <?php 
                                    $durum_id = $index + 1; // Sipariş durumları genellikle 1'den başlar
                                    $durum_rengi = durumRengiGetir($durum_id);
                                    $yuzde = $toplam_siparis > 0 ? round(($istatistik['sayi'] / $toplam_siparis) * 100) : 0;
                                    ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="d-flex justify-content-between">
                                            <span><?= guvenli($istatistik['durum_adi']) ?>:</span>
                                            <span class="badge bg-<?= $durum_rengi ?>"><?= $istatistik['sayi'] ?></span>
                                        </div>
                                        <div class="progress mt-1" style="height: 5px;">
                                            <div class="progress-bar bg-<?= $durum_rengi ?>" role="progressbar" 
                                                 style="width: <?= $yuzde ?>%;" aria-valuenow="<?= $yuzde ?>" 
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Son Siparişler Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Son Siparişler</h5>
                            <a href="siparisler.php?tedarikci_id=<?= $tedarikci_id ?>" class="btn btn-sm btn-primary">
                                Tüm Siparişler
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($siparisler) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sipariş No</th>
                                                <th>Proje</th>
                                                <th>Sorumlu</th>
                                                <th>Durum</th>
                                                <th>Tarih</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($siparisler as $siparis): ?>
                                                <?php $durum_rengi = durumRengiGetir($siparis['durum_id']); ?>
                                                <tr>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="text-decoration-none">
                                                            <?= guvenli($siparis['siparis_no']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= guvenli($siparis['proje_kodu']) ?></td>
                                                    <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $durum_rengi ?>">
                                                            <?= guvenli($siparis['durum_adi']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('d.m.Y', strtotime($siparis['olusturma_tarihi'])) ?></td>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-xs btn-outline-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted my-3">Bu tedarikçi için henüz sipariş oluşturulmamış.</p>
                                <div class="text-center">
                                    <a href="siparis_ekle.php?tedarikci_id=<?= $tedarikci_id ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-plus-circle"></i> Sipariş Oluştur
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tedarikçi Kullanıcıları Kartı -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Tedarikçi Kullanıcıları</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($kullanicilar) > 0): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($kullanicilar as $kullanici): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?= guvenli($kullanici['ad_soyad']) ?></strong>
                                                    <span class="badge <?= $kullanici['aktif'] ? 'bg-success' : 'bg-danger' ?> ms-2">
                                                        <?= $kullanici['aktif'] ? 'Aktif' : 'Pasif' ?>
                                                    </span><br>
                                                    <small class="text-muted"><?= guvenli($kullanici['email']) ?></small>
                                                    <small class="text-muted d-block">Kullanıcı Adı: <?= guvenli($kullanici['kullanici_adi']) ?></small>
                                                </div>
                                                <div>
                                                    <a href="kullanici_detay.php?id=<?= $kullanici['id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-center text-muted my-3">Bu tedarikçi için henüz kullanıcı tanımlanmamış.</p>
                                <div class="text-center">
                                    <a href="kullanici_ekle.php?rol=Tedarikci&firma_id=<?= $tedarikci_id ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-person-plus"></i> Kullanıcı Ekle
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 