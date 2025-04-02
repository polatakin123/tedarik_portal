<?php
// admin/index.php - Admin paneli ana sayfası
require_once '../config.php';
adminYetkisiKontrol();

// İstatistikler
// Sipariş istatistikleri
$siparisler_sql = "SELECT COUNT(*) as toplam, 
                  SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                  SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                  SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                  FROM siparisler";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute();
$siparisler_istatistik = $siparisler_stmt->fetch(PDO::FETCH_ASSOC);

// Tedarikçi sayısı
$tedarikciler_sql = "SELECT COUNT(*) as toplam FROM tedarikciler";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikci_sayisi = $tedarikciler_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Sorumlu sayısı
$sorumlular_sql = "SELECT COUNT(*) as toplam FROM kullanicilar WHERE rol = 'Sorumlu'";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute();
$sorumlu_sayisi = $sorumlular_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Proje sayısı
$projeler_sql = "SELECT COUNT(*) as toplam FROM projeler";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute();
$proje_sayisi = $projeler_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Son 10 sipariş
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, t.firma_adi, p.proje_adi, 
                      k.ad_soyad as sorumlu_adi
                      FROM siparisler s
                      LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                      LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                      LEFT JOIN projeler p ON s.proje_id = p.id
                      LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
                      ORDER BY s.olusturma_tarihi DESC
                      LIMIT 10";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute();
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Son 5 bildirim
$kullanici_id = $_SESSION['kullanici_id'];
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$kullanici_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Yaklaşan teslimatlar
$yaklasan_teslimler_sql = "SELECT s.*, sd.durum_adi, t.firma_adi, p.proje_adi
                          FROM siparisler s
                          LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                          LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                          LEFT JOIN projeler p ON s.proje_id = p.id
                          WHERE s.durum_id = 1
                          AND s.teslim_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                          ORDER BY s.teslim_tarihi ASC
                          LIMIT 5";
$yaklasan_teslimler_stmt = $db->prepare($yaklasan_teslimler_sql);
$yaklasan_teslimler_stmt->execute();
$yaklasan_teslimler = $yaklasan_teslimler_stmt->fetchAll(PDO::FETCH_ASSOC);

