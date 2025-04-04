<?php
// tedarikci/index.php - Tedarikçi paneli ana sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Tedarikçi bilgilerini al
$kullanici_id = $_SESSION['kullanici_id'];
$tedarikci_sql = "SELECT t.* FROM tedarikciler t 
                 WHERE t.id = (SELECT tedarikci_id FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?)";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute([$kullanici_id]);
$tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

// Eğer kullanıcı_tedarikci_iliskileri tablosu yoksa, kullanıcı rolünden tedarikçi bilgilerini al
if (!$tedarikci) {
    // Alternatif sorgu - direkt kullanıcı ID'sine göre tedarikçi bul (eğer standart isimler kullanılıyorsa)
    $tedarikci_sql = "SELECT t.* FROM tedarikciler t
                    INNER JOIN kullanicilar k ON t.email = k.email
                    WHERE k.id = ?";
    $tedarikci_stmt = $db->prepare($tedarikci_sql);
    $tedarikci_stmt->execute([$kullanici_id]);
    $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
}

// Yine bulamazsak kullanıcı adını kontrol edelim 
if (!$tedarikci) {
    $kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
    $kullanici_stmt = $db->prepare($kullanici_sql);
    $kullanici_stmt->execute([$kullanici_id]);
    $kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($kullanici) {
        $tedarikci_sql = "SELECT * FROM tedarikciler WHERE email = ? OR firma_adi LIKE ?";
        $tedarikci_stmt = $db->prepare($tedarikci_sql);
        $tedarikci_stmt->execute([$kullanici['email'], '%' . $kullanici['ad_soyad'] . '%']);
        $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!$tedarikci) {
    // Bu kullanıcı için tedarikçi kaydı bulunamadı
    header("Location: ../yetki_yok.php");
    exit;
}

$tedarikci_id = $tedarikci['id'];

// Tedarikçi için siparişlerin istatistiklerini al
$siparisler_sql = "SELECT COUNT(*) as toplam, 
                  SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                  SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                  SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                  FROM siparisler
                  WHERE tedarikci_id = ?";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$tedarikci_id]);
$siparisler_istatistik = $siparisler_stmt->fetch(PDO::FETCH_ASSOC);

// Son 10 siparişi al
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi
                      FROM siparisler s
                      LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                      LEFT JOIN projeler p ON s.proje_id = p.id
                      LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                      WHERE s.tedarikci_id = ?
                      ORDER BY s.olusturma_tarihi DESC
                      LIMIT 10";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute([$tedarikci_id]);
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Yaklaşan teslimat tarihleri
$yaklasan_teslimler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi
                          FROM siparisler s
                          LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                          LEFT JOIN projeler p ON s.proje_id = p.id
                          WHERE s.tedarikci_id = ? AND s.durum_id = 1
                          AND s.teslim_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                          ORDER BY s.teslim_tarihi ASC
                          LIMIT 5";
$yaklasan_teslimler_stmt = $db->prepare($yaklasan_teslimler_sql);
$yaklasan_teslimler_stmt->execute([$tedarikci_id]);
$yaklasan_teslimler = $yaklasan_teslimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Son bildirimler
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$kullanici_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);

