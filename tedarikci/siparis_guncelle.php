<?php
// tedarikci/siparis_guncelle.php - Tedarikçinin sipariş güncelleme sayfası
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

// Mesaj değişkenleri
$mesaj = '';
$hata = '';

// Sipariş ID kontrolü
$siparis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($siparis_id > 0) {
    // İlgili siparişi getir
    $siparis_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi
                   FROM siparisler s
                   LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                   LEFT JOIN projeler p ON s.proje_id = p.id
                   LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                   WHERE s.id = ? AND s.tedarikci_id = ?";
    $siparis_stmt = $db->prepare($siparis_sql);
    $siparis_stmt->execute([$siparis_id, $tedarikci_id]);
    $siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$siparis) {
        // Sipariş bulunamadı veya bu tedarikçiye ait değil
        header("Location: siparislerim.php");
        exit;
    }

    // Form gönderildi mi?
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Tedarikçi tarafından güncellenebilecek alanlar
            $tedarikci_tarihi = !empty($_POST['tedarikci_tarihi']) ? $_POST['tedarikci_tarihi'] : null;
            $tedarikci_notu = $_POST['tedarikci_notu'] ?? '';
            $tedarikci_parca_no = $_POST['tedarikci_parca_no'] ?? '';

            // Siparişi güncelle
            $guncelle_sql = "UPDATE siparisler 
                           SET tedarikci_tarihi = ?, tedarikci_notu = ?, tedarikci_parca_no = ?, 
                               guncelleme_tarihi = NOW() 
                           WHERE id = ? AND tedarikci_id = ?";
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_stmt->execute([
                $tedarikci_tarihi, 
                $tedarikci_notu, 
                $tedarikci_parca_no, 
                $siparis_id, 
                $tedarikci_id
            ]);

            // Güncelleme kaydı ekle
            $guncelleme_sql = "INSERT INTO siparis_guncellemeleri 
                             (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleyen_id) 
                             VALUES (?, ?, ?, ?)";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([
                $siparis_id,
                'Tedarikçi Güncellemesi',
                'Tedarikçi tarafından sipariş bilgileri güncellendi.',
                $kullanici_id
            ]);

            // Bildirim ekle (sorumlu için)
            if ($siparis['sorumlu_id']) {
                $bildirim_sql = "INSERT INTO bildirimler 
                               (kullanici_id, mesaj, ilgili_siparis_id) 
                               VALUES (?, ?, ?)";
                $bildirim_stmt = $db->prepare($bildirim_sql);
                $bildirim_stmt->execute([
                    $siparis['sorumlu_id'],
                    $tedarikci['firma_adi'] . ' tarafından ' . $siparis['siparis_no'] . ' no\'lu sipariş güncellendi.',
                    $siparis_id
                ]);
            }

            $mesaj = "Sipariş başarıyla güncellendi.";
            
            // Güncel sipariş bilgilerini al
            $siparis_stmt->execute([$siparis_id, $tedarikci_id]);
            $siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $hata = "Sipariş güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
} else {
    // Sipariş seçilmedi, sipariş listesine yönlendir
    header("Location: siparislerim.php");
    exit;
}

