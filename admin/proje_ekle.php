<?php
// admin/proje_ekle.php - Proje ekleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proje_adi = isset($_POST['proje_adi']) ? trim($_POST['proje_adi']) : '';
    $proje_kodu = isset($_POST['proje_kodu']) ? trim($_POST['proje_kodu']) : '';
    $proje_yoneticisi = isset($_POST['proje_yoneticisi']) ? trim($_POST['proje_yoneticisi']) : '';
    $aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : '';
    $baslangic_tarihi = isset($_POST['baslangic_tarihi']) ? trim($_POST['baslangic_tarihi']) : '';
    $bitis_tarihi = isset($_POST['bitis_tarihi']) ? trim($_POST['bitis_tarihi']) : null;
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Eklenen bilgilerin kontrolü
    $hatalar = [];
    
    if (empty($proje_adi)) {
        $hatalar[] = "Proje adı boş bırakılamaz.";
    }
    
    if (empty($proje_kodu)) {
        $hatalar[] = "Proje kodu boş bırakılamaz.";
    } else {
        // Proje kodunun benzersiz olup olmadığını kontrol et
        $kontrol = $db->prepare("SELECT COUNT(*) FROM projeler WHERE proje_kodu = ?");
        $kontrol->execute([$proje_kodu]);
        if ($kontrol->fetchColumn() > 0) {
            $hatalar[] = "Bu proje kodu zaten kullanılmaktadır. Lütfen başka bir kod seçin.";
        }
    }
    
    if (empty($baslangic_tarihi)) {
        $hatalar[] = "Başlangıç tarihi belirtilmelidir.";
    }
    
    // Hata yoksa projeyi ekle
    if (empty($hatalar)) {
        try {
            $sql = "INSERT INTO projeler (proje_adi, proje_kodu, proje_yoneticisi, aciklama, baslangic_tarihi, bitis_tarihi, aktif, olusturma_tarihi) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([$proje_adi, $proje_kodu, $proje_yoneticisi, $aciklama, $baslangic_tarihi, $bitis_tarihi, $aktif]);
            
            $proje_id = $db->lastInsertId();
            
            // Başarılı mesajı ile yönlendir
            $mesaj = "Proje başarıyla eklendi.";
            header("Location: proje_detay.php?id=" . $proje_id . "&mesaj=" . urlencode($mesaj));
            exit;
        } catch (PDOException $e) {
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

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
    <title>Yeni Proje Ekle - Admin Paneli</title>
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
            margin-bottom: 1.5rem;
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
                    <a class="nav-link" href="tedarikciler.php">
                        <i class="bi bi-building"></i> Tedarikçiler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="sorumlular.php">
                        <i class="bi bi-people"></i> Sorumlular
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="projeler.php">
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
                <h1 class="h2">Yeni Proje Ekle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="projeler.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Projelere Dön
                    </a>
                </div>
            </div>

            <?php if (!empty($hatalar)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($hatalar as $hata): ?>
                            <li><?= guvenli($hata) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Proje Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form action="" method="post">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="proje_adi" class="form-label">Proje Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_adi" name="proje_adi" 
                                       value="<?= isset($proje_adi) ? guvenli($proje_adi) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="proje_kodu" class="form-label">Proje Kodu <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_kodu" name="proje_kodu" 
                                       value="<?= isset($proje_kodu) ? guvenli($proje_kodu) : '' ?>" required>
                                <div class="form-text">Benzersiz bir proje kodu giriniz (örn: PRJ-2023-001)</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="proje_yoneticisi" class="form-label">Proje Yöneticisi</label>
                                <input type="text" class="form-control" id="proje_yoneticisi" name="proje_yoneticisi" 
                                       value="<?= isset($proje_yoneticisi) ? guvenli($proje_yoneticisi) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="aktif" class="form-label d-block">Durum</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="aktif" name="aktif" value="1" 
                                           <?= (!isset($aktif) || $aktif == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktif">Aktif</label>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" 
                                       value="<?= isset($baslangic_tarihi) ? guvenli($baslangic_tarihi) : '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" 
                                       value="<?= isset($bitis_tarihi) ? guvenli($bitis_tarihi) : '' ?>">
                                <div class="form-text">Eğer bitiş tarihi henüz belirlenmemişse, boş bırakabilirsiniz.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="4"><?= isset($aciklama) ? guvenli($aciklama) : '' ?></textarea>
                        </div>
                        <div class="row">
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Projeyi Kaydet
                                </button>
                                <a href="projeler.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> İptal
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Başlangıç ve bitiş tarihi validasyonu
        document.getElementById('bitis_tarihi').addEventListener('change', function() {
            var baslangicTarihi = document.getElementById('baslangic_tarihi').value;
            var bitisTarihi = this.value;
            
            if (baslangicTarihi && bitisTarihi && bitisTarihi < baslangicTarihi) {
                alert('Bitiş tarihi, başlangıç tarihinden önce olamaz!');
                this.value = '';
            }
        });
        
        document.getElementById('baslangic_tarihi').addEventListener('change', function() {
            var baslangicTarihi = this.value;
            var bitisTarihi = document.getElementById('bitis_tarihi').value;
            
            if (baslangicTarihi && bitisTarihi && bitisTarihi < baslangicTarihi) {
                alert('Başlangıç tarihi, bitiş tarihinden sonra olamaz!');
                document.getElementById('bitis_tarihi').value = '';
            }
        });
    </script>
</body>
</html> 