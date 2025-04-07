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

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Kullanıcı Bilgileri</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($hatalar)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($hatalar as $hata): ?>
                                    <li><?= $hata ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ad_soyad" class="form-label">Ad Soyad <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ad_soyad" name="ad_soyad" value="<?= isset($ad_soyad) ? guvenli($ad_soyad) : '' ?>" required>
                                <div class="invalid-feedback">Lütfen ad soyad girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? guvenli($email) : '' ?>" required>
                                <div class="invalid-feedback">Lütfen geçerli bir e-posta adresi girin.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon" value="<?= isset($telefon) ? guvenli($telefon) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="">-- Rol Seçin --</option>
                                    <option value="admin" <?= (isset($rol) && $rol == 'admin') ? 'selected' : '' ?>>Admin</option>
                                    <option value="sorumlu" <?= (isset($rol) && $rol == 'sorumlu') ? 'selected' : '' ?>>Sorumlu</option>
                                    <option value="tedarikci" <?= (isset($rol) && $rol == 'tedarikci') ? 'selected' : '' ?>>Tedarikçi</option>
                                </select>
                                <div class="invalid-feedback">Lütfen bir rol seçin.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sifre" class="form-label">Yeni Şifre</label>
                                <input type="password" class="form-control" id="sifre" name="sifre">
                                <div class="form-text">Şifreyi değiştirmek istemiyorsanız boş bırakın.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="sifre_tekrar" class="form-label">Yeni Şifre (Tekrar)</label>
                                <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="aktif" class="form-label">Durum</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" id="aktif" name="aktif" <?= (!isset($aktif) || $aktif) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="aktif">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="aciklama" class="form-label">Açıklama</label>
                                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= isset($aciklama) ? guvenli($aciklama) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="kullanicilar.php" class="btn btn-secondary">İptal</a>
                            <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 