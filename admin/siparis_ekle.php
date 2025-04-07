<?php
// admin/siparis_ekle.php - Sipariş ekleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tedarikci_id = isset($_POST['tedarikci_id']) ? intval($_POST['tedarikci_id']) : 0;
    $proje_id = isset($_POST['proje_id']) ? intval($_POST['proje_id']) : 0;
    $sorumlu_id = isset($_POST['sorumlu_id']) ? intval($_POST['sorumlu_id']) : 0;
    $durum_id = isset($_POST['durum_id']) ? intval($_POST['durum_id']) : 1; // Varsayılan: Açık
    $parca_no = isset($_POST['parca_no']) ? trim($_POST['parca_no']) : '';
    $parca_adi = isset($_POST['parca_adi']) ? trim($_POST['parca_adi']) : '';
    $miktar = isset($_POST['miktar']) ? trim($_POST['miktar']) : '';
    $birim = isset($_POST['birim']) ? trim($_POST['birim']) : '';
    $teslim_tarihi = isset($_POST['teslim_tarihi']) ? trim($_POST['teslim_tarihi']) : null;
    $aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : '';
    
    // Sipariş numarası oluşturma (Örnek: SIP-2023-0001)
    $yil = date('Y');
    $siparis_kodu = "SIP-" . $yil . "-";
    
    // Son sipariş numarasını bul ve bir artır
    $son_siparis_sql = "SELECT MAX(CAST(SUBSTRING_INDEX(siparis_no, '-', -1) AS UNSIGNED)) as son_no 
                       FROM siparisler 
                       WHERE siparis_no LIKE ?";
    $son_siparis_stmt = $db->prepare($son_siparis_sql);
    $son_siparis_stmt->execute([$siparis_kodu . "%"]);
    $son_no = $son_siparis_stmt->fetch(PDO::FETCH_ASSOC)['son_no'];
    
    $yeni_no = $son_no ? $son_no + 1 : 1;
    $siparis_no = $siparis_kodu . str_pad($yeni_no, 4, "0", STR_PAD_LEFT);
    
    // Validasyon
    $hatalar = [];
    
    if (empty($tedarikci_id)) {
        $hatalar[] = "Tedarikçi seçilmelidir.";
    }
    
    if (empty($proje_id)) {
        $hatalar[] = "Proje seçilmelidir.";
    }
    
    if (empty($sorumlu_id)) {
        $hatalar[] = "Sorumlu kişi seçilmelidir.";
    }
    
    if (empty($parca_no)) {
        $hatalar[] = "Parça numarası girilmelidir.";
    }
    
    if (empty($miktar)) {
        $hatalar[] = "Miktar girilmelidir.";
    } elseif (!is_numeric($miktar) || $miktar <= 0) {
        $hatalar[] = "Miktar pozitif bir sayı olmalıdır.";
    }
    
    if (empty($birim)) {
        $hatalar[] = "Birim seçilmelidir.";
    }
    
    // Hata yoksa siparişi ekle
    if (empty($hatalar)) {
        try {
            $db->beginTransaction();
            
            $sql = "INSERT INTO siparisler (
                        siparis_no, tedarikci_id, proje_id, sorumlu_id, durum_id, 
                        parca_no, parca_adi, miktar, birim, teslim_tarihi, 
                        aciklama, olusturan_id, olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $siparis_no, $tedarikci_id, $proje_id, $sorumlu_id, $durum_id,
                $parca_no, $parca_adi, $miktar, $birim, $teslim_tarihi,
                $aciklama, $_SESSION['kullanici_id']
            ]);
            
            $siparis_id = $db->lastInsertId();
            
            // Sorumluya bildirim gönder
            $bildirim_mesaji = "Yeni bir sipariş oluşturuldu: " . $siparis_no;
            bildirimOlustur($db, $sorumlu_id, $bildirim_mesaji, $siparis_id);
            
            // Tedarikçi kullanıcılarına bildirim gönder
            $tedarikci_kullanicilar_sql = "SELECT kullanici_id FROM kullanici_tedarikci_iliskileri WHERE tedarikci_id = ?";
            $tedarikci_kullanicilar_stmt = $db->prepare($tedarikci_kullanicilar_sql);
            $tedarikci_kullanicilar_stmt->execute([$tedarikci_id]);
            
            while ($kullanici = $tedarikci_kullanicilar_stmt->fetch(PDO::FETCH_ASSOC)) {
                $bildirim_mesaji = "Size yeni bir sipariş atandı: " . $siparis_no;
                bildirimOlustur($db, $kullanici['kullanici_id'], $bildirim_mesaji, $siparis_id);
            }
            
            $db->commit();
            
            // Başarılı mesajı ile yönlendir
            $mesaj = "Sipariş başarıyla oluşturuldu.";
            header("Location: siparis_detay.php?id=" . $siparis_id . "&mesaj=" . urlencode($mesaj));
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Tedarikçileri getir
$tedarikciler_sql = "SELECT id, firma_adi, firma_kodu FROM tedarikciler WHERE aktif = 1 ORDER BY firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri getir
$projeler_sql = "SELECT id, proje_adi, proje_kodu FROM projeler WHERE aktif = 1 ORDER BY proje_adi";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute();
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sorumluları getir
$sorumlular_sql = "SELECT id, ad_soyad, email FROM kullanicilar WHERE rol = 'Sorumlu' AND aktif = 1 ORDER BY ad_soyad";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute();
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını getir
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

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
                    <h5 class="mb-0">Sipariş Bilgileri</h5>
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
                                <label for="tedarikci_id" class="form-label">Tedarikçi <span class="text-danger">*</span></label>
                                <select class="form-select" id="tedarikci_id" name="tedarikci_id" required>
                                    <option value="">-- Tedarikçi Seçin --</option>
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <option value="<?= $tedarikci['id'] ?>" <?= (isset($tedarikci_id) && $tedarikci_id == $tedarikci['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Lütfen bir tedarikçi seçin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="proje_id" class="form-label">Proje <span class="text-danger">*</span></label>
                                <select class="form-select" id="proje_id" name="proje_id" required>
                                    <option value="">-- Proje Seçin --</option>
                                    <?php foreach ($projeler as $proje): ?>
                                        <option value="<?= $proje['id'] ?>" <?= (isset($proje_id) && $proje_id == $proje['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($proje['proje_adi']) ?> (<?= guvenli($proje['proje_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Lütfen bir proje seçin.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sorumlu_id" class="form-label">Sorumlu <span class="text-danger">*</span></label>
                                <select class="form-select" id="sorumlu_id" name="sorumlu_id" required>
                                    <option value="">-- Sorumlu Seçin --</option>
                                    <?php foreach ($sorumlular as $sorumlu): ?>
                                        <option value="<?= $sorumlu['id'] ?>" <?= (isset($sorumlu_id) && $sorumlu_id == $sorumlu['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($sorumlu['ad_soyad']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Lütfen bir sorumlu seçin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="durum_id" class="form-label">Durum</label>
                                <select class="form-select" id="durum_id" name="durum_id">
                                    <?php foreach ($durumlar as $durum): ?>
                                        <option value="<?= $durum['id'] ?>" <?= (isset($durum_id) && $durum_id == $durum['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($durum['durum_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parca_no" class="form-label">Parça No <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="parca_no" name="parca_no" value="<?= isset($parca_no) ? guvenli($parca_no) : '' ?>" required>
                                <div class="invalid-feedback">Lütfen parça numarası girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="parca_adi" class="form-label">Parça Adı</label>
                                <input type="text" class="form-control" id="parca_adi" name="parca_adi" value="<?= isset($parca_adi) ? guvenli($parca_adi) : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="miktar" class="form-label">Miktar <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="miktar" name="miktar" value="<?= isset($miktar) ? guvenli($miktar) : '' ?>" required min="0" step="0.01">
                                <div class="invalid-feedback">Lütfen geçerli bir miktar girin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="birim" class="form-label">Birim <span class="text-danger">*</span></label>
                                <select class="form-select" id="birim" name="birim" required>
                                    <option value="">-- Birim Seçin --</option>
                                    <option value="ADET" <?= (isset($birim) && $birim == 'ADET') ? 'selected' : '' ?>>Adet</option>
                                    <option value="KG" <?= (isset($birim) && $birim == 'KG') ? 'selected' : '' ?>>Kilogram</option>
                                    <option value="METRE" <?= (isset($birim) && $birim == 'METRE') ? 'selected' : '' ?>>Metre</option>
                                    <option value="LITRE" <?= (isset($birim) && $birim == 'LITRE') ? 'selected' : '' ?>>Litre</option>
                                </select>
                                <div class="invalid-feedback">Lütfen bir birim seçin.</div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="teslim_tarihi" class="form-label">Teslim Tarihi</label>
                                <input type="date" class="form-control" id="teslim_tarihi" name="teslim_tarihi" value="<?= isset($teslim_tarihi) ? guvenli($teslim_tarihi) : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-12">
                                <label for="aciklama" class="form-label">Açıklama</label>
                                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?= isset($aciklama) ? guvenli($aciklama) : '' ?></textarea>
                            </div>
                        </div>

                        <div class="text-end">
                            <a href="siparisler.php" class="btn btn-secondary">İptal</a>
                            <button type="submit" class="btn btn-primary">Sipariş Oluştur</button>
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