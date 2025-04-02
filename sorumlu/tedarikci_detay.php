<?php
// sorumlu/tedarikci_detay.php - Sorumlu olunan tedarikçinin detay bilgileri
require_once '../config.php';
sorumluYetkisiKontrol();

$sorumlu_id = $_SESSION['kullanici_id'];
$tedarikci_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Tedarikçi bilgilerini al ve yetki kontrolü yap
$tedarikci_sql = "SELECT t.* 
                  FROM tedarikciler t
                  INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                  WHERE t.id = ? AND s.sorumlu_id = ?";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute([$tedarikci_id, $sorumlu_id]);
$tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

// Tedarikçi bulunamadıysa veya yetki yoksa ana sayfaya yönlendir
if (!$tedarikci) {
    header("Location: tedarikcilerim.php");
    exit;
}

// Tedarikçinin sipariş özetini al
$siparis_ozet_sql = "SELECT 
                     COUNT(*) as toplam,
                     SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                     SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                     SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                     FROM siparisler 
                     WHERE tedarikci_id = ?";
$siparis_ozet_stmt = $db->prepare($siparis_ozet_sql);
$siparis_ozet_stmt->execute([$tedarikci_id]);
$siparis_ozet = $siparis_ozet_stmt->fetch(PDO::FETCH_ASSOC);

// Tedarikçinin son siparişlerini al
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi
                      FROM siparisler s
                      LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                      LEFT JOIN projeler p ON s.proje_id = p.id
                      WHERE s.tedarikci_id = ?
                      ORDER BY s.acilis_tarihi DESC
                      LIMIT 10";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute([$tedarikci_id]);
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Proje bazında sipariş özetini al
$proje_ozet_sql = "SELECT p.id, p.proje_adi, COUNT(s.id) as siparis_sayisi
                  FROM siparisler s
                  LEFT JOIN projeler p ON s.proje_id = p.id
                  WHERE s.tedarikci_id = ?
                  GROUP BY p.id, p.proje_adi
                  ORDER BY siparis_sayisi DESC";
$proje_ozet_stmt = $db->prepare($proje_ozet_sql);
$proje_ozet_stmt->execute([$tedarikci_id]);
$proje_ozet = $proje_ozet_stmt->fetchAll(PDO::FETCH_ASSOC);

// Teslimat durumu özetini al
$teslimat_ozet_sql = "SELECT 
                     COUNT(*) as toplam_siparis,
                     SUM(CASE WHEN teslimat_tarihi IS NOT NULL THEN 1 ELSE 0 END) as teslimat_yapilan,
                     SUM(CASE WHEN teslimat_tarihi IS NULL THEN 1 ELSE 0 END) as teslimat_bekleyen,
                     SUM(CASE WHEN teslimat_tarihi < NOW() AND durum_id = 1 THEN 1 ELSE 0 END) as geciken
                     FROM siparisler
                     WHERE tedarikci_id = ?";
