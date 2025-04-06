<?php
// tedarikci/profil.php - Tedarikçi profil sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Profil";

// Tedarikçi bilgilerini al
$kullanici_id = $_SESSION['kullanici_id'];
$tedarikci_sql = "SELECT t.* FROM tedarikciler t 
                 INNER JOIN kullanici_tedarikci_iliskileri kti ON t.id = kti.tedarikci_id
                 WHERE kti.kullanici_id = ?";
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

// Kullanıcı bilgilerini al
$kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
$kullanici_stmt = $db->prepare($kullanici_sql);
$kullanici_stmt->execute([$kullanici_id]);
$kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);

// Mesaj değişkenleri
$mesaj = '';
$hata = '';
$sifre_mesaj = '';
$sifre_hata = '';

// Profil güncelleme formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'profil_guncelle') {
    $yeni_ad_soyad = $_POST['ad_soyad'] ?? '';
    $yeni_email = $_POST['email'] ?? '';
    $yeni_telefon = $_POST['telefon'] ?? '';
    
    if (empty($yeni_ad_soyad) || empty($yeni_email)) {
        $hata = "Ad Soyad ve E-posta alanları zorunludur!";
    } else {
        try {
            // Kullanıcı bilgilerini güncelle
            $guncelle_sql = "UPDATE kullanicilar SET 
                           ad_soyad = ?, 
                           email = ?, 
                           telefon = ? 
                           WHERE id = ?";
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_stmt->execute([
                $yeni_ad_soyad,
                $yeni_email,
                $yeni_telefon,
                $kullanici_id
            ]);
            
            // Tedarikçi bilgilerini güncelle (e-posta ile bağlantılıysa)
            if ($tedarikci['email'] === $kullanici['email']) {
                $tedarikci_guncelle_sql = "UPDATE tedarikciler SET 
                                         email = ?, 
                                         telefon = ? 
                                         WHERE id = ?";
                $tedarikci_guncelle_stmt = $db->prepare($tedarikci_guncelle_sql);
                $tedarikci_guncelle_stmt->execute([
                    $yeni_email,
                    $yeni_telefon,
                    $tedarikci['id']
                ]);
            }
            
            // Session bilgilerini güncelle
            $_SESSION['ad_soyad'] = $yeni_ad_soyad;
            $_SESSION['email'] = $yeni_email;
            
            // Güncel bilgileri al
            $kullanici_stmt->execute([$kullanici_id]);
            $kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);
            
            $mesaj = "Profil bilgileriniz başarıyla güncellendi.";
        } catch (Exception $e) {
            $hata = "Profil güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Şifre değiştirme formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'sifre_degistir') {
    $mevcut_sifre = $_POST['mevcut_sifre'] ?? '';
    $yeni_sifre = $_POST['yeni_sifre'] ?? '';
    $yeni_sifre_tekrar = $_POST['yeni_sifre_tekrar'] ?? '';
    
    if (empty($mevcut_sifre) || empty($yeni_sifre) || empty($yeni_sifre_tekrar)) {
        $sifre_hata = "Tüm şifre alanları zorunludur!";
    } elseif ($yeni_sifre !== $yeni_sifre_tekrar) {
        $sifre_hata = "Yeni şifreler eşleşmiyor!";
    } elseif (strlen($yeni_sifre) < 6) {
        $sifre_hata = "Yeni şifre en az 6 karakter olmalıdır!";
    } else {
        // Mevcut şifreyi doğrula
        if (password_verify($mevcut_sifre, $kullanici['sifre'])) {
            try {
                // Şifreyi güncelle
                $hash_sifre = password_hash($yeni_sifre, PASSWORD_DEFAULT);
                $sifre_guncelle_sql = "UPDATE kullanicilar SET sifre = ? WHERE id = ?";
                $sifre_guncelle_stmt = $db->prepare($sifre_guncelle_sql);
                $sifre_guncelle_stmt->execute([$hash_sifre, $kullanici_id]);
                
                $sifre_mesaj = "Şifreniz başarıyla güncellendi.";
            } catch (Exception $e) {
                $sifre_hata = "Şifre güncellenirken bir hata oluştu: " . $e->getMessage();
            }
        } else {
            $sifre_hata = "Mevcut şifre doğru değil!";
        }
    }
}

