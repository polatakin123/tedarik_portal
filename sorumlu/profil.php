<?php
// sorumlu/profil.php - Sorumlu paneli profil sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Profilim";

$kullanici_id = $_SESSION['kullanici_id'];
$hata = '';
$basari = '';

// Kullanıcı bilgilerini al
$kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
$kullanici_stmt = $db->prepare($kullanici_sql);
$kullanici_stmt->execute([$kullanici_id]);
$kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);

// Profil güncelleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profil_guncelle'])) {
    $ad_soyad = trim($_POST['ad_soyad']);
    $email = trim($_POST['email']);
    $telefon = trim($_POST['telefon']);
    
    // Validasyon
    if (empty($ad_soyad) || empty($email)) {
        $hata = "Ad soyad ve e-posta alanları zorunludur!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hata = "Geçerli bir e-posta adresi giriniz!";
    } else {
        // E-posta adresi başka bir kullanıcı tarafından kullanılıyor mu kontrol et
        $email_kontrol_sql = "SELECT COUNT(*) as sayi FROM kullanicilar WHERE email = ? AND id != ?";
        $email_kontrol_stmt = $db->prepare($email_kontrol_sql);
        $email_kontrol_stmt->execute([$email, $kullanici_id]);
        $email_kontrol = $email_kontrol_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($email_kontrol['sayi'] > 0) {
            $hata = "Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor!";
        } else {
            // Profil bilgilerini güncelle
            $guncelle_sql = "UPDATE kullanicilar SET ad_soyad = ?, email = ?, telefon = ? WHERE id = ?";
            $guncelle_stmt = $db->prepare($guncelle_sql);
            
            if ($guncelle_stmt->execute([$ad_soyad, $email, $telefon, $kullanici_id])) {
                $basari = "Profil bilgileriniz başarıyla güncellendi!";
                
                // Session bilgilerini güncelle
                $_SESSION['ad_soyad'] = $ad_soyad;
                $_SESSION['email'] = $email;
                
                // Güncel kullanıcı bilgilerini al
                $kullanici_stmt->execute([$kullanici_id]);
                $kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $hata = "Profil bilgileri güncellenirken bir hata oluştu!";
            }
        }
    }
}

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sifre_degistir'])) {
    $mevcut_sifre = $_POST['mevcut_sifre'];
    $yeni_sifre = $_POST['yeni_sifre'];
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'];
    
    // Validasyon
    if (empty($mevcut_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
        $hata = "Tüm şifre alanlarını doldurunuz!";
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $hata = "Yeni şifreler eşleşmiyor!";
    } elseif (strlen($yeni_sifre) < 6) {
        $hata = "Şifre en az 6 karakter olmalıdır!";
    } else {
        // Mevcut şifreyi kontrol et
        if (password_verify($mevcut_sifre, $kullanici['sifre'])) {
            // Yeni şifreyi hashle
            $hash_sifre = password_hash($yeni_sifre, PASSWORD_DEFAULT);
            
            // Şifreyi güncelle
            $sifre_guncelle_sql = "UPDATE kullanicilar SET sifre = ? WHERE id = ?";
            $sifre_guncelle_stmt = $db->prepare($sifre_guncelle_sql);
            
            if ($sifre_guncelle_stmt->execute([$hash_sifre, $kullanici_id])) {
                $basari = "Şifreniz başarıyla güncellendi!";
                
                // Güncel kullanıcı bilgilerini al
                $kullanici_stmt->execute([$kullanici_id]);
                $kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $hata = "Şifre güncellenirken bir hata oluştu!";
            }
        } else {
            $hata = "Mevcut şifreniz hatalı!";
        }
    }
}

// Sorumlu olduğu tedarikçi sayısı
$tedarikci_sayisi_sql = "SELECT COUNT(*) as sayi FROM sorumluluklar WHERE sorumlu_id = ?";
$tedarikci_sayisi_stmt = $db->prepare($tedarikci_sayisi_sql);
$tedarikci_sayisi_stmt->execute([$kullanici_id]);
$tedarikci_sayisi = $tedarikci_sayisi_stmt->fetch(PDO::FETCH_ASSOC)['sayi'];

// Sipariş istatistikleri
$siparis_istatistik_sql = "SELECT 
                            COUNT(CASE WHEN durum_id = 1 THEN 1 END) as acik_siparis,
                            COUNT(CASE WHEN durum_id = 2 THEN 1 END) as kapali_siparis,
                            COUNT(CASE WHEN durum_id = 3 THEN 1 END) as bekleyen_siparis,
                            COUNT(*) as toplam_siparis
                            FROM siparisler
                            WHERE sorumlu_id = ? OR tedarikci_id IN (
                                SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                            )";
