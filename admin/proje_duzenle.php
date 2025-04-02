<?php
// admin/proje_duzenle.php - Proje düzenleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Proje ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: projeler.php?hata=" . urlencode("Proje ID belirtilmedi"));
    exit;
}

$proje_id = intval($_GET['id']);

// Proje bilgilerini getir
$proje_sql = "SELECT * FROM projeler WHERE id = ?";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$proje_id]);
$proje = $proje_stmt->fetch(PDO::FETCH_ASSOC);

if (!$proje) {
    header("Location: projeler.php?hata=" . urlencode("Proje bulunamadı"));
    exit;
}

$hatalar = [];
$mesajlar = [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $proje_adi = trim($_POST['proje_adi'] ?? '');
    $proje_kodu = trim($_POST['proje_kodu'] ?? '');
    $sorumlu_yonetici = trim($_POST['sorumlu_yonetici'] ?? '');
    $baslangic_tarihi = trim($_POST['baslangic_tarihi'] ?? '');
    $bitis_tarihi = trim($_POST['bitis_tarihi'] ?? '');
    $aciklama = trim($_POST['aciklama'] ?? '');
    $aktif = isset($_POST['aktif']) ? 1 : 0;

    // Validasyon
    if (empty($proje_adi)) {
        $hatalar[] = "Proje adı boş bırakılamaz";
    }

    if (empty($proje_kodu)) {
        $hatalar[] = "Proje kodu boş bırakılamaz";
    }

    // Proje kodu benzersiz mi kontrol et (mevcut projenin kodu hariç)
    if (!empty($proje_kodu)) {
        $kod_kontrol_sql = "SELECT COUNT(*) FROM projeler WHERE proje_kodu = ? AND id != ?";
        $kod_kontrol_stmt = $db->prepare($kod_kontrol_sql);
        $kod_kontrol_stmt->execute([$proje_kodu, $proje_id]);
        $kod_sayisi = $kod_kontrol_stmt->fetchColumn();

        if ($kod_sayisi > 0) {
            $hatalar[] = "Bu proje kodu zaten kullanılıyor, lütfen başka bir kod seçin";
        }
    }

    // Başlangıç ve bitiş tarihi kontrolü
    if (!empty($baslangic_tarihi) && !empty($bitis_tarihi)) {
        $baslangic = new DateTime($baslangic_tarihi);
        $bitis = new DateTime($bitis_tarihi);
        
        if ($bitis < $baslangic) {
            $hatalar[] = "Bitiş tarihi başlangıç tarihinden önce olamaz";
        }
    }

    // Hata yoksa güncelleme işlemi
    if (empty($hatalar)) {
        try {
            $guncelle_sql = "UPDATE projeler SET 
                proje_adi = ?, 
                proje_kodu = ?, 
                sorumlu_yonetici = ?, 
                baslangic_tarihi = ?, 
                bitis_tarihi = ?, 
                aciklama = ?, 
                aktif = ?, 
                guncelleme_tarihi = NOW() 
                WHERE id = ?";
            
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_sonuc = $guncelle_stmt->execute([
                $proje_adi, 
                $proje_kodu, 
                $sorumlu_yonetici, 
                $baslangic_tarihi ?: null, 
                $bitis_tarihi ?: null, 
                $aciklama, 
                $aktif, 
                $proje_id
            ]);

            if ($guncelle_sonuc) {
                header("Location: proje_detay.php?id=" . $proje_id . "&mesaj=" . urlencode("Proje başarıyla güncellendi"));
                exit;
            } else {
                $hatalar[] = "Proje güncellenirken bir hata oluştu";
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
    <title>Proje Düzenle: <?= guvenli($proje['proje_adi']) ?> - Admin Paneli</title>
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
                <h1 class="h2">Proje Düzenle: <?= guvenli($proje['proje_adi']) ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="proje_detay.php?id=<?= $proje_id ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Proje Detayına Dön
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

            <!-- Proje Düzenleme Formu -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Proje Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form action="proje_duzenle.php?id=<?= $proje_id ?>" method="post" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="proje_adi" class="form-label">Proje Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_adi" name="proje_adi" value="<?= guvenli($proje['proje_adi']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen proje adını girin
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="proje_kodu" class="form-label">Proje Kodu <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_kodu" name="proje_kodu" value="<?= guvenli($proje['proje_kodu']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen proje kodunu girin
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sorumlu_yonetici" class="form-label">Sorumlu Yönetici</label>
                                <input type="text" class="form-control" id="sorumlu_yonetici" name="sorumlu_yonetici" value="<?= guvenli($proje['sorumlu_yonetici']) ?>">
                            </div>
                            <div class="col-md-6">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $proje['aktif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktif">
                                        Proje Aktif
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?= !empty($proje['baslangic_tarihi']) ? date('Y-m-d', strtotime($proje['baslangic_tarihi'])) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?= !empty($proje['bitis_tarihi']) ? date('Y-m-d', strtotime($proje['bitis_tarihi'])) : '' ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= guvenli($proje['aciklama']) ?></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="proje_detay.php?id=<?= $proje_id ?>" class="btn btn-secondary me-2">İptal</a>
                            <button type="submit" class="btn btn-primary">Projeyi Güncelle</button>
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

        // Proje adından otomatik kod oluşturma
        document.getElementById('proje_adi').addEventListener('input', function() {
            const projeAdi = this.value.trim();
            const projeKoduInput = document.getElementById('proje_kodu');
            
            // Proje kodu alanı boşsa veya kullanıcı değiştirmediyse otomatik kod oluştur
            if (!projeKoduInput.dataset.userModified) {
                // Türkçe karakterleri değiştir
                let kod = projeAdi.replace(/ç/gi, 'c')
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
                
                projeKoduInput.value = kod;
            }
        });

        // Kullanıcı proje kodunu elle değiştirdiğinde işaretle
        document.getElementById('proje_kodu').addEventListener('input', function() {
            this.dataset.userModified = true;
        });

        // Tarih validasyonu
        document.getElementById('bitis_tarihi').addEventListener('change', function() {
            const baslangicTarihi = document.getElementById('baslangic_tarihi').value;
            const bitisTarihi = this.value;
            
            if (baslangicTarihi && bitisTarihi && new Date(bitisTarihi) < new Date(baslangicTarihi)) {
                this.setCustomValidity('Bitiş tarihi başlangıç tarihinden önce olamaz');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('baslangic_tarihi').addEventListener('change', function() {
            const bitisTarihiInput = document.getElementById('bitis_tarihi');
            const bitisTarihi = bitisTarihiInput.value;
            const baslangicTarihi = this.value;
            
            if (baslangicTarihi && bitisTarihi && new Date(bitisTarihi) < new Date(baslangicTarihi)) {
                bitisTarihiInput.setCustomValidity('Bitiş tarihi başlangıç tarihinden önce olamaz');
            } else {
                bitisTarihiInput.setCustomValidity('');
            }
        });
    </script>
</body>
</html> 