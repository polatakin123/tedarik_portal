<?php
// admin/siparisler.php - Admin paneli sipariş yönetimi sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Filtreleme parametreleri
$durum_id = isset($_GET['durum_id']) ? intval($_GET['durum_id']) : null;
$tedarikci_id = isset($_GET['tedarikci_id']) ? intval($_GET['tedarikci_id']) : null;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : null;
$sorumlu_id = isset($_GET['sorumlu_id']) ? intval($_GET['sorumlu_id']) : null;
$arama = isset($_GET['arama']) ? trim($_GET['arama']) : '';

// Sipariş silme işlemi
if (isset($_GET['sil']) && !empty($_GET['sil'])) {
    $siparis_id = intval($_GET['sil']);
    try {
        // Sipariş ID'si geçerliliğini kontrol et
        $kontrol_sql = "SELECT id FROM siparisler WHERE id = ?";
        $kontrol_stmt = $db->prepare($kontrol_sql);
        $kontrol_stmt->execute([$siparis_id]);
        
        if ($kontrol_stmt->rowCount() > 0) {
            // Önce sipariş güncellemelerini sil
            $guncelleme_sql = "DELETE FROM siparis_guncellemeleri WHERE siparis_id = ?";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([$siparis_id]);
            
            // Sonra sipariş dokümanlarını sil
            $dokuman_sql = "DELETE FROM siparis_dokumanlari WHERE siparis_id = ?";
            $dokuman_stmt = $db->prepare($dokuman_sql);
            $dokuman_stmt->execute([$siparis_id]);
            
            // Son olarak siparişi sil
            $siparis_sql = "DELETE FROM siparisler WHERE id = ?";
            $siparis_stmt = $db->prepare($siparis_sql);
            $siparis_stmt->execute([$siparis_id]);
            
            $mesaj = "Sipariş başarıyla silindi.";
            header("Location: siparisler.php?mesaj=" . urlencode($mesaj));
            exit;
        } else {
            $hata = "Sipariş bulunamadı.";
            header("Location: siparisler.php?hata=" . urlencode($hata));
            exit;
        }
    } catch (PDOException $e) {
        $hata = "Sipariş silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: siparisler.php?hata=" . urlencode($hata));
        exit;
    }
}

// Filtreleme ölçütlerine göre sipariş sorgusunu oluştur
$sql_params = [];
$sql = "SELECT s.*, sd.durum_adi, t.firma_adi as tedarikci_adi, p.proje_adi, 
       k.ad_soyad as sorumlu_adi
       FROM siparisler s
       LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
       LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
       LEFT JOIN projeler p ON s.proje_id = p.id
       LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
       WHERE 1=1";

if ($durum_id) {
    $sql .= " AND s.durum_id = ?";
    $sql_params[] = $durum_id;
}

if ($tedarikci_id) {
    $sql .= " AND s.tedarikci_id = ?";
    $sql_params[] = $tedarikci_id;
}

if ($proje_id) {
    $sql .= " AND s.proje_id = ?";
    $sql_params[] = $proje_id;
}

if ($sorumlu_id) {
    $sql .= " AND s.sorumlu_id = ?";
    $sql_params[] = $sorumlu_id;
}

if ($arama) {
    $sql .= " AND (s.siparis_no LIKE ? OR s.parca_no LIKE ? OR s.tanim LIKE ? OR t.firma_adi LIKE ? OR p.proje_adi LIKE ?)";
    $arama_param = "%$arama%";
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
}

$sql .= " ORDER BY s.olusturma_tarihi DESC";

