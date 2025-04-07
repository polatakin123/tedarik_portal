<?php
// admin/proje_detay.php - Proje detay sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Proje ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: projeler.php?hata=" . urlencode("Proje ID belirtilmedi"));
    exit;
}

$proje_id = intval($_GET['id']);

// Proje bilgilerini getir
$proje_sql = "SELECT * FROM projeler WHERE id = ?";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute([$proje_id]);
$proje = $proje_stmt->fetch(PDO::FETCH_ASSOC);

if (!$proje) {
    header("Location: projeler.php?hata=" . urlencode("Proje bulunamadı"));
    exit;
}

// Proje siparişlerini getir (son 10 sipariş)
$siparisler_sql = "SELECT s.*, sd.durum_adi, t.firma_adi as tedarikci_adi, t.firma_kodu as tedarikci_kodu, k.ad_soyad as sorumlu_adi
                  FROM siparisler s
                  LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                  LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                  LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
                  WHERE s.proje_id = ?
                  ORDER BY s.olusturma_tarihi DESC
                  LIMIT 10";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$proje_id]);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durum istatistikleri
$siparis_istatistik_sql = "SELECT sd.durum_adi, COUNT(s.id) as sayi
                          FROM siparis_durumlari sd
                          LEFT JOIN siparisler s ON sd.id = s.durum_id AND s.proje_id = ?
                          GROUP BY sd.id, sd.durum_adi
                          ORDER BY sd.id";
$siparis_istatistik_stmt = $db->prepare($siparis_istatistik_sql);
$siparis_istatistik_stmt->execute([$proje_id]);
$siparis_istatistikleri = $siparis_istatistik_stmt->fetchAll(PDO::FETCH_ASSOC);

// Proje tedarikçi dağılımı
$tedarikci_dagilim_sql = "SELECT t.firma_adi, COUNT(s.id) as siparis_sayisi
                         FROM tedarikciler t
                         INNER JOIN siparisler s ON t.id = s.tedarikci_id
                         WHERE s.proje_id = ?
                         GROUP BY t.id, t.firma_adi
                         ORDER BY siparis_sayisi DESC
                         LIMIT 5";
$tedarikci_dagilim_stmt = $db->prepare($tedarikci_dagilim_sql);
$tedarikci_dagilim_stmt->execute([$proje_id]);
$tedarikci_dagilim = $tedarikci_dagilim_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısını al
$kullanici_id = $_SESSION['kullanici_id'];
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);

// Son 5 bildirimi al
$bildirimler_sql = "SELECT b.*, s.siparis_no
                   FROM bildirimler b
                   LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                   WHERE b.kullanici_id = ? AND b.okundu = 0
                   ORDER BY b.bildirim_tarihi DESC
                   LIMIT 5";
$bildirimler_stmt = $db->prepare($bildirimler_sql);
$bildirimler_stmt->execute([$kullanici_id]);
$bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Proje Bilgileri</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($hatalar)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($hatalar as $hata): ?>
                                    <li><?= $hata ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Proje Adı</label>
                            <p class="form-control-static"><?= guvenli($proje['proje_adi']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proje Kodu</label>
                            <p class="form-control-static"><?= guvenli($proje['proje_kodu']) ?></p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Proje Yöneticisi</label>
                            <p class="form-control-static"><?= guvenli($proje['proje_yoneticisi']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Durum</label>
                            <p class="form-control-static">
                                <?php if ($proje['aktif']): ?>
                                    <span class="badge bg-success">Aktif</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Pasif</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Proje Açıklaması</label>
                            <p class="form-control-static"><?= guvenli($proje['proje_aciklama']) ?: '-' ?></p>
                        </div>
                    </div>

                    <div class="text-end">
                        <a href="projeler.php" class="btn btn-secondary">Geri Dön</a>
                        <a href="proje_duzenle.php?id=<?= $proje_id ?>" class="btn btn-primary">Düzenle</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 