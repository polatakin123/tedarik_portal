<?php
// admin/ayarlar.php - Admin paneli ayarları
require_once '../config.php';
adminYetkisiKontrol();

$hatalar = [];
$mesajlar = [];

// Ayarları veritabanından çek
$ayarlar_sql = "SELECT * FROM ayarlar WHERE id = 1";
$ayarlar_stmt = $db->prepare($ayarlar_sql);
$ayarlar_stmt->execute();
$ayarlar = $ayarlar_stmt->fetch(PDO::FETCH_ASSOC);

// Eğer ayarlar tablosu boşsa varsayılan değerleri oluştur
if (!$ayarlar) {
    $varsayilan_ayarlar_sql = "INSERT INTO ayarlar (id, site_basligi, site_aciklamasi, email, telefon, adres, tema_renk, logo_url, favicon_url) 
                               VALUES (1, 'Tedarik Portalı', 'Tedarikçi ve Sipariş Yönetim Sistemi', 'info@tedarikportali.com', '0212 123 45 67', 'İstanbul, Türkiye', '#4e73df', '', '')";
    $db->exec($varsayilan_ayarlar_sql);
    
    // Ayarları tekrar çek
    $ayarlar_stmt->execute();
    $ayarlar = $ayarlar_stmt->fetch(PDO::FETCH_ASSOC);
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $site_basligi = trim($_POST['site_basligi'] ?? '');
    $site_aciklamasi = trim($_POST['site_aciklamasi'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefon = trim($_POST['telefon'] ?? '');
    $adres = trim($_POST['adres'] ?? '');
    $tema_renk = trim($_POST['tema_renk'] ?? '#4e73df');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $favicon_url = trim($_POST['favicon_url'] ?? '');
    
    // Validasyon
    if (empty($site_basligi)) {
        $hatalar[] = "Site başlığı boş bırakılamaz";
    }
    
    if (empty($email)) {
        $hatalar[] = "E-posta adresi boş bırakılamaz";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $hatalar[] = "Geçerli bir e-posta adresi girin";
    }
    
    // Hata yoksa güncelleme işlemi
    if (empty($hatalar)) {
        try {
            $guncelle_sql = "UPDATE ayarlar SET 
                site_basligi = ?, 
                site_aciklamasi = ?, 
                email = ?, 
                telefon = ?, 
                adres = ?, 
                tema_renk = ?, 
                logo_url = ?, 
                favicon_url = ?, 
                guncelleme_tarihi = NOW() 
                WHERE id = 1";
            
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_sonuc = $guncelle_stmt->execute([
                $site_basligi, 
                $site_aciklamasi, 
                $email, 
                $telefon, 
                $adres, 
                $tema_renk, 
                $logo_url, 
                $favicon_url
            ]);
            
            if ($guncelle_sonuc) {
                $mesajlar[] = "Ayarlar başarıyla güncellendi";
                
                // Ayarları tekrar çek
                $ayarlar_stmt->execute();
                $ayarlar = $ayarlar_stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $hatalar[] = "Ayarlar güncellenirken bir hata oluştu";
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
                    <h5 class="mb-0">Sistem Ayarları</h5>
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
                    
                    <?php if (!empty($mesajlar)): ?>
                        <div class="alert alert-success">
                            <ul class="mb-0">
                                <?php foreach ($mesajlar as $mesaj): ?>
                                    <li><?= $mesaj ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="site_basligi" class="form-label">Site Başlığı <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="site_basligi" name="site_basligi" value="<?= isset($site_basligi) ? guvenli($site_basligi) : guvenli($ayarlar['site_basligi']) ?>" required>
                                <div class="invalid-feedback">Site başlığı gereklidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="site_aciklamasi" class="form-label">Site Açıklaması</label>
                                <input type="text" class="form-control" id="site_aciklamasi" name="site_aciklamasi" value="<?= isset($site_aciklamasi) ? guvenli($site_aciklamasi) : guvenli($ayarlar['site_aciklamasi']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= isset($email) ? guvenli($email) : guvenli($ayarlar['email']) ?>" required>
                                <div class="invalid-feedback">Geçerli bir e-posta adresi girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="telefon" class="form-label">Telefon</label>
                                <input type="text" class="form-control" id="telefon" name="telefon" value="<?= isset($telefon) ? guvenli($telefon) : guvenli($ayarlar['telefon']) ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="adres" class="form-label">Adres</label>
                                <textarea class="form-control" id="adres" name="adres" rows="3"><?= isset($adres) ? guvenli($adres) : guvenli($ayarlar['adres']) ?></textarea>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="logo_url" class="form-label">Logo URL</label>
                                <input type="text" class="form-control" id="logo_url" name="logo_url" value="<?= isset($logo_url) ? guvenli($logo_url) : guvenli($ayarlar['logo_url']) ?>">
                                <div class="form-text">Logo için URL adresi girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="favicon_url" class="form-label">Favicon URL</label>
                                <input type="text" class="form-control" id="favicon_url" name="favicon_url" value="<?= isset($favicon_url) ? guvenli($favicon_url) : guvenli($ayarlar['favicon_url']) ?>">
                                <div class="form-text">Favicon için URL adresi girin.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="tema_renk" class="form-label">Tema Rengi</label>
                                <div class="d-flex align-items-center">
                                    <input type="color" class="form-control form-control-color me-2" id="tema_renk" name="tema_renk" value="<?= isset($tema_renk) ? $tema_renk : $ayarlar['tema_renk'] ?>" title="Tema rengini seçin">
                                    <span id="renk_secici_etiket" class="ms-2">Renk Seçin</span>
                                </div>
                                <div class="form-text">Panel temasının ana rengini seçin.</div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <h6 class="mb-3">Renk Paleti</h6>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn renk-secici" data-renk="#4e73df" style="background-color: #4e73df; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#1cc88a" style="background-color: #1cc88a; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#36b9cc" style="background-color: #36b9cc; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#f6c23e" style="background-color: #f6c23e; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#e74a3b" style="background-color: #e74a3b; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#5a5c69" style="background-color: #5a5c69; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#6f42c1" style="background-color: #6f42c1; width: 40px; height: 40px; border-radius: 50%;"></button>
                                    <button type="button" class="btn renk-secici" data-renk="#fd7e14" style="background-color: #fd7e14; width: 40px; height: 40px; border-radius: 50%;"></button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">Ayarları Kaydet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Renk seçici butonları için event listener
    document.querySelectorAll('.renk-secici').forEach(button => {
        button.addEventListener('click', function() {
            const renk = this.getAttribute('data-renk');
            document.getElementById('tema_renk').value = renk;
            document.getElementById('renk_secici_etiket').textContent = renk;
        });
    });
    
    // Renk seçici input değiştiğinde etiketi güncelle
    document.getElementById('tema_renk').addEventListener('input', function() {
        document.getElementById('renk_secici_etiket').textContent = this.value;
    });
    
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
</script>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 