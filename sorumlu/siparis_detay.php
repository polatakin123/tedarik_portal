<?php
// sorumlu/siparis_detay.php - Sipariş detay sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

$sorumlu_id = $_SESSION['kullanici_id'];
$siparis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Sipariş bilgilerini al
$siparis_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, t.firma_adi, t.firma_kodu, t.email as tedarikci_email,
                u.ad_soyad as sorumlu_adi
                FROM siparisler s
                LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                LEFT JOIN projeler p ON s.proje_id = p.id
                LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                WHERE s.id = ? AND (s.sorumlu_id = ? OR s.tedarikci_id IN (
                    SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                ))";
$siparis_stmt = $db->prepare($siparis_sql);
$siparis_stmt->execute([$siparis_id, $sorumlu_id, $sorumlu_id]);
$siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

// Sipariş yoksa veya yetki yoksa hata
if (!$siparis) {
    header("Location: siparisler.php");
    exit;
}

// Sipariş belgeleri
$dokuman_sql = "SELECT * FROM siparis_dokumanlari WHERE siparis_id = ? ORDER BY yukleme_tarihi DESC";
$dokuman_stmt = $db->prepare($dokuman_sql);
$dokuman_stmt->execute([$siparis_id]);
$dokumanlar = $dokuman_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş geçmişi
$log_sql = "SELECT sl.*, u.ad_soyad, sd.durum_adi
           FROM siparis_log sl
           LEFT JOIN kullanicilar u ON sl.islem_yapan_id = u.id
           LEFT JOIN siparis_durumlari sd ON sl.durum_id = sd.id
           WHERE sl.siparis_id = ?
           ORDER BY sl.islem_tarihi DESC";
