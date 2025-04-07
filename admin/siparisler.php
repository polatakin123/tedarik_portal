<?php
// admin/siparisler.php - Admin paneli sipariş yönetimi sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Siparişler";

// Filtreleme parametreleri
$durum_id = isset($_GET['durum_id']) ? intval($_GET['durum_id']) : null;
$tedarikci_id = isset($_GET['tedarikci_id']) ? intval($_GET['tedarikci_id']) : null;
$proje_id = isset($_GET['proje_id']) ? intval($_GET['proje_id']) : null;
$sorumlu_id = isset($_GET['sorumlu_id']) ? intval($_GET['sorumlu_id']) : null;
$arama = isset($_GET['arama']) ? trim($_GET['arama']) : '';

// Sipariş silme işlemi
if (isset($_GET['sil']) && !empty($_GET['sil'])) {
    $siparis_id = intval($_GET['sil']);
    try {
        // Sipariş ID'si geçerliliğini kontrol et
        $kontrol_sql = "SELECT id FROM siparisler WHERE id = ?";
        $kontrol_stmt = $db->prepare($kontrol_sql);
        $kontrol_stmt->execute([$siparis_id]);
        
        if ($kontrol_stmt->rowCount() > 0) {
            // Önce sipariş güncellemelerini sil
            $guncelleme_sql = "DELETE FROM siparis_guncellemeleri WHERE siparis_id = ?";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([$siparis_id]);
            
            // Sonra sipariş dokümanlarını sil
            $dokuman_sql = "DELETE FROM siparis_dokumanlari WHERE siparis_id = ?";
            $dokuman_stmt = $db->prepare($dokuman_sql);
            $dokuman_stmt->execute([$siparis_id]);
            
            // Son olarak siparişi sil
            $siparis_sql = "DELETE FROM siparisler WHERE id = ?";
            $siparis_stmt = $db->prepare($siparis_sql);
            $siparis_stmt->execute([$siparis_id]);
            
            $mesaj = "Sipariş başarıyla silindi.";
            header("Location: siparisler.php?mesaj=" . urlencode($mesaj));
            exit;
        } else {
            $hata = "Sipariş bulunamadı.";
            header("Location: siparisler.php?hata=" . urlencode($hata));
            exit;
        }
    } catch (PDOException $e) {
        $hata = "Sipariş silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: siparisler.php?hata=" . urlencode($hata));
        exit;
    }
}

// Filtreleme ölçütlerine göre sipariş sorgusunu oluştur
$sql_params = [];
$sql = "SELECT s.*, sd.durum_adi, t.firma_adi as tedarikci_adi, p.proje_adi, 
       k.ad_soyad as sorumlu_adi
       FROM siparisler s
       LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
       LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
       LEFT JOIN projeler p ON s.proje_id = p.id
       LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
       WHERE 1=1";

if ($durum_id) {
    $sql .= " AND s.durum_id = ?";
    $sql_params[] = $durum_id;
}

if ($tedarikci_id) {
    $sql .= " AND s.tedarikci_id = ?";
    $sql_params[] = $tedarikci_id;
}

if ($proje_id) {
    $sql .= " AND s.proje_id = ?";
    $sql_params[] = $proje_id;
}

if ($sorumlu_id) {
    $sql .= " AND s.sorumlu_id = ?";
    $sql_params[] = $sorumlu_id;
}

if ($arama) {
    $sql .= " AND (s.siparis_no LIKE ? OR s.parca_no LIKE ? OR s.tanim LIKE ? OR t.firma_adi LIKE ? OR p.proje_adi LIKE ?)";
    $arama_param = "%$arama%";
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
}

$sql .= " ORDER BY s.olusturma_tarihi DESC";

