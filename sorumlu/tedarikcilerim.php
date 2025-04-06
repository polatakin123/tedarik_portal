<?php
// sorumlu/tedarikcilerim.php - Sorumlu olunan tedarikçilerin listelenmesi
require_once '../config.php';
sorumluYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Tedarikçilerim";

$sorumlu_id = $_SESSION['kullanici_id'];

// Sorumlu olunan tedarikçileri al
$tedarikciler_sql = "SELECT t.*, 
                      (SELECT COUNT(*) FROM siparisler WHERE tedarikci_id = t.id) as siparis_sayisi
                      FROM tedarikciler t
                      INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                      WHERE s.sorumlu_id = ?
                      ORDER BY t.firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute([$sorumlu_id]);
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Her tedarikçi için sipariş özetini al
foreach ($tedarikciler as $key => $tedarikci) {
    $siparis_ozet_sql = "SELECT 
                          COUNT(*) as toplam,
                          SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                          SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                          SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                          FROM siparisler 
                          WHERE tedarikci_id = ?";
    $siparis_ozet_stmt = $db->prepare($siparis_ozet_sql);
    $siparis_ozet_stmt->execute([$tedarikci['id']]);
    $tedarikciler[$key]['siparis_ozet'] = $siparis_ozet_stmt->fetch(PDO::FETCH_ASSOC);
}

// Header dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Tedarikçilerim</h2>
    </div>

    <!-- Tedarikçi Kartları -->
    <div class="row">
        <?php if (count($tedarikciler) > 0): ?>
            <?php foreach ($tedarikciler as $tedarikci): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="card-header bg-primary text-white">
                            <h5 class="card-title mb-0"><?= guvenli($tedarikci['firma_adi']) ?></h5>
                            <small><?= guvenli($tedarikci['firma_kodu']) ?></small>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <i class="bi bi-person me-2"></i> <strong>Yetkili:</strong> <?= guvenli($tedarikci['yetkili_kisi'] ?: 'Belirtilmemiş') ?>
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-envelope me-2"></i> <strong>E-posta:</strong> <?= guvenli($tedarikci['email'] ?: 'Belirtilmemiş') ?>
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-telephone me-2"></i> <strong>Telefon:</strong> <?= guvenli($tedarikci['telefon'] ?: 'Belirtilmemiş') ?>
                            </div>
                            <div class="mb-3">
                                <i class="bi bi-geo-alt me-2"></i> <strong>Adres:</strong> <?= guvenli($tedarikci['adres'] ?: 'Belirtilmemiş') ?>
                            </div>

                            <div class="mt-4">
                                <h6>Sipariş Özeti</h6>
                                <div class="d-flex justify-content-between mt-3">
                                    <span class="badge bg-primary rounded-pill px-3 py-2">
                                        <i class="bi bi-box me-1"></i> Toplam: <?= $tedarikci['siparis_ozet']['toplam'] ?? 0 ?>
                                    </span>
                                    <span class="badge bg-warning rounded-pill px-3 py-2">
                                        <i class="bi bi-clock me-1"></i> Açık: <?= $tedarikci['siparis_ozet']['acik'] ?? 0 ?>
                                    </span>
                                    <span class="badge bg-success rounded-pill px-3 py-2">
                                        <i class="bi bi-check-circle me-1"></i> Kapalı: <?= $tedarikci['siparis_ozet']['kapali'] ?? 0 ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between">
                            <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="btn btn-primary">
                                <i class="bi bi-info-circle"></i> Detay
                            </a>
                            <a href="siparisler.php?tedarikci_id=<?= $tedarikci['id'] ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-list-check"></i> Siparişler
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <h5><i class="bi bi-info-circle me-2"></i> Bilgi</h5>
                    <p>Henüz sorumlu olduğunuz tedarikçi bulunmamaktadır. Tedarikçi atamaları sistem yöneticisi tarafından yapılmaktadır.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?> 