$log_stmt = $db->prepare($log_sql);
$log_stmt->execute([$siparis_id]);
$log_kayitlari = $log_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısı
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $sorumlu_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı - Sorumlu Paneli - Tedarik Portalı</title>
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
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -22px;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e0e0e0;
        }
        .timeline-item:after {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            height: 16px;
            width: 16px;
            border-radius: 50%;
            background-color: #36b9cc;
            border: 3px solid white;
            box-shadow: 0 0 0 1px #e0e0e0;
        }
        .timeline-item:last-child:before {
            height: 0;
        }
        .detail-section {
            margin-bottom: 2rem;
            background-color: #f8f9fc;
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .detail-title {
            border-bottom: 1px solid #e3e6f0;
            padding-bottom: 0.75rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: #4e73df;
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
                <h1 class="h2">Sipariş Detayları</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="siparis_guncelle.php?id=<?= $siparis_id ?>" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil-square"></i> Güncelle
                        </a>
                        <a href="siparisler.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Siparişlere Dön
                        </a>
                    </div>
                </div>
            </div>

            <!-- Sipariş Özeti -->
            <div class="row">
                <div class="col-md-6">
                    <div class="detail-section">
                        <h3 class="detail-title"><i class="bi bi-card-heading"></i> Sipariş Bilgileri</h3>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Sipariş No:</div>
                            <div class="col-md-7"><?= guvenli($siparis['siparis_no']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Parça No:</div>
                            <div class="col-md-7"><?= guvenli($siparis['parca_no']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Tanım:</div>
                            <div class="col-md-7"><?= guvenli($siparis['tanim']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Miktar:</div>
                            <div class="col-md-7"><?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Teslim Edilen Miktar:</div>
                            <div class="col-md-7"><?= guvenli($siparis['teslim_edilen_miktar']) ?> <?= guvenli($siparis['birim']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Açılış Tarihi:</div>
                            <div class="col-md-7"><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Teslimat Tarihi:</div>
                            <div class="col-md-7">
                                <?php if ($siparis['teslimat_tarihi']): ?>
                                    <?= date('d.m.Y', strtotime($siparis['teslimat_tarihi'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">Belirtilmemiş</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Tedarikçi Teslim Tarihi:</div>
                            <div class="col-md-7">
                                <?php if ($siparis['tedarikci_tarihi']): ?>
                                    <?= date('d.m.Y', strtotime($siparis['tedarikci_tarihi'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">Belirtilmemiş</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Durum:</div>
                            <div class="col-md-7">
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
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Tedarikçi Notu:</div>
                            <div class="col-md-7">
                                <?= !empty($siparis['tedarikci_notu']) ? nl2br(guvenli($siparis['tedarikci_notu'])) : '<span class="text-muted">Not bulunmamaktadır</span>' ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="detail-section">
                        <h3 class="detail-title"><i class="bi bi-building"></i> Tedarikçi ve Proje Bilgileri</h3>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Tedarikçi:</div>
                            <div class="col-md-7"><?= guvenli($siparis['firma_adi']) ?> (<?= guvenli($siparis['firma_kodu']) ?>)</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Tedarikçi E-posta:</div>
                            <div class="col-md-7">
                                <a href="mailto:<?= guvenli($siparis['tedarikci_email']) ?>"><?= guvenli($siparis['tedarikci_email']) ?></a>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Proje:</div>
                            <div class="col-md-7"><?= guvenli($siparis['proje_adi']) ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Sorumlu:</div>
                            <div class="col-md-7"><?= guvenli($siparis['sorumlu_adi']) ?></div>
                        </div>
                        
                        <h3 class="detail-title mt-4"><i class="bi bi-info-circle"></i> Diğer Bilgiler</h3>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">FAI:</div>
                            <div class="col-md-7"><?= !empty($siparis['fai']) ? guvenli($siparis['fai']) : '<span class="text-muted">Belirtilmemiş</span>' ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Satınalmacı:</div>
                            <div class="col-md-7"><?= !empty($siparis['satinalmaci']) ? guvenli($siparis['satinalmaci']) : '<span class="text-muted">Belirtilmemiş</span>' ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Alt Malzeme:</div>
                            <div class="col-md-7"><?= !empty($siparis['alt_malzeme']) ? guvenli($siparis['alt_malzeme']) : '<span class="text-muted">Belirtilmemiş</span>' ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Onaylanan Revizyon:</div>
                            <div class="col-md-7"><?= !empty($siparis['onaylanan_revizyon']) ? guvenli($siparis['onaylanan_revizyon']) : '<span class="text-muted">Belirtilmemiş</span>' ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Tedarikçi Parça No:</div>
                            <div class="col-md-7"><?= !empty($siparis['tedarikci_parca_no']) ? guvenli($siparis['tedarikci_parca_no']) : '<span class="text-muted">Belirtilmemiş</span>' ?></div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-md-5 fw-bold">Vehicle ID:</div>
                            <div class="col-md-7"><?= !empty($siparis['vehicle_id']) ? guvenli($siparis['vehicle_id']) : '<span class="text-muted">Belirtilmemiş</span>' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Belge ve Geçmiş Bölümü -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0">Sipariş Belgeleri</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($dokumanlar) > 0): ?>
                                <div class="list-group">
                                    <?php foreach ($dokumanlar as $dokuman): ?>
                                        <a href="../uploads/<?= $dokuman['dosya_yolu'] ?>" target="_blank" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= guvenli($dokuman['dosya_adi']) ?></h6>
                                                <small><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></small>
                                            </div>
                                            <small class="text-muted">
                                                <?php
                                                $uzanti = pathinfo($dokuman['dosya_adi'], PATHINFO_EXTENSION);
                                                $icon = 'file-earmark';
                                                
                                                if (in_array($uzanti, ['pdf'])) $icon = 'file-earmark-pdf';
                                                elseif (in_array($uzanti, ['doc', 'docx'])) $icon = 'file-earmark-word';
                                                elseif (in_array($uzanti, ['xls', 'xlsx'])) $icon = 'file-earmark-excel';
                                                elseif (in_array($uzanti, ['jpg', 'jpeg', 'png', 'gif'])) $icon = 'file-earmark-image';
                                                ?>
                                                <i class="bi bi-<?= $icon ?>"></i> <?= strtoupper($uzanti) ?> Dosyası
                                                (<?= round($dokuman['dosya_boyutu'] / 1024, 2) ?> KB)
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-center text-muted mt-3">Bu siparişe ait belge bulunmamaktadır.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h5 class="mb-0">Sipariş Geçmişi</h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php if (count($log_kayitlari) > 0): ?>
                                    <?php foreach ($log_kayitlari as $log): ?>
                                        <div class="timeline-item">
                                            <div class="card mb-2">
                                                <div class="card-body py-2">
                                                    <div class="d-flex justify-content-between">
                                                        <div>
                                                            <h6 class="mb-0 fw-bold"><?= guvenli($log['islem_turu']) ?></h6>
                                                            <p class="mb-0"><?= guvenli($log['aciklama']) ?></p>
                                                            <?php if ($log['durum_id']): ?>
                                                                <span class="badge bg-info">Durum: <?= guvenli($log['durum_adi']) ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="text-end">
                                                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($log['islem_tarihi'])) ?></small>
                                                            <div><small><?= guvenli($log['ad_soyad']) ?></small></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-center text-muted mt-3">Sipariş geçmişi bulunamadı.</p>
                                <?php endif; ?>
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