// Sorguyu çalıştır
$stmt = $db->prepare($sql);
$stmt->execute($sql_params);
$siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtreleme için sipariş durumlarını al
$durum_sql = "SELECT * FROM siparis_durumlari ORDER BY durum_adi";
$durum_stmt = $db->prepare($durum_sql);
$durum_stmt->execute();
$durumlar = $durum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçileri al
$tedarikci_sql = "SELECT * FROM tedarikciler ORDER BY firma_adi";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute();
$tedarikciler = $tedarikci_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri al
$proje_sql = "SELECT * FROM projeler ORDER BY proje_adi";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute();
$projeler = $proje_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sorumluları al
$sorumlu_sql = "SELECT * FROM kullanicilar WHERE rol = 'Sorumlu' ORDER BY ad_soyad";
$sorumlu_stmt = $db->prepare($sorumlu_sql);
$sorumlu_stmt->execute();
$sorumlular = $sorumlu_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4">Sipariş Yönetimi</h2>

    <?php if (isset($_GET['mesaj'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= guvenli($_GET['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['hata'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= guvenli($_GET['hata']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
    <?php endif; ?>

    <!-- Filtreleme -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Sipariş Filtrele</h5>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-2">
                    <label for="durum_id" class="form-label">Durum</label>
                    <select class="form-select" id="durum_id" name="durum_id">
                        <option value="">Tümü</option>
                        <?php foreach ($durumlar as $durum): ?>
                        <option value="<?= $durum['id'] ?>" <?= ($durum_id == $durum['id']) ? 'selected' : '' ?>><?= guvenli($durum['durum_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="tedarikci_id" class="form-label">Tedarikçi</label>
                    <select class="form-select" id="tedarikci_id" name="tedarikci_id">
                        <option value="">Tümü</option>
                        <?php foreach ($tedarikciler as $tedarikci): ?>
                        <option value="<?= $tedarikci['id'] ?>" <?= ($tedarikci_id == $tedarikci['id']) ? 'selected' : '' ?>><?= guvenli($tedarikci['firma_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="proje_id" class="form-label">Proje</label>
                    <select class="form-select" id="proje_id" name="proje_id">
                        <option value="">Tümü</option>
                        <?php foreach ($projeler as $proje): ?>
                        <option value="<?= $proje['id'] ?>" <?= ($proje_id == $proje['id']) ? 'selected' : '' ?>><?= guvenli($proje['proje_adi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="sorumlu_id" class="form-label">Sorumlu</label>
                    <select class="form-select" id="sorumlu_id" name="sorumlu_id">
                        <option value="">Tümü</option>
                        <?php foreach ($sorumlular as $sorumlu): ?>
                        <option value="<?= $sorumlu['id'] ?>" <?= ($sorumlu_id == $sorumlu['id']) ? 'selected' : '' ?>><?= guvenli($sorumlu['ad_soyad']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="arama" class="form-label">Arama</label>
                    <input type="text" class="form-control" id="arama" name="arama" value="<?= guvenli($arama) ?>" placeholder="Sipariş no, parça no, tanım...">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <div>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search"></i> Ara</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Sipariş Listesi -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Sipariş Listesi</h5>
            <div>
                <a href="siparis_ekle.php" class="btn btn-success"><i class="bi bi-plus"></i> Yeni Sipariş</a>
                <span class="badge bg-primary ms-2"><?= count($siparisler) ?> Sipariş</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>Sipariş No</th>
                            <th>Proje</th>
                            <th>Tedarikçi</th>
                            <th>Parça No</th>
                            <th>Tanım</th>
                            <th>Miktar</th>
                            <th>FAI</th>
                            <th>Satınalmacı</th>
                            <th>Alt Malzeme</th>
                            <th>Onaylanan Revizyon</th>
                            <th>Tedarikçi Parça No</th>
                            <th>Vehicle ID</th>
                            <th>Tarih</th>
                            <th>Teslim Tarihi</th>
                            <th>Durum</th>
                            <th>Sorumlu</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($siparisler) > 0): ?>
                            <?php foreach ($siparisler as $siparis): ?>
                            <tr>
                                <td><?= guvenli($siparis['siparis_no']) ?></td>
                                <td><?= guvenli($siparis['proje_adi']) ?></td>
                                <td><?= guvenli($siparis['tedarikci_adi']) ?></td>
                                <td><?= guvenli($siparis['parca_no']) ?></td>
                                <td><?= guvenli($siparis['tanim']) ?></td>
                                <td><?= guvenli($siparis['miktar']) ?></td>
                                <td><?= !empty($siparis['fai']) ? guvenli($siparis['fai']) : '-' ?></td>
                                <td><?= !empty($siparis['satinalmaci']) ? guvenli($siparis['satinalmaci']) : '-' ?></td>
                                <td><?= !empty($siparis['alt_malzeme']) ? guvenli($siparis['alt_malzeme']) : '-' ?></td>
                                <td><?= !empty($siparis['onaylanan_revizyon']) ? guvenli($siparis['onaylanan_revizyon']) : '-' ?></td>
                                <td><?= !empty($siparis['tedarikci_parca_no']) ? guvenli($siparis['tedarikci_parca_no']) : '-' ?></td>
                                <td><?= !empty($siparis['vehicle_id']) ? guvenli($siparis['vehicle_id']) : '-' ?></td>
                                <td><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></td>
                                <td><?= $siparis['teslim_tarihi'] ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                        <?= guvenli($siparis['durum_adi']) ?>
                                    </span>
                                </td>
                                <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info" title="Detay"><i class="bi bi-eye"></i></a>
                                        <a href="siparis_duzenle.php?id=<?= $siparis['id'] ?>" class="btn btn-warning" title="Düzenle"><i class="bi bi-pencil"></i></a>
                                        <a href="javascript:void(0)" onclick="siparisSil(<?= $siparis['id'] ?>)" class="btn btn-danger" title="Sil"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-4">Sipariş bulunamadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript ek kodları
$extra_js = "
    function siparisSil(id) {
        if (confirm('Bu siparişi silmek istediğinizden emin misiniz?')) {
            window.location.href = 'siparisler.php?sil=' + id;
        }
    }
";

// Sipariş durumuna göre renk döndüren yardımcı fonksiyon
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