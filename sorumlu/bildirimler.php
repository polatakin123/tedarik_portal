<?php
// sorumlu/bildirimler.php - Sorumlu paneli bildirimler sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

$sorumlu_id = $_SESSION['kullanici_id'];

// Bildirimleri okundu olarak işaretle
if (isset($_GET['okundu']) && $_GET['okundu'] == 1) {
    $bildirim_guncelle_sql = "UPDATE bildirimler SET okundu = 1 WHERE kullanici_id = ? AND okundu = 0";
    $bildirim_guncelle_stmt = $db->prepare($bildirim_guncelle_sql);
    $bildirim_guncelle_stmt->execute([$sorumlu_id]);
    
    header("Location: bildirimler.php");
    exit;
}

// Tek bildirimi okundu olarak işaretle
if (isset($_GET['bildirim_id']) && is_numeric($_GET['bildirim_id'])) {
    $bildirim_id = intval($_GET['bildirim_id']);
    $bildirim_guncelle_sql = "UPDATE bildirimler SET okundu = 1 WHERE id = ? AND kullanici_id = ?";
    $bildirim_guncelle_stmt = $db->prepare($bildirim_guncelle_sql);
    $bildirim_guncelle_stmt->execute([$bildirim_id, $sorumlu_id]);
    
    // Yönlendirme linki varsa oraya yönlendir
    if (isset($_GET['yonlendir']) && !empty($_GET['yonlendir'])) {
        header("Location: " . $_GET['yonlendir']);
        exit;
    }
    
    header("Location: bildirimler.php");
    exit;
}

// Bildirimleri al
$bildirimler_sql = "SELECT b.*, k.ad_soyad as gonderen_adi
                   FROM bildirimler b
                   LEFT JOIN kullanicilar k ON b.gonderen_id = k.id
                   WHERE b.kullanici_id = ?
                   ORDER BY b.bildirim_tarihi DESC";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$sorumlu_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bildirimlerim - Sorumlu Paneli - Tedarik Portalı</title>
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
        .notification-item {
            border-left: 4px solid #e0e0e0;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
            background-color: #f8f9fc;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.2s;
        }
        .notification-item:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .notification-item.unread {
            border-left-color: #36b9cc;
            background-color: rgba(54, 185, 204, 0.05);
        }
        .notification-time {
            font-size: 0.8rem;
            color: #858796;
        }
        .notification-title {
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .notification-sender {
            font-size: 0.9rem;
            color: #5a5c69;
        }
        .notification-action {
            display: flex;
            justify-content: flex-end;
            margin-top: 0.5rem;
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
                <h1 class="h2">Bildirimlerim</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if ($okunmamis_bildirim_sayisi > 0): ?>
                        <a href="bildirimler.php?okundu=1" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-check-all"></i> Tümünü Okundu İşaretle
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bildirimler Listesi -->
            <div class="row">
                <div class="col-12">
                    <?php if (count($bildirimler) > 0): ?>
                        <?php foreach ($bildirimler as $bildirim): ?>
                            <div class="notification-item <?= $bildirim['okundu'] ? '' : 'unread' ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="notification-title">
                                        <?php if (!$bildirim['okundu']): ?>
                                            <span class="badge bg-primary me-2">Yeni</span>
                                        <?php endif; ?>
                                        <?= guvenli($bildirim['mesaj']) ?>
                                    </div>
                                    <div class="notification-time">
                                        <?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?>
                                    </div>
                                </div>
                                <div class="notification-content">
                                    <?= nl2br(guvenli($bildirim['mesaj'])) ?>
                                </div>
                                <?php if (!empty($bildirim['gonderen_adi'])): ?>
                                    <div class="notification-sender mt-2">
                                        <i class="bi bi-person-circle"></i> <?= guvenli($bildirim['gonderen_adi']) ?>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($bildirim['link'])): ?>
                                    <div class="notification-action">
                                        <?php if (!$bildirim['okundu']): ?>
                                            <a href="bildirimler.php?bildirim_id=<?= $bildirim['id'] ?>&yonlendir=<?= urlencode($bildirim['link']) ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-arrow-right"></i> Git
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= guvenli($bildirim['link']) ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-arrow-right"></i> Git
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (!$bildirim['okundu']): ?>
                                    <div class="notification-action">
                                        <a href="bildirimler.php?bildirim_id=<?= $bildirim['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-check"></i> Okundu İşaretle
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Henüz bildiriminiz bulunmamaktadır.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 