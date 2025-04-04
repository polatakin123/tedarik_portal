<?php
// tedarikci/dokumanlar.php - Tedarikçi dokümanları sayfası
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

// Filtre değerlerini al
$filtre_proje = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;
$filtre_tip = isset($_GET['dokuman_tipi']) ? $_GET['dokuman_tipi'] : '';
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// Tedarikçiye ait siparişlerin dokümanlarını getir
$dokuman_sql = "SELECT sd.*, s.siparis_no, s.parca_no, p.proje_adi, 
                u.ad_soyad AS yukleyen_adi
                FROM siparis_dokumanlari sd 
                INNER JOIN siparisler s ON sd.siparis_id = s.id
                LEFT JOIN projeler p ON s.proje_id = p.id
                LEFT JOIN kullanicilar u ON sd.yukleyen_id = u.id
                WHERE s.tedarikci_id = ?";
$params = [$tedarikci_id];

// Filtreleri ekle
if ($filtre_proje > 0) {
    $dokuman_sql .= " AND s.proje_id = ?";
    $params[] = $filtre_proje;
}

if (!empty($filtre_tip)) {
    $dokuman_sql .= " AND sd.dosya_turu = ?";
    $params[] = $filtre_tip;
}

if (!empty($arama)) {
    $dokuman_sql .= " AND (sd.dokuman_adi LIKE ? OR s.siparis_no LIKE ? OR s.parca_no LIKE ?)";
    $arama_param = "%" . $arama . "%";
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$dokuman_sql .= " ORDER BY sd.yukleme_tarihi DESC";

$dokuman_stmt = $db->prepare($dokuman_sql);
$dokuman_stmt->execute($params);
$dokumanlar = $dokuman_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri al (filtreleme için)
$proje_sql = "SELECT DISTINCT p.* FROM projeler p 
              INNER JOIN siparisler s ON p.id = s.proje_id 
              WHERE s.tedarikci_id = ? 
              ORDER BY p.proje_adi";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$tedarikci_id]);
$projeler = $proje_stmt->fetchAll(PDO::FETCH_ASSOC);

// Doküman tiplerini al (filtreleme için)
$tip_sql = "SELECT DISTINCT sd.dosya_turu FROM siparis_dokumanlari sd
            INNER JOIN siparisler s ON sd.siparis_id = s.id
            WHERE s.tedarikci_id = ? AND sd.dosya_turu IS NOT NULL AND sd.dosya_turu != ''
            ORDER BY sd.dosya_turu";
$tip_stmt = $db->prepare($tip_sql);
$tip_stmt->execute([$tedarikci_id]);
$dokuman_tipleri = $tip_stmt->fetchAll(PDO::FETCH_COLUMN);

// Okunmamış bildirimleri al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokümanlar - Tedarikçi Paneli</title>
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
                    <a class="nav-link active" href="dokumanlar.php">
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
                <span class="navbar-brand mb-0 h1">Dokümanlar</span>
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

        <!-- Filtreleme ve arama formu -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Filtreleme</h5>
            </div>
            <div class="card-body">
                <form method="get" action="" class="row g-3">
                    <div class="col-md-3">
                        <label for="proje_id" class="form-label">Proje</label>
                        <select name="proje_id" id="proje_id" class="form-select">
                            <option value="0">Tüm Projeler</option>
                            <?php foreach ($projeler as $proje): ?>
                                <option value="<?= $proje['id'] ?>" <?= $filtre_proje == $proje['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proje['proje_adi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="dokuman_tipi" class="form-label">Doküman Tipi</label>
                        <select name="dokuman_tipi" id="dokuman_tipi" class="form-select">
                            <option value="">Tüm Tipler</option>
                            <?php foreach ($dokuman_tipleri as $tip): ?>
                                <option value="<?= htmlspecialchars($tip) ?>" <?= $filtre_tip == $tip ? 'selected' : '' ?>>
                                    <?= strtoupper(htmlspecialchars($tip)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="arama" class="form-label">Arama</label>
                        <input type="text" name="arama" id="arama" class="form-control" placeholder="Doküman adı, Sipariş no veya Parça no" value="<?= htmlspecialchars($arama) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Dokümanlar Tablosu -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Doküman Listesi</h5>
            </div>
            <div class="card-body">
                <?php if (empty($dokumanlar)): ?>
                    <div class="alert alert-info">
                        Herhangi bir doküman bulunamadı.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Doküman Adı</th>
                                    <th>Sipariş No</th>
                                    <th>Parça No</th>
                                    <th>Proje</th>
                                    <th>Tip</th>
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
                                            <i class="bi <?= $icon ?> file-type-icon"></i>
                                            <?= htmlspecialchars($dokuman['dokuman_adi']) ?>
                                        </td>
                                        <td>
                                            <a href="siparis_detay.php?id=<?= $dokuman['siparis_id'] ?>">
                                                <?= htmlspecialchars($dokuman['siparis_no']) ?>
                                            </a>
                                        </td>
                                        <td><?= htmlspecialchars($dokuman['parca_no']) ?></td>
                                        <td><?= htmlspecialchars($dokuman['proje_adi']) ?></td>
                                        <td>
                                            <?php if (!empty($dokuman['dosya_turu'])): ?>
                                                <span class="badge bg-secondary"><?= strtoupper(htmlspecialchars($dokuman['dosya_turu'])) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">Belirtilmemiş</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></td>
                                        <td><?= htmlspecialchars($dokuman['yukleyen_adi']) ?></td>
                                        <td>
                                            <a href="<?= htmlspecialchars($dokuman['dosya_yolu']) ?>" class="btn btn-sm btn-primary" download title="İndir">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="<?= htmlspecialchars($dokuman['dosya_yolu']) ?>" class="btn btn-sm btn-info" target="_blank" title="Görüntüle">
                                                <i class="bi bi-eye"></i>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 