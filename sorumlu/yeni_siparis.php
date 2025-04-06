<?php
// sorumlu/yeni_siparis.php - Yeni sipariş ekleme sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Yeni Sipariş";

$sorumlu_id = $_SESSION['kullanici_id'];
$hata_mesaji = '';
$basari_mesaji = '';

// Tedarikçileri al
$tedarikciler_sql = "SELECT t.* 
                     FROM tedarikciler t
                     INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                     WHERE s.sorumlu_id = ?
                     ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri al
$projeler_sql = "SELECT id, proje_adi, proje_kodu FROM projeler ORDER BY proje_adi";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute();
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını al
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ekle'])) {
    // Form verilerini al
    $siparis_no = $_POST['siparis_no'];
    $parca_no = $_POST['parca_no'];
    $tanim = $_POST['tanim'];
    $tedarikci_id = intval($_POST['tedarikci_id']);
    $proje_id = intval($_POST['proje_id']);
    $miktar = floatval($_POST['miktar']);
    $birim = $_POST['birim'];
    $acilis_tarihi = $_POST['acilis_tarihi'];
    $teslim_tarihi = !empty($_POST['teslim_tarihi']) ? $_POST['teslim_tarihi'] : null;
    $durum_id = intval($_POST['durum_id'] ?? 1); // Varsayılan: Açık
    $fai = $_POST['fai'] ?? null;
    $satinalmaci = $_POST['satinalmaci'] ?? null;
    $alt_malzeme = $_POST['alt_malzeme'] ?? null;
    $onaylanan_revizyon = $_POST['onaylanan_revizyon'] ?? null;
    $tedarikci_parca_no = $_POST['tedarikci_parca_no'] ?? null;
    $vehicle_id = $_POST['vehicle_id'] ?? null;
    
    // Validation
    if (empty($siparis_no) || empty($parca_no) || empty($tanim) || $tedarikci_id <= 0 || $proje_id <= 0 || $miktar <= 0 || empty($birim) || empty($acilis_tarihi)) {
        $hata_mesaji = "Lütfen tüm zorunlu alanları doldurunuz.";
    } else {
        try {
            $db->beginTransaction();
            
            // Siparişi ekle
            $ekle_sql = "INSERT INTO siparisler (
                            siparis_no, parca_no, tanim, tedarikci_id, proje_id, 
                            miktar, birim, acilis_tarihi, teslim_tarihi, durum_id, 
                            fai, satinalmaci, alt_malzeme, onaylanan_revizyon, 
                            tedarikci_parca_no, vehicle_id, sorumlu_id, 
                            olusturma_tarihi, guncelleme_tarihi, son_guncelleyen_id
                        ) VALUES (
                            ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, ?, 
                            ?, ?, ?, ?, 
                            ?, ?, ?, 
                            NOW(), NOW(), ?
                        )";
            $ekle_stmt = $db->prepare($ekle_sql);
            $ekle_stmt->execute([
                $siparis_no, $parca_no, $tanim, $tedarikci_id, $proje_id,
                $miktar, $birim, $acilis_tarihi, $teslim_tarihi, $durum_id,
                $fai, $satinalmaci, $alt_malzeme, $onaylanan_revizyon,
                $tedarikci_parca_no, $vehicle_id, $sorumlu_id,
                $sorumlu_id
            ]);
            
            $siparis_id = $db->lastInsertId();
            
            // Sipariş log kaydı oluştur
            $log_sql = "INSERT INTO siparis_log (siparis_id, islem_turu, islem_yapan_id, islem_tarihi, durum_id, aciklama) 
                       VALUES (?, 'Oluşturma', ?, NOW(), ?, ?)";
            $log_stmt = $db->prepare($log_sql);
            $log_stmt->execute([$siparis_id, $sorumlu_id, $durum_id, "Sipariş oluşturuldu."]);
            
            // Güncelleme tablosuna kayıt ekle
            $guncelleme_sql = "INSERT INTO siparis_guncellemeleri (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleme_tarihi, guncelleyen_id) 
                               VALUES (?, 'Sipariş Oluşturma', ?, NOW(), ?)";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([$siparis_id, "Sipariş oluşturuldu: $siparis_no", $sorumlu_id]);
            
            // Tedarikçi için bildirim oluştur
            $tedarikci_kullanici_sql = "SELECT kullanici_id FROM kullanici_tedarikci_iliskileri WHERE tedarikci_id = ?";
            $tedarikci_kullanici_stmt = $db->prepare($tedarikci_kullanici_sql);
            $tedarikci_kullanici_stmt->execute([$tedarikci_id]);
            $tedarikci_kullanicilar = $tedarikci_kullanici_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $bildirim_mesaji = "Yeni sipariş oluşturuldu: {$siparis_no}";
            foreach ($tedarikci_kullanicilar as $kullanici) {
                $bildirim_sql = "INSERT INTO bildirimler (kullanici_id, mesaj, bildirim_tarihi, okundu, ilgili_siparis_id) 
                                VALUES (?, ?, NOW(), 0, ?)";
                $bildirim_stmt = $db->prepare($bildirim_sql);
                $bildirim_stmt->execute([$kullanici['kullanici_id'], $bildirim_mesaji, $siparis_id]);
            }
            
            $db->commit();
            $basari_mesaji = "Sipariş başarıyla oluşturuldu.";
            
            // Başarıyla eklendiğinde formu temizle
            if (!empty($basari_mesaji)) {
                header("Location: siparis_detay.php?id=$siparis_id");
                exit;
            }
        } catch (PDOException $e) {
            $db->rollBack();
            $hata_mesaji = "Sipariş oluşturulurken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Yeni Sipariş Oluştur</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="siparisler.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Siparişlere Dön
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($hata_mesaji)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $hata_mesaji ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($basari_mesaji)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $basari_mesaji ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
        </div>
        <div class="card-body">
            <form method="post" action="yeni_siparis.php" class="needs-validation" novalidate>
                <div class="row">
                    <!-- Temel Bilgiler -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0">Temel Bilgiler</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label for="siparis_no" class="col-sm-4 col-form-label">Sipariş No <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="siparis_no" name="siparis_no" required>
                                        <div class="invalid-feedback">Sipariş no gereklidir.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="parca_no" class="col-sm-4 col-form-label">Parça No <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="parca_no" name="parca_no" required>
                                        <div class="invalid-feedback">Parça no gereklidir.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="tanim" class="col-sm-4 col-form-label">Tanım <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <textarea class="form-control" id="tanim" name="tanim" rows="2" required></textarea>
                                        <div class="invalid-feedback">Tanım gereklidir.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="tedarikci_id" class="col-sm-4 col-form-label">Tedarikçi <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <select class="form-select" id="tedarikci_id" name="tedarikci_id" required>
                                            <option value="">Tedarikçi Seçin</option>
                                            <?php foreach ($tedarikciler as $tedarikci): ?>
                                                <option value="<?= $tedarikci['id'] ?>">
                                                    <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Tedarikçi seçimi gereklidir.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="proje_id" class="col-sm-4 col-form-label">Proje <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <select class="form-select" id="proje_id" name="proje_id" required>
                                            <option value="">Proje Seçin</option>
                                            <?php foreach ($projeler as $proje): ?>
                                                <option value="<?= $proje['id'] ?>">
                                                    <?= guvenli($proje['proje_adi']) ?> (<?= guvenli($proje['proje_kodu']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="invalid-feedback">Proje seçimi gereklidir.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="durum_id" class="col-sm-4 col-form-label">Durum <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <select class="form-select" id="durum_id" name="durum_id" required>
                                            <?php foreach ($durumlar as $durum): ?>
                                                <option value="<?= $durum['id'] ?>" <?= ($durum['id'] == 1) ? 'selected' : '' ?>>
                                                    <?= guvenli($durum['durum_adi']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Miktar ve Tarih Bilgileri -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h6 class="mb-0">Miktar ve Tarih Bilgileri</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <label for="miktar" class="col-sm-4 col-form-label">Miktar <span class="text-danger">*</span></label>
                                    <div class="col-sm-4">
                                        <input type="number" class="form-control" id="miktar" name="miktar" min="0.01" step="0.01" required>
                                        <div class="invalid-feedback">Miktar gereklidir.</div>
                                    </div>
                                    <div class="col-sm-4">
                                        <select class="form-select" id="birim" name="birim" required>
                                            <option value="Adet">Adet</option>
                                            <option value="Kg">Kg</option>
                                            <option value="Metre">Metre</option>
                                            <option value="Litre">Litre</option>
                                            <option value="Set">Set</option>
                                            <option value="Takım">Takım</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="acilis_tarihi" class="col-sm-4 col-form-label">Açılış Tarihi <span class="text-danger">*</span></label>
                                    <div class="col-sm-8">
                                        <input type="date" class="form-control" id="acilis_tarihi" name="acilis_tarihi" value="<?= date('Y-m-d') ?>" required>
                                        <div class="invalid-feedback">Açılış tarihi gereklidir.</div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="teslim_tarihi" class="col-sm-4 col-form-label">Teslim Tarihi</label>
                                    <div class="col-sm-8">
                                        <input type="date" class="form-control" id="teslim_tarihi" name="teslim_tarihi">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Ek Bilgiler -->
                        <div class="card">
                            <div class="card-header bg-success text-white">
                                <h6 class="mb-0">Ek Bilgiler</h6>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <label for="fai" class="col-sm-4 col-form-label">FAI</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="fai" name="fai">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label for="satinalmaci" class="col-sm-4 col-form-label">Satınalmacı</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="satinalmaci" name="satinalmaci">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label for="alt_malzeme" class="col-sm-4 col-form-label">Alt Malzeme</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="alt_malzeme" name="alt_malzeme">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label for="onaylanan_revizyon" class="col-sm-4 col-form-label">Onaylanan Revizyon</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="onaylanan_revizyon" name="onaylanan_revizyon">
                                    </div>
                                </div>
                                <div class="row mb-2">
                                    <label for="tedarikci_parca_no" class="col-sm-4 col-form-label">Tedarikçi Parça No</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="tedarikci_parca_no" name="tedarikci_parca_no">
                                    </div>
                                </div>
                                <div class="row">
                                    <label for="vehicle_id" class="col-sm-4 col-form-label">Vehicle ID</label>
                                    <div class="col-sm-8">
                                        <input type="text" class="form-control" id="vehicle_id" name="vehicle_id">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <button type="reset" class="btn btn-secondary me-md-2">
                        <i class="bi bi-arrow-counterclockwise"></i> Formu Temizle
                    </button>
                    <button type="submit" name="ekle" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Siparişi Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form doğrulama için Bootstrap validation
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
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

<?php include 'footer.php'; ?> 