$teslimat_ozet_stmt = $db->prepare($teslimat_ozet_sql);
$teslimat_ozet_stmt->execute([$tedarikci_id]);
$teslimat_ozet = $teslimat_ozet_stmt->fetch(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= guvenli($tedarikci['firma_adi']) ?> - Tedarikçi Detayları - Sorumlu Paneli</title>
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
        .info-box {
            border-radius: 0.5rem;
            padding: 1.5rem;
            transition: all 0.3s;
        }
        .info-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .info-box-icon {
            font-size: 2rem;
            margin-right: 1rem;
        }
        .info-box-count {
            font-size: 1.75rem;
            font-weight: 600;
        }
        .info-box-label {
            color: #6c757d;
        }
        .bg-info-light {
            background-color: rgba(54, 185, 204, 0.15);
        }
        .bg-success-light {
            background-color: rgba(28, 200, 138, 0.15);
        }
        .bg-warning-light {
            background-color: rgba(246, 194, 62, 0.15);
        }
        .bg-danger-light {
            background-color: rgba(231, 74, 59, 0.15);
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
                <h1 class="h2">Tedarikçi Detayları</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="siparisler.php?tedarikci_id=<?= $tedarikci_id ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-list-check"></i> Tüm Siparişler
                        </a>
                        <a href="tedarikcilerim.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Tedarikçilere Dön
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tedarikçi Bilgileri -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><?= guvenli($tedarikci['firma_adi']) ?></h5>
                    <small><?= guvenli($tedarikci['firma_kodu']) ?></small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="mb-3 border-bottom pb-2"><i class="bi bi-info-circle"></i> Temel Bilgiler</h6>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Firma Kodu:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['firma_kodu']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Vergi No:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['vergi_no']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Vergi Dairesi:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['vergi_dairesi']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Adres:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['adres']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">İl / İlçe:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['il']) ?> / <?= guvenli($tedarikci['ilce']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Web Sitesi:</div>
                                <div class="col-md-8">
                                    <?php if (!empty($tedarikci['website'])): ?>
                                        <a href="<?= guvenli($tedarikci['website']) ?>" target="_blank"><?= guvenli($tedarikci['website']) ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">Belirtilmemiş</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="mb-3 border-bottom pb-2"><i class="bi bi-person-check"></i> İletişim Bilgileri</h6>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Yetkili Kişi:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['yetkili_kisi']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">E-posta:</div>
                                <div class="col-md-8">
                                    <a href="mailto:<?= guvenli($tedarikci['email']) ?>"><?= guvenli($tedarikci['email']) ?></a>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Telefon:</div>
                                <div class="col-md-8"><?= guvenli($tedarikci['telefon']) ?></div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Fax:</div>
                                <div class="col-md-8">
                                    <?= !empty($tedarikci['fax']) ? guvenli($tedarikci['fax']) : '<span class="text-muted">Belirtilmemiş</span>' ?>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Cep Tel:</div>
                                <div class="col-md-8">
                                    <?= !empty($tedarikci['cep_telefonu']) ? guvenli($tedarikci['cep_telefonu']) : '<span class="text-muted">Belirtilmemiş</span>' ?>
                                </div>
                            </div>
                            <div class="row mb-2">
                                <div class="col-md-4 fw-bold">Açıklama:</div>
                                <div class="col-md-8">
                                    <?= !empty($tedarikci['aciklama']) ? guvenli($tedarikci['aciklama']) : '<span class="text-muted">Belirtilmemiş</span>' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sipariş İstatistikleri -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="info-box d-flex align-items-center bg-info-light">
                        <div class="info-box-icon text-info">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <div>
                            <div class="info-box-count text-info"><?= $siparis_ozet['toplam'] ?? 0 ?></div>
                            <div class="info-box-label">Toplam Sipariş</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="info-box d-flex align-items-center bg-success-light">
                        <div class="info-box-icon text-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <div class="info-box-count text-success"><?= $siparis_ozet['acik'] ?? 0 ?></div>
                            <div class="info-box-label">Açık Sipariş</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="info-box d-flex align-items-center bg-warning-light">
                        <div class="info-box-icon text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <div class="info-box-count text-warning"><?= $siparis_ozet['beklemede'] ?? 0 ?></div>
                            <div class="info-box-label">Bekleyen Sipariş</div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-4">
                    <div class="info-box d-flex align-items-center bg-danger-light">
                        <div class="info-box-icon text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <div>
                            <div class="info-box-count text-danger"><?= $teslimat_ozet['geciken'] ?? 0 ?></div>
                            <div class="info-box-label">Geciken Sipariş</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row mb-4">
                <!-- Son Siparişler -->
                <div class="col-lg-8">
                    <div class="card h-100">
                        <div class="card-header bg-success text-white">
                            <h6 class="m-0 font-weight-bold">Son Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Sipariş No</th>
                                            <th>Parça No</th>
                                            <th>Proje</th>
                                            <th>Miktar</th>
                                            <th>Durum</th>
                                            <th>Tarih</th>
                                            <th>İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($son_siparisler) > 0): ?>
                                            <?php foreach ($son_siparisler as $siparis): ?>
                                                <tr>
                                                    <td><?= guvenli($siparis['siparis_no']) ?></td>
                                                    <td><?= guvenli($siparis['parca_no']) ?></td>
                                                    <td><?= guvenli($siparis['proje_adi']) ?></td>
                                                    <td><?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?></td>
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
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="siparis_guncelle.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-success">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Bu tedarikçiye ait sipariş bulunamadı.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($son_siparisler) > 0): ?>
                                <div class="text-center mt-3">
                                    <a href="siparisler.php?tedarikci_id=<?= $tedarikci_id ?>" class="btn btn-success">
                                        Tüm Siparişleri Görüntüle
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Proje Bazında Sipariş Özeti ve Teslimat Durumu -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h6 class="m-0 font-weight-bold">Proje Bazında Siparişler</h6>
                        </div>
                        <div class="card-body">
                            <?php if (count($proje_ozet) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($proje_ozet as $proje): ?>
                                        <a href="siparisler.php?tedarikci_id=<?= $tedarikci_id ?>&proje_id=<?= $proje['id'] ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                            <?= guvenli($proje['proje_adi']) ?>
                                            <span class="badge bg-primary rounded-pill"><?= $proje['siparis_sayisi'] ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center">Bu tedarikçiye ait sipariş bulunamadı.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="m-0 font-weight-bold">Teslimat Durumu</h6>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border p-3 rounded mb-3">
                                        <h5 class="text-success"><?= $teslimat_ozet['teslimat_yapilan'] ?? 0 ?></h5>
                                        <p class="mb-0">Teslimat Yapılan</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="border p-3 rounded mb-3">
                                        <h5 class="text-warning"><?= $teslimat_ozet['teslimat_bekleyen'] ?? 0 ?></h5>
                                        <p class="mb-0">Teslimat Bekleyen</p>
                                    </div>
                                </div>
                            </div>
                            <div class="progress mb-2" style="height: 20px;">
                                <?php
                                $toplam = $teslimat_ozet['toplam_siparis'] ?? 1; // 0'a bölmemek için en az 1 
                                $yapilan_oran = ($teslimat_ozet['teslimat_yapilan'] ?? 0) / $toplam * 100;
                                ?>
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?= $yapilan_oran ?>%;" aria-valuenow="<?= $yapilan_oran ?>" aria-valuemin="0" aria-valuemax="100">
                                    <?= round($yapilan_oran) ?>%
                                </div>
                            </div>
                            <p class="text-center small text-muted">
                                Toplam <?= $teslimat_ozet['toplam_siparis'] ?? 0 ?> siparişten <?= $teslimat_ozet['teslimat_yapilan'] ?? 0 ?> tanesi teslim edildi
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 