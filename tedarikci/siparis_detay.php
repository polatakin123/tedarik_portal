<?php
// tedarikci/siparis_detay.php - Tedarikçinin sipariş detaylarını görebildiği sayfa
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

// Sipariş ID kontrolü
$siparis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($siparis_id <= 0) {
    header("Location: siparislerim.php");
    exit;
}

// Tedarikçiye ait sipariş bilgisini getir
$siparis_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi, u.email as sorumlu_email,
                u.telefon as sorumlu_telefon, o.ad_soyad as olusturan_adi
                FROM siparisler s
                LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                LEFT JOIN projeler p ON s.proje_id = p.id
                LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                LEFT JOIN kullanicilar o ON s.olusturan_id = o.id
                WHERE s.id = ? AND s.tedarikci_id = ?";
$siparis_stmt = $db->prepare($siparis_sql);
$siparis_stmt->execute([$siparis_id, $tedarikci_id]);
$siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

if (!$siparis) {
    // Sipariş bulunamadı veya bu tedarikçiye ait değil
    header("Location: siparislerim.php");
    exit;
}

// Sipariş dokümanlarını getir
$dokuman_sql = "SELECT * FROM siparis_dokumanlari WHERE siparis_id = ? ORDER BY yukleme_tarihi DESC";
$dokuman_stmt = $db->prepare($dokuman_sql);
$dokuman_stmt->execute([$siparis_id]);
$dokumanlar = $dokuman_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş güncellemelerini getir
$guncelleme_sql = "SELECT sg.*, k.ad_soyad
                  FROM siparis_guncellemeleri sg
                  LEFT JOIN kullanicilar k ON sg.guncelleyen_id = k.id
                  WHERE sg.siparis_id = ?
                  ORDER BY sg.guncelleme_tarihi DESC";
$guncelleme_stmt = $db->prepare($guncelleme_sql);
$guncelleme_stmt->execute([$siparis_id]);
$guncellemeler = $guncelleme_stmt->fetchAll(PDO::FETCH_ASSOC);

// Teslimat bilgilerini getir
try {
    // Teslimatlar tablosu var mı diye kontrol et
    $tabloKontrol = $db->query("SHOW TABLES LIKE 'siparis_teslimatlari'");
    $teslimatlarTablosuVar = $tabloKontrol->rowCount() > 0;
    
    if ($teslimatlarTablosuVar) {
        $teslimatlar_sql = "SELECT st.*, k.ad_soyad AS teslim_eden_adi
                           FROM siparis_teslimatlari st
                           LEFT JOIN kullanicilar k ON st.olusturan_id = k.id
                           WHERE st.siparis_id = ?
                           ORDER BY st.teslimat_tarihi DESC";
        $teslimatlar_stmt = $db->prepare($teslimatlar_sql);
        $teslimatlar_stmt->execute([$siparis_id]);
        $teslimatlar = $teslimatlar_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $teslimatlar = [];
    }
} catch (Exception $e) {
    // Tablo yoksa boş dizi döndür
    $teslimatlar = [];
}