// Sorumlu kişiler
$sorumlular_sql = "SELECT k.id, k.ad_soyad, k.email, k.telefon 
                  FROM kullanicilar k
                  INNER JOIN sorumluluklar s ON k.id = s.sorumlu_id
                  WHERE s.tedarikci_id = ?";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute([$tedarikci_id]);
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ana Sayfa - Tedarikçi Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fc;
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #26c281;
            width: 204px;
        }
        .sidebar-sticky {
            position: sticky;
            top: 0;
            height: 100vh;
            padding-top: 0.5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            font-weight: 500;
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
        .sidebar-heading {
            color: white;
            text-align: center;
            padding: 20px 0;
        }
        .sidebar-heading h4 {
            margin-bottom: 0.25rem;
            font-weight: 600;
        }
        main {
            margin-left: 204px;
            padding: 1.5rem;
        }
        .navbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            height: 56px;
            background-color: #fff !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .navbar-toggler {
            padding: 0.25rem 0.75rem;
            font-size: 1.25rem;
            line-height: 1;
            background-color: transparent;
            border: 1px solid transparent;
            border-radius: 0.25rem;
        }
        .card {
            border: none;
            margin-bottom: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .card-header {
            padding: 0.75rem 1.25rem;
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        .badge-notification {
            position: absolute;
            top: 0.2rem;
            right: 0.2rem;
            font-size: 0.75rem;
        }
        .stats-card {
            border-left: 0.25rem solid;
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-4px);
        }
        .stats-card-primary {
            border-left-color: #4e73df;
        }
        .stats-card-success {
            border-left-color: #1cc88a;
        }
        .stats-card-info {
            border-left-color: #36b9cc;
        }
        .stats-card-warning {
            border-left-color: #f6c23e;
        }
        .stats-card-icon {
            font-size: 2rem;
            opacity: 0.2;
        }
        .card-link {
            text-decoration: none;
            color: inherit;
        }
        .card-link:hover {
            color: inherit;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar">
        <div class="sidebar-heading">
            <h4>Tedarik Portalı</h4>
            <p>Tedarikçi Paneli</p>
        </div>
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siparislerim.php">
                        <i class="bi bi-list-check"></i> Siparişlerim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="siparis_guncelle.php">
                        <i class="bi bi-pencil-square"></i> Sipariş Güncelle
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="teslimatlarim.php">
                        <i class="bi bi-truck"></i> Teslimatlarım
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dokumanlar.php">
                        <i class="bi bi-file-earmark-text"></i> Dokümanlar
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

    <!-- Ana içerik -->
    <main>
        <!-- Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-white mb-4">
            <div class="container-fluid">
                <span class="navbar-brand mb-0 h1">Tedarikçi Paneli</span>
                <div class="ms-auto d-flex">
                    <div class="dropdown me-3">
                        <a class="nav-link position-relative" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5"></i>
                            <?php if ($okunmamis_bildirim_sayisi > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= $okunmamis_bildirim_sayisi ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="#">Bildirimleri Gör</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?= htmlspecialchars($_SESSION['ad_soyad']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profil.php">Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../cikis.php">Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Firma Bilgileri -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Firma Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Firma Adı:</div>
                            <div class="col-md-8"><?= htmlspecialchars($tedarikci['firma_adi']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Firma Kodu:</div>
                            <div class="col-md-8"><?= htmlspecialchars($tedarikci['firma_kodu'] ?? '-') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Vergi No:</div>
                            <div class="col-md-8"><?= htmlspecialchars($tedarikci['vergi_no'] ?? '-') ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Yetkili Kişi:</div>
                            <div class="col-md-8"><?= htmlspecialchars($tedarikci['yetkili_kisi'] ?? '-') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">E-posta:</div>
                            <div class="col-md-8"><?= htmlspecialchars($tedarikci['email'] ?? '-') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Telefon:</div>
                            <div class="col-md-8"><?= htmlspecialchars($tedarikci['telefon'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- İstatistikler -->
        <div class="row">
            <!-- Toplam Siparişler -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card stats-card-primary h-100">
                    <a href="siparislerim.php" class="card-link">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">Toplam Siparişler</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $siparisler_istatistik['toplam'] ?? 0 ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clipboard-check stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Açık Siparişler -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card stats-card-warning h-100">
                    <a href="siparislerim.php?durum_id=1" class="card-link">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">Açık Siparişler</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $siparisler_istatistik['acik'] ?? 0 ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-hourglass-split stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Bekleyen Siparişler -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card stats-card-info h-100">
                    <a href="siparislerim.php?durum_id=3" class="card-link">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-info text-uppercase mb-1">Bekleyen Siparişler</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $siparisler_istatistik['beklemede'] ?? 0 ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-clock-history stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Tamamlanan Siparişler -->
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card stats-card-success h-100">
                    <a href="siparislerim.php?durum_id=2" class="card-link">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">Tamamlanan Siparişler</div>
                                    <div class="h5 mb-0 fw-bold text-gray-800"><?= $siparisler_istatistik['kapali'] ?? 0 ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="bi bi-check-circle stats-card-icon"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Yaklaşan Teslimatlar -->
        <div class="card mb-4">
            <div class="card-header bg-warning text-dark">
                <h5 class="card-title mb-0">Yaklaşan Teslimatlar</h5>
            </div>
            <div class="card-body">
                <?php if (empty($yaklasan_teslimler)): ?>
                    <div class="alert alert-info">
                        Yaklaşan teslimat bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Parça No</th>
                                    <th>Tanım</th>
                                    <th>Proje</th>
                                    <th>Teslim Tarihi</th>
                                    <th>Kalan Gün</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yaklasan_teslimler as $teslimat): ?>
                                    <tr class="<?= $teslimat['kalan_gun'] <= 3 ? 'table-danger' : ($teslimat['kalan_gun'] <= 7 ? 'table-warning' : '') ?>">
                                        <td><?= htmlspecialchars($teslimat['siparis_no']) ?></td>
                                        <td><?= htmlspecialchars($teslimat['parca_no']) ?></td>
                                        <td><?= htmlspecialchars($teslimat['aciklama']) ?></td>
                                        <td><?= htmlspecialchars($teslimat['proje_adi']) ?></td>
                                        <td><?= date('d.m.Y', strtotime($teslimat['teslim_tarihi'])) ?></td>
                                        <td>
                                            <span class="badge <?= $teslimat['kalan_gun'] <= 3 ? 'bg-danger' : ($teslimat['kalan_gun'] <= 7 ? 'bg-warning' : 'bg-info') ?>">
                                                <?= $teslimat['kalan_gun'] ?> gün
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="siparis_detay.php?id=<?= $teslimat['id'] ?>" class="btn btn-info" title="Detay">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="siparis_guncelle.php?id=<?= $teslimat['id'] ?>" class="btn btn-primary" title="Güncelle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <a href="teslimatlarim.php" class="btn btn-outline-warning">
                            Tüm Teslimatları Görüntüle <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Son Siparişler -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Son Siparişler</h5>
            </div>
            <div class="card-body">
                <?php if (empty($son_siparisler)): ?>
                    <div class="alert alert-info">
                        Henüz sipariş bulunmamaktadır.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Parça No</th>
                                    <th>Tanım</th>
                                    <th>Proje</th>
                                    <th>Açılış Tarihi</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($son_siparisler as $siparis): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($siparis['siparis_no']) ?></td>
                                        <td><?= htmlspecialchars($siparis['parca_no']) ?></td>
                                        <td><?= htmlspecialchars($siparis['aciklama']) ?></td>
                                        <td><?= htmlspecialchars($siparis['proje_adi']) ?></td>
                                        <td><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></td>
                                        <td>
                                            <span class="badge <?= ($siparis['durum_id'] == 1 ? 'bg-warning' : ($siparis['durum_id'] == 2 ? 'bg-success' : ($siparis['durum_id'] == 3 ? 'bg-info' : 'bg-secondary'))) ?>">
                                                <?= htmlspecialchars($siparis['durum_adi']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info" title="Detay">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="siparis_guncelle.php?id=<?= $siparis['id'] ?>" class="btn btn-primary" title="Güncelle">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-end mt-3">
                        <a href="siparislerim.php" class="btn btn-outline-success">
                            Tüm Siparişleri Görüntüle <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 