$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Paneli - Tedarik Portalı</title>
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
            transition: all 0.3s;
            margin-bottom: 1.5rem;
        }
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        .card-primary .card-header {
            background-color: #4e73df;
            color: white;
        }
        .card-success .card-header {
            background-color: #1cc88a;
            color: white;
        }
        .card-info .card-header {
            background-color: #36b9cc;
            color: white;
        }
        .card-warning .card-header {
            background-color: #f6c23e;
            color: white;
        }
        .table-responsive {
            overflow-x: auto;
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
                    <a class="nav-link active" href="index.php">
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
                <h1 class="h2">Özet Paneli</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="siparis_ekle.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus"></i> Yeni Sipariş
                        </a>
                        <a href="tedarikci_ekle.php" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-plus"></i> Yeni Tedarikçi
                        </a>
                    </div>
                </div>
            </div>

            <!-- İstatistikler -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card card-primary h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Toplam Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-cart h1 text-primary"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $siparisler_istatistik['toplam'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="siparisler.php" class="text-primary">Siparişleri Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-success h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Aktif Tedarikçiler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-building h1 text-success"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $tedarikci_sayisi ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="tedarikciler.php" class="text-success">Tedarikçileri Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-info h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Aktif Sorumlular</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-people h1 text-info"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $sorumlu_sayisi ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="sorumlular.php" class="text-info">Sorumluları Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card card-warning h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Aktif Projeler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-diagram-3 h1 text-warning"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $proje_sayisi ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="projeler.php" class="text-warning">Projeleri Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sipariş Durumları -->
            <div class="row mt-4">
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white">Açık Siparişler</h6>
                                    <h2 class="mb-0"><?= $siparisler_istatistik['acik'] ?? 0 ?></h2>
                                </div>
                                <i class="bi bi-clipboard-check h1"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="siparisler.php?durum=1" class="text-white text-decoration-none">Detayları Görüntüle</a>
                            <i class="bi bi-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white">Kapalı Siparişler</h6>
                                    <h2 class="mb-0"><?= $siparisler_istatistik['kapali'] ?? 0 ?></h2>
                                </div>
                                <i class="bi bi-check-circle h1"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="siparisler.php?durum=2" class="text-white text-decoration-none">Detayları Görüntüle</a>
                            <i class="bi bi-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-warning text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white">Bekleyen Siparişler</h6>
                                    <h2 class="mb-0"><?= $siparisler_istatistik['beklemede'] ?? 0 ?></h2>
                                </div>
                                <i class="bi bi-hourglass-split h1"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="siparisler.php?durum=3" class="text-white text-decoration-none">Detayları Görüntüle</a>
                            <i class="bi bi-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white">Hızlı İşlemler</h6>
                                    <p class="mb-0">Yönetim Araçları</p>
                                </div>
                                <i class="bi bi-gear h1"></i>
                            </div>
                        </div>
                        <div class="card-footer d-flex align-items-center justify-content-between">
                            <a href="ayarlar.php" class="text-white text-decoration-none">Ayarlara Git</a>
                            <i class="bi bi-arrow-right text-white"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Son Siparişler ve Bildirimler -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">Son Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Tedarikçi</th>
                                            <th>Proje</th>
                                            <th>Sorumlu</th>
                                            <th>Teslim Tarihi</th>
                                            <th>Durum</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($son_siparisler) > 0): ?>
                                            <?php foreach ($son_siparisler as $siparis): ?>
                                                <tr>
                                                    <td><?= guvenli($siparis['siparis_no']) ?></td>
                                                    <td><?= guvenli($siparis['firma_adi']) ?></td>
                                                    <td><?= guvenli($siparis['proje_adi']) ?></td>
                                                    <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
                                                    <td><?= date('d.m.Y', strtotime($siparis['teslim_tarihi'])) ?></td>
                                                    <td>
                                                        <?php
                                                            $durum_renk = '';
                                                            switch ($siparis['durum_id']) {
                                                                case 1: $durum_renk = 'success'; break; // Açık
                                                                case 2: $durum_renk = 'secondary'; break; // Kapalı
                                                                case 3: $durum_renk = 'warning'; break; // Beklemede
                                                                case 4: $durum_renk = 'danger'; break; // İptal
                                                                default: $durum_renk = 'primary';
                                                            }
                                                        ?>
                                                        <span class="badge bg-<?= $durum_renk ?>"><?= guvenli($siparis['durum_adi']) ?></span>
                                                    </td>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="siparis_duzenle.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-warning">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Henüz sipariş bulunmamaktadır.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="siparisler.php" class="btn btn-primary">Tüm Siparişleri Görüntüle</a>
                            </div>
                        </div>
                    </div>

                    <!-- Yaklaşan Teslimatlar -->
                    <div class="card mt-4">
                        <div class="card-header bg-warning text-white">
                            <h6 class="m-0 font-weight-bold">Yaklaşan Teslimatlar</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Tedarikçi</th>
                                            <th>Proje</th>
                                            <th>Teslim Tarihi</th>
                                            <th>Kalan Gün</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($yaklasan_teslimler) > 0): ?>
                                            <?php foreach ($yaklasan_teslimler as $teslim): ?>
                                                <?php 
                                                    $teslim_tarihi = new DateTime($teslim['teslim_tarihi']);
                                                    $bugun = new DateTime();
                                                    $kalan_gun = $bugun->diff($teslim_tarihi)->days;
                                                    $renk_kodu = $kalan_gun <= 7 ? 'danger' : 'warning';
                                                ?>
                                                <tr>
                                                    <td><?= guvenli($teslim['siparis_no']) ?></td>
                                                    <td><?= guvenli($teslim['firma_adi']) ?></td>
                                                    <td><?= guvenli($teslim['proje_adi']) ?></td>
                                                    <td><?= date('d.m.Y', strtotime($teslim['teslim_tarihi'])) ?></td>
                                                    <td><span class="badge bg-<?= $renk_kodu ?>"><?= $kalan_gun ?> gün</span></td>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $teslim['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center">30 gün içinde yaklaşan teslimat bulunmamaktadır.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <!-- Son Bildirimler -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="m-0 font-weight-bold">Son Bildirimler</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($bildirimler) > 0): ?>
                                <ul class="list-group">
                                    <?php foreach ($bildirimler as $bildirim): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= guvenli(mb_substr($bildirim['mesaj'], 0, 50)) ?>...</h6>
                                                <small><?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?></small>
                                            </div>
                                            <p class="mb-1">
                                                <?php if ($bildirim['siparis_no']): ?>
                                                    <span class="badge bg-primary">Sipariş: <?= guvenli($bildirim['siparis_no']) ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <small>
                                                <a href="bildirim_goruntule.php?id=<?= $bildirim['id'] ?>" class="text-primary">Detayları görüntüle</a>
                                            </small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-center">Bildirim bulunmamaktadır.</p>
                            <?php endif; ?>
                            <div class="text-center mt-3">
                                <a href="bildirimler.php" class="btn btn-info">Tüm Bildirimleri Görüntüle</a>
                            </div>
                        </div>
                    </div>

                    <!-- Hızlı İşlemler -->
                    <div class="card mt-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="m-0 font-weight-bold">Hızlı İşlemler</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <a href="siparis_ekle.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-plus-circle text-success me-2"></i> Yeni Sipariş Ekle
                                        </div>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </a>
                                <a href="tedarikci_ekle.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-building-add text-primary me-2"></i> Yeni Tedarikçi Ekle
                                        </div>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </a>
                                <a href="kullanici_ekle.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-person-plus text-info me-2"></i> Yeni Kullanıcı Ekle
                                        </div>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </a>
                                <a href="proje_ekle.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-folder-plus text-warning me-2"></i> Yeni Proje Ekle
                                        </div>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </a>
                                <a href="raporlar.php" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-file-earmark-bar-graph text-danger me-2"></i> Raporları Görüntüle
                                        </div>
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </a>
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