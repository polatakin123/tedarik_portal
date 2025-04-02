<?php
// admin/tedarikci_sorumlular.php - Tedarikçi sorumlu atama sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Tedarikçi ID kontrolü
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi ID belirtilmedi."));
    exit;
}

$tedarikci_id = intval($_GET['id']);

// Tedarikçi bilgilerini getir
$tedarikci_sql = "SELECT * FROM tedarikciler WHERE id = ?";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute([$tedarikci_id]);
$tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

if (!$tedarikci) {
    header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi bulunamadı."));
    exit;
}

// Sorumlu ekleme/silme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';
    $sorumlu_id = isset($_POST['sorumlu_id']) ? intval($_POST['sorumlu_id']) : 0;
    
    if ($islem === 'ekle' && $sorumlu_id > 0) {
        // Sorumluluğun zaten var olup olmadığını kontrol et
        $kontrol_sql = "SELECT COUNT(*) FROM sorumluluklar WHERE sorumlu_id = ? AND tedarikci_id = ?";
        $kontrol_stmt = $db->prepare($kontrol_sql);
        $kontrol_stmt->execute([$sorumlu_id, $tedarikci_id]);
        
        if ($kontrol_stmt->fetchColumn() == 0) {
            try {
                $ekle_sql = "INSERT INTO sorumluluklar (sorumlu_id, tedarikci_id, atama_tarihi, olusturan_id) 
                           VALUES (?, ?, NOW(), ?)";
                $ekle_stmt = $db->prepare($ekle_sql);
                $ekle_stmt->execute([$sorumlu_id, $tedarikci_id, $_SESSION['kullanici_id']]);
                
                // Sorumluya bildirim gönder
                $mesaj = $tedarikci['firma_adi'] . " firması sorumluluğunuza atandı.";
                bildirimOlustur($db, $sorumlu_id, $mesaj);
                
                $mesaj = "Sorumlu başarıyla atandı.";
                header("Location: tedarikci_sorumlular.php?id=$tedarikci_id&mesaj=" . urlencode($mesaj));
                exit;
            } catch (PDOException $e) {
                $hata = "Sorumlu atanırken bir hata oluştu: " . $e->getMessage();
                header("Location: tedarikci_sorumlular.php?id=$tedarikci_id&hata=" . urlencode($hata));
                exit;
            }
        } else {
            $hata = "Bu sorumlu zaten bu tedarikçiye atanmış.";
            header("Location: tedarikci_sorumlular.php?id=$tedarikci_id&hata=" . urlencode($hata));
            exit;
        }
    } elseif ($islem === 'sil' && $sorumlu_id > 0) {
        try {
            $sil_sql = "DELETE FROM sorumluluklar WHERE sorumlu_id = ? AND tedarikci_id = ?";
            $sil_stmt = $db->prepare($sil_sql);
            $sil_stmt->execute([$sorumlu_id, $tedarikci_id]);
            
            $mesaj = "Sorumlu başarıyla kaldırıldı.";
            header("Location: tedarikci_sorumlular.php?id=$tedarikci_id&mesaj=" . urlencode($mesaj));
            exit;
        } catch (PDOException $e) {
            $hata = "Sorumlu kaldırılırken bir hata oluştu: " . $e->getMessage();
            header("Location: tedarikci_sorumlular.php?id=$tedarikci_id&hata=" . urlencode($hata));
            exit;
        }
    } else {
        $hata = "Geçersiz işlem veya sorumlu ID.";
        header("Location: tedarikci_sorumlular.php?id=$tedarikci_id&hata=" . urlencode($hata));
        exit;
    }
}

// Bu tedarikçinin sorumluları
$sorumluluklari_sql = "SELECT s.*, k.ad_soyad, k.email, k.telefon, k.kullanici_adi, k.son_giris
                       FROM sorumluluklar s
                       INNER JOIN kullanicilar k ON s.sorumlu_id = k.id
                       WHERE s.tedarikci_id = ?
                       ORDER BY k.ad_soyad";
$sorumluluklari_stmt = $db->prepare($sorumluluklari_sql);
$sorumluluklari_stmt->execute([$tedarikci_id]);
$sorumlular = $sorumluluklari_stmt->fetchAll(PDO::FETCH_ASSOC);

// Atanabilecek diğer sorumlular (henüz atanmamış olanlar)
$atanmamis_sql = "SELECT id, ad_soyad, email, telefon
                  FROM kullanicilar
                  WHERE rol = 'Sorumlu'
                  AND id NOT IN (
                      SELECT sorumlu_id FROM sorumluluklar WHERE tedarikci_id = ?
                  )
                  AND aktif = 1
                  ORDER BY ad_soyad";
