<?php
// sorumlu/tedarikcilerim.php - Sorumlu olunan tedarikçilerin listelenmesi
require_once '../config.php';
sorumluYetkisiKontrol();

$sorumlu_id = $_SESSION['kullanici_id'];

// Sorumlu olunan tedarikçileri al
$tedarikciler_sql = "SELECT t.*, 
                      (SELECT COUNT(*) FROM siparisler WHERE tedarikci_id = t.id) as siparis_sayisi
                      FROM tedarikciler t
                      INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                      WHERE s.sorumlu_id = ?
                      ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Her tedarikçi için sipariş özetini al
foreach ($tedarikciler as $key => $tedarikci) {
    $siparis_ozet_sql = "SELECT 
                          COUNT(*) as toplam,
                          SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                          SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                          SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                          FROM siparisler 
                          WHERE tedarikci_id = ?";
    $siparis_ozet_stmt = $db->prepare($siparis_ozet_sql);
    $siparis_ozet_stmt->execute([$tedarikci['id']]);
    $tedarikciler[$key]['siparis_ozet'] = $siparis_ozet_stmt->fetch(PDO::FETCH_ASSOC);
}

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tedarikçilerim - Sorumlu Paneli - Tedarik Portalı</title>
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
        .badge-notification {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            font-size: 0.75rem;
        }
        .card-tedarikci {
            transition: all 0.3s;
        }
        .card-tedarikci:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .tedarikci-header {
            background-color: #36b9cc;
            color: white;
            padding: 1rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .badge-count {
            font-size: 0.875rem;
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
                    <a class="nav-link active" href="tedarikcilerim.php">
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
                            <li><a class="dropdown-item" href="bildirimler.php">Tüm Bildirimleri Gör</a></li>
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
                <h1 class="h2">Tedarikçilerim</h1>
            </div>

            <!-- Tedarikçiler -->
            <div class="row">
                <?php if (count($tedarikciler) > 0): ?>
                    <?php foreach ($tedarikciler as $tedarikci): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card card-tedarikci h-100">
                                <div class="tedarikci-header">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?= guvenli($tedarikci['firma_adi']) ?></h5>
                                        <span class="badge bg-light text-dark badge-count"><?= $tedarikci['siparis_sayisi'] ?> Sipariş</span>
                                    </div>
                                    <small class="text-white-50"><?= guvenli($tedarikci['firma_kodu']) ?></small>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-6">
                                                <p class="mb-1"><i class="bi bi-person"></i> <strong>İletişim:</strong></p>
                                                <p class="mb-0"><?= guvenli($tedarikci['yetkili_kisi']) ?></p>
                                            </div>
                                            <div class="col-6">
                                                <p class="mb-1"><i class="bi bi-telephone"></i> <strong>Telefon:</strong></p>
                                                <p class="mb-0"><?= guvenli($tedarikci['telefon']) ?></p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <p class="mb-1"><i class="bi bi-envelope"></i> <strong>E-posta:</strong></p>
                                        <p class="mb-0"><?= guvenli($tedarikci['email']) ?></p>
                                    </div>
                                    <div class="mb-3">
                                        <p class="mb-1"><i class="bi bi-geo-alt"></i> <strong>Adres:</strong></p>
                                        <p class="mb-0"><?= guvenli($tedarikci['adres']) ?></p>
                                    </div>
                                    
                                    <hr>
                                    
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="d-flex flex-column">
                                                <span class="h4 mb-0 text-success"><?= $tedarikci['siparis_ozet']['acik'] ?? 0 ?></span>
                                                <span class="small text-muted">Açık</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="d-flex flex-column">
                                                <span class="h4 mb-0 text-warning"><?= $tedarikci['siparis_ozet']['beklemede'] ?? 0 ?></span>
                                                <span class="small text-muted">Beklemede</span>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="d-flex flex-column">
                                                <span class="h4 mb-0 text-secondary"><?= $tedarikci['siparis_ozet']['kapali'] ?? 0 ?></span>
                                                <span class="small text-muted">Tamamlandı</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between">
                                        <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-eye"></i> Detaylar
                                        </a>
                                        <a href="siparisler.php?tedarikci_id=<?= $tedarikci['id'] ?>" class="btn btn-success btn-sm">
                                            <i class="bi bi-list-check"></i> Siparişler
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">
                            <i class="bi bi-info-circle me-2"></i> Henüz size atanmış tedarikçi bulunmamaktadır.
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 