$siparis_istatistik_stmt = $db->prepare($siparis_istatistik_sql);
$siparis_istatistik_stmt->execute([$kullanici_id, $kullanici_id]);
$siparis_istatistik = $siparis_istatistik_stmt->fetch(PDO::FETCH_ASSOC);

// Özel CSS
$extra_css = "
.profile-header {
    background-color: #f8f9fc;
    border-radius: 0.5rem;
    padding: 2rem;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    margin-bottom: 2rem;
}
.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background-color: #e0e0e0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3rem;
    color: #36b9cc;
    margin: 0 auto 1rem;
}
.card-counter {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    padding: 20px 10px;
    background-color: #fff;
    height: 100px;
    border-radius: 5px;
    transition: .3s linear all;
    margin-bottom: 1.5rem;
}
.card-counter:hover {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    transition: .3s linear all;
}
.card-counter i {
    font-size: 4em;
    opacity: 0.3;
}
.card-counter .count-numbers {
    position: absolute;
    right: 35px;
    top: 20px;
    font-size: 32px;
    display: block;
}
.card-counter .count-name {
    position: absolute;
    right: 35px;
    top: 65px;
    font-style: italic;
    text-transform: capitalize;
    opacity: 0.8;
    display: block;
}
.card-counter.primary {
    background-color: #4e73df;
    color: #fff;
}
.card-counter.success {
    background-color: #1cc88a;
    color: #fff;
}
.card-counter.info {
    background-color: #36b9cc;
    color: #fff;
}
.card-counter.warning {
    background-color: #f6c23e;
    color: #fff;
}
";

// Header'ı dahil et
include 'header.php';
?>
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Profilim</h1>
</div>

<?php if (!empty($hata)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $hata ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<?php if (!empty($basari)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $basari ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<!-- Profil Başlık -->
<div class="profile-header text-center">
    <div class="profile-avatar">
        <i class="bi bi-person"></i>
    </div>
    <h3 class="mt-3"><?= guvenli($kullanici['ad_soyad']) ?></h3>
    <p class="text-muted mb-0"><?= ucfirst(guvenli($kullanici['rol'])) ?></p>
    <p><i class="bi bi-envelope"></i> <?= guvenli($kullanici['email']) ?></p>
    <p class="mb-0"><i class="bi bi-telephone"></i> <?= !empty($kullanici['telefon']) ? guvenli($kullanici['telefon']) : 'Belirtilmemiş' ?></p>
</div>

<!-- İstatistikler -->
<div class="row">
    <div class="col-md-3">
        <div class="card-counter primary">
            <i class="bi bi-list-check"></i>
            <span class="count-numbers"><?= $siparis_istatistik['toplam_siparis'] ?></span>
            <span class="count-name">Toplam Sipariş</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-counter success">
            <i class="bi bi-check-circle"></i>
            <span class="count-numbers"><?= $siparis_istatistik['acik_siparis'] ?></span>
            <span class="count-name">Açık Sipariş</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-counter warning">
            <i class="bi bi-hourglass-split"></i>
            <span class="count-numbers"><?= $siparis_istatistik['bekleyen_siparis'] ?></span>
            <span class="count-name">Bekleyen Sipariş</span>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card-counter info">
            <i class="bi bi-building"></i>
            <span class="count-numbers"><?= $tedarikci_sayisi ?></span>
            <span class="count-name">Tedarikçi</span>
        </div>
    </div>
</div>

<!-- Profil ve Şifre Değiştirme -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Profil Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="ad_soyad" class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" value="<?= guvenli($kullanici['ad_soyad']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= guvenli($kullanici['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefon" class="form-label">Telefon</label>
                        <input type="tel" class="form-control" id="telefon" name="telefon" value="<?= guvenli($kullanici['telefon']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rol</label>
                        <input type="text" class="form-control" value="<?= ucfirst(guvenli($kullanici['rol'])) ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kayıt Tarihi</label>
                        <input type="text" class="form-control" value="<?= date('d.m.Y H:i', strtotime($kullanici['kayit_tarihi'])) ?>" readonly>
                    </div>
                    <div class="text-end">
                        <button type="submit" name="profil_guncelle" class="btn btn-primary">Profili Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Şifre Değiştir</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <div class="mb-3">
                        <label for="mevcut_sifre" class="form-label">Mevcut Şifre</label>
                        <input type="password" class="form-control" id="mevcut_sifre" name="mevcut_sifre" required>
                    </div>
                    <div class="mb-3">
                        <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" id="yeni_sifre" name="yeni_sifre" required minlength="6">
                        <div class="form-text">Şifre en az 6 karakter olmalıdır.</div>
                    </div>
                    <div class="mb-3">
                        <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" class="form-control" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required minlength="6">
                    </div>
                    <div class="text-end">
                        <button type="submit" name="sifre_degistir" class="btn btn-warning">Şifreyi Değiştir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 