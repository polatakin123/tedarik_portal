<?php
// sorumlu/index.php - Sorumlu paneli ana sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Ana Sayfa";

$sorumlu_id = $_SESSION['kullanici_id'];

// Sipariş özeti
$siparis_ozet_sql = "SELECT 
                     COUNT(CASE WHEN durum_id = 1 THEN 1 END) as acik_siparis,
                     COUNT(CASE WHEN durum_id = 2 THEN 1 END) as kapali_siparis,
                     COUNT(CASE WHEN durum_id = 3 THEN 1 END) as bekleyen_siparis,
                     COUNT(*) as toplam_siparis
                     FROM siparisler
                     WHERE sorumlu_id = ? OR tedarikci_id IN (
                         SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                     )";
$siparis_ozet_stmt = $db->prepare($siparis_ozet_sql);
$siparis_ozet_stmt->execute([$sorumlu_id, $sorumlu_id]);
$siparis_ozet = $siparis_ozet_stmt->fetch(PDO::FETCH_ASSOC);

// Son siparişler
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, t.firma_adi
                       FROM siparisler s
                       LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                       LEFT JOIN projeler p ON s.proje_id = p.id
                       LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                       WHERE s.sorumlu_id = ? OR s.tedarikci_id IN (
                           SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                       )
                       ORDER BY s.olusturma_tarihi DESC
                       LIMIT 5";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute([$sorumlu_id, $sorumlu_id]);
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi sayısı
$tedarikci_sayisi_sql = "SELECT COUNT(*) as sayi FROM sorumluluklar WHERE sorumlu_id = ?";
$tedarikci_sayisi_stmt = $db->prepare($tedarikci_sayisi_sql);
$tedarikci_sayisi_stmt->execute([$sorumlu_id]);
$tedarikci_sayisi = $tedarikci_sayisi_stmt->fetch(PDO::FETCH_ASSOC)['sayi'];

// Tedarikçi listesi
$tedarikciler_sql = "SELECT t.*, COUNT(s.id) as siparis_sayisi
                    FROM tedarikciler t
                    LEFT JOIN siparisler s ON t.id = s.tedarikci_id
                    INNER JOIN sorumluluklar sr ON t.id = sr.tedarikci_id
                    WHERE sr.sorumlu_id = ?
                    GROUP BY t.id
                    ORDER BY siparis_sayisi DESC
                    LIMIT 5";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Yaklaşan teslim tarihleri
$yaklasan_teslimatlar_sql = "SELECT s.*, t.firma_adi, p.proje_adi
                            FROM siparisler s
                            LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                            LEFT JOIN projeler p ON s.proje_id = p.id
                            WHERE (s.sorumlu_id = ? OR s.tedarikci_id IN (
                                SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                            ))
                            AND s.durum_id = 1
                            AND s.teslim_tarihi IS NOT NULL
                            AND s.teslim_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
                            ORDER BY s.teslim_tarihi ASC
                            LIMIT 5";
