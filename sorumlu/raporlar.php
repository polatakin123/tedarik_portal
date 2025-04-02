<?php
// sorumlu/raporlar.php - Sorumlu paneli raporlar sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

$sorumlu_id = $_SESSION['kullanici_id'];

// Rapor türü
$rapor_turu = isset($_GET['rapor']) ? $_GET['rapor'] : 'siparis_durum';

// Filtre parametreleri
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-d', strtotime('-1 month'));
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$tedarikci_id = isset($_GET['tedarikci_id']) ? intval($_GET['tedarikci_id']) : 0;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;

// Sorumlu olunan tedarikçileri al
$tedarikciler_sql = "SELECT t.id, t.firma_adi, t.firma_kodu
                    FROM tedarikciler t
                    INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                    WHERE s.sorumlu_id = ?
                    ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi ID'lerini al
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

// Projeleri al
$projeler_sql = "SELECT DISTINCT p.id, p.proje_adi
                FROM projeler p
                INNER JOIN siparisler s ON p.id = s.proje_id
                WHERE s.tedarikci_id IN ($in) OR s.sorumlu_id = ?
                ORDER BY p.proje_adi";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute($params);
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Rapor verilerini al
$rapor_data = [];
$chart_labels = [];
$chart_data = [];
$chart_colors = [];

// Filtreleme parametreleri
$where_conditions = [];
$where_params = [];

// Temel koşul - kullanıcının sorumlu olduğu tedarikçilerle ilgili siparişler
$where_conditions[] = "(s.tedarikci_id IN ($in) OR s.sorumlu_id = ?)";
$where_params = $params;

// Tarih filtresi
$where_conditions[] = "s.acilis_tarihi BETWEEN ? AND ?";
$where_params[] = $baslangic_tarihi;
$where_params[] = $bitis_tarihi . ' 23:59:59';

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

