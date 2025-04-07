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

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Tedarikçi Bilgileri</h5>
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
                                <label for="firma_adi" class="form-label">Firma Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firma_adi" name="firma_adi" value="<?= isset($firma_adi) ? guvenli($firma_adi) : '' ?>" required>
                                <div class="invalid-feedback">Lütfen firma adını girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="firma_kodu" class="form-label">Firma Kodu <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="firma_kodu" name="firma_kodu" value="<?= isset($firma_kodu) ? guvenli($firma_kodu) : '' ?>" required>
                                <div class="invalid-feedback">Lütfen firma kodunu girin.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="yetkili_kisi" class="form-label">Yetkili Kişi</label>
                                <input type="text" class="form-control" id="yetkili_kisi" name="yetkili_kisi" value="<?= isset($yetkili_kisi) ? guvenli($yetkili_kisi) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? guvenli($email) : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="telefon" name="telefon" value="<?= isset($telefon) ? guvenli($telefon) : '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="vergi_no" class="form-label">Vergi No</label>
                                <input type="text" class="form-control" id="vergi_no" name="vergi_no" value="<?= isset($vergi_no) ? guvenli($vergi_no) : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="vergi_dairesi" class="form-label">Vergi Dairesi</label>
                                <input type="text" class="form-control" id="vergi_dairesi" name="vergi_dairesi" value="<?= isset($vergi_dairesi) ? guvenli($vergi_dairesi) : '' ?>">
                            </div>
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
                                <label for="adres" class="form-label">Adres</label>
                                <textarea class="form-control" id="adres" name="adres" rows="3"><?= isset($adres) ? guvenli($adres) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="aciklama" class="form-label">Açıklama</label>
                                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= isset($aciklama) ? guvenli($aciklama) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="tedarikciler.php" class="btn btn-secondary">İptal</a>
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