<?php
// tedarikci/bildirim_detay.php - Tedarikçi bildirim detay sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Bildirim Detayı";

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

// Bildirim ID'sini al
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    // Geçerli bir ID yoksa bildirimlere yönlendir
    header("Location: bildirimler.php");
    exit;
}

$bildirim_id = intval($_GET['id']);

// Bildirimi getir
$bildirim_sql = "SELECT b.*, k.ad_soyad as gonderen_adi
                FROM bildirimler b
                LEFT JOIN kullanicilar k ON b.gonderen_id = k.id
                WHERE b.id = ?";
$bildirim_stmt = $db->prepare($bildirim_sql);
$bildirim_stmt->execute([$bildirim_id]);
$bildirim = $bildirim_stmt->fetch(PDO::FETCH_ASSOC);

if (!$bildirim) {
    // Bildirim bulunamadı veya bu kullanıcıya ait değil
    header("Location: bildirimler.php");
    exit;
}

// İlgili sipariş bilgilerini getir
$siparis = null;
if (!empty($bildirim['ilgili_siparis_id'])) {
    $siparis_sql = "SELECT s.*, sd.durum_adi, p.proje_adi
                   FROM siparisler s
                   LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                   LEFT JOIN projeler p ON s.proje_id = p.id
                   WHERE s.id = ? AND s.tedarikci_id = ?";
    $siparis_stmt = $db->prepare($siparis_sql);
    $siparis_stmt->execute([$bildirim['ilgili_siparis_id'], $tedarikci_id]);
    $siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);
}

// İlgili doküman bilgilerini getir
$dokuman = null;
if (!empty($bildirim['ilgili_dokuman_id'])) {
    $dokuman_sql = "SELECT sd.*, s.siparis_no 
                    FROM siparis_dokumanlari sd
                    INNER JOIN siparisler s ON sd.siparis_id = s.id
                    WHERE sd.id = ? AND s.tedarikci_id = ?";
    $dokuman_stmt = $db->prepare($dokuman_sql);
    $dokuman_stmt->execute([$bildirim['ilgili_dokuman_id'], $tedarikci_id]);
    $dokuman = $dokuman_stmt->fetch(PDO::FETCH_ASSOC);
}

// Bildirimi okundu olarak işaretle
if ($bildirim['okundu'] == 0) {
    $update_sql = "UPDATE bildirimler SET okundu = 1 WHERE id = ?";
    $update_stmt = $db->prepare($update_sql);
    $update_stmt->execute([$bildirim_id]);
    $bildirim['okundu'] = 1;
}

// Geri dönüş URL'sini oluştur
$geri_url = 'bildirimler.php';
if (isset($_GET['ref'])) {
    $ref = $_GET['ref'];
    if ($ref == 'dash') {
        $geri_url = 'index.php';
    }
}