// Okunmamış bildirimleri al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Detayı - Tedarikçi Paneli</title>
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
        .file-type-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 0.75rem;
            width: 2px;
            background-color: #e9ecef;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -2rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #26c281;
            z-index: 1;
        }
        .timeline-item:last-child {
            padding-bottom: 0;
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
                    <a class="nav-link" href="index.php">
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
                <span class="navbar-brand mb-0 h1">Sipariş Detayı: <?= htmlspecialchars($siparis['siparis_no']) ?></span>
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

        <div class="d-flex justify-content-end mb-3">
            <a href="siparislerim.php" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Siparişlerime Dön
            </a>
            <a href="siparis_guncelle.php?id=<?= $siparis_id ?>" class="btn btn-primary">
                <i class="bi bi-pencil-square"></i> Sipariş Güncelle
            </a>
        </div>

        <div class="row">
            <div class="col-md-6">
                <!-- Sipariş Genel Bilgileri -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Sipariş No:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['siparis_no']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Parça No:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['parca_no']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Tedarikçi Parça No:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['tedarikci_parca_no'] ?? '-') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Tanım:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['tanim']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Proje:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['proje_adi']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Durum:</div>
                            <div class="col-md-8">
                                <span class="badge <?= ($siparis['durum_id'] == 1 ? 'bg-warning' : ($siparis['durum_id'] == 2 ? 'bg-success' : ($siparis['durum_id'] == 3 ? 'bg-info' : 'bg-secondary'))) ?>">
                                    <?= htmlspecialchars($siparis['durum_adi']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Teslimat Bilgileri -->
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0">Teslimat Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Toplam Miktar:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['miktar']) ?> <?= htmlspecialchars($siparis['birim']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Kalan Miktar:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['kalan_miktar']) ?> <?= htmlspecialchars($siparis['birim']) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Talep Edilen Tarih:</div>
                            <div class="col-md-8"><?= $siparis['teslim_tarihi'] ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : '-' ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Tedarikçi Teslim Tarihi:</div>
                            <div class="col-md-8"><?= $siparis['tedarikci_tarihi'] ? date('d.m.Y', strtotime($siparis['tedarikci_tarihi'])) : '-' ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Açılış Tarihi:</div>
                            <div class="col-md-8"><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Son Güncelleme:</div>
                            <div class="col-md-8"><?= date('d.m.Y H:i', strtotime($siparis['guncelleme_tarihi'])) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Sorumlu Kişi Bilgileri -->
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">Sorumlu Kişi Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Sorumlu Adı:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['sorumlu_adi']) ?></div>
                        </div>
                        <?php if (!empty($siparis['sorumlu_email'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">E-posta:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['sorumlu_email']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($siparis['sorumlu_telefon'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Telefon:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['sorumlu_telefon']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <!-- Notlar ve Açıklamalar -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Notlar ve Açıklamalar</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Sipariş Notu:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['siparis_notu'] ?? '-') ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Tedarikçi Notu:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['tedarikci_notu'] ?? '-') ?></div>
                        </div>
                        <?php if (!empty($siparis['teknik_resim_no'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Teknik Resim No:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['teknik_resim_no']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($siparis['revizyon'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Revizyon:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['revizyon']) ?></div>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($siparis['onaylanan_revizyon'])): ?>
                        <div class="row mb-3">
                            <div class="col-md-4 fw-bold">Onaylanan Revizyon:</div>
                            <div class="col-md-8"><?= htmlspecialchars($siparis['onaylanan_revizyon']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dokümanlar -->
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="card-title mb-0">Sipariş Dokümanları</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($dokumanlar)): ?>
                            <p class="text-center">Bu sipariş için henüz bir doküman yüklenmemiş.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($dokumanlar as $dokuman): ?>
                                    <?php
                                    // Dosya tipine göre ikon belirle
                                    $icon = 'bi-file-earmark';
                                    $tipExt = strtolower($dokuman['dosya_turu'] ?? '');
                                    
                                    if ($tipExt == 'pdf') $icon = 'bi-file-earmark-pdf';
                                    else if (in_array($tipExt, ['doc', 'docx'])) $icon = 'bi-file-earmark-word';
                                    else if (in_array($tipExt, ['xls', 'xlsx'])) $icon = 'bi-file-earmark-excel';
                                    else if (in_array($tipExt, ['jpg', 'jpeg', 'png', 'gif'])) $icon = 'bi-file-earmark-image';
                                    else if (in_array($tipExt, ['ppt', 'pptx'])) $icon = 'bi-file-earmark-ppt';
                                    else if ($tipExt == 'zip') $icon = 'bi-file-earmark-zip';
                                    ?>
                                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi <?= $icon ?> file-type-icon"></i>
                                            <?= htmlspecialchars($dokuman['dokuman_adi']) ?>
                                            <small class="text-muted d-block">
                                                Yüklenme: <?= date('d.m.Y', strtotime($dokuman['yukleme_tarihi'])) ?>
                                            </small>
                                        </div>
                                        <div>
                                            <a href="<?= htmlspecialchars($dokuman['dosya_yolu']) ?>" class="btn btn-sm btn-primary" download title="İndir">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($dokuman['dosya_yolu']) ?>" class="btn btn-sm btn-info" target="_blank" title="Görüntüle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Teslimat Geçmişi -->
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">Teslimat Geçmişi</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($teslimatlar)): ?>
                            <p class="text-center">Bu sipariş için henüz teslimat kaydı bulunmamaktadır.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Teslimat Tarihi</th>
                                            <th>Miktar</th>
                                            <th>İrsaliye No</th>
                                            <th>Not</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($teslimatlar as $teslimat): ?>
                                            <tr>
                                                <td><?= date('d.m.Y', strtotime($teslimat['teslimat_tarihi'])) ?></td>
                                                <td><?= $teslimat['teslim_edilen'] ?> <?= htmlspecialchars($siparis['birim']) ?></td>
                                                <td><?= htmlspecialchars($teslimat['irsaliye_no'] ?? '-') ?></td>
                                                <td><?= htmlspecialchars($teslimat['teslimat_notu'] ?? '-') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sipariş Tarihçesi -->
        <div class="card mt-3">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Sipariş Tarihçesi</h5>
            </div>
            <div class="card-body">
                <?php if (empty($guncellemeler)): ?>
                    <p class="text-center">Bu sipariş için henüz bir güncelleme kaydı bulunmamaktadır.</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($guncellemeler as $guncelleme): ?>
                            <div class="timeline-item">
                                <div class="mb-2">
                                    <strong><?= htmlspecialchars($guncelleme['guncelleme_tipi']) ?></strong>
                                    <span class="text-muted ms-2">
                                        <?= date('d.m.Y H:i', strtotime($guncelleme['guncelleme_tarihi'])) ?>
                                    </span>
                                </div>
                                <p><?= htmlspecialchars($guncelleme['guncelleme_detay']) ?></p>
                                <p class="text-muted">
                                    <small>Güncelleyen: <?= htmlspecialchars($guncelleme['ad_soyad']) ?></small>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 