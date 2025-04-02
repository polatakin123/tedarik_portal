<?php
// admin/raporlar.php - Raporlar sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sipariş durumlarına göre istatistikler
$siparis_durum_sql = "SELECT sd.id, sd.durum_adi, COUNT(s.id) as sayi
                     FROM siparis_durumlari sd
                     LEFT JOIN siparisler s ON sd.id = s.durum_id
                     GROUP BY sd.id, sd.durum_adi
                     ORDER BY sd.id";
$siparis_durum_stmt = $db->prepare($siparis_durum_sql);
$siparis_durum_stmt->execute();
$siparis_durum_istatistikleri = $siparis_durum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi sayısı ve aktif/pasif durumu
$tedarikci_sql = "SELECT COUNT(*) as toplam, 
                 SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif,
                 SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) as pasif
                 FROM tedarikciler";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute();
$tedarikci_istatistikleri = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

// Proje sayısı ve aktif/pasif durumu
$proje_sql = "SELECT COUNT(*) as toplam, 
             SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif,
             SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) as pasif
             FROM projeler";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute();
$proje_istatistikleri = $proje_stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcı rolleri dağılımı
$kullanici_rol_sql = "SELECT rol, COUNT(*) as sayi
                     FROM kullanicilar
                     GROUP BY rol
                     ORDER BY sayi DESC";
$kullanici_rol_stmt = $db->prepare($kullanici_rol_sql);
$kullanici_rol_stmt->execute();
$kullanici_rol_istatistikleri = $kullanici_rol_stmt->fetchAll(PDO::FETCH_ASSOC);

// En aktif tedarikçiler (sipariş sayısına göre)
$aktif_tedarikciler_sql = "SELECT t.firma_adi, COUNT(s.id) as siparis_sayisi
                          FROM tedarikciler t
                          INNER JOIN siparisler s ON t.id = s.tedarikci_id
                          GROUP BY t.id, t.firma_adi
                          ORDER BY siparis_sayisi DESC
                          LIMIT 5";
