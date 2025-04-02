<?php
// sorumlu/index.php - Sorumlu paneli ana sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

$sorumlu_id = $_SESSION['kullanici_id'];

// Sipariş özeti
$siparis_ozet_sql = "SELECT 
                     COUNT(CASE WHEN durum_id = 1 THEN 1 END) as acik_siparis,
                     COUNT(CASE WHEN durum_id = 2 THEN 1 END) as kapali_siparis,
                     COUNT(CASE WHEN durum_id = 3 THEN 1 END) as bekleyen_siparis,
                     COUNT(*) as toplam_siparis
                     FROM siparisler
                     WHERE sorumlu_id = ? OR tedarikci_id IN (
                         SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                     )";
$siparis_ozet_stmt = $db->prepare($siparis_ozet_sql);
$siparis_ozet_stmt->execute([$sorumlu_id, $sorumlu_id]);
$siparis_ozet = $siparis_ozet_stmt->fetch(PDO::FETCH_ASSOC);

// Son siparişler
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, t.firma_adi
                       FROM siparisler s
                       LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                       LEFT JOIN projeler p ON s.proje_id = p.id
                       LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                       WHERE s.sorumlu_id = ? OR s.tedarikci_id IN (
                           SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                       )
                       ORDER BY s.acilis_tarihi DESC
                       LIMIT 5";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute([$sorumlu_id, $sorumlu_id]);
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi sayısı
$tedarikci_sayisi_sql = "SELECT COUNT(*) as sayi FROM sorumluluklar WHERE sorumlu_id = ?";
$tedarikci_sayisi_stmt = $db->prepare($tedarikci_sayisi_sql);
$tedarikci_sayisi_stmt->execute([$sorumlu_id]);
$tedarikci_sayisi = $tedarikci_sayisi_stmt->fetch(PDO::FETCH_ASSOC)['sayi'];

// Tedarikçi listesi
$tedarikciler_sql = "SELECT t.*, COUNT(s.id) as siparis_sayisi
                    FROM tedarikciler t
                    LEFT JOIN siparisler s ON t.id = s.tedarikci_id
                    INNER JOIN sorumluluklar sr ON t.id = sr.tedarikci_id
                    WHERE sr.sorumlu_id = ?
                    GROUP BY t.id
                    ORDER BY siparis_sayisi DESC
                    LIMIT 5";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Son bildirimler
$bildirimler_sql = "SELECT b.*, k.ad_soyad as gonderen_adi
                   FROM bildirimler b
                   LEFT JOIN kullanicilar k ON b.gonderen_id = k.id
                   WHERE b.kullanici_id = ?
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$sorumlu_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Yaklaşan teslim tarihleri
$yaklasan_teslimatlar_sql = "SELECT s.*, t.firma_adi, p.proje_adi
                            FROM siparisler s
                            LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                            LEFT JOIN projeler p ON s.proje_id = p.id
                            WHERE (s.sorumlu_id = ? OR s.tedarikci_id IN (
                                SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                            ))
                            AND s.durum_id = 1
                            AND s.tedarikci_tarihi IS NOT NULL
                            AND s.tedarikci_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                            ORDER BY s.tedarikci_tarihi ASC
                            LIMIT 5";
