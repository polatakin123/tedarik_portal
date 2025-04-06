<?php
// tedarikci/siparis_detay.php - Tedarikçinin sipariş detaylarını görebildiği sayfa
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Sipariş Detayı";

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

// Sipariş ID kontrolü
$siparis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($siparis_id <= 0) {
    header("Location: siparislerim.php");
    exit;
}

// Tedarikçiye ait sipariş bilgisini getir
$siparis_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi, u.email as sorumlu_email,
                u.telefon as sorumlu_telefon, o.ad_soyad as olusturan_adi
                FROM siparisler s
                LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                LEFT JOIN projeler p ON s.proje_id = p.id
                LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                LEFT JOIN kullanicilar o ON s.olusturan_id = o.id
                WHERE s.id = ? AND s.tedarikci_id = ?";
$siparis_stmt = $db->prepare($siparis_sql);
$siparis_stmt->execute([$siparis_id, $tedarikci_id]);
$siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

if (!$siparis) {
    // Sipariş bulunamadı veya bu tedarikçiye ait değil
    header("Location: siparislerim.php");
    exit;
}

// Sipariş dokümanlarını getir
$dokuman_sql = "SELECT * FROM siparis_dokumanlari WHERE siparis_id = ? ORDER BY yukleme_tarihi DESC";
$dokuman_stmt = $db->prepare($dokuman_sql);
$dokuman_stmt->execute([$siparis_id]);
$dokumanlar = $dokuman_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş güncellemelerini getir
$guncelleme_sql = "SELECT sg.*, k.ad_soyad
                  FROM siparis_guncellemeleri sg
                  LEFT JOIN kullanicilar k ON sg.guncelleyen_id = k.id
                  WHERE sg.siparis_id = ?
                  ORDER BY sg.guncelleme_tarihi DESC";
$guncelleme_stmt = $db->prepare($guncelleme_sql);
$guncelleme_stmt->execute([$siparis_id]);
$guncellemeler = $guncelleme_stmt->fetchAll(PDO::FETCH_ASSOC);

// Teslimat bilgilerini getir
try {
    // Teslimatlar tablosu var mı diye kontrol et
    $tabloKontrol = $db->query("SHOW TABLES LIKE 'siparis_teslimatlari'");
    $teslimatlarTablosuVar = $tabloKontrol->rowCount() > 0;
    
    if ($teslimatlarTablosuVar) {
        $teslimatlar_sql = "SELECT st.*, k.ad_soyad AS teslim_eden_adi
                           FROM siparis_teslimatlari st
                           LEFT JOIN kullanicilar k ON st.olusturan_id = k.id
                           WHERE st.siparis_id = ?
                           ORDER BY st.teslimat_tarihi DESC";
        $teslimatlar_stmt = $db->prepare($teslimatlar_sql);
        $teslimatlar_stmt->execute([$siparis_id]);
        $teslimatlar = $teslimatlar_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $teslimatlar = [];
    }
} catch (Exception $e) {
    // Tablo yoksa boş dizi döndür
    $teslimatlar = [];
}

// Header'ı dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sipariş Detayı: <?= guvenli($siparis['siparis_no']) ?></h2>
    <div>
        <a href="siparislerim.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Siparişlerime Dön</a>
        <a href="siparis_guncelle.php?id=<?= $siparis_id ?>" class="btn btn-primary"><i class="bi bi-pencil"></i> Güncelle</a>
    </div>
</div>

