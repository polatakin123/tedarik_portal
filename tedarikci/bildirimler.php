<?php
// tedarikci/bildirimler.php - Tedarikçi bildirimleri sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Bildirimler";

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

$tedarikci_id = $tedarikci['id'];

// Filtreleme parametreleri
$durum = isset($_GET['durum']) ? $_GET['durum'] : 'tumu';
$tarih = isset($_GET['tarih']) ? $_GET['tarih'] : '';
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// SQL sorgusu için koşullar - tüm bildirimleri gösteriyoruz
$where_conditions = ["1=1"];  // Her zaman true olan koşul, tüm bildirimler gelecek
$where_params = [];

// Durum filtresi
if ($durum == 'okunmamis') {
    $where_conditions[] = "b.okundu = 0";
} elseif ($durum == 'okunmus') {
    $where_conditions[] = "b.okundu = 1";
}

// Tarih filtresi
if (!empty($tarih)) {
    $where_conditions[] = "DATE(b.bildirim_tarihi) = ?";
    $where_params[] = $tarih;
}

// Arama filtresi
if (!empty($arama)) {
    $where_conditions[] = "(b.mesaj LIKE ? OR b.mesaj LIKE ?)";
    $arama_param = "%$arama%";
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
}

// Koşulları birleştir
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Bildirimleri al
$bildirimler_sql = "SELECT b.*, k.ad_soyad as gonderen_adi
                    FROM bildirimler b
                    LEFT JOIN kullanicilar k ON b.gonderen_id = k.id
                    $where_clause
                    ORDER BY b.bildirim_tarihi DESC";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute($where_params);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirimleri işaretle
if (isset($_GET['isaretle_hepsi']) && $_GET['isaretle_hepsi'] == 1) {
    $update_sql = "UPDATE bildirimler 
                  SET okundu = 1 
                  WHERE kullanici_id = ?
                  AND okundu = 0";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute([$kullanici_id]);
    
    // Kullanıcıyı yönlendir
    header("Location: bildirimler.php?durum=tumu");
    exit;
}

// Tek bildirimi okundu olarak işaretle
if (isset($_GET['isaretle']) && is_numeric($_GET['isaretle'])) {
    $bildirim_id = intval($_GET['isaretle']);
    $update_sql = "UPDATE bildirimler SET okundu = 1 WHERE id = ? AND kullanici_id = ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute([$bildirim_id, $kullanici_id]);
    
    // Kullanıcıyı yönlendir
    header("Location: bildirimler.php?durum=$durum" . (!empty($tarih) ? "&tarih=$tarih" : "") . (!empty($arama) ? "&arama=$arama" : ""));
    exit;
}

// Okunmamış bildirim sayısını al
$okunmamis_sql = "SELECT COUNT(*) as sayi FROM bildirimler 
                  WHERE kullanici_id = ?
                  AND okundu = 0";
$okunmamis_stmt = $db->prepare($okunmamis_sql);
$okunmamis_stmt->execute([$kullanici_id]);
$okunmamis = $okunmamis_stmt->fetch(PDO::FETCH_ASSOC);
$okunmamis_sayi = $okunmamis['sayi'];

// Header dosyasını dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Bildirimler</h2>
    <?php if ($okunmamis_sayi > 0): ?>
    <a href="bildirimler.php?isaretle_hepsi=1" class="btn btn-outline-primary">
        <i class="bi bi-check-all"></i> Tümünü Okundu İşaretle
    </a>
    <?php endif; ?>
</div>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Bildirimleri Filtrele</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="durum" class="form-label">Durum</label>
                <select class="form-select" id="durum" name="durum">
                    <option value="tumu" <?= ($durum == 'tumu') ? 'selected' : '' ?>>Tümü</option>
                    <option value="okunmamis" <?= ($durum == 'okunmamis') ? 'selected' : '' ?>>Okunmamış</option>
                    <option value="okunmus" <?= ($durum == 'okunmus') ? 'selected' : '' ?>>Okunmuş</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="tarih" class="form-label">Tarih</label>
                <input type="date" class="form-control" id="tarih" name="tarih" value="<?= guvenli($tarih) ?>">
            </div>
            <div class="col-md-4">
                <label for="arama" class="form-label">Arama</label>
                <input type="text" class="form-control" id="arama" name="arama" value="<?= guvenli($arama) ?>" placeholder="Bildirim başlığı veya içeriği...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div>
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Ara</button>
                    <a href="bildirimler.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Temizle</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Bildirim Listesi -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Bildirim Listesi</h5>
        <div>
            <span class="badge bg-primary"><?= count($bildirimler) ?> Bildirim</span>
            <?php if ($okunmamis_sayi > 0): ?>
            <span class="badge bg-danger ms-2"><?= $okunmamis_sayi ?> Okunmamış</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($bildirimler) > 0): ?>
            <div class="list-group">
                <?php foreach ($bildirimler as $bildirim): ?>
                <div class="list-group-item list-group-item-action <?= $bildirim['okundu'] ? '' : 'list-group-item-light' ?>">
                    <div class="d-flex w-100 justify-content-between align-items-center">
                        <h5 class="mb-1 <?= $bildirim['okundu'] ? '' : 'fw-bold' ?>">
                            <?php if (!$bildirim['okundu']): ?>
                            <span class="badge bg-danger me-2">Yeni</span>
                            <?php endif; ?>
                            <?= guvenli($bildirim['baslik'] ?? $bildirim['mesaj']) ?>
                        </h5>
                        <small class="text-muted"><?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?></small>
                    </div>
                    <p class="mb-1 mt-2"><?= nl2br(guvenli($bildirim['mesaj'])) ?></p>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted">
                            <?php if ($bildirim['gonderen_id']): ?>
                            <i class="bi bi-person"></i> <?= guvenli($bildirim['gonderen_adi']) ?>
                            <?php else: ?>
                            <i class="bi bi-robot"></i> Sistem
                            <?php endif; ?>
                        </small>
                        <div>
                            <?php if ($bildirim['ilgili_siparis_id']): ?>
                            <a href="siparis_detay.php?id=<?= $bildirim['ilgili_siparis_id'] ?>" class="btn btn-sm btn-info">
                                <i class="bi bi-box"></i> Siparişe Git
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($bildirim['ilgili_dokuman_id']): ?>
                            <a href="dokumanlar.php?dokuman_id=<?= $bildirim['ilgili_dokuman_id'] ?>" class="btn btn-sm btn-info">
                                <i class="bi bi-file-earmark"></i> Dokümana Git
                            </a>
                            <?php endif; ?>
                            
                            <?php if (!$bildirim['okundu']): ?>
                            <a href="bildirimler.php?isaretle=<?= $bildirim['id'] ?>&durum=<?= $durum ?><?= !empty($tarih) ? '&tarih='.$tarih : '' ?><?= !empty($arama) ? '&arama='.$arama : '' ?>" class="btn btn-sm btn-success">
                                <i class="bi bi-check"></i> Okundu İşaretle
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> Bildirim bulunamadı.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Footer dosyasını dahil et
include 'footer.php';
?> 