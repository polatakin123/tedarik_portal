<?php
// admin/kullanici_ekle.php - Kullanıcı ekleme sayfası
require_once '../config.php';
girisKontrol();

// Admin veya Sorumlu rolüne sahip kullanıcılar erişebilir
if (!isset($_SESSION['rol']) || ($_SESSION['rol'] !== 'Admin' && $_SESSION['rol'] !== 'Sorumlu')) {
    header("Location: ../savunma/yetki_yok.php");
    exit;
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad_soyad = isset($_POST['ad_soyad']) ? trim($_POST['ad_soyad']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $kullanici_adi = isset($_POST['kullanici_adi']) ? trim($_POST['kullanici_adi']) : '';
    $sifre = isset($_POST['sifre']) ? trim($_POST['sifre']) : '';
    $sifre_tekrar = isset($_POST['sifre_tekrar']) ? trim($_POST['sifre_tekrar']) : '';
    $telefon = isset($_POST['telefon']) ? trim($_POST['telefon']) : '';
    $rol = isset($_POST['rol']) ? trim($_POST['rol']) : '';
    $firma_id = isset($_POST['firma_id']) && !empty($_POST['firma_id']) ? intval($_POST['firma_id']) : null;
    $aktif = isset($_POST['aktif']) ? 1 : 0;
    
    // Eklenen bilgilerin kontrolü
    $hatalar = [];
    
    if (empty($ad_soyad)) {
        $hatalar[] = "Ad Soyad alanı boş bırakılamaz.";
    }
    
    if (empty($email)) {
        $hatalar[] = "E-posta alanı boş bırakılamaz.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = "Geçerli bir e-posta adresi giriniz.";
    } else {
        // E-posta adresi benzersiz mi kontrol et
        $kontrol = $db->prepare("SELECT COUNT(*) FROM kullanicilar WHERE email = ?");
        $kontrol->execute([$email]);
        if ($kontrol->fetchColumn() > 0) {
            $hatalar[] = "Bu e-posta adresi zaten kullanılmaktadır.";
        }
    }
    
    if (empty($kullanici_adi)) {
        $hatalar[] = "Kullanıcı adı boş bırakılamaz.";
    } else {
        // Kullanıcı adı benzersiz mi kontrol et
        $kontrol = $db->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?");
        $kontrol->execute([$kullanici_adi]);
        if ($kontrol->fetchColumn() > 0) {
            $hatalar[] = "Bu kullanıcı adı zaten kullanılmaktadır.";
        }
    }
    
    if (empty($sifre)) {
        $hatalar[] = "Şifre alanı boş bırakılamaz.";
    } elseif (strlen($sifre) < 6) {
        $hatalar[] = "Şifre en az 6 karakter olmalıdır.";
    } elseif ($sifre !== $sifre_tekrar) {
        $hatalar[] = "Girilen şifreler uyuşmuyor.";
    }
    
    if (empty($rol)) {
        $hatalar[] = "Kullanıcı rolü seçilmelidir.";
    }
    
    // Rol Tedarikci ise firma_id zorunlu
    if ($rol === 'Tedarikci' && empty($firma_id)) {
        $hatalar[] = "Tedarikçi kullanıcılar için firma seçilmelidir.";
    }
    
    // Hata yoksa kullanıcıyı ekle
    if (empty($hatalar)) {
        try {
            $db->beginTransaction();
            
            // Şifreyi hash'le
            $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);
            
            $sql = "INSERT INTO kullanicilar (ad_soyad, email, kullanici_adi, sifre, telefon, rol, firma_id, aktif, olusturma_tarihi) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $db->prepare($sql);
            $stmt->execute([$ad_soyad, $email, $kullanici_adi, $hashed_password, $telefon, $rol, $firma_id, $aktif]);
            
            $kullanici_id = $db->lastInsertId();
            
            // Eğer tedarikçi kullanıcısı ise, tedarikçi ile ilişkilendir
            if ($rol === 'Tedarikci' && !empty($firma_id)) {
                $iliski_sql = "INSERT INTO kullanici_tedarikci_iliskileri (kullanici_id, tedarikci_id) VALUES (?, ?)";
                $iliski_stmt = $db->prepare($iliski_sql);
                $iliski_stmt->execute([$kullanici_id, $firma_id]);
            }
            
            $db->commit();
            
            // Başarılı mesajı ile yönlendir
            $mesaj = "Kullanıcı başarıyla eklendi.";
            header("Location: kullanicilar.php?mesaj=" . urlencode($mesaj));
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Tedarikçileri getir
$tedarikciler_sql = "SELECT id, firma_adi, firma_kodu FROM tedarikciler WHERE aktif = 1 ORDER BY firma_adi ASC";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Yeni Kullanıcı Ekle</h5>
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
                                <div class="invalid-feedback">Ad soyad gereklidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? guvenli($email) : '' ?>" required>
                                <div class="invalid-feedback">Geçerli bir e-posta adresi giriniz.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="kullanici_adi" class="form-label">Kullanıcı Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="kullanici_adi" name="kullanici_adi" value="<?= isset($kullanici_adi) ? guvenli($kullanici_adi) : '' ?>" required>
                                <div class="invalid-feedback">Kullanıcı adı gereklidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon" value="<?= isset($telefon) ? guvenli($telefon) : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" id="rol" name="rol" required>
                                    <option value="">-- Rol Seçin --</option>
                                    <option value="Admin" <?= (isset($rol) && $rol == 'Admin') ? 'selected' : '' ?>>Admin</option>
                                    <option value="Sorumlu" <?= (isset($rol) && $rol == 'Sorumlu') ? 'selected' : '' ?>>Sorumlu</option>
                                    <option value="Tedarikci" <?= (isset($rol) && $rol == 'Tedarikci') ? 'selected' : '' ?>>Tedarikçi</option>
                                </select>
                                <div class="invalid-feedback">Rol seçimi gereklidir.</div>
                            </div>
                            <div class="col-md-6" id="firma_secim_alani" style="display: none;">
                                <label for="firma_id" class="form-label">Firma <span class="text-danger">*</span></label>
                                <select class="form-select" id="firma_id" name="firma_id">
                                    <option value="">-- Firma Seçin --</option>
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <option value="<?= $tedarikci['id'] ?>" <?= (isset($firma_id) && $firma_id == $tedarikci['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Tedarikçi rolü seçildiğinde firma seçimi zorunludur.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sifre" class="form-label">Şifre <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="sifre" name="sifre" required>
                                <div class="invalid-feedback">Şifre gereklidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="sifre_tekrar" class="form-label">Şifre Tekrar <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" required>
                                <div class="invalid-feedback">Şifre tekrarı gereklidir.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="aktif" class="form-label">Durum</label>
                                <select class="form-select" id="aktif" name="aktif">
                                    <option value="1" <?= (isset($aktif) && $aktif == 1) ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= (isset($aktif) && $aktif == 0) ? 'selected' : '' ?>>Pasif</option>
                                </select>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="kullanicilar.php" class="btn btn-secondary">İptal</a>
                            <button type="submit" class="btn btn-primary">Kullanıcı Ekle</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Rol seçimine göre firma seçim alanını göster/gizle
    document.addEventListener('DOMContentLoaded', function() {
        const rolSelect = document.getElementById('rol');
        const firmaSecimAlani = document.getElementById('firma_secim_alani');
        const firmaSelect = document.getElementById('firma_id');
        
        // Sayfa yüklendiğinde mevcut rol seçimine göre firma alanını göster/gizle
        if (rolSelect.value === 'Tedarikci') {
            firmaSecimAlani.style.display = 'block';
            firmaSelect.required = true;
        } else {
            firmaSecimAlani.style.display = 'none';
            firmaSelect.required = false;
        }
        
        // Rol değiştiğinde firma alanını göster/gizle
        rolSelect.addEventListener('change', function() {
            if (this.value === 'Tedarikci') {
                firmaSecimAlani.style.display = 'block';
                firmaSelect.required = true;
            } else {
                firmaSecimAlani.style.display = 'none';
                firmaSelect.required = false;
                firmaSelect.value = ''; // Firma seçimini temizle
            }
        });
    });
</script>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 