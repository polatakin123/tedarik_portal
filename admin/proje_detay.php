<?php
// admin/proje_detay.php - Proje detay sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Proje ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: projeler.php?hata=" . urlencode("Proje ID belirtilmedi"));
    exit;
}

$proje_id = intval($_GET['id']);

// Proje bilgilerini getir
$proje_sql = "SELECT * FROM projeler WHERE id = ?";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$proje_id]);
$proje = $proje_stmt->fetch(PDO::FETCH_ASSOC);

if (!$proje) {
    header("Location: projeler.php?hata=" . urlencode("Proje bulunamadı"));
    exit;
}

// Proje siparişlerini getir (son 10 sipariş)
$siparisler_sql = "SELECT s.*, sd.durum_adi, t.firma_adi as tedarikci_adi, t.firma_kodu as tedarikci_kodu, k.ad_soyad as sorumlu_adi
                  FROM siparisler s
                  LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                  LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                  LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
                  WHERE s.proje_id = ?
                  ORDER BY s.olusturma_tarihi DESC
                  LIMIT 10";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$proje_id]);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durum istatistikleri
$siparis_istatistik_sql = "SELECT sd.durum_adi, COUNT(s.id) as sayi
                          FROM siparis_durumlari sd
                          LEFT JOIN siparisler s ON sd.id = s.durum_id AND s.proje_id = ?
                          GROUP BY sd.id, sd.durum_adi
                          ORDER BY sd.id";
$siparis_istatistik_stmt = $db->prepare($siparis_istatistik_sql);
$siparis_istatistik_stmt->execute([$proje_id]);
$siparis_istatistikleri = $siparis_istatistik_stmt->fetchAll(PDO::FETCH_ASSOC);

// Proje tedarikçi dağılımı
$tedarikci_dagilim_sql = "SELECT t.firma_adi, COUNT(s.id) as siparis_sayisi
                         FROM tedarikciler t
                         INNER JOIN siparisler s ON t.id = s.tedarikci_id
                         WHERE s.proje_id = ?
                         GROUP BY t.id, t.firma_adi
                         ORDER BY siparis_sayisi DESC
                         LIMIT 5";
