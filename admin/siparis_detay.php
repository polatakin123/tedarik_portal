<?php
// admin/siparis_detay.php - Sipariş detay görüntüleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığı
$page_title = "Sipariş Detayı";

// Sipariş ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: siparisler.php?hata=" . urlencode("Sipariş ID belirtilmedi"));
    exit;
}

$siparis_id = intval($_GET['id']);

// Sipariş bilgilerini getir
$siparis_sql = "SELECT s.*, 
               sd.durum_adi,
               t.firma_adi AS tedarikci_adi, t.firma_kodu AS tedarikci_kodu,
               p.proje_adi, p.proje_kodu,
               k.ad_soyad AS sorumlu_adi, k.email AS sorumlu_email,
               o.ad_soyad AS olusturan_adi
               FROM siparisler s
               LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
               LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
               LEFT JOIN projeler p ON s.proje_id = p.id
               LEFT JOIN kullanicilar k ON s.sorumlu_id = k.id
               LEFT JOIN kullanicilar o ON s.olusturan_id = o.id
               WHERE s.id = ?";

$siparis_stmt = $db->prepare($siparis_sql);
$siparis_stmt->execute([$siparis_id]);
$siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

if (!$siparis) {
    header("Location: siparisler.php?hata=" . urlencode("Sipariş bulunamadı"));
    exit;
}

// Sipariş durumlarını getir (durum değiştirme için)
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş notlarını getir
$notlar_sql = "SELECT n.*, k.ad_soyad 
              FROM siparis_notlari n
              LEFT JOIN kullanicilar k ON n.ekleyen_id = k.id
              WHERE n.siparis_id = ?
              ORDER BY n.eklenme_tarihi DESC";
$notlar_stmt = $db->prepare($notlar_sql);
$notlar_stmt->execute([$siparis_id]);
$notlar = $notlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durum değişikliği
if (isset($_POST['durum_degistir']) && isset($_POST['yeni_durum_id'])) {
    $yeni_durum_id = intval($_POST['yeni_durum_id']);
    $aciklama = isset($_POST['durum_aciklama']) ? trim($_POST['durum_aciklama']) : '';
    
    try {
        $db->beginTransaction();
        
        // Durum güncelleme
        $guncelle_sql = "UPDATE siparisler SET durum_id = ?, guncelleme_tarihi = NOW() WHERE id = ?";
        $guncelle_stmt = $db->prepare($guncelle_sql);
        $guncelle_stmt->execute([$yeni_durum_id, $siparis_id]);
        
        // Durum değişikliği notu ekle
        if (!empty($aciklama)) {
            $not_sql = "INSERT INTO siparis_notlari (siparis_id, not_metni, ekleyen_id, eklenme_tarihi) 
                      VALUES (?, ?, ?, NOW())";
            $not_stmt = $db->prepare($not_sql);
            $not_stmt->execute([$siparis_id, $aciklama, $_SESSION['kullanici_id']]);
        }
        
        // Durum adını al
        $durum_adi_sql = "SELECT durum_adi FROM siparis_durumlari WHERE id = ?";
        $durum_adi_stmt = $db->prepare($durum_adi_sql);
        $durum_adi_stmt->execute([$yeni_durum_id]);
        $durum_adi = $durum_adi_stmt->fetchColumn();
        
        // Bildirim gönder
        $bildirim_mesaji = "Sipariş durumu değişti: " . $siparis['siparis_no'] . " - " . $durum_adi;
        
        // Sorumluya bildirim
        bildirimOlustur($db, $siparis['sorumlu_id'], $bildirim_mesaji, $siparis_id);
        
        // Tedarikçi kullanıcılarına bildirim
        $tedarikci_kullanicilar_sql = "SELECT kullanici_id FROM kullanici_tedarikci_iliskileri WHERE tedarikci_id = ?";
        $tedarikci_kullanicilar_stmt = $db->prepare($tedarikci_kullanicilar_sql);
        $tedarikci_kullanicilar_stmt->execute([$siparis['tedarikci_id']]);
        
        while ($kullanici = $tedarikci_kullanicilar_stmt->fetch(PDO::FETCH_ASSOC)) {
            bildirimOlustur($db, $kullanici['kullanici_id'], $bildirim_mesaji, $siparis_id);
        }
        
        $db->commit();
        
        $mesaj = "Sipariş durumu başarıyla güncellendi.";
        header("Location: siparis_detay.php?id=" . $siparis_id . "&mesaj=" . urlencode($mesaj));
        exit;
        
    } catch (PDOException $e) {
        $db->rollBack();
        $hata = "Sipariş durumu güncellenirken bir hata oluştu: " . $e->getMessage();
        header("Location: siparis_detay.php?id=" . $siparis_id . "&hata=" . urlencode($hata));
        exit;
    }
}

