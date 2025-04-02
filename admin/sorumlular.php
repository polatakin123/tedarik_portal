<?php
// admin/sorumlular.php - Admin paneli sorumlular yönetimi sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sorumlular listesini getir
$arama = isset($_GET['arama']) ? trim($_GET['arama']) : '';

$sql_params = [];
$sql = "SELECT k.*, 
       (SELECT COUNT(*) FROM sorumluluklar WHERE sorumlu_id = k.id) as tedarikci_sayisi,
       (SELECT COUNT(*) FROM siparisler WHERE sorumlu_id = k.id) as siparis_sayisi
       FROM kullanicilar k
       WHERE k.rol = 'Sorumlu'";

if ($arama) {
    $sql .= " AND (k.ad_soyad LIKE ? OR k.email LIKE ? OR k.kullanici_adi LIKE ?)";
    $arama_param = "%$arama%";
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
}

$sql .= " ORDER BY k.ad_soyad";

$stmt = $db->prepare($sql);
$stmt->execute($sql_params);
$sorumlular = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Sorumlular - Admin Paneli</title>
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
                    <a class="nav-link active" href="sorumlular.php">
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
                <h1 class="h2">Sorumlular</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="kullanici_ekle.php?rol=Sorumlu" class="btn btn-primary me-2">
                        <i class="bi bi-plus"></i> Yeni Sorumlu Ekle
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

            <!-- Arama Formu -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <form method="get" action="" class="d-flex">
                        <input type="text" class="form-control me-2" name="arama" placeholder="Ad soyad, kullanıcı adı veya email ara..." value="<?= guvenli($arama) ?>">
                        <button type="submit" class="btn btn-primary">Ara</button>
                        <?php if ($arama): ?>
                            <a href="sorumlular.php" class="btn btn-secondary ms-2">Temizle</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Sorumlular Tablosu -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sorumlu Listesi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Ad Soyad</th>
                                    <th>Kullanıcı Adı</th>
                                    <th>E-posta</th>
                                    <th>Telefon</th>
                                    <th>Tedarikçi Sayısı</th>
                                    <th>Sipariş Sayısı</th>
                                    <th>Son Giriş</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($sorumlular) > 0): ?>
                                    <?php foreach ($sorumlular as $sorumlu): ?>
                                        <tr>
                                            <td><?= guvenli($sorumlu['ad_soyad']) ?></td>
                                            <td><?= guvenli($sorumlu['kullanici_adi']) ?></td>
                                            <td><?= guvenli($sorumlu['email']) ?></td>
                                            <td><?= guvenli($sorumlu['telefon']) ?></td>
                                            <td>
                                                <?php if ($sorumlu['tedarikci_sayisi'] > 0): ?>
                                                    <a href="sorumlu_tedarikciler.php?id=<?= $sorumlu['id'] ?>" class="badge bg-primary text-decoration-none">
                                                        <?= $sorumlu['tedarikci_sayisi'] ?> Tedarikçi
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($sorumlu['siparis_sayisi'] > 0): ?>
                                                    <a href="siparisler.php?sorumlu_id=<?= $sorumlu['id'] ?>" class="badge bg-info text-decoration-none">
                                                        <?= $sorumlu['siparis_sayisi'] ?> Sipariş
                                                    </a>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $sorumlu['son_giris'] ? date('d.m.Y H:i', strtotime($sorumlu['son_giris'])) : '-' ?></td>
                                            <td>
                                                <?php if ($sorumlu['aktif']): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Pasif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="kullanici_detay.php?id=<?= $sorumlu['id'] ?>" class="btn btn-info btn-xs" title="Görüntüle">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="kullanici_duzenle.php?id=<?= $sorumlu['id'] ?>" class="btn btn-primary btn-xs" title="Düzenle">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="sorumlu_tedarikciler.php?id=<?= $sorumlu['id'] ?>" class="btn btn-warning btn-xs" title="Tedarikçi Ata">
                                                        <i class="bi bi-building"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">Sorumlu bulunamadı.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 