// Firma bilgileri güncelleme formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'firma_guncelle') {
    $firma_adi = $_POST['firma_adi'] ?? '';
    $firma_adres = $_POST['adres'] ?? '';
    $firma_vergi_no = $_POST['vergi_no'] ?? '';
    $firma_yetkili = $_POST['yetkili_kisi'] ?? '';
    
    if (empty($firma_adi)) {
        $hata = "Firma adı zorunludur!";
    } else {
        try {
            // Firma bilgilerini güncelle
            $firma_guncelle_sql = "UPDATE tedarikciler SET 
                                  firma_adi = ?, 
                                  adres = ?, 
                                  vergi_no = ?, 
                                  yetkili_kisi = ?, 
                                  guncelleme_tarihi = NOW() 
                                  WHERE id = ?";
            $firma_guncelle_stmt = $db->prepare($firma_guncelle_sql);
            $firma_guncelle_stmt->execute([
                $firma_adi,
                $firma_adres,
                $firma_vergi_no,
                $firma_yetkili,
                $tedarikci['id']
            ]);
            
            // Güncel bilgileri al
            $tedarikci_stmt->execute([$kullanici_id]);
            $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
            
            $mesaj = "Firma bilgileriniz başarıyla güncellendi.";
        } catch (Exception $e) {
            $hata = "Firma bilgileri güncellenirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Sorumluları getir
$sorumlular_sql = "SELECT k.id, k.ad_soyad, k.email, k.telefon 
                  FROM kullanicilar k
                  INNER JOIN sorumluluklar s ON k.id = s.sorumlu_id
                  WHERE s.tedarikci_id = ?";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute([$tedarikci['id']]);
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<h2 class="mb-4">Profil Yönetimi</h2>

<!-- Bildirimler -->
<?php if (!empty($mesaj)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= guvenli($mesaj) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($hata)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= guvenli($hata) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="row">
    <!-- Kullanıcı Bilgileri -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Kullanıcı Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="action" value="profil_guncelle">
                    <div class="mb-3">
                        <label for="ad_soyad" class="form-label">Ad Soyad</label>
                        <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" value="<?= guvenli($kullanici['ad_soyad']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">E-posta</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= guvenli($kullanici['email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telefon" class="form-label">Telefon</label>
                        <input type="tel" class="form-control" id="telefon" name="telefon" value="<?= guvenli($kullanici['telefon']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="kayit_tarihi" class="form-label">Kayıt Tarihi</label>
                        <input type="text" class="form-control" id="kayit_tarihi" value="<?= date('d.m.Y H:i', strtotime($kullanici['olusturma_tarihi'])) ?>" readonly>
                    </div>
                    <button type="submit" class="btn btn-primary">Bilgileri Güncelle</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Şifre Değiştirme -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Şifre Değiştir</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($sifre_mesaj)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= guvenli($sifre_mesaj) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($sifre_hata)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= guvenli($sifre_hata) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <form method="post" action="">
                    <input type="hidden" name="action" value="sifre_degistir">
                    <div class="mb-3">
                        <label for="mevcut_sifre" class="form-label">Mevcut Şifre</label>
                        <input type="password" class="form-control" id="mevcut_sifre" name="mevcut_sifre" required>
                    </div>
                    <div class="mb-3">
                        <label for="yeni_sifre" class="form-label">Yeni Şifre</label>
                        <input type="password" class="form-control" id="yeni_sifre" name="yeni_sifre" required>
                        <div class="form-text">Şifreniz en az 6 karakter olmalıdır.</div>
                    </div>
                    <div class="mb-3">
                        <label for="yeni_sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                        <input type="password" class="form-control" id="yeni_sifre_tekrar" name="yeni_sifre_tekrar" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Şifreyi Değiştir</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Firma Bilgileri -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Firma Bilgileri</h5>
            </div>
            <div class="card-body">
                <form method="post" action="">
                    <input type="hidden" name="action" value="firma_guncelle">
                    <div class="mb-3">
                        <label for="firma_adi" class="form-label">Firma Adı</label>
                        <input type="text" class="form-control" id="firma_adi" name="firma_adi" value="<?= guvenli($tedarikci['firma_adi']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="firma_kodu" class="form-label">Firma Kodu</label>
                        <input type="text" class="form-control" id="firma_kodu" value="<?= guvenli($tedarikci['firma_kodu'] ?? '') ?>" readonly>
                        <div class="form-text">Firma kodu sistem tarafından atanır ve değiştirilemez.</div>
                    </div>
                    <div class="mb-3">
                        <label for="vergi_no" class="form-label">Vergi No</label>
                        <input type="text" class="form-control" id="vergi_no" name="vergi_no" value="<?= guvenli($tedarikci['vergi_no'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="yetkili_kisi" class="form-label">Yetkili Kişi</label>
                        <input type="text" class="form-control" id="yetkili_kisi" name="yetkili_kisi" value="<?= guvenli($tedarikci['yetkili_kisi'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="adres" class="form-label">Adres</label>
                        <textarea class="form-control" id="adres" name="adres" rows="3"><?= guvenli($tedarikci['adres'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Firma Bilgilerini Güncelle</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Sorumlu Kişiler -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">İlgili Sorumlular</h5>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sorumlular as $sorumlu): ?>
                            <tr>
                                <td><?= guvenli($sorumlu['ad_soyad']) ?></td>
                                <td><?= guvenli($sorumlu['email']) ?></td>
                                <td><?= guvenli($sorumlu['telefon'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> Size henüz sorumlu atanmamış.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Footer dosyasını dahil et
include 'footer.php';
?> 