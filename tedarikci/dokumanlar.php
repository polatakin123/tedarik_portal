<?php
// tedarikci/dokumanlar.php - Tedarikçi dokümanları sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Dokümanlar";

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

// Filtre değerlerini al
$filtre_proje = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;
$filtre_tip = isset($_GET['dokuman_tipi']) ? $_GET['dokuman_tipi'] : '';
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// Tedarikçiye ait siparişlerin dokümanlarını getir
$dokuman_sql = "SELECT sd.*, sd.dokuman_adi AS dosya_adi, s.siparis_no, s.parca_no, p.proje_adi, 
                u.ad_soyad AS yukleyen_adi
                FROM siparis_dokumanlari sd 
                INNER JOIN siparisler s ON sd.siparis_id = s.id
                LEFT JOIN projeler p ON s.proje_id = p.id
                LEFT JOIN kullanicilar u ON sd.yukleyen_id = u.id
                WHERE s.tedarikci_id = ?";
$params = [$tedarikci_id];

// Filtreleri ekle
if ($filtre_proje > 0) {
    $dokuman_sql .= " AND s.proje_id = ?";
    $params[] = $filtre_proje;
}

if (!empty($filtre_tip)) {
    $dokuman_sql .= " AND sd.dosya_turu = ?";
    $params[] = $filtre_tip;
}