// Koşulları birleştir
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Rapor türüne göre sorgu
switch ($rapor_turu) {
    case 'siparis_durum':
        // Sipariş durumuna göre rapor
        $sql = "SELECT sd.durum_adi, COUNT(s.id) AS siparis_sayisi
                FROM siparisler s
                LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                $where_clause
                GROUP BY sd.durum_adi
                ORDER BY siparis_sayisi DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($where_params);
        $rapor_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grafik verileri
        foreach ($rapor_data as $row) {
            $chart_labels[] = $row['durum_adi'];
            $chart_data[] = $row['siparis_sayisi'];
            
            // Duruma göre renk belirle
            switch ($row['durum_adi']) {
                case 'Açık': $chart_colors[] = '#1cc88a'; break; // Yeşil
                case 'Kapalı': $chart_colors[] = '#858796'; break; // Gri
                case 'Beklemede': $chart_colors[] = '#f6c23e'; break; // Sarı
                case 'İptal': $chart_colors[] = '#e74a3b'; break; // Kırmızı
                default: $chart_colors[] = '#4e73df'; // Mavi
            }
        }
        break;
        
    case 'tedarikci_performans':
        // Tedarikçi performans raporu
        $sql = "SELECT t.firma_adi, 
                COUNT(s.id) AS toplam_siparis,
                SUM(CASE WHEN s.durum_id = 2 THEN 1 ELSE 0 END) AS tamamlanan_siparis,
                ROUND(SUM(CASE WHEN s.durum_id = 2 THEN 1 ELSE 0 END) / COUNT(s.id) * 100, 2) AS tamamlanma_orani,
                ROUND(AVG(DATEDIFF(s.teslimat_tarihi, s.acilis_tarihi)), 0) AS ortalama_teslimat_suresi,
                SUM(CASE WHEN s.teslimat_tarihi > s.tedarikci_tarihi THEN 1 ELSE 0 END) AS gecikmeli_teslimat_sayisi
                FROM siparisler s
                LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                $where_clause
                GROUP BY t.firma_adi
                ORDER BY tamamlanma_orani DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($where_params);
        $rapor_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grafik verileri
        foreach ($rapor_data as $row) {
            $chart_labels[] = $row['firma_adi'];
            $chart_data[] = $row['tamamlanma_orani'];
            $chart_colors[] = '#36b9cc'; // Mavi-Yeşil
        }
        break;
        
    case 'proje_siparis':
        // Proje bazında sipariş raporu
        $sql = "SELECT p.proje_adi,
                COUNT(s.id) AS toplam_siparis,
                SUM(CASE WHEN s.durum_id = 1 THEN 1 ELSE 0 END) AS acik_siparis,
                SUM(CASE WHEN s.durum_id = 2 THEN 1 ELSE 0 END) AS kapali_siparis,
                SUM(CASE WHEN s.durum_id = 3 THEN 1 ELSE 0 END) AS bekleyen_siparis
                FROM siparisler s
                LEFT JOIN projeler p ON s.proje_id = p.id
                $where_clause
                GROUP BY p.proje_adi
                ORDER BY toplam_siparis DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($where_params);
        $rapor_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grafik verileri - toplam sipariş
        foreach ($rapor_data as $row) {
            $chart_labels[] = $row['proje_adi'];
            $chart_data[] = $row['toplam_siparis'];
            $chart_colors[] = '#4e73df'; // Mavi
        }
        break;
        
    case 'teslimat_zamanlama':
        // Teslimat zamanlaması raporu
        $sql = "SELECT
                COUNT(s.id) AS toplam_siparis,
                SUM(CASE WHEN s.teslimat_tarihi <= s.tedarikci_tarihi THEN 1 ELSE 0 END) AS zamaninda_teslim,
                SUM(CASE WHEN s.teslimat_tarihi > s.tedarikci_tarihi THEN 1 ELSE 0 END) AS gecikmeli_teslim,
                ROUND(SUM(CASE WHEN s.teslimat_tarihi <= s.tedarikci_tarihi THEN 1 ELSE 0 END) / COUNT(s.id) * 100, 2) AS zamaninda_teslim_orani,
                t.firma_adi
                FROM siparisler s
                LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                $where_clause AND s.teslimat_tarihi IS NOT NULL AND s.tedarikci_tarihi IS NOT NULL
                GROUP BY t.firma_adi
                ORDER BY zamaninda_teslim_orani DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($where_params);
        $rapor_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grafik verileri
        foreach ($rapor_data as $row) {
            $chart_labels[] = $row['firma_adi'];
            $chart_data[] = [
                'zamaninda' => $row['zamaninda_teslim'],
                'gecikmeli' => $row['gecikmeli_teslim']
            ];
        }
        break;
}

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Sorumlu Paneli - Tedarik Portalı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .chart-container {
            position: relative;
            margin: auto;
            height: 60vh;
            width: 100%;
        }
        .nav-pills .nav-link.active {
            background-color: #36b9cc;
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
                    <a class="nav-link active" href="raporlar.php">
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
                <h1 class="h2">Raporlar</h1>
            </div>

            <!-- Rapor Türü Seçimi -->
            <div class="row mb-4">
                <div class="col-12">
                    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $rapor_turu == 'siparis_durum' ? 'active' : '' ?>" href="raporlar.php?rapor=siparis_durum<?= isset($_GET['baslangic_tarihi']) ? '&baslangic_tarihi=' . $_GET['baslangic_tarihi'] : '' ?><?= isset($_GET['bitis_tarihi']) ? '&bitis_tarihi=' . $_GET['bitis_tarihi'] : '' ?><?= isset($_GET['tedarikci_id']) && $_GET['tedarikci_id'] > 0 ? '&tedarikci_id=' . $_GET['tedarikci_id'] : '' ?><?= isset($_GET['proje_id']) && $_GET['proje_id'] > 0 ? '&proje_id=' . $_GET['proje_id'] : '' ?>">
                                <i class="bi bi-pie-chart"></i> Sipariş Durumu
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $rapor_turu == 'tedarikci_performans' ? 'active' : '' ?>" href="raporlar.php?rapor=tedarikci_performans<?= isset($_GET['baslangic_tarihi']) ? '&baslangic_tarihi=' . $_GET['baslangic_tarihi'] : '' ?><?= isset($_GET['bitis_tarihi']) ? '&bitis_tarihi=' . $_GET['bitis_tarihi'] : '' ?><?= isset($_GET['tedarikci_id']) && $_GET['tedarikci_id'] > 0 ? '&tedarikci_id=' . $_GET['tedarikci_id'] : '' ?><?= isset($_GET['proje_id']) && $_GET['proje_id'] > 0 ? '&proje_id=' . $_GET['proje_id'] : '' ?>">
                                <i class="bi bi-bar-chart"></i> Tedarikçi Performansı
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $rapor_turu == 'proje_siparis' ? 'active' : '' ?>" href="raporlar.php?rapor=proje_siparis<?= isset($_GET['baslangic_tarihi']) ? '&baslangic_tarihi=' . $_GET['baslangic_tarihi'] : '' ?><?= isset($_GET['bitis_tarihi']) ? '&bitis_tarihi=' . $_GET['bitis_tarihi'] : '' ?><?= isset($_GET['tedarikci_id']) && $_GET['tedarikci_id'] > 0 ? '&tedarikci_id=' . $_GET['tedarikci_id'] : '' ?><?= isset($_GET['proje_id']) && $_GET['proje_id'] > 0 ? '&proje_id=' . $_GET['proje_id'] : '' ?>">
                                <i class="bi bi-clipboard-data"></i> Proje Siparişleri
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link <?= $rapor_turu == 'teslimat_zamanlama' ? 'active' : '' ?>" href="raporlar.php?rapor=teslimat_zamanlama<?= isset($_GET['baslangic_tarihi']) ? '&baslangic_tarihi=' . $_GET['baslangic_tarihi'] : '' ?><?= isset($_GET['bitis_tarihi']) ? '&bitis_tarihi=' . $_GET['bitis_tarihi'] : '' ?><?= isset($_GET['tedarikci_id']) && $_GET['tedarikci_id'] > 0 ? '&tedarikci_id=' . $_GET['tedarikci_id'] : '' ?><?= isset($_GET['proje_id']) && $_GET['proje_id'] > 0 ? '&proje_id=' . $_GET['proje_id'] : '' ?>">
                                <i class="bi bi-calendar-check"></i> Teslimat Zamanlaması
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Filtreler -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Rapor Filtreleme</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="raporlar.php" class="row g-3">
                        <input type="hidden" name="rapor" value="<?= $rapor_turu ?>">
                        
                        <div class="col-md-3">
                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?= $baslangic_tarihi ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?= $bitis_tarihi ?>">
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
                            <a href="raporlar.php?rapor=<?= $rapor_turu ?>" class="btn btn-secondary">Sıfırla</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Rapor Sonuçları -->
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h6 class="m-0 font-weight-bold">
                        <?php
                        switch ($rapor_turu) {
                            case 'siparis_durum': echo 'Sipariş Durumu Raporu'; break;
                            case 'tedarikci_performans': echo 'Tedarikçi Performansı Raporu'; break;
                            case 'proje_siparis': echo 'Proje Siparişleri Raporu'; break;
                            case 'teslimat_zamanlama': echo 'Teslimat Zamanlaması Raporu'; break;
                        }
                        ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Grafik -->
                            <div class="chart-container">
                                <canvas id="myChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <!-- Tablo -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <?php switch ($rapor_turu): ?>
                                        <?php case 'siparis_durum': ?>
                                            <thead>
                                                <tr>
                                                    <th>Durum</th>
                                                    <th>Sipariş Sayısı</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rapor_data as $row): ?>
                                                    <tr>
                                                        <td><?= guvenli($row['durum_adi']) ?></td>
                                                        <td><?= $row['siparis_sayisi'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php break; ?>
                                        
                                        <?php case 'tedarikci_performans': ?>
                                            <thead>
                                                <tr>
                                                    <th>Tedarikçi</th>
                                                    <th>Toplam Sipariş</th>
                                                    <th>Tamamlanan</th>
                                                    <th>Tamamlanma %</th>
                                                    <th>Ort. Süre (Gün)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rapor_data as $row): ?>
                                                    <tr>
                                                        <td><?= guvenli($row['firma_adi']) ?></td>
                                                        <td><?= $row['toplam_siparis'] ?></td>
                                                        <td><?= $row['tamamlanan_siparis'] ?></td>
                                                        <td><?= $row['tamamlanma_orani'] ?>%</td>
                                                        <td><?= $row['ortalama_teslimat_suresi'] ?? 'N/A' ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php break; ?>
                                        
                                        <?php case 'proje_siparis': ?>
                                            <thead>
                                                <tr>
                                                    <th>Proje</th>
                                                    <th>Toplam</th>
                                                    <th>Açık</th>
                                                    <th>Kapalı</th>
                                                    <th>Bekleyen</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rapor_data as $row): ?>
                                                    <tr>
                                                        <td><?= guvenli($row['proje_adi']) ?></td>
                                                        <td><?= $row['toplam_siparis'] ?></td>
                                                        <td><?= $row['acik_siparis'] ?></td>
                                                        <td><?= $row['kapali_siparis'] ?></td>
                                                        <td><?= $row['bekleyen_siparis'] ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php break; ?>
                                        
                                        <?php case 'teslimat_zamanlama': ?>
                                            <thead>
                                                <tr>
                                                    <th>Tedarikçi</th>
                                                    <th>Toplam</th>
                                                    <th>Zamanında</th>
                                                    <th>Gecikmeli</th>
                                                    <th>Zamanında %</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($rapor_data as $row): ?>
                                                    <tr>
                                                        <td><?= guvenli($row['firma_adi']) ?></td>
                                                        <td><?= $row['toplam_siparis'] ?></td>
                                                        <td><?= $row['zamaninda_teslim'] ?></td>
                                                        <td><?= $row['gecikmeli_teslim'] ?></td>
                                                        <td><?= $row['zamaninda_teslim_orani'] ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        <?php break; ?>
                                    <?php endswitch; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('myChart').getContext('2d');
        
        <?php if ($rapor_turu == 'siparis_durum'): ?>
            // Sipariş Durumu - Pie Chart
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: <?= json_encode($chart_colors) ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        },
                        title: {
                            display: true,
                            text: 'Sipariş Durumu Dağılımı'
                        }
                    }
                }
            });
            
        <?php elseif ($rapor_turu == 'tedarikci_performans'): ?>
            // Tedarikçi Performansı - Bar Chart
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Tamamlanma Oranı (%)',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: <?= json_encode($chart_colors) ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        title: {
                            display: true,
                            text: 'Tedarikçi Sipariş Tamamlanma Oranları'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            title: {
                                display: true,
                                text: 'Tamamlanma Oranı (%)'
                            }
                        }
                    }
                }
            });
            
        <?php elseif ($rapor_turu == 'proje_siparis'): ?>
            // Proje Siparişleri - Bar Chart
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'Toplam Sipariş',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: '#4e73df',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Proje Bazında Sipariş Sayıları'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sipariş Sayısı'
                            }
                        }
                    }
                }
            });
            
        <?php elseif ($rapor_turu == 'teslimat_zamanlama'): ?>
            // Teslimat Zamanlaması - Stacked Bar Chart
            const datasets = [];
            <?php if (!empty($chart_labels)): ?>
                datasets.push({
                    label: 'Zamanında Teslim',
                    data: <?= json_encode(array_map(function($item) { return $item['zamaninda']; }, $chart_data)) ?>,
                    backgroundColor: '#1cc88a',
                });
                datasets.push({
                    label: 'Gecikmeli Teslim',
                    data: <?= json_encode(array_map(function($item) { return $item['gecikmeli']; }, $chart_data)) ?>,
                    backgroundColor: '#e74a3b',
                });
            <?php endif; ?>
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Tedarikçi Bazında Teslimat Zamanlaması'
                        }
                    },
                    scales: {
                        x: {
                            stacked: true,
                        },
                        y: {
                            stacked: true,
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Sipariş Sayısı'
                            }
                        }
                    }
                }
            });
        <?php endif; ?>
    });
    </script>
</body>
</html> 