$yaklasan_teslimatlar_stmt = $db->prepare($yaklasan_teslimatlar_sql);
$yaklasan_teslimatlar_stmt->execute([$sorumlu_id, $sorumlu_id]);
$yaklasan_teslimatlar = $yaklasan_teslimatlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa - Sorumlu Paneli - Tedarik Portalı</title>
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
        .card-counter {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            padding: 20px 10px;
            background-color: #fff;
            height: 100px;
            border-radius: 5px;
            transition: .3s linear all;
            margin-bottom: 1.5rem;
        }
        .card-counter:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transition: .3s linear all;
        }
        .card-counter i {
            font-size: 4em;
            opacity: 0.3;
        }
        .card-counter .count-numbers {
            position: absolute;
            right: 35px;
            top: 20px;
            font-size: 32px;
            display: block;
        }
        .card-counter .count-name {
            position: absolute;
            right: 35px;
            top: 65px;
            font-style: italic;
            text-transform: capitalize;
            opacity: 0.8;
            display: block;
        }
        .card-counter.primary {
            background-color: #4e73df;
            color: #fff;
        }
        .card-counter.success {
            background-color: #1cc88a;
            color: #fff;
        }
        .card-counter.info {
            background-color: #36b9cc;
            color: #fff;
        }
        .card-counter.warning {
            background-color: #f6c23e;
            color: #fff;
        }
        .notification-item {
            border-left: 4px solid #36b9cc;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            background-color: #f8f9fc;
            border-radius: 0.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .notification-item.unread {
            background-color: rgba(54, 185, 204, 0.05);
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
                <h1 class="h2">Sorumlu Paneli</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="siparisler.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-list-check"></i> Siparişleri Görüntüle
                        </a>
                        <a href="tedarikcilerim.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-building"></i> Tedarikçilerim
                        </a>
                    </div>
                </div>
            </div>

            <!-- Özet Kartları -->
            <div class="row">
                <div class="col-md-3">
                    <div class="card-counter primary">
                        <i class="bi bi-list-check"></i>
                        <span class="count-numbers"><?= $siparis_ozet['toplam_siparis'] ?></span>
                        <span class="count-name">Toplam Sipariş</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-counter success">
                        <i class="bi bi-check-circle"></i>
                        <span class="count-numbers"><?= $siparis_ozet['acik_siparis'] ?></span>
                        <span class="count-name">Açık Sipariş</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-counter warning">
                        <i class="bi bi-hourglass-split"></i>
                        <span class="count-numbers"><?= $siparis_ozet['bekleyen_siparis'] ?></span>
                        <span class="count-name">Bekleyen Sipariş</span>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card-counter info">
                        <i class="bi bi-building"></i>
                        <span class="count-numbers"><?= $tedarikci_sayisi ?></span>
                        <span class="count-name">Tedarikçi</span>
                    </div>
                </div>
            </div>

            <!-- İçerik Bölümü -->
            <div class="row mt-4">
                <!-- Son Siparişler -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-list-check"></i> Son Siparişler</h5>
                                <a href="siparisler.php" class="btn btn-sm btn-light">Tümünü Gör</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($son_siparisler) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sipariş No</th>
                                                <th>Tedarikçi</th>
                                                <th>Durum</th>
                                                <th>Tarih</th>
                                                <th>İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($son_siparisler as $siparis): ?>
                                                <tr>
                                                    <td><?= guvenli($siparis['siparis_no']) ?></td>
                                                    <td><?= guvenli($siparis['firma_adi']) ?></td>
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
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted">Henüz sipariş bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Tedarikçi Listesi -->
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-building"></i> Tedarikçilerim</h5>
                                <a href="tedarikcilerim.php" class="btn btn-sm btn-light">Tümünü Gör</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($tedarikciler) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= guvenli($tedarikci['firma_adi']) ?></h6>
                                                <small class="text-muted"><?= guvenli($tedarikci['firma_kodu']) ?></small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill"><?= $tedarikci['siparis_sayisi'] ?> Sipariş</span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted">Henüz tedarikçi bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Yaklaşan Teslimatlar -->
                    <div class="card mb-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-clock"></i> Yaklaşan Teslimatlar</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($yaklasan_teslimatlar) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sipariş No</th>
                                                <th>Tedarikçi</th>
                                                <th>Teslim Tarihi</th>
                                                <th>Kalan Gün</th>
                                                <th>İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($yaklasan_teslimatlar as $teslimat): 
                                                $teslim_tarihi = new DateTime($teslimat['tedarikci_tarihi']);
                                                $bugun = new DateTime();
                                                $kalan_gun = $bugun->diff($teslim_tarihi)->days;
                                                $gecikme_durumu = $bugun > $teslim_tarihi ? 'danger' : ($kalan_gun <= 3 ? 'warning' : 'success');
                                            ?>
                                                <tr>
                                                    <td><?= guvenli($teslimat['siparis_no']) ?></td>
                                                    <td><?= guvenli($teslimat['firma_adi']) ?></td>
                                                    <td><?= date('d.m.Y', strtotime($teslimat['tedarikci_tarihi'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $gecikme_durumu ?>">
                                                            <?php if ($bugun > $teslim_tarihi): ?>
                                                                <?= $kalan_gun ?> gün gecikti
                                                            <?php else: ?>
                                                                <?= $kalan_gun ?> gün kaldı
                                                            <?php endif; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $teslimat['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted">Yaklaşan teslimat bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Son Bildirimler -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-bell"></i> Son Bildirimler</h5>
                                <a href="bildirimler.php" class="btn btn-sm btn-light">Tümünü Gör</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (count($bildirimler) > 0): ?>
                                <?php foreach ($bildirimler as $bildirim): ?>
                                    <div class="notification-item <?= $bildirim['okundu'] ? '' : 'unread' ?>">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-0"><?= guvenli($bildirim['mesaj']) ?></h6>
                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?></small>
                                        </div>
                                        <p class="mb-1"><?= guvenli(substr($bildirim['mesaj'], 0, 100)) . (strlen($bildirim['mesaj']) > 100 ? '...' : '') ?></p>
                                        <?php if (!$bildirim['okundu']): ?>
                                            <div class="text-end">
                                                <a href="bildirimler.php?bildirim_id=<?= $bildirim['id'] ?>" class="btn btn-sm btn-outline-primary">Detay</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">Henüz bildirim bulunmamaktadır.</p>
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