// Okunmamış bildirimleri al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sipariş Güncelle - Tedarikçi Paneli</title>
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
        .alert-alt {
            padding: 0.75rem 1.25rem;
            margin-bottom: 1.5rem;
            border: 1px solid transparent;
            border-radius: 0.5rem;
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
                    <a class="nav-link active" href="siparis_guncelle.php">
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
                <span class="navbar-brand mb-0 h1">
                    <?= !empty($siparis) ? 'Sipariş Güncelle: ' . htmlspecialchars($siparis['siparis_no']) : 'Sipariş Güncelle' ?>
                </span>
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

        <?php if (isset($mesaj)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($mesaj) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($hata)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($hata) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($siparis_id)): ?>
            <!-- Sipariş Seçme Formu -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">Sipariş Seçin</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($acik_siparisler)): ?>
                        <div class="alert alert-info">
                            Güncellenebilir durumda açık siparişiniz bulunmamaktadır.
                        </div>
                        <div class="text-center mt-3">
                            <a href="siparislerim.php" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-left"></i> Siparişlerime Dön
                            </a>
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
                                        <th>Miktar</th>
                                        <th>Teslim Tarihi</th>
                                        <th>İşlem</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($acik_siparisler as $siparis): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($siparis['siparis_no']) ?></td>
                                            <td><?= htmlspecialchars($siparis['parca_no']) ?></td>
                                            <td><?= htmlspecialchars($siparis['tanim']) ?></td>
                                            <td><?= htmlspecialchars($siparis['proje_adi']) ?></td>
                                            <td><?= $siparis['kalan_miktar'] ?> <?= htmlspecialchars($siparis['birim']) ?></td>
                                            <td><?= $siparis['teslim_tarihi'] ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : '-' ?></td>
                                            <td>
                                                <a href="siparis_guncelle.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="bi bi-pencil"></i> Güncelle
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Sipariş Güncelleme Formu -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pencil me-2"></i> Sipariş Bilgilerini Güncelle
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="siparis_guncelle">
                        <input type="hidden" name="siparis_id" value="<?= $siparis_id ?>">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2">Sipariş Bilgileri</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Sipariş No:</div>
                                    <div class="col-md-8"><?= htmlspecialchars($siparis['siparis_no']) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Parça No:</div>
                                    <div class="col-md-8"><?= htmlspecialchars($siparis['parca_no']) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Tanım:</div>
                                    <div class="col-md-8"><?= htmlspecialchars($siparis['tanim']) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Miktar:</div>
                                    <div class="col-md-8"><?= htmlspecialchars($siparis['miktar']) ?> <?= htmlspecialchars($siparis['birim']) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Talep Edilen Teslim Tarihi:</div>
                                    <div class="col-md-8"><?= $siparis['teslim_tarihi'] ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : '-' ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Proje:</div>
                                    <div class="col-md-8"><?= htmlspecialchars($siparis['proje_adi']) ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-4 fw-bold">Sorumlu:</div>
                                    <div class="col-md-8"><?= htmlspecialchars($siparis['sorumlu_adi'] ?? '-') ?></div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2">Güncelleme Bilgileri</h5>
                                
                                <div class="mb-3">
                                    <label for="tedarikci_parca_no" class="form-label">Tedarikçi Parça No</label>
                                    <input type="text" class="form-control" id="tedarikci_parca_no" name="tedarikci_parca_no" value="<?= htmlspecialchars($siparis['tedarikci_parca_no'] ?? '') ?>">
                                    <div class="form-text">Kendi firmanızdaki parça numarasını girebilirsiniz.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tedarikci_tarihi" class="form-label">Tahmini Teslim Tarihi <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="tedarikci_tarihi" name="tedarikci_tarihi" value="<?= !empty($siparis['tedarikci_tarihi']) ? date('Y-m-d', strtotime($siparis['tedarikci_tarihi'])) : '' ?>" required>
                                    <div class="form-text">Sipariş için tahmini teslim tarihinizi belirtiniz.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="durum_id" class="form-label">Durum <span class="text-danger">*</span></label>
                                    <select class="form-select" id="durum_id" name="durum_id" required>
                                        <?php foreach ($durumlar as $durum): ?>
                                            <option value="<?= $durum['id'] ?>" <?= $siparis['durum_id'] == $durum['id'] ? 'selected' : '' ?>><?= htmlspecialchars($durum['durum_adi']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tedarikci_notu" class="form-label">Tedarikçi Notu</label>
                                    <textarea class="form-control" id="tedarikci_notu" name="tedarikci_notu" rows="4"><?= htmlspecialchars($siparis['tedarikci_notu'] ?? '') ?></textarea>
                                    <div class="form-text">Sipariş ile ilgili notlarınızı girebilirsiniz.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Doküman Yükleme -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2">Dokuman Yükleme</h5>
                                <div class="mb-3">
                                    <label for="dokuman" class="form-label">Doküman Ekle</label>
                                    <input class="form-control" type="file" id="dokuman" name="dokuman">
                                    <div class="form-text">İzin verilen dosya türleri: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG (Max: 5MB)</div>
                                </div>
                                <div class="mb-3">
                                    <label for="dokuman_adi" class="form-label">Doküman Adı</label>
                                    <input type="text" class="form-control" id="dokuman_adi" name="dokuman_adi">
                                </div>
                            </div>
                        </div>

                        <!-- Mevcut Dokümanlar -->
                        <?php if (!empty($dokumanlar)): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h5 class="border-bottom pb-2">Mevcut Dokümanlar</h5>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Doküman Adı</th>
                                                    <th>Yüklenme Tarihi</th>
                                                    <th>Yükleyen</th>
                                                    <th>İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dokumanlar as $dokuman): ?>
                                                    <tr>
                                                        <td>
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
                                                            <i class="bi <?= $icon ?> me-2"></i>
                                                            <?= htmlspecialchars($dokuman['dokuman_adi']) ?>
                                                        </td>
                                                        <td><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></td>
                                                        <td><?= htmlspecialchars($dokuman['yukleyen_adi'] ?? 'Bilinmeyen') ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="<?= htmlspecialchars($dokuman['dosya_yolu']) ?>" class="btn btn-primary" download title="İndir">
                                                                    <i class="bi bi-download"></i>
                                                                </a>
                                                                <a href="<?= htmlspecialchars($dokuman['dosya_yolu']) ?>" class="btn btn-info" target="_blank" title="Görüntüle">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Teslimat Ekleme -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h5 class="border-bottom pb-2">Teslimat Bildirimi</h5>
                                <div class="alert alert-alt bg-light border">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="teslimat_ekle" name="teslimat_ekle" value="1">
                                        <label class="form-check-label" for="teslimat_ekle">
                                            <strong>Teslimat bildirimi ekle</strong>
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="teslimat_formu" style="display: none;">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label for="teslimat_tarihi" class="form-label">Teslimat Tarihi</label>
                                            <input type="date" class="form-control" id="teslimat_tarihi" name="teslimat_tarihi" value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="col-md-3">
                                            <label for="teslim_edilen" class="form-label">Teslim Edilen Miktar</label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="teslim_edilen" name="teslim_edilen" min="1" max="<?= $siparis['kalan_miktar'] ?>" value="<?= $siparis['kalan_miktar'] ?>">
                                                <span class="input-group-text"><?= htmlspecialchars($siparis['birim']) ?></span>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="irsaliye_no" class="form-label">İrsaliye No</label>
                                            <input type="text" class="form-control" id="irsaliye_no" name="irsaliye_no">
                                        </div>
                                    </div>
                                    <div class="mb-3 mt-3">
                                        <label for="teslimat_notu" class="form-label">Teslimat Notu</label>
                                        <textarea class="form-control" id="teslimat_notu" name="teslimat_notu" rows="2"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-12 text-end">
                                <a href="<?= !empty($siparis_id) ? "siparis_detay.php?id={$siparis_id}" : "siparislerim.php" ?>" class="btn btn-outline-secondary me-2">
                                    <i class="bi bi-x-circle"></i> İptal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const teslimatEkleCheckbox = document.getElementById('teslimat_ekle');
            const teslimatFormu = document.getElementById('teslimat_formu');
            
            if (teslimatEkleCheckbox && teslimatFormu) {
                teslimatEkleCheckbox.addEventListener('change', function() {
                    teslimatFormu.style.display = this.checked ? 'block' : 'none';
                });
            }
        });
    </script>
</body>
</html> 