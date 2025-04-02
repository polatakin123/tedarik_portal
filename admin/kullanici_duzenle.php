<?php
// admin/kullanici_duzenle.php - Kullanıcı düzenleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Kullanıcı ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kullanicilar.php?hata=" . urlencode("Kullanıcı ID belirtilmedi"));
    exit;
}

$kullanici_id = intval($_GET['id']);

// Kendi hesabını düzenlemeye çalışıyorsa profil sayfasına yönlendir
if ($kullanici_id == $_SESSION['kullanici_id']) {
    header("Location: profil.php");
    exit;
}

// Kullanıcı bilgilerini getir
$kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
$kullanici_stmt = $db->prepare($kullanici_sql);
$kullanici_stmt->execute([$kullanici_id]);
$kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);

if (!$kullanici) {
    header("Location: kullanicilar.php?hata=" . urlencode("Kullanıcı bulunamadı"));
    exit;
}

// Tedarikçi kullanıcısıysa firma bilgisini al
$tedarikci_id = null;
if ($kullanici['rol'] == 'Tedarikci') {
    $firma_sql = "SELECT tedarikci_id FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?";
    $firma_stmt = $db->prepare($firma_sql);
    $firma_stmt->execute([$kullanici_id]);
    $tedarikci_id = $firma_stmt->fetchColumn();
}

// Aktif tedarikçileri getir
$tedarikciler_sql = "SELECT id, firma_adi, firma_kodu FROM tedarikciler WHERE aktif = 1 ORDER BY firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