$tedarikci_dagilim_stmt = $db->prepare($tedarikci_dagilim_sql);
$tedarikci_dagilim_stmt->execute([$proje_id]);
$tedarikci_dagilim = $tedarikci_dagilim_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Proje Detayı: <?= guvenli($proje['proje_adi']) ?> - Admin Paneli</title>
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
                    <a class="nav-link active" href="projeler.php">
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
                    Proje: <?= guvenli($proje['proje_adi']) ?>
                    <span class="badge <?= $proje['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                        <?= $proje['aktif'] ? 'Aktif' : 'Pasif' ?>
                    </span>
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="projeler.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Projelere Dön
                    </a>
                    <a href="proje_duzenle.php?id=<?= $proje_id ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="bi bi-pencil"></i> Düzenle
                    </a>
                    <a href="siparis_ekle.php?proje_id=<?= $proje_id ?>" class="btn btn-sm btn-outline-success">
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
                    <!-- Proje Bilgileri Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Proje Bilgileri</h5>
                            <div>
                                <span class="badge <?= $proje['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $proje['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Proje Adı:</p>
                                    <p><?= guvenli($proje['proje_adi']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Proje Kodu:</p>
                                    <p><?= guvenli($proje['proje_kodu']) ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Sorumlu Yönetici:</p>
                                    <p><?= !empty($proje['sorumlu_yonetici']) ? guvenli($proje['sorumlu_yonetici']) : '-' ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Kayıt Tarihi:</p>
                                    <p><?= date('d.m.Y', strtotime($proje['olusturma_tarihi'])) ?></p>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Başlangıç Tarihi:</p>
                                    <p><?= !empty($proje['baslangic_tarihi']) ? date('d.m.Y', strtotime($proje['baslangic_tarihi'])) : '-' ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="fw-bold mb-1">Bitiş Tarihi:</p>
                                    <p><?= !empty($proje['bitis_tarihi']) ? date('d.m.Y', strtotime($proje['bitis_tarihi'])) : '-' ?></p>
                                </div>
                            </div>
                            <?php if (!empty($proje['aciklama'])): ?>
                                <div class="mb-3">
                                    <p class="fw-bold mb-1">Açıklama:</p>
                                    <p><?= nl2br(guvenli($proje['aciklama'])) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

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

                    <!-- Tedarikçi Dağılımı Kartı -->
                    <?php if (count($tedarikci_dagilim) > 0): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Tedarikçi Dağılımı</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Tedarikçi</th>
                                                <th>Sipariş Sayısı</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($tedarikci_dagilim as $tedarikci): ?>
                                                <tr>
                                                    <td><?= guvenli($tedarikci['firma_adi']) ?></td>
                                                    <td>
                                                        <span class="badge bg-primary"><?= $tedarikci['siparis_sayisi'] ?></span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <!-- Son Siparişler Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Proje Siparişleri</h5>
                            <a href="siparisler.php?proje_id=<?= $proje_id ?>" class="btn btn-sm btn-primary">
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
                                                <th>Tedarikçi</th>
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
                                                    <td><?= guvenli($siparis['tedarikci_kodu']) ?></td>
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
                                <p class="text-center text-muted my-3">Bu proje için henüz sipariş oluşturulmamış.</p>
                                <div class="text-center">
                                    <a href="siparis_ekle.php?proje_id=<?= $proje_id ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-plus-circle"></i> Sipariş Oluştur
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Proje Takvimi Bilgisi -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Proje Takvimi</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $bugun = new DateTime();
                            $baslangic = !empty($proje['baslangic_tarihi']) ? new DateTime($proje['baslangic_tarihi']) : null;
                            $bitis = !empty($proje['bitis_tarihi']) ? new DateTime($proje['bitis_tarihi']) : null;
                            
                            if ($baslangic && $bitis) {
                                $toplam_gun = $baslangic->diff($bitis)->days;
                                $gecen_gun = $baslangic->diff($bugun)->days;
                                
                                if ($bugun < $baslangic) {
                                    // Proje başlamadı
                                    $ilerleme = 0;
                                    $durum = "Başlamadı";
                                    $durum_renk = "secondary";
                                    $kalan_gun = $baslangic->diff($bugun)->days;
                                    $mesaj = "Projenin başlamasına $kalan_gun gün kaldı.";
                                } elseif ($bugun > $bitis) {
                                    // Proje bitti
                                    $ilerleme = 100;
                                    $durum = "Tamamlandı";
                                    $durum_renk = "success";
                                    $gecen_gun = $bitis->diff($bugun)->days;
                                    $mesaj = "Proje $gecen_gun gün önce tamamlandı.";
                                } else {
                                    // Proje devam ediyor
                                    $ilerleme = min(100, round(($gecen_gun / $toplam_gun) * 100));
                                    $durum = "Devam Ediyor";
                                    $durum_renk = "primary";
                                    $kalan_gun = $bitis->diff($bugun)->days;
                                    $mesaj = "Projenin tamamlanmasına $kalan_gun gün kaldı.";
                                }
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Proje İlerlemesi</span>
                                        <span class="badge bg-<?= $durum_renk ?>"><?= $durum ?></span>
                                    </div>
                                    <div class="progress" style="height: 10px;">
                                        <div class="progress-bar bg-<?= $durum_renk ?>" role="progressbar" 
                                             style="width: <?= $ilerleme ?>%;" aria-valuenow="<?= $ilerleme ?>" 
                                             aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <div class="text-muted small mt-1">%<?= $ilerleme ?> tamamlandı</div>
                                </div>
                                
                                <div class="row mt-4">
                                    <div class="col-md-6">
                                        <p class="fw-bold mb-1">Başlangıç Tarihi:</p>
                                        <p><?= $baslangic->format('d.m.Y') ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="fw-bold mb-1">Bitiş Tarihi:</p>
                                        <p><?= $bitis->format('d.m.Y') ?></p>
                                    </div>
                                </div>
                                
                                <div class="mt-2">
                                    <span class="text-<?= $durum_renk ?>"><?= $mesaj ?></span>
                                </div>
                            <?php } else { ?>
                                <div class="alert alert-warning mb-0">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                    Proje için başlangıç ve/veya bitiş tarihi belirlenmemiş.
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 