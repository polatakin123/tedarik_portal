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
    $proje_yoneticisi = trim($_POST['proje_yoneticisi'] ?? '');
    $baslangic_tarihi = trim($_POST['baslangic_tarihi'] ?? '');
    $bitis_tarihi = trim($_POST['bitis_tarihi'] ?? '');
    $proje_aciklama = trim($_POST['proje_aciklama'] ?? '');
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
                proje_yoneticisi = ?, 
                baslangic_tarihi = ?, 
                bitis_tarihi = ?, 
                proje_aciklama = ?, 
                aktif = ?, 
                guncelleme_tarihi = NOW() 
                WHERE id = ?";
            
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_sonuc = $guncelle_stmt->execute([
                $proje_adi, 
                $proje_kodu, 
                $proje_yoneticisi, 
                $baslangic_tarihi ?: null, 
                $bitis_tarihi ?: null, 
                $proje_aciklama, 
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

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Proje Düzenle</h5>
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
                                <label for="proje_adi" class="form-label">Proje Adı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_adi" name="proje_adi" value="<?= isset($proje_adi) ? guvenli($proje_adi) : guvenli($proje['proje_adi']) ?>" required>
                                <div class="invalid-feedback">Proje adı gereklidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="proje_kodu" class="form-label">Proje Kodu <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_kodu" name="proje_kodu" value="<?= isset($proje_kodu) ? guvenli($proje_kodu) : guvenli($proje['proje_kodu']) ?>" required>
                                <div class="invalid-feedback">Proje kodu gereklidir.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="proje_yoneticisi" class="form-label">Proje Yöneticisi <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="proje_yoneticisi" name="proje_yoneticisi" value="<?= isset($proje_yoneticisi) ? guvenli($proje_yoneticisi) : guvenli($proje['proje_yoneticisi']) ?>" required>
                                <div class="invalid-feedback">Proje yöneticisi gereklidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="aktif" class="form-label">Durum</label>
                                <select class="form-select" id="aktif" name="aktif">
                                    <option value="1" <?= (isset($aktif) ? $aktif : $proje['aktif']) == 1 ? 'selected' : '' ?>>Aktif</option>
                                    <option value="0" <?= (isset($aktif) ? $aktif : $proje['aktif']) == 0 ? 'selected' : '' ?>>Pasif</option>
                                </select>
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

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="proje_aciklama" class="form-label">Proje Açıklaması</label>
                                <textarea class="form-control" id="proje_aciklama" name="proje_aciklama" rows="3"><?= isset($proje_aciklama) ? guvenli($proje_aciklama) : guvenli($proje['proje_aciklama']) ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="projeler.php" class="btn btn-secondary">İptal</a>
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