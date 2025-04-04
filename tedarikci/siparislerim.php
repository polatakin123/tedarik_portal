<?php
// tedarikci/siparislerim.php - Tedarikçi siparişleri sayfası
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

// Filtreleme parametreleri
$durum_id = isset($_GET['durum_id']) ? intval($_GET['durum_id']) : 0;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// Sipariş listesini sorgula
$sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi
        FROM siparisler s
        LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
        LEFT JOIN projeler p ON s.proje_id = p.id
        LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
        WHERE s.tedarikci_id = ?";

$params = [$tedarikci_id];

// Filtreleme koşullarını ekle
if ($durum_id > 0) {
    $sql .= " AND s.durum_id = ?";
    $params[] = $durum_id;
}

if ($proje_id > 0) {
    $sql .= " AND s.proje_id = ?";
    $params[] = $proje_id;
}

if (!empty($arama)) {
    $sql .= " AND (s.siparis_no LIKE ? OR s.parca_no LIKE ? OR s.tanim LIKE ?)";
    $arama_param = "%" . $arama . "%";
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$sql .= " ORDER BY s.olusturma_tarihi DESC";

$siparisler_stmt = $db->prepare($sql);
$siparisler_stmt->execute($params);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını al (filtreleme için)
$durum_sql = "SELECT * FROM siparis_durumlari ORDER BY id";
$durum_stmt = $db->prepare($durum_sql);
$durum_stmt->execute();
$durumlar = $durum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri al (filtreleme için)
$proje_sql = "SELECT DISTINCT p.* FROM projeler p 
              INNER JOIN siparisler s ON p.id = s.proje_id 
              WHERE s.tedarikci_id = ? 
              ORDER BY p.proje_adi";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$tedarikci_id]);
$projeler = $proje_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirimleri al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siparişlerim - Tedarikçi Paneli</title>
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
        .table th, .table td {
            padding: 0.75rem;
            vertical-align: middle;
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
                    <a class="nav-link active" href="siparislerim.php">
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
                <span class="navbar-brand mb-0 h1">Siparişlerim</span>
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
                        <label for="durum_id" class="form-label">Durum</label>
                        <select name="durum_id" id="durum_id" class="form-select">
                            <option value="0">Tüm Durumlar</option>
                            <?php foreach ($durumlar as $durum): ?>
                                <option value="<?= $durum['id'] ?>" <?= $durum_id == $durum['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($durum['durum_adi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="proje_id" class="form-label">Proje</label>
                        <select name="proje_id" id="proje_id" class="form-select">
                            <option value="0">Tüm Projeler</option>
                            <?php foreach ($projeler as $proje): ?>
                                <option value="<?= $proje['id'] ?>" <?= $proje_id == $proje['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($proje['proje_adi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="arama" class="form-label">Arama</label>
                        <input type="text" name="arama" id="arama" class="form-control" placeholder="Sipariş No, Parça No veya Tanım" value="<?= htmlspecialchars($arama) ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Siparişler tablosu -->
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="card-title mb-0">Sipariş Listesi</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Sipariş No</th>
                                <th>Parça No</th>
                                <th>Tanım</th>
                                <th>Proje</th>
                                <th>Açılış Tarihi</th>
                                <th>Teslim Tarihi</th>
                                <th>Miktar</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($siparisler)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">Herhangi bir sipariş bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($siparisler as $siparis): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($siparis['siparis_no']) ?></td>
                                        <td><?= htmlspecialchars($siparis['parca_no']) ?></td>
                                        <td><?= htmlspecialchars($siparis['tanim']) ?></td>
                                        <td><?= htmlspecialchars($siparis['proje_adi']) ?></td>
                                        <td><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></td>
                                        <td><?= $siparis['teslim_tarihi'] ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : '-' ?></td>
                                        <td><?= $siparis['miktar'] ?> <?= htmlspecialchars($siparis['birim']) ?></td>
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
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 