$aktif_tedarikciler_stmt = $db->prepare($aktif_tedarikciler_sql);
$aktif_tedarikciler_stmt->execute();
$aktif_tedarikciler = $aktif_tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Raporlar - Admin Paneli</title>
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
        .stat-card {
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .stat-icon {
            font-size: 2rem;
            opacity: 0.8;
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
                    <a class="nav-link" href="kullanicilar.php">
                        <i class="bi bi-person-badge"></i> Kullanıcılar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="raporlar.php">
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
                <h1 class="h2">Raporlar ve İstatistikler</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> Yazdır
                    </button>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-download"></i> Dışa Aktar
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                            <li><a class="dropdown-item" href="#">Excel (.xlsx)</a></li>
                            <li><a class="dropdown-item" href="#">PDF (.pdf)</a></li>
                            <li><a class="dropdown-item" href="#">CSV (.csv)</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Özet İstatistikler -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Toplam Sipariş</h6>
                                    <?php
                                    $toplam_siparis = 0;
                                    foreach ($siparis_durum_istatistikleri as $istatistik) {
                                        $toplam_siparis += $istatistik['sayi'];
                                    }
                                    ?>
                                    <h3 class="mb-0"><?= $toplam_siparis ?></h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-list-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Tedarikçiler</h6>
                                    <h3 class="mb-0"><?= $tedarikci_istatistikleri['toplam'] ?></h3>
                                    <small><?= $tedarikci_istatistikleri['aktif'] ?> Aktif / <?= $tedarikci_istatistikleri['pasif'] ?> Pasif</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-building"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-warning text-dark">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Projeler</h6>
                                    <h3 class="mb-0"><?= $proje_istatistikleri['toplam'] ?></h3>
                                    <small><?= $proje_istatistikleri['aktif'] ?> Aktif / <?= $proje_istatistikleri['pasif'] ?> Pasif</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-diagram-3"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card stat-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Tamamlanan Siparişler</h6>
                                    <?php
                                    $tamamlanan = 0;
                                    foreach ($siparis_durum_istatistikleri as $istatistik) {
                                        if ($istatistik['id'] == 4) { // Tamamlandı durumu
                                            $tamamlanan = $istatistik['sayi'];
                                            break;
                                        }
                                    }
                                    $oran = $toplam_siparis > 0 ? round(($tamamlanan / $toplam_siparis) * 100) : 0;
                                    ?>
                                    <h3 class="mb-0"><?= $tamamlanan ?></h3>
                                    <small>Toplam Siparişlerin %<?= $oran ?>'i</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <!-- Sipariş Durumları Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Sipariş Durumları</h5>
                            <a href="siparisler.php" class="btn btn-sm btn-outline-primary">Tüm Siparişler</a>
                        </div>
                        <div class="card-body">
                            <?php if ($toplam_siparis > 0): ?>
                                <?php foreach ($siparis_durum_istatistikleri as $istatistik): ?>
                                    <?php 
                                    $durum_rengi = durumRengiGetir($istatistik['id']);
                                    $yuzde = $toplam_siparis > 0 ? round(($istatistik['sayi'] / $toplam_siparis) * 100) : 0;
                                    ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= guvenli($istatistik['durum_adi']) ?></span>
                                            <span><?= $istatistik['sayi'] ?> (<?= $yuzde ?>%)</span>
                                        </div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar bg-<?= $durum_rengi ?>" role="progressbar" 
                                                 style="width: <?= $yuzde ?>%;" aria-valuenow="<?= $yuzde ?>" 
                                                 aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted my-3">Henüz sipariş bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Kullanıcı Rolleri Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Kullanıcı Rolleri</h5>
                            <a href="kullanicilar.php" class="btn btn-sm btn-outline-primary">Tüm Kullanıcılar</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Rol</th>
                                            <th>Kullanıcı Sayısı</th>
                                            <th>Oran</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $toplam_kullanici = 0;
                                        foreach ($kullanici_rol_istatistikleri as $rol) {
                                            $toplam_kullanici += $rol['sayi'];
                                        }
                                        ?>
                                        <?php foreach ($kullanici_rol_istatistikleri as $rol): ?>
                                            <?php 
                                            $rol_yuzde = $toplam_kullanici > 0 ? round(($rol['sayi'] / $toplam_kullanici) * 100) : 0;
                                            $rol_renk = "";
                                            switch ($rol['rol']) {
                                                case 'Admin': $rol_renk = "danger"; break;
                                                case 'Tedarikci': $rol_renk = "success"; break;
                                                case 'Sorumlu': $rol_renk = "primary"; break;
                                                default: $rol_renk = "secondary";
                                            }
                                            ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-<?= $rol_renk ?>"><?= guvenli($rol['rol']) ?></span>
                                                </td>
                                                <td><?= $rol['sayi'] ?></td>
                                                <td>
                                                    <div class="progress" style="height: 5px;">
                                                        <div class="progress-bar bg-<?= $rol_renk ?>" role="progressbar" 
                                                             style="width: <?= $rol_yuzde ?>%;" aria-valuenow="<?= $rol_yuzde ?>" 
                                                             aria-valuemin="0" aria-valuemax="100"></div>
                                                    </div>
                                                    <small class="text-muted"><?= $rol_yuzde ?>%</small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- En Aktif Tedarikçiler Kartı -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">En Aktif Tedarikçiler</h5>
                            <a href="tedarikciler.php" class="btn btn-sm btn-outline-primary">Tüm Tedarikçiler</a>
                        </div>
                        <div class="card-body">
                            <?php if (count($aktif_tedarikciler) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Firma</th>
                                                <th>Sipariş Sayısı</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($aktif_tedarikciler as $tedarikci): ?>
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
                            <?php else: ?>
                                <p class="text-center text-muted my-3">Henüz aktif tedarikçi bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Geliştirilebilecek Özellikler -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Daha Fazla Rapor</h5>
                        </div>
                        <div class="card-body">
                            <p class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                Bu sayfada yalnızca temel istatistikler gösterilmektedir. Daha kapsamlı raporlar için aşağıdaki seçeneklere göz atabilirsiniz.
                            </p>
                            <div class="list-group">
                                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-calendar-check me-2"></i>
                                        Zaman Bazlı Sipariş Analizi
                                    </div>
                                    <span class="badge bg-primary rounded-pill">Yakında</span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-bar-chart-line me-2"></i>
                                        Tedarikçi Performans Raporu
                                    </div>
                                    <span class="badge bg-primary rounded-pill">Yakında</span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-clock-history me-2"></i>
                                        Teslimat Süresi Analizi
                                    </div>
                                    <span class="badge bg-primary rounded-pill">Yakında</span>
                                </a>
                                <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <div>
                                        <i class="bi bi-pie-chart me-2"></i>
                                        Proje Bazlı Sipariş Dağılımı
                                    </div>
                                    <span class="badge bg-primary rounded-pill">Yakında</span>
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