<?php
// admin/projeler.php - Proje yönetim sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Proje silme işlemi
if (isset($_GET['sil']) && !empty($_GET['sil'])) {
    $proje_id = intval($_GET['sil']);
    
    // Önce projeye bağlı siparişleri kontrol et
    $siparis_kontrol = $db->prepare("SELECT COUNT(*) FROM siparisler WHERE proje_id = ?");
    $siparis_kontrol->execute([$proje_id]);
    $siparis_sayisi = $siparis_kontrol->fetchColumn();
    
    if ($siparis_sayisi > 0) {
        $hata = "Bu projeye ait " . $siparis_sayisi . " adet sipariş bulunduğu için silinemez! Önce bağlı siparişleri silmeniz veya başka bir projeye aktarmanız gerekiyor.";
        header("Location: projeler.php?hata=" . urlencode($hata));
        exit;
    }
    
    try {
        // Proje ile ilgili tüm ilişkileri temizle
        $db->beginTransaction();
        
        // Ana proje kaydını sil
        $sil = $db->prepare("DELETE FROM projeler WHERE id = ?");
        $sil->execute([$proje_id]);
        
        $db->commit();
        
        $mesaj = "Proje başarıyla silindi.";
        header("Location: projeler.php?mesaj=" . urlencode($mesaj));
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $hata = "Proje silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: projeler.php?hata=" . urlencode($hata));
        exit;
    }
}

// Arama parametresi
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';
$arama_sorgu = '';
$params = [];

if (!empty($arama)) {
    $arama_sorgu = " WHERE (p.proje_adi LIKE ? OR p.proje_kodu LIKE ? OR p.proje_yoneticisi LIKE ? OR p.aciklama LIKE ?)";
    $params = ["%$arama%", "%$arama%", "%$arama%", "%$arama%"];
}

// Projeleri getir
$sql = "SELECT p.*, 
               (SELECT COUNT(*) FROM siparisler s WHERE s.proje_id = p.id) AS siparis_sayisi
        FROM projeler p
        $arama_sorgu
        ORDER BY p.proje_adi ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$projeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Projeler - Admin Paneli</title>
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
        .table th, .table td {
            vertical-align: middle;
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
                <h1 class="h2">Projeler</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="proje_ekle.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Yeni Proje Ekle
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

            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="get" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" class="form-control" name="arama" placeholder="Proje adı, kodu veya yöneticisi ara..." value="<?= guvenli($arama) ?>">
                                <button class="btn btn-outline-primary" type="submit">
                                    <i class="bi bi-search"></i> Ara
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if (!empty($arama)): ?>
                                <a href="projeler.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Filtreleri Temizle
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Tüm Projeler (<?= count($projeler) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($projeler) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Proje Adı</th>
                                        <th>Proje Kodu</th>
                                        <th>Proje Yöneticisi</th>
                                        <th>Başlangıç Tarihi</th>
                                        <th>Bitiş Tarihi</th>
                                        <th>Sipariş Sayısı</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projeler as $proje): ?>
                                        <tr>
                                            <td>
                                                <a href="proje_detay.php?id=<?= $proje['id'] ?>" class="text-decoration-none fw-bold">
                                                    <?= guvenli($proje['proje_adi']) ?>
                                                </a>
                                            </td>
                                            <td><?= guvenli($proje['proje_kodu']) ?></td>
                                            <td><?= guvenli($proje['proje_yoneticisi']) ?></td>
                                            <td><?= tarihFormatla($proje['baslangic_tarihi']) ?></td>
                                            <td><?= $proje['bitis_tarihi'] ? tarihFormatla($proje['bitis_tarihi']) : '-' ?></td>
                                            <td>
                                                <?php if ($proje['siparis_sayisi'] > 0): ?>
                                                    <a href="siparisler.php?proje_id=<?= $proje['id'] ?>" class="text-decoration-none">
                                                        <?= $proje['siparis_sayisi'] ?> sipariş
                                                    </a>
                                                <?php else: ?>
                                                    0 sipariş
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php 
                                                if ($proje['aktif']) {
                                                    $bugun = new DateTime();
                                                    $baslangic = new DateTime($proje['baslangic_tarihi']);
                                                    $bitis = !empty($proje['bitis_tarihi']) ? new DateTime($proje['bitis_tarihi']) : null;
                                                    
                                                    if ($baslangic > $bugun) {
                                                        echo '<span class="badge bg-info">Planlandı</span>';
                                                    } elseif ($bitis && $bitis < $bugun) {
                                                        echo '<span class="badge bg-secondary">Tamamlandı</span>';
                                                    } else {
                                                        echo '<span class="badge bg-success">Devam Ediyor</span>';
                                                    }
                                                } else {
                                                    echo '<span class="badge bg-danger">Pasif</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="proje_detay.php?id=<?= $proje['id'] ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Görüntüle
                                                    </a>
                                                    <a href="proje_duzenle.php?id=<?= $proje['id'] ?>" class="btn btn-sm btn-warning">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <?php if ($proje['siparis_sayisi'] == 0): ?>
                                                        <a href="projeler.php?sil=<?= $proje['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= guvenli($proje['proje_adi']) ?> projesini silmek istediğinize emin misiniz?');">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <?php if (!empty($arama)): ?>
                                <i class="bi bi-info-circle"></i> "<?= guvenli($arama) ?>" araması için sonuç bulunamadı.
                            <?php else: ?>
                                <i class="bi bi-info-circle"></i> Henüz proje bulunmamaktadır. Yeni proje ekleyebilirsiniz.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 