<?php
// tedarikci/siparislerim.php - Tedarikçi siparişleri sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Siparişlerim";

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
$durum_id = isset($_GET['durum']) ? intval($_GET['durum']) : 0;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// SQL sorgusu için koşullar
$where_conditions = ["s.tedarikci_id = ?"];
$where_params = [$tedarikci_id];

// Durum filtresi
if ($durum_id > 0) {
    $where_conditions[] = "s.durum_id = ?";
    $where_params[] = $durum_id;
}

// Proje filtresi
if ($proje_id > 0) {
    $where_conditions[] = "s.proje_id = ?";
    $where_params[] = $proje_id;
}

// Arama filtresi
if (!empty($arama)) {
    $where_conditions[] = "(s.siparis_no LIKE ? OR s.parca_no LIKE ? OR s.tanim LIKE ? OR p.proje_adi LIKE ? OR s.tedarikci_parca_no LIKE ? OR s.vehicle_id LIKE ?)";
    $arama_param = "%$arama%";
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
}

// Koşulları birleştir
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Projeleri al (filtreleme için)
$proje_sql = "SELECT DISTINCT p.* FROM projeler p 
              INNER JOIN siparisler s ON p.id = s.proje_id 
              WHERE s.tedarikci_id = ? 
              ORDER BY p.proje_adi";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$tedarikci_id]);
$projeler = $proje_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını al
$durum_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durum_stmt = $db->prepare($durum_sql);
$durum_stmt->execute();
$durumlar = $durum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Siparişleri al
$siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi,
                          (SELECT COUNT(*) FROM siparis_dokumanlari WHERE siparis_id = s.id) as dokuman_sayisi
                   FROM siparisler s
                   LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                   LEFT JOIN projeler p ON s.proje_id = p.id
                   LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                   $where_clause
                   ORDER BY s.olusturma_tarihi DESC";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute($where_params);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<h2 class="mb-4">Siparişlerim</h2>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">Sipariş Filtrele</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label for="durum" class="form-label">Sipariş Durumu</label>
                <select class="form-select" id="durum" name="durum">
                    <option value="0">Tümü</option>
                    <?php foreach ($durumlar as $durum): ?>
                    <option value="<?= $durum['id'] ?>" <?= ($durum_id == $durum['id']) ? 'selected' : '' ?>><?= guvenli($durum['durum_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="proje_id" class="form-label">Proje</label>
                <select class="form-select" id="proje_id" name="proje_id">
                    <option value="0">Tümü</option>
                    <?php foreach ($projeler as $proje): ?>
                    <option value="<?= $proje['id'] ?>" <?= ($proje_id == $proje['id']) ? 'selected' : '' ?>><?= guvenli($proje['proje_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="arama" class="form-label">Arama</label>
                <input type="text" class="form-control" id="arama" name="arama" value="<?= guvenli($arama) ?>" placeholder="Sipariş no, parça no, tanım, tedarikçi parça no...">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div>
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Ara</button>
                    <a href="siparislerim.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> Temizle</a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Sipariş Listesi -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Sipariş Listesi</h5>
        <span class="badge bg-primary"><?= count($siparisler) ?> Sipariş</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Sipariş No</th>
                        <th>Proje</th>
                        <th>Parça No</th>
                        <th>Tanım</th>
                        <th>Miktar</th>
                        <th>FAI</th>
                        <th>Satınalmacı</th>
                        <th>Alt Malzeme</th>
                        <th>Onaylanan Revizyon</th>
                        <th>Tedarikçi Parça No</th>
                        <th>Vehicle ID</th>
                        <th>Teslim Tarihi</th>
                        <th>Durum</th>
                        <th>Sorumlu</th>
                        <th>Dokümanlar</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($siparisler) > 0): ?>
                        <?php foreach ($siparisler as $siparis): 
                            $gecikme = false;
                            $teslim_tarihi = !empty($siparis['teslim_tarihi']) ? strtotime($siparis['teslim_tarihi']) : 0;
                            if ($teslim_tarihi && $teslim_tarihi < time() && $siparis['durum_id'] == 1) {
                                $gecikme = true;
                            }
                        ?>
                        <tr <?= $gecikme ? 'class="table-danger"' : '' ?>>
                            <td>
                                <a href="siparis_detay.php?id=<?= $siparis['id'] ?>">
                                    <?= guvenli($siparis['siparis_no']) ?>
                                </a>
                            </td>
                            <td><?= guvenli($siparis['proje_adi']) ?></td>
                            <td><?= guvenli($siparis['parca_no']) ?></td>
                            <td><?= guvenli($siparis['tanim']) ?></td>
                            <td><?= $siparis['miktar'] ?> <?= guvenli($siparis['birim']) ?></td>
                            <td><?= !empty($siparis['fai']) ? guvenli($siparis['fai']) : '-' ?></td>
                            <td><?= !empty($siparis['satinalmaci']) ? guvenli($siparis['satinalmaci']) : '-' ?></td>
                            <td><?= !empty($siparis['alt_malzeme']) ? guvenli($siparis['alt_malzeme']) : '-' ?></td>
                            <td><?= !empty($siparis['onaylanan_revizyon']) ? guvenli($siparis['onaylanan_revizyon']) : '-' ?></td>
                            <td><?= !empty($siparis['tedarikci_parca_no']) ? guvenli($siparis['tedarikci_parca_no']) : '-' ?></td>
                            <td><?= !empty($siparis['vehicle_id']) ? guvenli($siparis['vehicle_id']) : '-' ?></td>
                            <td><?= !empty($siparis['teslim_tarihi']) ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : '-' ?></td>
                            <td>
                                <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                    <?= guvenli($siparis['durum_adi']) ?>
                                </span>
                            </td>
                            <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
                            <td>
                                <?php if ($siparis['dokuman_sayisi'] > 0): ?>
                                <span class="badge bg-info">
                                    <?= $siparis['dokuman_sayisi'] ?> doküman
                                </span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Doküman yok</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info" title="Detay">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="16" class="text-center py-4">Sipariş bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
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