// Not ekleme işlemi
if (isset($_POST['not_ekle']) && isset($_POST['not_metni']) && !empty($_POST['not_metni'])) {
    $not_metni = trim($_POST['not_metni']);
    
    try {
        $not_sql = "INSERT INTO siparis_notlari (siparis_id, not_metni, ekleyen_id, eklenme_tarihi) 
                  VALUES (?, ?, ?, NOW())";
        $not_stmt = $db->prepare($not_sql);
        $not_stmt->execute([$siparis_id, $not_metni, $_SESSION['kullanici_id']]);
        
        $mesaj = "Not başarıyla eklendi.";
        header("Location: siparis_detay.php?id=" . $siparis_id . "&mesaj=" . urlencode($mesaj));
        exit;
        
    } catch (PDOException $e) {
        $hata = "Not eklenirken bir hata oluştu: " . $e->getMessage();
        header("Location: siparis_detay.php?id=" . $siparis_id . "&hata=" . urlencode($hata));
        exit;
    }
}

// Duruma göre renk sınıfı belirle
function durumRengiGetir($durum_id) {
    switch ($durum_id) {
        case 1: return "info"; // Açık
        case 2: return "primary"; // İşlemde
        case 3: return "warning"; // Beklemede
        case 4: return "success"; // Tamamlandı
        case 5: return "danger"; // İptal Edildi
        default: return "secondary";
    }
}

$durum_rengi = durumRengiGetir($siparis['durum_id']);

