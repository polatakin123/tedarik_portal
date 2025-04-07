<?php
// sorumlu/siparisler.php - Sorumlu paneli siparişler sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Siparişler";

// Kullanıcının sorumlu olduğu tedarikçileri al
$sorumlu_id = $_SESSION['kullanici_id'];
$tedarikciler_sql = "SELECT t.id, t.firma_adi, t.firma_kodu
                    FROM tedarikciler t
                    INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                    WHERE s.sorumlu_id = ?
                    ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçilerin id'lerini al
$tedarikci_idleri = array_column($tedarikciler, 'id');
$in = '';
$params = [];

if (count($tedarikci_idleri) > 0) {
    $in = str_repeat('?,', count($tedarikci_idleri) - 1) . '?';
    $params = array_merge($tedarikci_idleri, [$sorumlu_id]);
} else {
    // Tedarikçi yoksa sadece sorumlu_id ile sorgula
    $in = '?';
    $params = [$sorumlu_id];
}

// Filtre parametreleri
$durum_id = isset($_GET['durum']) ? intval($_GET['durum']) : 0;
$tedarikci_id = isset($_GET['tedarikci_id']) ? intval($_GET['tedarikci_id']) : 0;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : 0;
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// SQL sorgusu için koşullar
$where_conditions = [];
$where_params = [];

// Temel koşul - kullanıcının sorumlu olduğu tedarikçilerle ilgili siparişler
if (count($tedarikci_idleri) > 0) {
    $where_conditions[] = "(s.tedarikci_id IN ($in) OR s.sorumlu_id = ?)";
    $where_params = $params;
} else {
    $where_conditions[] = "s.sorumlu_id = ?";
    $where_params = [$sorumlu_id];
}

// Durum filtresi
if ($durum_id > 0) {
    $where_conditions[] = "s.durum_id = ?";
    $where_params[] = $durum_id;
}

// Tedarikçi filtresi
if ($tedarikci_id > 0) {
    $where_conditions[] = "s.tedarikci_id = ?";
    $where_params[] = $tedarikci_id;
}

// Proje filtresi
if ($proje_id > 0) {
    $where_conditions[] = "s.proje_id = ?";
    $where_params[] = $proje_id;
}

// Arama filtresi
if (!empty($arama)) {
    $where_conditions[] = "(s.siparis_no LIKE ? OR s.parca_no LIKE ? OR s.tanim LIKE ? OR t.firma_adi LIKE ? OR p.proje_adi LIKE ? OR s.tedarikci_parca_no LIKE ? OR s.vehicle_id LIKE ?)";
    $arama_param = "%$arama%";
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
    $where_params[] = $arama_param;
}

// Koşulları birleştir
$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Projeleri al
if (count($tedarikci_idleri) > 0) {
    $projeler_sql = "SELECT DISTINCT p.id, p.proje_adi
                    FROM projeler p
                    INNER JOIN siparisler s ON p.id = s.proje_id
                    WHERE s.tedarikci_id IN ($in) OR s.sorumlu_id = ?
                    ORDER BY p.proje_adi";
    $projeler_stmt = $db->prepare($projeler_sql);
    $projeler_stmt->execute($params);
} else {
    $projeler_sql = "SELECT DISTINCT p.id, p.proje_adi
                    FROM projeler p
                    INNER JOIN siparisler s ON p.id = s.proje_id
                    WHERE s.sorumlu_id = ?
                    ORDER BY p.proje_adi";
    $projeler_stmt = $db->prepare($projeler_sql);
    $projeler_stmt->execute([$sorumlu_id]);
}
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını al
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Siparişleri al
$siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, t.firma_adi, 
                          (SELECT COUNT(*) FROM siparis_dokumanlari WHERE siparis_id = s.id) as dokuman_sayisi
                   FROM siparisler s
                   LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                   LEFT JOIN projeler p ON s.proje_id = p.id
                   LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                   $where_clause
                   ORDER BY s.olusturma_tarihi DESC";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute($where_params);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Siparişler</h2>
    <a href="yeni_siparis.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Yeni Sipariş</a>
</div>

<!-- Filtreleme -->
<div class="card mb-4">
    <div class="card-body">
        <form action="" method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="durum" class="form-label">Durum</label>
                <select name="durum" id="durum" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($durumlar as $durum): ?>
                        <option value="<?= $durum['id'] ?>" <?= $durum_id == $durum['id'] ? 'selected' : '' ?>><?= guvenli($durum['durum_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="tedarikci_id" class="form-label">Tedarikçi</label>
                <select name="tedarikci_id" id="tedarikci_id" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($tedarikciler as $tedarikci): ?>
                        <option value="<?= $tedarikci['id'] ?>" <?= $tedarikci_id == $tedarikci['id'] ? 'selected' : '' ?>><?= guvenli($tedarikci['firma_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="proje_id" class="form-label">Proje</label>
                <select name="proje_id" id="proje_id" class="form-select">
                    <option value="">Tümü</option>
                    <?php foreach ($projeler as $proje): ?>
                        <option value="<?= $proje['id'] ?>" <?= $proje_id == $proje['id'] ? 'selected' : '' ?>><?= guvenli($proje['proje_adi']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label for="arama" class="form-label">Arama</label>
                <input type="text" class="form-control" id="arama" name="arama" placeholder="Sipariş no, parça no, tedarikçi parça no..." value="<?= guvenli($arama) ?>">
            </div>

            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            </div>

            <?php if ($durum_id > 0 || $tedarikci_id > 0 || $proje_id > 0 || !empty($arama)): ?>
            <div class="col-12 mt-2">
                <a href="siparisler.php" class="btn btn-sm btn-outline-secondary">Filtreleri Temizle</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Siparişler Tablosu -->
<div class="card mb-4">
    <div class="card-body">
        <?php if (count($siparisler) > 0): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Sipariş No</th>
                        <th>Tedarikçi</th>
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
                        <th>Dokümanlar</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
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
                        <td><?= guvenli($siparis['firma_adi']) ?></td>
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
                                <a href="siparis_guncelle.php?id=<?= $siparis['id'] ?>" class="btn btn-warning" title="Güncelle">
                                    <i class="bi bi-pencil"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i> Filtrelere uygun sipariş bulunamadı.
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-end">
        <span>Toplam <?= count($siparisler) ?> sipariş</span>
    </div>
</div>

<?php
// Durum renk kodu fonksiyonu
function getDurumRenk($durum_id) {
    switch ($durum_id) {
        case 1: return 'warning'; // Beklemede
        case 2: return 'success'; // Tamamlandı
        case 3: return 'danger';  // İptal Edildi
        case 4: return 'info';    // İşlemde
        default: return 'secondary';
    }
}

// Footer dosyasını dahil et
include 'footer.php';
?> 