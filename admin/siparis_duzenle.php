<?php
// admin/siparis_duzenle.php - Sipariş düzenleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığı
$page_title = "Sipariş Düzenle";

// Sipariş ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: siparisler.php?hata=" . urlencode("Sipariş ID belirtilmedi"));
    exit;
}

$siparis_id = intval($_GET['id']);

// Sipariş bilgilerini getir
$siparis_sql = "SELECT * FROM siparisler WHERE id = ?";
$siparis_stmt = $db->prepare($siparis_sql);
$siparis_stmt->execute([$siparis_id]);
$siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

if (!$siparis) {
    header("Location: siparisler.php?hata=" . urlencode("Sipariş bulunamadı"));
    exit;
}

// Durum bilgilerini getir
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Proje bilgilerini getir
$projeler_sql = "SELECT id, proje_adi, proje_kodu FROM projeler ORDER BY proje_adi";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute();
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi bilgilerini getir
$tedarikciler_sql = "SELECT id, firma_adi, firma_kodu FROM tedarikciler ORDER BY firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sorumlu bilgilerini getir
$sorumlular_sql = "SELECT id, ad_soyad, email FROM kullanicilar WHERE rol = 'Sorumlu' AND aktif = 1 ORDER BY ad_soyad";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute();
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['siparis_duzenle'])) {
    try {
        // Form verilerini doğrula ve hazırla
        $tedarikci_id = intval($_POST['tedarikci_id']);
        $proje_id = intval($_POST['proje_id']);
        $sorumlu_id = intval($_POST['sorumlu_id']);
        $durum_id = intval($_POST['durum_id']);
        $parca_no = trim($_POST['parca_no']);
        $parca_adi = trim($_POST['parca_adi']);
        $miktar = floatval($_POST['miktar']);
        $birim = trim($_POST['birim']);
        $teslim_tarihi = empty($_POST['teslim_tarihi']) ? null : $_POST['teslim_tarihi'];
        $aciklama = trim($_POST['aciklama']);
        
        // Güncelleme sorgusunu hazırla
        $guncelle_sql = "UPDATE siparisler SET 
                          tedarikci_id = ?, 
                          proje_id = ?, 
                          sorumlu_id = ?, 
                          durum_id = ?, 
                          parca_no = ?, 
                          parca_adi = ?, 
                          miktar = ?, 
                          birim = ?, 
                          teslim_tarihi = ?, 
                          aciklama = ?, 
                          guncelleme_tarihi = NOW() 
                          WHERE id = ?";
        
        $guncelle_stmt = $db->prepare($guncelle_sql);
        $guncelle_stmt->execute([
            $tedarikci_id, 
            $proje_id, 
            $sorumlu_id, 
            $durum_id, 
            $parca_no, 
            $parca_adi, 
            $miktar, 
            $birim, 
            $teslim_tarihi, 
            $aciklama, 
            $siparis_id
        ]);
        
        // Başarılı ise siparişin detay sayfasına yönlendir
        $mesaj = "Sipariş başarıyla güncellendi.";
        header("Location: siparis_detay.php?id={$siparis_id}&mesaj=" . urlencode($mesaj));
        exit;
        
    } catch (PDOException $e) {
        $hata = "Sipariş güncellenirken bir hata oluştu: " . $e->getMessage();
    }
}

