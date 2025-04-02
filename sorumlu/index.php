<?php
// sorumlu/index.php - Sorumlu paneli ana sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Kullanıcının sorumlu olduğu tedarikçileri al
$sorumlu_id = $_SESSION['kullanici_id'];
$tedarikciler_sql = "SELECT t.id, t.firma_adi, t.firma_kodu
                    FROM tedarikciler t
                    INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                    WHERE s.sorumlu_id = ? AND t.aktif = 1
                    ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sorumlu olunan tedarikçi sayısı
$tedarikci_sayisi = count($tedarikciler);

// Tedarikçilerin sipariş istatistikleri
$siparisler_sql = "SELECT COUNT(*) as toplam, 
                  SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                  SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                  SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                  FROM siparisler
                  WHERE sorumlu_id = ?";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$sorumlu_id]);
$siparisler_istatistik = $siparisler_stmt->fetch(PDO::FETCH_ASSOC);

// Son siparişler
$in  = str_repeat('?,', count($tedarikciler) - 1) . '?';
$tedarikci_idleri = array_column($tedarikciler, 'id');

$son_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, t.firma_adi
                      FROM siparisler s
                      LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                      LEFT JOIN projeler p ON s.proje_id = p.id
                      LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                      WHERE s.tedarikci_id IN ($in) OR s.sorumlu_id = ?
                      ORDER BY s.olusturma_tarihi DESC
                      LIMIT 10";

// Parametreleri birleştir
$params = array_merge($tedarikci_idleri, [$sorumlu_id]);
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute($params);
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Son bildirimler
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$sorumlu_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sorumlu Paneli - Tedarik Portalı</title>
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
            background-color: #36b9cc;
            transition: all 0.3s;
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
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s;
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
            <p>Sorumlu Paneli</p>
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
                    <a class="nav-link" href="tedarikcilerim.php">
                        <i class="bi bi-building"></i> Tedarikçilerim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siparis_guncelle.php">
                        <i class="bi bi-pencil-square"></i> Sipariş Güncelle
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="raporlar.php">
                        <i class="bi bi-graph-up"></i> Raporlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profil.php">
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
                <h1 class="h2">Sorumlu Paneli Özeti</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="siparis_guncelle.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil-square"></i> Sipariş Güncelle
                        </a>
                    </div>
                </div>
            </div>

            <!-- İstatistikler -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-primary h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Sorumlu Olduğum Tedarikçiler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-building h1 text-primary"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $tedarikci_sayisi ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="tedarikcilerim.php" class="text-primary">Tedarikçilerimi Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-success h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Açık Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-clipboard-check h1 text-success"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $siparisler_istatistik['acik'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="siparisler.php?durum=1" class="text-success">Açık Siparişleri Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-info h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Bekleyen Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-hourglass-split h1 text-info"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $siparisler_istatistik['beklemede'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="siparisler.php?durum=3" class="text-info">Bekleyen Siparişleri Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card card-warning h-100">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Tamamlanan Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col-auto">
                                    <i class="bi bi-check-circle h1 text-warning"></i>
                                </div>
                                <div class="col ml-3">
                                    <div class="h2 mb-0 font-weight-bold"><?= $siparisler_istatistik['kapali'] ?? 0 ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-light">
                            <a href="siparisler.php?durum=2" class="text-warning">Tamamlanan Siparişleri Görüntüle <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tedarikçiler ve Son Siparişler -->
            <div class="row">
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">Sorumlu Olduğum Tedarikçiler</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($tedarikciler) > 0): ?>
                                <ul class="list-group">
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="text-decoration-none">
                                                    <?= guvenli($tedarikci['firma_adi']) ?>
                                                </a>
                                                <small class="d-block text-muted"><?= guvenli($tedarikci['firma_kodu']) ?></small>
                                            </div>
                                            <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-center">Henüz size atanmış tedarikçi bulunmamaktadır.</p>
                            <?php endif; ?>
                            <div class="text-center mt-3">
                                <a href="tedarikcilerim.php" class="btn btn-primary">Tüm Tedarikçileri Görüntüle</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="m-0 font-weight-bold">Bildirimler</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($bildirimler) > 0): ?>
                                <ul class="list-group">
                                    <?php foreach ($bildirimler as $bildirim): ?>
                                        <li class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <p class="mb-1"><?= guvenli($bildirim['mesaj']) ?></p>
                                            </div>
                                            <small class="text-muted">
                                                <?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?>
                                                <?php if ($bildirim['siparis_no']): ?>
                                                    - Sipariş: <?= guvenli($bildirim['siparis_no']) ?>
                                                <?php endif; ?>
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
                </div>
                
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="m-0 font-weight-bold">Son Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Parça No</th>
                                            <th>Tedarikçi</th>
                                            <th>Proje</th>
                                            <th>Durum</th>
                                            <th>Tarih</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($son_siparisler) > 0): ?>
                                            <?php foreach ($son_siparisler as $siparis): ?>
                                                <tr>
                                                    <td><?= guvenli($siparis['siparis_no']) ?></td>
                                                    <td><?= guvenli($siparis['parca_no']) ?></td>
                                                    <td><?= guvenli($siparis['firma_adi']) ?></td>
                                                    <td><?= guvenli($siparis['proje_adi']) ?></td>
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
                                                    <td><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></td>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="siparis_guncelle.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-success">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Henüz sizin sorumluluğunuzda sipariş bulunmamaktadır.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-3">
                                <a href="siparisler.php" class="btn btn-success">Tüm Siparişleri Görüntüle</a>
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