// Sorguyu çalıştır
$stmt = $db->prepare($sql);
$stmt->execute($sql_params);
$siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtreleme için sipariş durumlarını al
$durum_sql = "SELECT * FROM siparis_durumlari ORDER BY durum_adi";
$durum_stmt = $db->prepare($durum_sql);
$durum_stmt->execute();
$durumlar = $durum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçileri al
$tedarikci_sql = "SELECT * FROM tedarikciler ORDER BY firma_adi";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute();
$tedarikciler = $tedarikci_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri al
$proje_sql = "SELECT * FROM projeler ORDER BY proje_adi";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute();
$projeler = $proje_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sorumluları al
$sorumlu_sql = "SELECT * FROM kullanicilar WHERE rol = 'Sorumlu' ORDER BY ad_soyad";
$sorumlu_stmt = $db->prepare($sorumlu_sql);
$sorumlu_stmt->execute();
$sorumlular = $sorumlu_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Siparişler - Admin Paneli</title>
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
        .table-responsive {
            overflow-x: auto;
        }
        .table th, .table td {
            white-space: nowrap;
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
                    <a class="nav-link active" href="siparisler.php">
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
                <h1 class="h2">Siparişler</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="siparis_ekle.php" class="btn btn-primary me-2">
                        <i class="bi bi-plus"></i> Yeni Sipariş Ekle
                    </a>
                    <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filtrelemeForm">
                        <i class="bi bi-funnel"></i> Filtrele
                    </button>
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

            <!-- Filtreleme Formu -->
            <div class="collapse mb-4 <?= ($durum_id || $tedarikci_id || $proje_id || $sorumlu_id || $arama) ? 'show' : '' ?>" id="filtrelemeForm">
                <div class="card card-body">
                    <form method="get" action="">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="durum_id" class="form-label">Durum</label>
                                <select class="form-select" id="durum_id" name="durum_id">
                                    <option value="">Tümü</option>
                                    <?php foreach ($durumlar as $durum): ?>
                                        <option value="<?= $durum['id'] ?>" <?= ($durum_id == $durum['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($durum['durum_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="tedarikci_id" class="form-label">Tedarikçi</label>
                                <select class="form-select" id="tedarikci_id" name="tedarikci_id">
                                    <option value="">Tümü</option>
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <option value="<?= $tedarikci['id'] ?>" <?= ($tedarikci_id == $tedarikci['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($tedarikci['firma_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="proje_id" class="form-label">Proje</label>
                                <select class="form-select" id="proje_id" name="proje_id">
                                    <option value="">Tümü</option>
                                    <?php foreach ($projeler as $proje): ?>
                                        <option value="<?= $proje['id'] ?>" <?= ($proje_id == $proje['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($proje['proje_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="sorumlu_id" class="form-label">Sorumlu</label>
                                <select class="form-select" id="sorumlu_id" name="sorumlu_id">
                                    <option value="">Tümü</option>
                                    <?php foreach ($sorumlular as $sorumlu): ?>
                                        <option value="<?= $sorumlu['id'] ?>" <?= ($sorumlu_id == $sorumlu['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($sorumlu['ad_soyad']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-9">
                                <label for="arama" class="form-label">Arama</label>
                                <input type="text" class="form-control" id="arama" name="arama" placeholder="Sipariş no, parça no, tanım, tedarikçi veya proje adı" value="<?= guvenli($arama) ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Filtrele</button>
                                    <a href="siparisler.php" class="btn btn-secondary">Sıfırla</a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Siparişler Tablosu -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sipariş Listesi</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Parça No</th>
                                    <th>Tedarikçi</th>
                                    <th>Proje</th>
                                    <th>Sorumlu</th>
                                    <th>Miktar</th>
                                    <th>Kalan</th>
                                    <th>Teslim Tarihi</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($siparisler) > 0): ?>
                                    <?php foreach ($siparisler as $siparis): ?>
                                        <tr>
                                            <td><?= guvenli($siparis['siparis_no']) ?></td>
                                            <td><?= guvenli($siparis['parca_no']) ?></td>
                                            <td><?= guvenli($siparis['tedarikci_adi']) ?></td>
                                            <td><?= guvenli($siparis['proje_adi']) ?></td>
                                            <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
                                            <td><?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?></td>
                                            <td><?= guvenli($siparis['kalan_miktar']) ?> <?= guvenli($siparis['birim']) ?></td>
                                            <td><?= $siparis['teslim_tarihi'] ? tarihFormatla($siparis['teslim_tarihi']) : '-' ?></td>
                                            <td>
                                                <?php 
                                                $durum_renk = '';
                                                switch($siparis['durum_id']) {
                                                    case 1: $durum_renk = 'primary'; break; // Açık
                                                    case 2: $durum_renk = 'success'; break; // Kapalı
                                                    case 3: $durum_renk = 'warning'; break; // Beklemede
                                                    case 4: $durum_renk = 'danger'; break;  // İptal
                                                    default: $durum_renk = 'secondary';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $durum_renk ?>"><?= guvenli($siparis['durum_adi']) ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info btn-xs" title="Görüntüle">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="siparis_duzenle.php?id=<?= $siparis['id'] ?>" class="btn btn-primary btn-xs" title="Düzenle">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="javascript:void(0);" onclick="siparisiSil(<?= $siparis['id'] ?>, '<?= guvenli($siparis['siparis_no']) ?>')" class="btn btn-danger btn-xs" title="Sil">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center">Sipariş bulunamadı.</td>
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
    <script>
        function siparisiSil(id, siparisNo) {
            if (confirm(siparisNo + " numaralı siparişi silmek istediğinize emin misiniz?")) {
                window.location.href = "siparisler.php?sil=" + id;
            }
        }
    </script>
</body>
</html> 