// Header dosyasını dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Bildirim Detayı</h2>
    <a href="<?= $geri_url ?>" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> Geri Dön
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Bildirim Detayı -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0"><?= guvenli($bildirim['mesaj']) ?></h5>
                <span class="badge <?= $bildirim['okundu'] ? 'bg-success' : 'bg-danger' ?>">
                    <?= $bildirim['okundu'] ? 'Okundu' : 'Okunmadı' ?>
                </span>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <p class="mb-1 text-muted"><strong>Tarih:</strong> <?= date('d.m.Y H:i', strtotime($bildirim['tarih'])) ?></p>
                    <p class="mb-1 text-muted">
                        <strong>Gönderen:</strong> 
                        <?php if ($bildirim['gonderen_id']): ?>
                            <?= guvenli($bildirim['gonderen_adi']) ?>
                        <?php else: ?>
                            Sistem Bildirimi
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="mb-3">
                    <h6 class="fw-bold">Bildirim İçeriği:</h6>
                    <div class="p-3 border rounded bg-light">
                        <?= nl2br(guvenli($bildirim['mesaj'])) ?>
                    </div>
                </div>
                
                <!-- İlgili Sipariş -->
                <?php if ($siparis): ?>
                <div class="mb-3">
                    <h6 class="fw-bold">İlgili Sipariş:</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="30%">Sipariş No</th>
                                <td><?= guvenli($siparis['siparis_no']) ?></td>
                            </tr>
                            <tr>
                                <th>Parça No</th>
                                <td><?= guvenli($siparis['parca_no']) ?></td>
                            </tr>
                            <tr>
                                <th>Proje</th>
                                <td><?= guvenli($siparis['proje_adi']) ?></td>
                            </tr>
                            <tr>
                                <th>Durum</th>
                                <td>
                                    <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                        <?= guvenli($siparis['durum_adi']) ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Teslim Tarihi</th>
                                <td>
                                    <?= !empty($siparis['teslim_tarihi']) ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : 'Belirtilmemiş' ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info btn-sm mt-2">
                        <i class="bi bi-box"></i> Sipariş Detayına Git
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- İlgili Doküman -->
                <?php if ($dokuman): ?>
                <div class="mb-3">
                    <h6 class="fw-bold">İlgili Doküman:</h6>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <tr>
                                <th width="30%">Doküman Adı</th>
                                <td><?= guvenli($dokuman['dokuman_adi']) ?></td>
                            </tr>
                            <tr>
                                <th>Sipariş No</th>
                                <td><?= guvenli($dokuman['siparis_no']) ?></td>
                            </tr>
                            <tr>
                                <th>Dosya Türü</th>
                                <td><?= guvenli($dokuman['dosya_turu']) ?></td>
                            </tr>
                            <tr>
                                <th>Yükleme Tarihi</th>
                                <td><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></td>
                            </tr>
                        </table>
                    </div>
                    
                    <div class="mt-2">
                        <a href="dokumanlar.php?dokuman_id=<?= $dokuman['id'] ?>" class="btn btn-info btn-sm">
                            <i class="bi bi-file-earmark"></i> Dokümana Git
                        </a>
                        <?php if (!empty($dokuman['dosya_adi'])): ?>
                        <a href="../dosyalar/<?= $dokuman['dosya_adi'] ?>" class="btn btn-success btn-sm" target="_blank">
                            <i class="bi bi-download"></i> İndir
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Son Bildirimler -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Son Bildirimler</h5>
            </div>
            <div class="card-body">
                <?php
                // Son 5 bildirimi getir
                $son_bildirimler_sql = "SELECT * FROM bildirimler 
                                      WHERE id != ? 
                                      ORDER BY tarih DESC LIMIT 5";
                $son_bildirimler_stmt = $db->prepare($son_bildirimler_sql);
                $son_bildirimler_stmt->execute([$bildirim_id]);
                $son_bildirimler = $son_bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($son_bildirimler) > 0):
                ?>
                <div class="list-group">
                    <?php foreach ($son_bildirimler as $b): ?>
                    <a href="bildirim_detay.php?id=<?= $b['id'] ?>" class="list-group-item list-group-item-action <?= $b['okundu'] ? '' : 'list-group-item-warning' ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1 text-truncate" style="max-width: 200px;">
                                <?php if (!$b['okundu']): ?>
                                <span class="badge bg-danger me-1">Yeni</span>
                                <?php endif; ?>
                                <?= guvenli($b['mesaj']) ?>
                            </h6>
                            <small><?= date('d.m.Y', strtotime($b['tarih'])) ?></small>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-center">
                    <a href="bildirimler.php" class="btn btn-sm btn-outline-primary">Tüm Bildirimleri Görüntüle</a>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i> Başka bildiriminiz bulunmuyor.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Durum rengini belirleyen yardımcı fonksiyon
function getDurumRenk($durum_id) {
    switch ($durum_id) {
        case 1: return 'warning'; // Açık
        case 2: return 'success'; // Kapalı/Tamamlanmış
        case 3: return 'info';    // Beklemede
        case 4: return 'danger';  // İptal
        default: return 'secondary';
    }
}

// Footer dosyasını dahil et
include 'footer.php';
?> 