$atanmamis_stmt = $db->prepare($atanmamis_sql);
$atanmamis_stmt->execute([$tedarikci_id]);
$atanmamis_sorumlular = $atanmamis_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçinin siparişleri
$siparisler_sql = "SELECT s.*, sd.durum_adi, k.ad_soyad as sorumlu_adi, p.proje_adi
                  FROM siparisler s
                  LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                  LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
                  LEFT JOIN projeler p ON s.proje_id = p.id
                  WHERE s.tedarikci_id = ?
                  ORDER BY s.olusturma_tarihi DESC
                  LIMIT 10";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$tedarikci_id]);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Tedarikçi Sorumluları - Admin Paneli</title>
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
                    <a class="nav-link" href="siparisler.php">
                        <i class="bi bi-list-check"></i> Siparişler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="tedarikciler.php">
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
                <h1 class="h2"><?= guvenli($tedarikci['firma_adi']) ?> - Sorumlular</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="tedarikciler.php" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Tedarikçilere Dön
                    </a>
                    <a href="tedarikci_detay.php?id=<?= $tedarikci_id ?>" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Tedarikçi Detayları
                    </a>
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

            <div class="row">
                <div class="col-md-4">
                    <!-- Tedarikçi Bilgileri Kartı -->
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">Tedarikçi Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>Firma Adı:</strong> <?= guvenli($tedarikci['firma_adi']) ?></p>
                            <p><strong>Firma Kodu:</strong> <?= guvenli($tedarikci['firma_kodu']) ?></p>
                            <p><strong>Yetkili Kişi:</strong> <?= guvenli($tedarikci['yetkili_kisi']) ?></p>
                            <p><strong>E-posta:</strong> <?= guvenli($tedarikci['email']) ?></p>
                            <p><strong>Telefon:</strong> <?= guvenli($tedarikci['telefon']) ?></p>
                            <p><strong>Vergi No:</strong> <?= guvenli($tedarikci['vergi_no']) ?></p>
                            <p class="mb-0"><strong>Durum:</strong> 
                                <?php if ($tedarikci['aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Pasif</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <?php if (count($atanmamis_sorumlular) > 0): ?>
                    <!-- Sorumlu Atama Formu -->
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0">Yeni Sorumlu Ata</h5>
                        </div>
                        <div class="card-body">
                            <form method="post" action="">
                                <input type="hidden" name="islem" value="ekle">
                                <div class="mb-3">
                                    <label for="sorumlu_id" class="form-label">Sorumlu Seçin</label>
                                    <select class="form-select" id="sorumlu_id" name="sorumlu_id" required>
                                        <option value="">-- Sorumlu Seçin --</option>
                                        <?php foreach ($atanmamis_sorumlular as $sorumlu): ?>
                                            <option value="<?= $sorumlu['id'] ?>">
                                                <?= guvenli($sorumlu['ad_soyad']) ?> (<?= guvenli($sorumlu['email']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-person-plus"></i> Sorumlu Ata
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Atanabilecek başka sorumlu bulunamadı.
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-8">
                    <!-- Mevcut Sorumlular Listesi -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Atanmış Sorumlular</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($sorumlular) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Ad Soyad</th>
                                                <th>E-posta</th>
                                                <th>Telefon</th>
                                                <th>Atama Tarihi</th>
                                                <th>Son Giriş</th>
                                                <th>İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sorumlular as $sorumlu): ?>
                                                <tr>
                                                    <td><?= guvenli($sorumlu['ad_soyad']) ?></td>
                                                    <td><?= guvenli($sorumlu['email']) ?></td>
                                                    <td><?= guvenli($sorumlu['telefon']) ?></td>
                                                    <td><?= tarihFormatla($sorumlu['atama_tarihi']) ?></td>
                                                    <td><?= $sorumlu['son_giris'] ? tarihFormatla($sorumlu['son_giris']) : '-' ?></td>
                                                    <td>
                                                        <form method="post" action="" onsubmit="return confirm('<?= $sorumlu['ad_soyad'] ?> adlı sorumluyu bu tedarikçiden kaldırmak istediğinize emin misiniz?');" class="d-inline">
                                                            <input type="hidden" name="islem" value="sil">
                                                            <input type="hidden" name="sorumlu_id" value="<?= $sorumlu['sorumlu_id'] ?>">
                                                            <button type="submit" class="btn btn-danger btn-sm">
                                                                <i class="bi bi-trash"></i> Kaldır
                                                            </button>
                                                        </form>
                                                        <a href="kullanici_detay.php?id=<?= $sorumlu['sorumlu_id'] ?>" class="btn btn-info btn-sm">
                                                            <i class="bi bi-eye"></i> Görüntüle
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle"></i> Bu tedarikçiye henüz sorumlu atanmamış.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tedarikçinin Siparişleri -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Son 10 Sipariş</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($siparisler) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Sipariş No</th>
                                                <th>Parça No</th>
                                                <th>Proje</th>
                                                <th>Miktar</th>
                                                <th>Teslim Tarihi</th>
                                                <th>Sorumlu</th>
                                                <th>Durum</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($siparisler as $siparis): ?>
                                                <tr>
                                                    <td>
                                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="text-decoration-none">
                                                            <?= guvenli($siparis['siparis_no']) ?>
                                                        </a>
                                                    </td>
                                                    <td><?= guvenli($siparis['parca_no']) ?></td>
                                                    <td><?= guvenli($siparis['proje_adi']) ?></td>
                                                    <td><?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?></td>
                                                    <td><?= $siparis['teslim_tarihi'] ? tarihFormatla($siparis['teslim_tarihi']) : '-' ?></td>
                                                    <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
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
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($siparisler) == 10): ?>
                                    <div class="text-center mt-3">
                                        <a href="siparisler.php?tedarikci_id=<?= $tedarikci_id ?>" class="btn btn-outline-primary btn-sm">
                                            Tüm Siparişleri Görüntüle
                                        </a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i> Bu tedarikçiye ait sipariş bulunmamaktadır.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 