// Sayfa başlığını düzenle
$page_title = "Sipariş Detayı: " . $siparis['siparis_no'];

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            Sipariş Detayı: <?= guvenli($siparis['siparis_no']) ?>
            <span class="badge bg-<?= $durum_rengi ?>"><?= guvenli($siparis['durum_adi']) ?></span>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="siparisler.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Siparişlere Dön
            </a>
            <a href="siparis_duzenle.php?id=<?= $siparis_id ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-pencil"></i> Düzenle
            </a>
            <button type="button" class="btn btn-sm btn-outline-success me-2" data-bs-toggle="modal" data-bs-target="#durumModal">
                <i class="bi bi-arrow-repeat"></i> Durum Değiştir
            </button>
            <button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#notEkleModal">
                <i class="bi bi-chat-square-text"></i> Not Ekle
            </button>
        </div>
    </div>

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

    <div class="row">
        <div class="col-md-7">
            <!-- Sipariş Detayları Kartı -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sipariş Bilgileri</h5>
                    <div>
                        <span class="badge bg-<?= $durum_rengi ?>"><?= guvenli($siparis['durum_adi']) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Sipariş No:</p>
                            <p><?= guvenli($siparis['siparis_no']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Tedarikçi:</p>
                            <p>
                                <a href="tedarikci_detay.php?id=<?= $siparis['tedarikci_id'] ?>" class="text-decoration-none">
                                    <?= guvenli($siparis['tedarikci_adi']) ?> (<?= guvenli($siparis['tedarikci_kodu']) ?>)
                                </a>
                            </p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Proje:</p>
                            <p>
                                <a href="proje_detay.php?id=<?= $siparis['proje_id'] ?>" class="text-decoration-none">
                                    <?= guvenli($siparis['proje_adi']) ?> (<?= guvenli($siparis['proje_kodu']) ?>)
                                </a>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Sorumlu:</p>
                            <p>
                                <a href="kullanici_detay.php?id=<?= $siparis['sorumlu_id'] ?>" class="text-decoration-none">
                                    <?= guvenli($siparis['sorumlu_adi']) ?>
                                </a>
                                (<?= guvenli($siparis['sorumlu_email']) ?>)
                            </p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Parça No:</p>
                            <p><?= guvenli($siparis['parca_no']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Parça Adı:</p>
                            <p><?= !empty($siparis['parca_adi']) ? guvenli($siparis['parca_adi']) : '-' ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Miktar:</p>
                            <p><?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Teslim Tarihi:</p>
                            <p><?= $siparis['teslim_tarihi'] ? date('d.m.Y', strtotime($siparis['teslim_tarihi'])) : 'Belirtilmemiş' ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <p class="fw-bold mb-1">Açıklama:</p>
                        <p><?= !empty($siparis['aciklama']) ? nl2br(guvenli($siparis['aciklama'])) : 'Açıklama bulunmuyor.' ?></p>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <div class="row text-muted small">
                        <div class="col-md-6">
                            <span>Oluşturan: <?= guvenli($siparis['olusturan_adi']) ?></span><br>
                            <span>Oluşturma: <?= date('d.m.Y H:i', strtotime($siparis['olusturma_tarihi'])) ?></span>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <?php if ($siparis['guncelleme_tarihi']): ?>
                                <span>Son Güncelleme: <?= date('d.m.Y H:i', strtotime($siparis['guncelleme_tarihi'])) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-5">
            <!-- Notlar Kartı -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Sipariş Notları</h5>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#notEkleModal">
                        <i class="bi bi-plus-circle"></i> Not Ekle
                    </button>
                </div>
                <div class="card-body">
                    <?php if (count($notlar) > 0): ?>
                        <ul class="timeline">
                            <?php foreach ($notlar as $not): ?>
                                <li class="timeline-item">
                                    <div class="fw-bold d-flex justify-content-between">
                                        <span><?= guvenli($not['ad_soyad']) ?></span>
                                        <span class="text-muted small"><?= date('d.m.Y H:i', strtotime($not['eklenme_tarihi'])) ?></span>
                                    </div>
                                    <p class="mb-0"><?= nl2br(guvenli($not['not_metni'])) ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-center text-muted fst-italic">Bu siparişe ait not bulunmamaktadır.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Durum Değiştirme Modal -->
<div class="modal fade" id="durumModal" tabindex="-1" aria-labelledby="durumModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="durumModalLabel">Sipariş Durumunu Değiştir</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="yeni_durum_id" class="form-label">Yeni Durum</label>
                        <select class="form-select" id="yeni_durum_id" name="yeni_durum_id" required>
                            <?php foreach ($durumlar as $durum): ?>
                                <option value="<?= $durum['id'] ?>" <?= ($siparis['durum_id'] == $durum['id']) ? 'selected' : '' ?>>
                                    <?= guvenli($durum['durum_adi']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="durum_aciklama" class="form-label">Açıklama (Opsiyonel)</label>
                        <textarea class="form-control" id="durum_aciklama" name="durum_aciklama" rows="3" placeholder="Durum değişikliği hakkında açıklama yazabilirsiniz..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="durum_degistir" class="btn btn-primary">Durumu Güncelle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Not Ekleme Modal -->
<div class="modal fade" id="notEkleModal" tabindex="-1" aria-labelledby="notEkleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="">
                <div class="modal-header">
                    <h5 class="modal-title" id="notEkleModalLabel">Siparişe Not Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="not_metni" class="form-label">Not</label>
                        <textarea class="form-control" id="not_metni" name="not_metni" rows="5" required placeholder="Notunuzu buraya yazın..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" name="not_ekle" class="btn btn-primary">Not Ekle</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 