if (!empty($arama)) {
    $dokuman_sql .= " AND (sd.dokuman_adi LIKE ? OR s.siparis_no LIKE ? OR s.parca_no LIKE ?)";
    $arama_param = "%" . $arama . "%";
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$dokuman_sql .= " ORDER BY sd.yukleme_tarihi DESC";

$dokuman_stmt = $db->prepare($dokuman_sql);
$dokuman_stmt->execute($params);
$dokumanlar = $dokuman_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri al (filtreleme için)
$proje_sql = "SELECT DISTINCT p.* FROM projeler p 
              INNER JOIN siparisler s ON p.id = s.proje_id 
              WHERE s.tedarikci_id = ? 
              ORDER BY p.proje_adi";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$tedarikci_id]);
$projeler = $proje_stmt->fetchAll(PDO::FETCH_ASSOC);

// Doküman tiplerini al (filtreleme için)
$tip_sql = "SELECT DISTINCT sd.dosya_turu FROM siparis_dokumanlari sd
            INNER JOIN siparisler s ON sd.siparis_id = s.id
            WHERE s.tedarikci_id = ? AND sd.dosya_turu IS NOT NULL AND sd.dosya_turu != ''
            ORDER BY sd.dosya_turu";
$tip_stmt = $db->prepare($tip_sql);
$tip_stmt->execute([$tedarikci_id]);
$dokuman_tipleri = $tip_stmt->fetchAll(PDO::FETCH_COLUMN);

// Header dosyasını dahil et
include 'header.php';
?>

<h2 class="mb-4">Dokümanlar</h2>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Doküman Filtrele</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="proje_id" class="form-label">Proje</label>
                <select class="form-select" id="proje_id" name="proje_id">
                    <option value="0">Tümü</option>
                    <?php foreach ($projeler as $proje): ?>
                    <option value="<?= $proje['id'] ?>" <?= ($filtre_proje == $proje['id']) ? 'selected' : '' ?>><?= guvenli($proje['proje_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="dokuman_tipi" class="form-label">Doküman Tipi</label>
                <select class="form-select" id="dokuman_tipi" name="dokuman_tipi">
                    <option value="">Tümü</option>
                    <?php foreach ($dokuman_tipleri as $tip): ?>
                    <option value="<?= guvenli($tip) ?>" <?= ($filtre_tip == $tip) ? 'selected' : '' ?>><?= guvenli($tip) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="arama" class="form-label">Arama</label>
                <input type="text" class="form-control" id="arama" name="arama" value="<?= guvenli($arama) ?>" placeholder="Doküman adı, sipariş no veya parça no...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div>
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Ara</button>
                    <a href="dokumanlar.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Temizle</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Doküman Listesi -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Doküman Listesi</h5>
        <span class="badge bg-primary"><?= count($dokumanlar) ?> Doküman</span>
    </div>
    <div class="card-body">
        <?php if (count($dokumanlar) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Doküman Adı</th>
                        <th>Dosya Türü</th>
                        <th>Sipariş No</th>
                        <th>Proje</th>
                        <th>Yüklenme Tarihi</th>
                        <th>Yükleyen</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dokumanlar as $dokuman): ?>
                    <tr>
                        <td><?= guvenli($dokuman['dokuman_adi']) ?></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= guvenli($dokuman['dosya_turu'] ?? 'Bilinmiyor') ?>
                            </span>
                        </td>
                        <td><?= guvenli($dokuman['siparis_no']) ?></td>
                        <td><?= guvenli($dokuman['proje_adi']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></td>
                        <td><?= guvenli($dokuman['yukleyen_adi']) ?></td>
                        <td>
                            <?php if (isset($dokuman['dosya_adi']) && !empty($dokuman['dosya_adi'])): ?>
                                <a href="../dosyalar/<?= guvenli($dokuman['dosya_adi']) ?>" class="btn btn-sm btn-primary" target="_blank">İndir</a>
                            <?php else: ?>
                                <span class="badge bg-warning">Dosya Yok</span>
                            <?php endif; ?>
                            <?php if ($dokuman['yukleyen_id'] == $kullanici_id): ?>
                            <a href="dokuman_sil.php?id=<?= $dokuman['id'] ?>" class="btn btn-sm btn-danger" title="Sil" onclick="return confirm('Bu dokümanı silmek istediğinize emin misiniz?');">
                                <i class="bi bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> Doküman bulunamadı.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($_SESSION['dokuman_yukleme_izni']) && $_SESSION['dokuman_yukleme_izni']): ?>
<!-- Doküman Yükleme -->
<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Doküman Yükle</h5>
    </div>
    <div class="card-body">
        <form action="dokuman_yukle.php" method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-4">
                <label for="siparis_id" class="form-label">Sipariş</label>
                <select class="form-select" id="siparis_id" name="siparis_id" required>
                    <option value="">-- Sipariş Seçiniz --</option>
                    <?php
                    $siparisler_sql = "SELECT s.id, s.siparis_no, s.parca_no FROM siparisler s WHERE s.tedarikci_id = ? ORDER BY s.tarih DESC";
                    $siparisler_stmt = $db->prepare($siparisler_sql);
                    $siparisler_stmt->execute([$tedarikci_id]);
                    $siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($siparisler as $siparis):
                    ?>
                    <option value="<?= $siparis['id'] ?>"><?= guvenli($siparis['siparis_no']) ?> (<?= guvenli($siparis['parca_no']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="dokuman_adi" class="form-label">Doküman Adı</label>
                <input type="text" class="form-control" id="dokuman_adi" name="dokuman_adi" required>
            </div>
            <div class="col-md-4">
                <label for="dosya_turu" class="form-label">Dosya Türü</label>
                <select class="form-select" id="dosya_turu" name="dosya_turu" required>
                    <option value="">-- Seçiniz --</option>
                    <option value="Teknik Çizim">Teknik Çizim</option>
                    <option value="Kalite Raporu">Kalite Raporu</option>
                    <option value="Test Sonucu">Test Sonucu</option>
                    <option value="Sertifika">Sertifika</option>
                    <option value="Fatura">Fatura</option>
                    <option value="Diğer">Diğer</option>
                </select>
            </div>
            <div class="col-md-12">
                <label for="aciklama" class="form-label">Açıklama</label>
                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"></textarea>
            </div>
            <div class="col-md-12">
                <label for="dosya" class="form-label">Dosya Seçin</label>
                <input type="file" class="form-control" id="dosya" name="dosya" required>
                <div class="form-text">İzin verilen dosya formatları: PDF, DOC, DOCX, XLS, XLSX, PNG, JPG, JPEG, DWG, DXF (Max. 10MB)</div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> Doküman Yükle</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
// Footer dosyasını dahil et
include 'footer.php';
?> 