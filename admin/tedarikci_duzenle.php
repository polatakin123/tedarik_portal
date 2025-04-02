<?php
// admin/tedarikci_duzenle.php - Tedarikçi düzenleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Tedarikçi ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi ID belirtilmedi"));
    exit;
}

$tedarikci_id = intval($_GET['id']);

// Tedarikçi bilgilerini getir
$tedarikci_sql = "SELECT * FROM tedarikciler WHERE id = ?";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute([$tedarikci_id]);
$tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

if (!$tedarikci) {
    header("Location: tedarikciler.php?hata=" . urlencode("Tedarikçi bulunamadı"));
    exit;
}

$hatalar = [];
$mesajlar = [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $firma_adi = trim($_POST['firma_adi'] ?? '');
    $firma_kodu = trim($_POST['firma_kodu'] ?? '');
    $yetkili_kisi = trim($_POST['yetkili_kisi'] ?? '');
    $adres = trim($_POST['adres'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $vergi_no = trim($_POST['vergi_no'] ?? '');
    $vergi_dairesi = trim($_POST['vergi_dairesi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Validasyon
    if (empty($firma_adi)) {
        $hatalar[] = "Firma adı boş bırakılamaz";
    }

    if (empty($firma_kodu)) {
        $hatalar[] = "Firma kodu boş bırakılamaz";
    }

    // Firma kodu benzersiz mi kontrol et (mevcut firmanın kodu hariç)
    if (!empty($firma_kodu)) {
        $kod_kontrol_sql = "SELECT COUNT(*) FROM tedarikciler WHERE firma_kodu = ? AND id != ?";
        $kod_kontrol_stmt = $db->prepare($kod_kontrol_sql);
        $kod_kontrol_stmt->execute([$firma_kodu, $tedarikci_id]);
        $kod_sayisi = $kod_kontrol_stmt->fetchColumn();

        if ($kod_sayisi > 0) {
            $hatalar[] = "Bu firma kodu zaten kullanılıyor, lütfen başka bir kod seçin";
        }
    }

    // E-posta doğrulama
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = "Geçerli bir e-posta adresi girin";
    }

    // Hata yoksa güncelleme işlemi
    if (empty($hatalar)) {
        try {
            $guncelle_sql = "UPDATE tedarikciler SET 
                firma_adi = ?, 
                firma_kodu = ?, 
                yetkili_kisi = ?, 
                adres = ?, 
                telefon = ?, 
                email = ?, 
                vergi_no = ?, 
                vergi_dairesi = ?, 
                aciklama = ?, 
                aktif = ?, 
                guncelleme_tarihi = NOW() 
                WHERE id = ?";
            
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_sonuc = $guncelle_stmt->execute([
                $firma_adi, 
                $firma_kodu, 
                $yetkili_kisi, 
                $adres, 
                $telefon, 
                $email, 
                $vergi_no, 
                $vergi_dairesi, 
                $aciklama, 
                $aktif, 
                $tedarikci_id
            ]);

            if ($guncelle_sonuc) {
                header("Location: tedarikci_detay.php?id=" . $tedarikci_id . "&mesaj=" . urlencode("Tedarikçi başarıyla güncellendi"));
                exit;
            } else {
                $hatalar[] = "Tedarikçi güncellenirken bir hata oluştu";
            }
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
    <title>Tedarikçi Düzenle: <?= guvenli($tedarikci['firma_adi']) ?> - Admin Paneli</title>
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
            background-color: #f8f9fc !important;
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
                <h1 class="h2">Tedarikçi Düzenle: <?= guvenli($tedarikci['firma_adi']) ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="tedarikci_detay.php?id=<?= $tedarikci_id ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Tedarikçi Detayına Dön
                    </a>
                </div>
            </div>

            <!-- Hata ve mesaj bildirimleri -->
            <?php if (!empty($hatalar)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Hata!</strong>
                    <ul class="mb-0">
                        <?php foreach ($hatalar as $hata): ?>
                            <li><?= guvenli($hata) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($mesajlar)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($mesajlar as $mesaj): ?>
                            <li><?= guvenli($mesaj) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <!-- Tedarikçi Düzenleme Formu -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Tedarikçi Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form action="tedarikci_duzenle.php?id=<?= $tedarikci_id ?>" method="post" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="firma_adi" class="form-label">Firma Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firma_adi" name="firma_adi" value="<?= guvenli($tedarikci['firma_adi']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen firma adını girin
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="firma_kodu" class="form-label">Firma Kodu <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firma_kodu" name="firma_kodu" value="<?= guvenli($tedarikci['firma_kodu']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen firma kodunu girin
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="yetkili_kisi" class="form-label">Yetkili Kişi</label>
                                <input type="text" class="form-control" id="yetkili_kisi" name="yetkili_kisi" value="<?= guvenli($tedarikci['yetkili_kisi']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="text" class="form-control" id="telefon" name="telefon" value="<?= guvenli($tedarikci['telefon']) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= guvenli($tedarikci['email']) ?>">
                                <div class="invalid-feedback">
                                    Lütfen geçerli bir e-posta adresi girin
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="vergi_no" class="form-label">Vergi No</label>
                                <input type="text" class="form-control" id="vergi_no" name="vergi_no" value="<?= guvenli($tedarikci['vergi_no']) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="vergi_dairesi" class="form-label">Vergi Dairesi</label>
                                <input type="text" class="form-control" id="vergi_dairesi" name="vergi_dairesi" value="<?= guvenli($tedarikci['vergi_dairesi']) ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $tedarikci['aktif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktif">
                                        Tedarikçi Aktif
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="adres" class="form-label">Adres</label>
                            <textarea class="form-control" id="adres" name="adres" rows="3"><?= guvenli($tedarikci['adres']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= guvenli($tedarikci['aciklama']) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="tedarikci_detay.php?id=<?= $tedarikci_id ?>" class="btn btn-secondary me-2">İptal</a>
                            <button type="submit" class="btn btn-primary">Tedarikçiyi Güncelle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form doğrulama için Bootstrap validation
        (function () {
            'use strict'

            // Tüm formları seçin
            var forms = document.querySelectorAll('.needs-validation')

            // Doğrulama yapmak için döngü
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }

                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Firma adından otomatik kod oluşturma
        document.getElementById('firma_adi').addEventListener('blur', function() {
            const firmaAdi = this.value.trim();
            const firmaKoduInput = document.getElementById('firma_kodu');
            
            // Firma kodu alanı boşsa veya kullanıcı değiştirmediyse otomatik kod oluştur
            if (!firmaKoduInput.dataset.userModified) {
                // Türkçe karakterleri değiştir
                let kod = firmaAdi.replace(/ç/gi, 'c')
                    .replace(/ğ/gi, 'g')
                    .replace(/ı/gi, 'i')
                    .replace(/ö/gi, 'o')
                    .replace(/ş/gi, 's')
                    .replace(/ü/gi, 'u');
                
                // Alfanumerik olmayan tüm karakterleri kaldır ve boşlukları tire ile değiştir
                kod = kod.replace(/[^a-z0-9\s]/gi, '')
                    .replace(/\s+/g, '-')
                    .toUpperCase();
                
                // En fazla 10 karakter
                if (kod.length > 10) {
                    kod = kod.substring(0, 10);
                }
                
                firmaKoduInput.value = kod;
            }
        });

        // Kullanıcı firma kodunu elle değiştirdiğinde işaretle
        document.getElementById('firma_kodu').addEventListener('input', function() {
            this.dataset.userModified = true;
        });
    </script>
</body>
</html> 