<div class="row">
    <!-- Sipariş Bilgileri -->
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Sipariş No:</div>
                            <div class="col-md-8"><?= guvenli($siparis['siparis_no']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Durum:</div>
                            <div class="col-md-8">
                                <span class="badge bg-<?= getDurumRenk($siparis['durum_id']) ?>">
                                    <?= guvenli($siparis['durum_adi']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Parça No:</div>
                            <div class="col-md-8"><?= guvenli($siparis['parca_no']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Proje:</div>
                            <div class="col-md-8"><?= guvenli($siparis['proje_adi']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Miktar:</div>
                            <div class="col-md-8"><?= guvenli($siparis['miktar']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Birim:</div>
                            <div class="col-md-8"><?= guvenli($siparis['birim']) ?></div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Açılış Tarihi:</div>
                            <div class="col-md-8"><?= date('d.m.Y', strtotime($siparis['tarih'])) ?></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row">
                            <div class="col-md-4 fw-bold">Teslim Tarihi:</div>
                            <div class="col-md-8">
                                <?php if ($siparis['teslim_tarihi']): ?>
                                    <?= date('d.m.Y', strtotime($siparis['teslim_tarihi'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">Belirtilmemiş</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-2 fw-bold">Tanım:</div>
                    <div class="col-md-10"><?= nl2br(guvenli($siparis['tanim'])) ?></div>
                </div>
                <?php if (!empty($siparis['aciklama'])): ?>
                <div class="row mb-3">
                    <div class="col-md-2 fw-bold">Açıklama:</div>
                    <div class="col-md-10"><?= nl2br(guvenli($siparis['aciklama'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Teslimat Bilgileri -->
        <?php if (count($teslimatlar) > 0): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Teslimat Bilgileri</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Teslimat Tarihi</th>
                                <th>Miktar</th>
                                <th>Açıklama</th>
                                <th>Teslim Eden</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teslimatlar as $teslimat): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i', strtotime($teslimat['teslimat_tarihi'])) ?></td>
                                <td><?= guvenli($teslimat['miktar']) ?> <?= guvenli($siparis['birim']) ?></td>
                                <td><?= guvenli($teslimat['aciklama']) ?></td>
                                <td><?= guvenli($teslimat['teslim_eden_adi']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Dokümanlar -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Dokümanlar</h5>
            </div>
            <div class="card-body">
                <?php if (count($dokumanlar) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Doküman Adı</th>
                                <th>Türü</th>
                                <th>Yüklenme Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dokumanlar as $dokuman): ?>
                            <tr>
                                <td><?= guvenli($dokuman['dokuman_adi']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= guvenli($dokuman['dosya_turu'] ?? 'Belirtilmemiş') ?></span>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></td>
                                <td>
                                    <a href="../dosyalar/<?= $dokuman['dosya_adi'] ?>" class="btn btn-sm btn-success" target="_blank">
                                        <i class="bi bi-download"></i> İndir
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info">Bu siparişe ait doküman bulunmamaktadır.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Sorumlu Kişi Bilgileri -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Sorumlu Kişi</h5>
            </div>
            <div class="card-body">
                <?php if ($siparis['sorumlu_id']): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="ms-2">
                        <h6 class="mb-1"><?= guvenli($siparis['sorumlu_adi']) ?></h6>
                        <p class="mb-1 small"><i class="bi bi-envelope me-2"></i><?= guvenli($siparis['sorumlu_email']) ?></p>
                        <p class="mb-0 small"><i class="bi bi-telephone me-2"></i><?= guvenli($siparis['sorumlu_telefon']) ?></p>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted">Sorumlu kişi atanmamış.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notlar -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Notlar</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#notEkleModal">
                    <i class="bi bi-plus"></i> Not Ekle
                </button>
            </div>
            <div class="card-body">
                <?php
                // Notları getir
                $notlar_sql = "SELECT n.*, k.ad_soyad FROM siparis_notlari n
                              LEFT JOIN kullanicilar k ON n.kullanici_id = k.id
                              WHERE n.siparis_id = ?
                              ORDER BY n.olusturma_tarihi DESC";
                $notlar_stmt = $db->prepare($notlar_sql);
                $notlar_stmt->execute([$siparis_id]);
                $notlar = $notlar_stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <?php if (count($notlar) > 0): ?>
                <div class="timeline">
                    <?php foreach ($notlar as $not): ?>
                    <div class="timeline-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= guvenli($not['ad_soyad']) ?></strong>
                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($not['olusturma_tarihi'])) ?></small>
                        </div>
                        <p class="mb-0"><?= nl2br(guvenli($not['icerik'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-3">Henüz not eklenmemiş.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sipariş Geçmişi -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Sipariş Geçmişi</h5>
            </div>
            <div class="card-body">
                <?php if (count($guncellemeler) > 0): ?>
                <div class="timeline">
                    <?php foreach ($guncellemeler as $guncelleme): ?>
                    <div class="timeline-item">
                        <div class="d-flex justify-content-between">
                            <strong><?= guvenli($guncelleme['ad_soyad']) ?></strong>
                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($guncelleme['guncelleme_tarihi'])) ?></small>
                        </div>
                        <p class="mb-0"><?= nl2br(guvenli($guncelleme['aciklama'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-3">Henüz güncelleme yok.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Not Ekleme Modal -->
<div class="modal fade" id="notEkleModal" tabindex="-1" aria-labelledby="notEkleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="not_ekle.php" method="post">
                <input type="hidden" name="siparis_id" value="<?= $siparis_id ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="notEkleModalLabel">Not Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="not_icerik" class="form-label">Not İçeriği</label>
                        <textarea class="form-control" id="not_icerik" name="icerik" rows="4" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Not Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include 'footer.php';

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
?> 