$yaklasan_teslimatlar_stmt = $db->prepare($yaklasan_teslimatlar_sql);
$yaklasan_teslimatlar_stmt->execute([$sorumlu_id, $sorumlu_id]);
$yaklasan_teslimatlar = $yaklasan_teslimatlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gösterge Paneli</h2>
        <div>
            <a href="siparisler.php" class="btn btn-primary"><i class="bi bi-list-check"></i> Siparişleri Görüntüle</a>
            <a href="yeni_siparis.php" class="btn btn-success"><i class="bi bi-plus-circle"></i> Yeni Sipariş</a>
        </div>
    </div>

    <!-- Özet Kartları -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Toplam Sipariş</h6>
                            <h2 class="mt-2 mb-0"><?= $siparis_ozet['toplam_siparis'] ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-list-check fs-1"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="siparisler.php" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Açık Sipariş</h6>
                            <h2 class="mt-2 mb-0"><?= $siparis_ozet['acik_siparis'] ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="siparisler.php?durum_id=1" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Bekleyen Sipariş</h6>
                            <h2 class="mt-2 mb-0"><?= $siparis_ozet['bekleyen_siparis'] ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-hourglass-split fs-1"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="siparisler.php?durum_id=3" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Tedarikçi</h6>
                            <h2 class="mt-2 mb-0"><?= $tedarikci_sayisi ?></h2>
                        </div>
                        <div>
                            <i class="bi bi-shop fs-1"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="tedarikcilerim.php" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <!-- Ana İçerik -->
    <div class="row">
        <!-- Son Siparişler -->
        <div class="col-md-7 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Son Siparişler</h5>
                </div>
                <div class="card-body">
                    <?php if (count($son_siparisler) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Tedarikçi</th>
                                    <th>Proje</th>
                                    <th>Tarih</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($son_siparisler as $siparis): ?>
                                <tr>
                                    <td><?= guvenli($siparis['siparis_no']) ?></td>
                                    <td><?= guvenli($siparis['firma_adi']) ?></td>
                                    <td><?= guvenli($siparis['proje_adi']) ?></td>
                                    <td><?= date('d.m.Y', strtotime($siparis['olusturma_tarihi'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                            <?= guvenli($siparis['durum_adi']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center py-3">Henüz sipariş bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="siparisler.php" class="btn btn-sm btn-primary">Tüm Siparişleri Gör</a>
                </div>
            </div>
        </div>

        <!-- Yaklaşan Teslimatlar -->
        <div class="col-md-5 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Yaklaşan Teslimatlar</h5>
                </div>
                <div class="card-body">
                    <?php if (count($yaklasan_teslimatlar) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sipariş No</th>
                                    <th>Tedarikçi</th>
                                    <th>Teslim Tarihi</th>
                                    <th>Kalan Gün</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($yaklasan_teslimatlar as $teslimat): 
                                    $teslim_tarihi = new DateTime($teslimat['teslim_tarihi']);
                                    $bugun = new DateTime();
                                    $kalan_gun = $bugun->diff($teslim_tarihi)->days;
                                    $renk_class = '';
                                    if ($kalan_gun <= 3) {
                                        $renk_class = 'table-danger';
                                    } elseif ($kalan_gun <= 7) {
                                        $renk_class = 'table-warning';
                                    }
                                ?>
                                <tr class="<?= $renk_class ?>">
                                    <td>
                                        <a href="siparis_detay.php?id=<?= $teslimat['id'] ?>">
                                            <?= guvenli($teslimat['siparis_no']) ?>
                                        </a>
                                    </td>
                                    <td><?= guvenli($teslimat['firma_adi']) ?></td>
                                    <td><?= date('d.m.Y', strtotime($teslimat['teslim_tarihi'])) ?></td>
                                    <td>
                                        <span class="badge <?= $kalan_gun <= 3 ? 'bg-danger' : ($kalan_gun <= 7 ? 'bg-warning' : 'bg-info') ?>">
                                            <?= $kalan_gun ?> gün
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-center py-3">Yaklaşan teslimat bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tedarikçiler -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Tedarikçilerim</h5>
                </div>
                <div class="card-body">
                    <?php if (count($tedarikciler) > 0): ?>
                    <div class="row">
                        <?php foreach ($tedarikciler as $tedarikci): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-0 shadow-sm">
                                <div class="card-body">
                                    <h5 class="card-title"><?= guvenli($tedarikci['firma_adi']) ?></h5>
                                    <h6 class="card-subtitle mb-2 text-muted"><?= guvenli($tedarikci['firma_kodu'] ?? '-') ?></h6>
                                    <p class="card-text">
                                        <i class="bi bi-envelope-fill me-2"></i><?= guvenli($tedarikci['email'] ?? '-') ?><br>
                                        <i class="bi bi-telephone-fill me-2"></i><?= guvenli($tedarikci['telefon'] ?? '-') ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="badge bg-primary"><?= $tedarikci['siparis_sayisi'] ?> Sipariş</span>
                                        <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="btn btn-sm btn-outline-primary">Detay</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p class="text-center py-3">Sorumlusu olduğunuz tedarikçi bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-end">
                    <a href="tedarikcilerim.php" class="btn btn-sm btn-primary">Tüm Tedarikçileri Gör</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
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