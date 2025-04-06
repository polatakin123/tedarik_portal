<?php
// tedarikci/index.php - Tedarikçi paneli ana sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Ana Sayfa";

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

// Tedarikçi için siparişlerin istatistiklerini al
$siparisler_sql = "SELECT COUNT(*) as toplam, 
                  SUM(CASE WHEN durum_id = 1 THEN 1 ELSE 0 END) as acik,
                  SUM(CASE WHEN durum_id = 2 THEN 1 ELSE 0 END) as kapali,
                  SUM(CASE WHEN durum_id = 3 THEN 1 ELSE 0 END) as beklemede
                  FROM siparisler
                  WHERE tedarikci_id = ?";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$tedarikci_id]);
$siparisler_istatistik = $siparisler_stmt->fetch(PDO::FETCH_ASSOC);

// Son 10 siparişi al
$son_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi
                      FROM siparisler s
                      LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                      LEFT JOIN projeler p ON s.proje_id = p.id
                      LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                      WHERE s.tedarikci_id = ?
                      ORDER BY s.olusturma_tarihi DESC
                      LIMIT 10";
$son_siparisler_stmt = $db->prepare($son_siparisler_sql);
$son_siparisler_stmt->execute([$tedarikci_id]);
$son_siparisler = $son_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Yaklaşan teslimat tarihleri
$yaklasan_teslimler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi
                          FROM siparisler s
                          LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                          LEFT JOIN projeler p ON s.proje_id = p.id
                          WHERE s.tedarikci_id = ? AND s.durum_id = 1
                          AND s.teslim_tarihi BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                          ORDER BY s.teslim_tarihi ASC
                          LIMIT 5";
$yaklasan_teslimler_stmt = $db->prepare($yaklasan_teslimler_sql);
$yaklasan_teslimler_stmt->execute([$tedarikci_id]);
$yaklasan_teslimler = $yaklasan_teslimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Son bildirimler
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$kullanici_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);

// Sorumlu kişiler
$sorumlular_sql = "SELECT k.id, k.ad_soyad, k.email, k.telefon 
                  FROM kullanicilar k
                  INNER JOIN sorumluluklar s ON k.id = s.sorumlu_id
                  WHERE s.tedarikci_id = ?";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute([$tedarikci_id]);
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header dosyasını dahil et
include 'header.php';
?>

<!-- Firma Bilgileri -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">Firma Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Firma Adı:</div>
                    <div class="col-md-8"><?= htmlspecialchars($tedarikci['firma_adi']) ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Firma Kodu:</div>
                    <div class="col-md-8"><?= htmlspecialchars($tedarikci['firma_kodu'] ?? '-') ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Vergi No:</div>
                    <div class="col-md-8"><?= htmlspecialchars($tedarikci['vergi_no'] ?? '-') ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Yetkili Kişi:</div>
                    <div class="col-md-8"><?= htmlspecialchars($tedarikci['yetkili_kisi'] ?? '-') ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">E-posta:</div>
                    <div class="col-md-8"><?= htmlspecialchars($tedarikci['email'] ?? '-') ?></div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 fw-bold">Telefon:</div>
                    <div class="col-md-8"><?= htmlspecialchars($tedarikci['telefon'] ?? '-') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Özet Bilgiler -->
<div class="row mb-4">
    <!-- Sipariş İstatistikleri -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Sipariş İstatistikleri</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card text-bg-secondary">
                            <div class="card-body text-center py-3">
                                <h5 class="card-title mb-1"><?= $siparisler_istatistik['toplam'] ?? 0 ?></h5>
                                <p class="card-text small mb-0">Toplam Sipariş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-bg-warning">
                            <div class="card-body text-center py-3">
                                <h5 class="card-title mb-1"><?= $siparisler_istatistik['acik'] ?? 0 ?></h5>
                                <p class="card-text small mb-0">Açık Sipariş</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-bg-success">
                            <div class="card-body text-center py-3">
                                <h5 class="card-title mb-1"><?= $siparisler_istatistik['kapali'] ?? 0 ?></h5>
                                <p class="card-text small mb-0">Tamamlanan</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card text-bg-info">
                            <div class="card-body text-center py-3">
                                <h5 class="card-title mb-1"><?= $siparisler_istatistik['beklemede'] ?? 0 ?></h5>
                                <p class="card-text small mb-0">Beklemede</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="siparislerim.php" class="btn btn-outline-primary">Tüm Siparişleri Görüntüle</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Yaklaşan Teslimatlar -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Yaklaşan Teslimatlar</h5>
            </div>
            <div class="card-body">
                <?php if (count($yaklasan_teslimler) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Sipariş No</th>
                                <th>Proje</th>
                                <th>Teslim Tarihi</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($yaklasan_teslimler as $teslimat): ?>
                            <tr>
                                <td><a href="siparis_detay.php?id=<?= $teslimat['id'] ?>"><?= htmlspecialchars($teslimat['siparis_no']) ?></a></td>
                                <td><?= htmlspecialchars($teslimat['proje_adi']) ?></td>
                                <td><?= date('d.m.Y', strtotime($teslimat['teslim_tarihi'])) ?></td>
                                <td><span class="badge bg-warning"><?= htmlspecialchars($teslimat['durum_adi']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-center py-3">Yaklaşan teslimat yok.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Son Siparişler ve İletişim -->
<div class="row">
    <!-- Son Siparişler -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Son Siparişler</h5>
            </div>
            <div class="card-body">
                <?php if (count($son_siparisler) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Sipariş No</th>
                                <th>Parça No</th>
                                <th>Proje</th>
                                <th>Tarih</th>
                                <th>Durum</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($son_siparisler as $siparis): ?>
                            <tr>
                                <td><?= htmlspecialchars($siparis['siparis_no']) ?></td>
                                <td><?= htmlspecialchars($siparis['parca_no']) ?></td>
                                <td><?= htmlspecialchars($siparis['proje_adi']) ?></td>
                                <td><?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                        <?= htmlspecialchars($siparis['durum_adi']) ?>
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
                <p class="text-center py-3">Henüz sipariş yok.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sorumlu Kişiler -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">Sorumlu Kişiler</h5>
            </div>
            <div class="card-body">
                <?php if (count($sorumlular) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($sorumlular as $sorumlu): ?>
                    <div class="list-group-item">
                        <h6 class="mb-1"><?= htmlspecialchars($sorumlu['ad_soyad']) ?></h6>
                        <p class="mb-1 small"><i class="bi bi-envelope me-2"></i><?= htmlspecialchars($sorumlu['email']) ?></p>
                        <p class="mb-0 small"><i class="bi bi-telephone me-2"></i><?= htmlspecialchars($sorumlu['telefon'] ?? '-') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-center py-3">Sorumlu atanmamış.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Sipariş durumuna göre renk döndüren yardımcı fonksiyon
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