// Sayfa başlığını düzenle
$page_title = "Sipariş Düzenle: " . $siparis['siparis_no'];

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Sipariş Düzenle: <?= guvenli($siparis['siparis_no']) ?></h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="siparis_detay.php?id=<?= $siparis_id ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Sipariş Detayına Dön
            </a>
        </div>
    </div>

    <?php if (isset($hata)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= guvenli($hata) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Sipariş Bilgilerini Düzenle</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="siparis_no" class="form-label">Sipariş No</label>
                                <input type="text" class="form-control" id="siparis_no" value="<?= guvenli($siparis['siparis_no']) ?>" readonly>
                                <div class="form-text text-muted">Sipariş numarası değiştirilemez.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="durum_id" class="form-label">Sipariş Durumu</label>
                                <select class="form-select" id="durum_id" name="durum_id" required>
                                    <?php foreach ($durumlar as $durum): ?>
                                        <option value="<?= $durum['id'] ?>" <?= ($siparis['durum_id'] == $durum['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($durum['durum_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tedarikci_id" class="form-label">Tedarikçi</label>
                                <select class="form-select" id="tedarikci_id" name="tedarikci_id" required>
                                    <option value="">Tedarikçi Seçin</option>
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <option value="<?= $tedarikci['id'] ?>" <?= ($siparis['tedarikci_id'] == $tedarikci['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="proje_id" class="form-label">Proje</label>
                                <select class="form-select" id="proje_id" name="proje_id" required>
                                    <option value="">Proje Seçin</option>
                                    <?php foreach ($projeler as $proje): ?>
                                        <option value="<?= $proje['id'] ?>" <?= ($siparis['proje_id'] == $proje['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($proje['proje_adi']) ?> (<?= guvenli($proje['proje_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sorumlu_id" class="form-label">Sorumlu</label>
                                <select class="form-select" id="sorumlu_id" name="sorumlu_id" required>
                                    <option value="">Sorumlu Seçin</option>
                                    <?php foreach ($sorumlular as $sorumlu): ?>
                                        <option value="<?= $sorumlu['id'] ?>" <?= ($siparis['sorumlu_id'] == $sorumlu['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($sorumlu['ad_soyad']) ?> (<?= guvenli($sorumlu['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="teslim_tarihi" class="form-label">Teslim Tarihi</label>
                                <input type="date" class="form-control" id="teslim_tarihi" name="teslim_tarihi" 
                                       value="<?= !empty($siparis['teslim_tarihi']) ? date('Y-m-d', strtotime($siparis['teslim_tarihi'])) : '' ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parca_no" class="form-label">Parça No</label>
                                <input type="text" class="form-control" id="parca_no" name="parca_no" 
                                       value="<?= guvenli($siparis['parca_no']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="parca_adi" class="form-label">Parça Adı</label>
                                <input type="text" class="form-control" id="parca_adi" name="parca_adi" 
                                       value="<?= guvenli($siparis['parca_adi']) ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="miktar" class="form-label">Miktar</label>
                                <input type="number" step="0.01" class="form-control" id="miktar" name="miktar" 
                                       value="<?= guvenli($siparis['miktar']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="birim" class="form-label">Birim</label>
                                <select class="form-select" id="birim" name="birim" required>
                                    <option value="Adet" <?= ($siparis['birim'] == 'Adet') ? 'selected' : '' ?>>Adet</option>
                                    <option value="Kg" <?= ($siparis['birim'] == 'Kg') ? 'selected' : '' ?>>Kg</option>
                                    <option value="Metre" <?= ($siparis['birim'] == 'Metre') ? 'selected' : '' ?>>Metre</option>
                                    <option value="Litre" <?= ($siparis['birim'] == 'Litre') ? 'selected' : '' ?>>Litre</option>
                                    <option value="Set" <?= ($siparis['birim'] == 'Set') ? 'selected' : '' ?>>Set</option>
                                    <option value="Takım" <?= ($siparis['birim'] == 'Takım') ? 'selected' : '' ?>>Takım</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="5"><?= guvenli($siparis['aciklama']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-12 text-end">
                                <a href="siparis_detay.php?id=<?= $siparis_id ?>" class="btn btn-secondary me-2">İptal</a>
                                <button type="submit" name="siparis_duzenle" class="btn btn-primary">Siparişi Güncelle</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Sipariş Bilgileri</h5>
                </div>
                <div class="card-body">
                    <div class="siparis-ozet">
                        <p><strong>Sipariş No:</strong> <?= guvenli($siparis['siparis_no']) ?></p>
                        <p><strong>Oluşturma:</strong> <?= date('d.m.Y H:i', strtotime($siparis['olusturma_tarihi'])) ?></p>
                        <?php if (!empty($siparis['guncelleme_tarihi'])): ?>
                            <p><strong>Son Güncelleme:</strong> <?= date('d.m.Y H:i', strtotime($siparis['guncelleme_tarihi'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">İşlemler</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="siparis_detay.php?id=<?= $siparis_id ?>" class="btn btn-outline-primary">
                            <i class="bi bi-eye"></i> Sipariş Detayını Görüntüle
                        </a>
                        <a href="siparisler.php" class="btn btn-outline-secondary">
                            <i class="bi bi-list"></i> Siparişler Listesine Dön
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 