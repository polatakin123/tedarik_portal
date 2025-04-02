<?php
// sorumlu/siparisler.php - Sorumlu paneli siparişler sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Kullanıcının sorumlu olduğu tedarikçileri al
$sorumlu_id = $_SESSION['kullanici_id'];
$tedarikciler_sql = "SELECT t.id, t.firma_adi, t.firma_kodu
                    FROM tedarikciler t
                    INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                    WHERE s.sorumlu_id = ?
                    ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçilerin id'lerini al
$tedarikci_idleri = array_column($tedarikciler, 'id');
$in = '';
$params = [];

if (count($tedarikci_idleri) > 0) {
    $in = str_repeat('?,', count($tedarikci_idleri) - 1) . '?';
    $params = array_merge($tedarikci_idleri, [$sorumlu_id]);
} else {
    $in = '?';
    $params = [$sorumlu_id];
}

// Filtre parametreleri
$durum_id = isset($_GET['durum']) ? intval($_GET['durum']) : 0;
$tedarikci_id = isset($_GET['tedarikci_id']) ? intval($_GET['tedarikci_id']) : 0;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// SQL sorgusu için koşullar
$where_conditions = [];
$where_params = [];

// Temel koşul - kullanıcının sorumlu olduğu tedarikçilerle ilgili siparişler
$where_conditions[] = "(s.tedarikci_id IN ($in) OR s.sorumlu_id = ?)";
$where_params = $params;

// Durum filtresi
if ($durum_id > 0) {
    $where_conditions[] = "s.durum_id = ?";
    $where_params[] = $durum_id;
}

// Tedarikçi filtresi
if ($tedarikci_id > 0) {
    $where_conditions[] = "s.tedarikci_id = ?";
    $where_params[] = $tedarikci_id;
}

// Proje filtresi
if ($proje_id > 0) {
    $where_conditions[] = "s.proje_id = ?";
    $where_params[] = $proje_id;
}

// Arama filtresi
if (!empty($arama)) {
    $where_conditions[] = "(s.siparis_no LIKE ? OR s.parca_no LIKE ? OR s.tanim LIKE ? OR t.firma_adi LIKE ? OR p.proje_adi LIKE ?)";
    $arama_param = "%$arama%";
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
}

// Koşulları birleştir
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Projeleri al
$projeler_sql = "SELECT DISTINCT p.id, p.proje_adi
                FROM projeler p
                INNER JOIN siparisler s ON p.id = s.proje_id
                WHERE s.tedarikci_id IN ($in) OR s.sorumlu_id = ?
                ORDER BY p.proje_adi";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute($params);
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını al
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Siparişleri al
$siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, t.firma_adi, 
                          (SELECT COUNT(*) FROM siparis_dokumanlari WHERE siparis_id = s.id) as dokuman_sayisi
                   FROM siparisler s
                   LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                   LEFT JOIN projeler p ON s.proje_id = p.id
                   LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                   $where_clause
                   ORDER BY s.acilis_tarihi DESC";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute($where_params);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişler - Sorumlu Paneli - Tedarik Portalı</title>
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
        .table-responsive {
            overflow-x: auto;
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
                    <a class="nav-link active" href="siparisler.php">
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
                <h1 class="h2">Siparişler</h1>
            </div>

            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Sipariş Filtreleme</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="siparisler.php" class="row g-3">
                        <div class="col-md-3">
                            <label for="arama" class="form-label">Arama</label>
                            <input type="text" class="form-control" id="arama" name="arama" placeholder="Sipariş no, parça no, tanım..." value="<?= guvenli($arama) ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="durum" class="form-label">Durumu</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="0">Tüm Durumlar</option>
                                <?php foreach ($durumlar as $durum): ?>
                                    <option value="<?= $durum['id'] ?>" <?= $durum_id == $durum['id'] ? 'selected' : '' ?>>
                                        <?= guvenli($durum['durum_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="tedarikci_id" class="form-label">Tedarikçi</label>
                            <select class="form-select" id="tedarikci_id" name="tedarikci_id">
                                <option value="0">Tüm Tedarikçiler</option>
                                <?php foreach ($tedarikciler as $tedarikci): ?>
                                    <option value="<?= $tedarikci['id'] ?>" <?= $tedarikci_id == $tedarikci['id'] ? 'selected' : '' ?>>
                                        <?= guvenli($tedarikci['firma_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="proje_id" class="form-label">Proje</label>
                            <select class="form-select" id="proje_id" name="proje_id">
                                <option value="0">Tüm Projeler</option>
                                <?php foreach ($projeler as $proje): ?>
                                    <option value="<?= $proje['id'] ?>" <?= $proje_id == $proje['id'] ? 'selected' : '' ?>>
                                        <?= guvenli($proje['proje_adi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary">Filtrele</button>
                            <a href="siparisler.php" class="btn btn-secondary">Sıfırla</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Siparişler Tablosu -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0 font-weight-bold">Sipariş Listesi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Parça No</th>
                                    <th>Tanım</th>
                                    <th>Tedarikçi</th>
                                    <th>Proje</th>
                                    <th>Miktar</th>
                                    <th>Açılış Tarihi</th>
                                    <th>Teslim Tarihi</th>
                                    <th>Durum</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($siparisler) > 0): ?>
                                    <?php foreach ($siparisler as $siparis): ?>
                                        <tr>
                                            <td><?= guvenli($siparis['siparis_no']) ?></td>
                                            <td><?= guvenli($siparis['parca_no']) ?></td>
                                            <td><?= guvenli($siparis['tanim']) ?></td>
                                            <td><?= guvenli($siparis['firma_adi']) ?></td>
                                            <td><?= guvenli($siparis['proje_adi']) ?></td>
                                            <td><?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?></td>
                                            <td><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></td>
                                            <td>
                                                <?php if ($siparis['teslim_tarihi']): ?>
                                                    <?= date('d.m.Y', strtotime($siparis['teslim_tarihi'])) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Belirtilmemiş</span>
                                                <?php endif; ?>
                                            </td>
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
                                                <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-info" title="Detay">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="siparis_guncelle.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-success" title="Güncelle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
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
                <div class="card-footer">
                    <div class="row">
                        <div class="col">
                            <p class="mb-0">Toplam <strong><?= count($siparisler) ?></strong> sipariş listeleniyor.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 