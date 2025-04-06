<?php
// admin/index.php - Admin paneli ana sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Ana Sayfa";

// İstatistikler
// Sipariş istatistikleri
$siparisler_sql = "SELECT COUNT(*) as toplam, 
                  SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                  SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                  SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                  FROM siparisler";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute();
$siparisler_istatistik = $siparisler_stmt->fetch(PDO::FETCH_ASSOC);

// Tedarikçi sayısı
$tedarikciler_sql = "SELECT COUNT(*) as toplam FROM tedarikciler";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikci_sayisi = $tedarikciler_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Sorumlu sayısı
$sorumlular_sql = "SELECT COUNT(*) as toplam FROM kullanicilar WHERE rol = 'Sorumlu'";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute();
$sorumlu_sayisi = $sorumlular_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Proje sayısı
$projeler_sql = "SELECT COUNT(*) as toplam FROM projeler";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute();
$proje_sayisi = $projeler_stmt->fetch(PDO::FETCH_ASSOC)['toplam'];

// Son 10 sipariş
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, t.firma_adi, p.proje_adi, 
                      k.ad_soyad as sorumlu_adi, s.olusturma_tarihi as tarih
                      FROM siparisler s
                      LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                      LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                      LEFT JOIN projeler p ON s.proje_id = p.id
                      LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
                      ORDER BY s.olusturma_tarihi DESC
                      LIMIT 10";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute();
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Yaklaşan teslimatlar
$yaklasan_teslimler_sql = "SELECT s.*, sd.durum_adi, t.firma_adi, p.proje_adi
                          FROM siparisler s
                          LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                          LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                          LEFT JOIN projeler p ON s.proje_id = p.id
                          WHERE s.durum_id = 1
                          AND s.teslim_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                          ORDER BY s.teslim_tarihi ASC
                          LIMIT 5";
$yaklasan_teslimler_stmt = $db->prepare($yaklasan_teslimler_sql);
$yaklasan_teslimler_stmt->execute();
$yaklasan_teslimler = $yaklasan_teslimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Yönetim Paneli</h2>
    <div>
        <a href="siparis_ekle.php" class="btn btn-success me-2"><i class="bi bi-plus-circle"></i> Yeni Sipariş</a>
        <a href="tedarikci_ekle.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Yeni Tedarikçi</a>
    </div>
</div>

<!-- İstatistik Kartları -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Toplam Sipariş</h6>
                        <h2 class="mt-2 mb-0"><?= $siparisler_istatistik['toplam'] ?? 0 ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-cart fs-1"></i>
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
                        <h6 class="mb-0">Tedarikçiler</h6>
                        <h2 class="mt-2 mb-0"><?= $tedarikci_sayisi ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-building fs-1"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="tedarikciler.php" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Sorumlular</h6>
                        <h2 class="mt-2 mb-0"><?= $sorumlu_sayisi ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-people fs-1"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="kullanicilar.php?rol=Sorumlu" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="mb-0">Projeler</h6>
                        <h2 class="mt-2 mb-0"><?= $proje_sayisi ?></h2>
                    </div>
                    <div>
                        <i class="bi bi-folder fs-1"></i>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-transparent border-0">
                <a href="projeler.php" class="text-white">Tümünü Gör <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Ana İçerik -->
<div class="row">
    <!-- Son Siparişler -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Son Siparişler</h5>
                <a href="siparisler.php" class="btn btn-sm btn-primary">Tümünü Gör</a>
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
                                <th>Sorumlu</th>
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
                                <td><?= guvenli($siparis['sorumlu_adi']) ?></td>
                                <td><?= !empty($siparis['tarih']) ? date('d.m.Y', strtotime($siparis['tarih'])) : '-' ?></td>
                                <td>
                                    <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                        <?= guvenli($siparis['durum_adi']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info" title="Detay">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="siparis_duzenle.php?id=<?= $siparis['id'] ?>" class="btn btn-warning" title="Düzenle">
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
                <p class="text-center py-3">Henüz sipariş bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Yaklaşan Teslimatlar -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="card-title mb-0">Yaklaşan Teslimatlar</h5>
            </div>
            <div class="card-body">
                <?php if (count($yaklasan_teslimler) > 0): ?>
                <div class="list-group">
                    <?php foreach ($yaklasan_teslimler as $teslim): 
                        $teslim_tarihi = !empty($teslim['teslim_tarihi']) ? new DateTime($teslim['teslim_tarihi']) : new DateTime();
                        $bugun = new DateTime();
                        $kalan_gun = $bugun->diff($teslim_tarihi)->days;
                        $renk_class = '';
                        if ($kalan_gun <= 3) {
                            $renk_class = 'list-group-item-danger';
                        } elseif ($kalan_gun <= 7) {
                            $renk_class = 'list-group-item-warning';
                        }
                    ?>
                    <a href="siparis_detay.php?id=<?= $teslim['id'] ?>" class="list-group-item list-group-item-action <?= $renk_class ?>">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?= guvenli($teslim['siparis_no']) ?></h6>
                            <small class="text-muted">
                                <span class="badge <?= $kalan_gun <= 3 ? 'bg-danger' : ($kalan_gun <= 7 ? 'bg-warning' : 'bg-info') ?>">
                                    <?= $kalan_gun ?> gün
                                </span>
                            </small>
                        </div>
                        <p class="mb-1"><?= guvenli($teslim['firma_adi']) ?></p>
                        <small class="text-muted">
                            Teslim: <?= !empty($teslim['teslim_tarihi']) ? date('d.m.Y', strtotime($teslim['teslim_tarihi'])) : '-' ?> | 
                            Proje: <?= guvenli($teslim['proje_adi']) ?>
                        </small>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center py-3">Yaklaşan teslimat bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Hızlı Erişim Menüleri -->
<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Hızlı Erişim</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-2 mb-3">
                        <a href="siparisler.php" class="btn btn-lg btn-outline-primary w-100 py-3">
                            <i class="bi bi-list-check d-block fs-3 mb-2"></i>
                            Siparişler
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="tedarikciler.php" class="btn btn-lg btn-outline-success w-100 py-3">
                            <i class="bi bi-building d-block fs-3 mb-2"></i>
                            Tedarikçiler
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="kullanicilar.php" class="btn btn-lg btn-outline-info w-100 py-3">
                            <i class="bi bi-people d-block fs-3 mb-2"></i>
                            Kullanıcılar
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="projeler.php" class="btn btn-lg btn-outline-warning w-100 py-3">
                            <i class="bi bi-folder d-block fs-3 mb-2"></i>
                            Projeler
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="raporlar.php" class="btn btn-lg btn-outline-danger w-100 py-3">
                            <i class="bi bi-bar-chart d-block fs-3 mb-2"></i>
                            Raporlar
                        </a>
                    </div>
                    <div class="col-md-2 mb-3">
                        <a href="ayarlar.php" class="btn btn-lg btn-outline-secondary w-100 py-3">
                            <i class="bi bi-gear d-block fs-3 mb-2"></i>
                            Ayarlar
                        </a>
                    </div>
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