$hatalar = [];
$mesajlar = [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $ad_soyad = trim($_POST['ad_soyad'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $kullanici_adi = trim($_POST['kullanici_adi'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $rol = trim($_POST['rol'] ?? '');
    $tedarikci_firma_id = ($rol == 'Tedarikci') ? (int)($_POST['tedarikci_firma_id'] ?? 0) : null;
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    $sifre = trim($_POST['sifre'] ?? '');

    // Validasyon
    if (empty($ad_soyad)) {
        $hatalar[] = "Ad soyad boş bırakılamaz";
    }

    if (empty($email)) {
        $hatalar[] = "E-posta boş bırakılamaz";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = "Geçerli bir e-posta adresi girin";
    } else {
        // E-posta başka kullanıcı tarafından kullanılıyor mu kontrol et
        $email_kontrol_sql = "SELECT COUNT(*) FROM kullanicilar WHERE email = ? AND id != ?";
        $email_kontrol_stmt = $db->prepare($email_kontrol_sql);
        $email_kontrol_stmt->execute([$email, $kullanici_id]);
        $email_sayisi = $email_kontrol_stmt->fetchColumn();

        if ($email_sayisi > 0) {
            $hatalar[] = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor";
        }
    }

    if (empty($kullanici_adi)) {
        $hatalar[] = "Kullanıcı adı boş bırakılamaz";
    } else {
        // Kullanıcı adı başka kullanıcı tarafından kullanılıyor mu kontrol et
        $kullanici_adi_kontrol_sql = "SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ? AND id != ?";
        $kullanici_adi_kontrol_stmt = $db->prepare($kullanici_adi_kontrol_sql);
        $kullanici_adi_kontrol_stmt->execute([$kullanici_adi, $kullanici_id]);
        $kullanici_adi_sayisi = $kullanici_adi_kontrol_stmt->fetchColumn();

        if ($kullanici_adi_sayisi > 0) {
            $hatalar[] = "Bu kullanıcı adı başka bir kullanıcı tarafından kullanılıyor";
        }
    }

    if (empty($rol)) {
        $hatalar[] = "Kullanıcı rolü seçilmelidir";
    }

    if ($rol == 'Tedarikci' && empty($tedarikci_firma_id)) {
        $hatalar[] = "Tedarikçi kullanıcısı için firma seçilmelidir";
    }

    // Son aktif admin kullanıcısını deaktif etmeye çalışıyorsa engelle
    if ($kullanici['rol'] == 'Admin' && $kullanici['aktif'] == 1 && $aktif == 0) {
        $aktif_admin_sayisi_sql = "SELECT COUNT(*) FROM kullanicilar WHERE rol = 'Admin' AND aktif = 1 AND id != ?";
        $aktif_admin_sayisi_stmt = $db->prepare($aktif_admin_sayisi_sql);
        $aktif_admin_sayisi_stmt->execute([$kullanici_id]);
        $aktif_admin_sayisi = $aktif_admin_sayisi_stmt->fetchColumn();

        if ($aktif_admin_sayisi == 0) {
            $hatalar[] = "Son aktif admin kullanıcısını deaktif edemezsiniz";
        }
    }

    // Hata yoksa güncelleme işlemi
    if (empty($hatalar)) {
        try {
            $db->beginTransaction();

            // Şifre değiştirilecek mi?
            $sifre_guncelleme = !empty($sifre);
            $sifre_hash = $sifre_guncelleme ? password_hash($sifre, PASSWORD_DEFAULT) : $kullanici['sifre'];

            $guncelle_sql = "UPDATE kullanicilar SET 
                ad_soyad = ?, 
                email = ?, 
                kullanici_adi = ?, 
                telefon = ?, 
                rol = ?, 
                aktif = ?";
            
            if ($sifre_guncelleme) {
                $guncelle_sql .= ", sifre = ?";
            }
            
            $guncelle_sql .= ", guncelleme_tarihi = NOW() WHERE id = ?";
            
            $guncelle_params = [
                $ad_soyad, 
                $email, 
                $kullanici_adi, 
                $telefon, 
                $rol, 
                $aktif
            ];

            if ($sifre_guncelleme) {
                $guncelle_params[] = $sifre_hash;
            }

            $guncelle_params[] = $kullanici_id;
            
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_sonuc = $guncelle_stmt->execute($guncelle_params);

            // Tedarikçi ilişkisini güncelle
            if ($rol == 'Tedarikci') {
                // Önce eski ilişkiyi sil
                $iliskileri_sil_sql = "DELETE FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?";
                $iliskileri_sil_stmt = $db->prepare($iliskileri_sil_sql);
                $iliskileri_sil_stmt->execute([$kullanici_id]);
                
                // Yeni ilişkiyi ekle
                $iliski_ekle_sql = "INSERT INTO kullanici_tedarikci_iliskileri (kullanici_id, tedarikci_id) VALUES (?, ?)";
                $iliski_ekle_stmt = $db->prepare($iliski_ekle_sql);
                $iliski_ekle_stmt->execute([$kullanici_id, $tedarikci_firma_id]);
            } else if ($kullanici['rol'] == 'Tedarikci' && $rol != 'Tedarikci') {
                // Kullanıcı artık tedarikçi değilse ilişkiyi sil
                $iliskileri_sil_sql = "DELETE FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?";
                $iliskileri_sil_stmt = $db->prepare($iliskileri_sil_sql);
                $iliskileri_sil_stmt->execute([$kullanici_id]);
            }

            // Eğer rol değiştiyse ve yeni rol "Sorumlu" değilse, sorumlulukları temizle
            if ($kullanici['rol'] == 'Sorumlu' && $rol != 'Sorumlu') {
                $sorumluluk_sil_sql = "DELETE FROM sorumluluklar WHERE sorumlu_id = ?";
                $sorumluluk_sil_stmt = $db->prepare($sorumluluk_sil_sql);
                $sorumluluk_sil_stmt->execute([$kullanici_id]);
            }

            $db->commit();

            header("Location: kullanici_detay.php?id=" . $kullanici_id . "&mesaj=" . urlencode("Kullanıcı başarıyla güncellendi"));
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Okunmamış bildirim sayısını al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $_SESSION['kullanici_id']);

// Son 5 bildirimi al
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$_SESSION['kullanici_id']]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Rol değerini Türkçe'ye çevir
function rolTurkce($rol) {
    switch ($rol) {
        case 'Admin': return 'Yönetici';
        case 'Tedarikci': return 'Tedarikçi';
        case 'Sorumlu': return 'Sorumlu';
        default: return $rol;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Düzenle: <?= guvenli($kullanici['ad_soyad']) ?> - Admin Paneli</title>
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
                    <a class="nav-link" href="projeler.php">
                        <i class="bi bi-diagram-3"></i> Projeler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="kullanicilar.php">
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
                <h1 class="h2">Kullanıcı Düzenle: <?= guvenli($kullanici['ad_soyad']) ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="kullanici_detay.php?id=<?= $kullanici_id ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Kullanıcı Detayına Dön
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

            <!-- Kullanıcı Düzenleme Formu -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Kullanıcı Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form action="kullanici_duzenle.php?id=<?= $kullanici_id ?>" method="post" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ad_soyad" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" value="<?= guvenli($kullanici['ad_soyad']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen ad soyad girin
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= guvenli($kullanici['email']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen geçerli bir e-posta adresi girin
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="kullanici_adi" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" value="<?= guvenli($kullanici['kullanici_adi']) ?>" required>
                                <div class="invalid-feedback">
                                    Lütfen kullanıcı adı girin
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="text" class="form-control" id="telefon" name="telefon" value="<?= guvenli($kullanici['telefon']) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="">Rol Seçin</option>
                                    <option value="Admin" <?= $kullanici['rol'] == 'Admin' ? 'selected' : '' ?>>Yönetici</option>
                                    <option value="Sorumlu" <?= $kullanici['rol'] == 'Sorumlu' ? 'selected' : '' ?>>Sorumlu</option>
                                    <option value="Tedarikci" <?= $kullanici['rol'] == 'Tedarikci' ? 'selected' : '' ?>>Tedarikçi</option>
                                </select>
                                <div class="invalid-feedback">
                                    Lütfen rol seçin
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div id="tedarikci_firma_container" class="<?= $kullanici['rol'] != 'Tedarikci' ? 'd-none' : '' ?>">
                                    <label for="tedarikci_firma_id" class="form-label">Tedarikçi Firma <span class="text-danger">*</span></label>
                                    <select class="form-select" id="tedarikci_firma_id" name="tedarikci_firma_id" <?= $kullanici['rol'] == 'Tedarikci' ? 'required' : '' ?>>
                                        <option value="">Firma Seçin</option>
                                        <?php foreach ($tedarikciler as $tedarikci): ?>
                                            <option value="<?= $tedarikci['id'] ?>" <?= $tedarikci_id == $tedarikci['id'] ? 'selected' : '' ?>>
                                                <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Lütfen tedarikçi firma seçin
                                    </div>
                                </div>

                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= $kullanici['aktif'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktif">
                                        Kullanıcı Aktif
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sifre" class="form-label">Şifre (Değiştirmek için doldurun)</label>
                                <input type="password" class="form-control" id="sifre" name="sifre">
                                <div class="form-text">Şifreyi değiştirmek istemiyorsanız bu alanı boş bırakın.</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <a href="kullanici_detay.php?id=<?= $kullanici_id ?>" class="btn btn-secondary me-2">İptal</a>
                            <button type="submit" class="btn btn-primary">Kullanıcıyı Güncelle</button>
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

        // Rol değiştiğinde tedarikçi firma seçeneğini göster/gizle
        document.getElementById('rol').addEventListener('change', function() {
            const tedarikciContainer = document.getElementById('tedarikci_firma_container');
            const tedarikciSelect = document.getElementById('tedarikci_firma_id');
            
            if (this.value === 'Tedarikci') {
                tedarikciContainer.classList.remove('d-none');
                tedarikciSelect.setAttribute('required', '');
            } else {
                tedarikciContainer.classList.add('d-none');
                tedarikciSelect.removeAttribute('required');
            }
        });
    </script>
</body>
</html> 