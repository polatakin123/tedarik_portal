<?php
// tedarikci/teslimatlarim.php - Tedarikçinin teslimatları sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Teslimatlarım";

// Header dosyasını dahil et
include 'header.php';

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

// Mesaj değişkenleri
$mesaj = '';
$hata = '';

// Yeni teslimat formu gönderildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'teslimat_ekle') {
    $siparis_id = isset($_POST['siparis_id']) ? intval($_POST['siparis_id']) : 0;
    $teslimat_tarihi = isset($_POST['teslimat_tarihi']) ? $_POST['teslimat_tarihi'] : date('Y-m-d');
    $teslim_edilen = isset($_POST['teslim_edilen']) ? intval($_POST['teslim_edilen']) : 0;
    $teslimat_notu = isset($_POST['teslimat_notu']) ? $_POST['teslimat_notu'] : '';
    $irsaliye_no = isset($_POST['irsaliye_no']) ? $_POST['irsaliye_no'] : '';
    
    // Sipariş tedarikçiye ait mi kontrol et
    $siparis_kontrol_sql = "SELECT * FROM siparisler WHERE id = ? AND tedarikci_id = ?";
    $siparis_kontrol_stmt = $db->prepare($siparis_kontrol_sql);
    $siparis_kontrol_stmt->execute([$siparis_id, $tedarikci_id]);
    $siparis = $siparis_kontrol_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$siparis) {
        $hata = "Sipariş bulunamadı veya bu tedarikçiye ait değil!";
    } elseif ($teslim_edilen <= 0) {
        $hata = "Teslim edilen miktar 0'dan büyük olmalıdır!";
    } else {
        try {
            // Teslimatları tutabilecek bir tablo henüz yok, o yüzden onu oluşturalım
            // siparis_teslimatları tablosu yoksa oluştur
            $db->exec("CREATE TABLE IF NOT EXISTS siparis_teslimatlari (
                      id INT AUTO_INCREMENT PRIMARY KEY,
                      siparis_id INT NOT NULL,
                      teslim_edilen INT NOT NULL,
                      teslimat_tarihi DATE NOT NULL,
                      teslimat_notu TEXT,
                      irsaliye_no VARCHAR(50),
                      olusturan_id INT NOT NULL,
                      olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
                      FOREIGN KEY (siparis_id) REFERENCES siparisler(id),
                      FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id)
                    )");
            
            // Teslimat kaydı ekle
            $teslimat_sql = "INSERT INTO siparis_teslimatlari 
                           (siparis_id, teslim_edilen, teslimat_tarihi, teslimat_notu, irsaliye_no, olusturan_id) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $teslimat_stmt = $db->prepare($teslimat_sql);
            $teslimat_stmt->execute([$siparis_id, $teslim_edilen, $teslimat_tarihi, $teslimat_notu, $irsaliye_no, $kullanici_id]);
            
            // Siparişin kalan miktarını güncelle
            $yeni_kalan = max(0, $siparis['kalan_miktar'] - $teslim_edilen);
            
            // Eğer kalan miktar 0 ise ve fai false ise, siparişi tamamlandı olarak işaretle
            $yeni_durum = ($yeni_kalan == 0 && !$siparis['fai']) ? 2 : $siparis['durum_id']; // 2 = Kapalı/Tamamlanmış
            
            $guncelle_sql = "UPDATE siparisler SET kalan_miktar = ?, durum_id = ?, guncelleme_tarihi = NOW() WHERE id = ?";
            $guncelle_stmt = $db->prepare($guncelle_sql);
            $guncelle_stmt->execute([$yeni_kalan, $yeni_durum, $siparis_id]);
            
            // Güncelleme kaydı ekle
            $guncelleme_sql = "INSERT INTO siparis_guncellemeleri 
                             (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleyen_id) 
                             VALUES (?, ?, ?, ?)";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([
                $siparis_id,
                'Teslimat Bildirimi',
                $teslim_edilen . ' adet ürün teslim edildi. İrsaliye No: ' . $irsaliye_no,
                $kullanici_id
            ]);
            
            // Bildirim ekle (sorumlu için)
            if ($siparis['sorumlu_id']) {
                $bildirim_sql = "INSERT INTO bildirimler 
                               (kullanici_id, mesaj, ilgili_siparis_id) 
                               VALUES (?, ?, ?)";
                $bildirim_stmt = $db->prepare($bildirim_sql);
                $bildirim_stmt->execute([
                    $siparis['sorumlu_id'],
                    $tedarikci['firma_adi'] . ' tarafından ' . $siparis['siparis_no'] . ' no\'lu sipariş için ' . $teslim_edilen . ' adet teslimat bildirildi.',
                    $siparis_id
                ]);
            }
            
            $mesaj = "Teslimat başarıyla kaydedildi.";
        } catch (Exception $e) {
            $hata = "Teslimat kaydedilirken bir hata oluştu: " . $e->getMessage();
        }
    }
}

// Teslim edilebilir siparişleri getir (açık olanları)
$acik_siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi
                       FROM siparisler s
                       LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                       LEFT JOIN projeler p ON s.proje_id = p.id
                       WHERE s.tedarikci_id = ? AND s.durum_id = 1 AND s.kalan_miktar > 0
                       ORDER BY s.teslim_tarihi ASC";
$acik_siparisler_stmt = $db->prepare($acik_siparisler_sql);
$acik_siparisler_stmt->execute([$tedarikci_id]);
$acik_siparisler = $acik_siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Önceki teslimatları getir
try {
    // Teslimatlar tablosu var mı diye kontrol et
    $tabloKontrol = $db->query("SHOW TABLES LIKE 'siparis_teslimatlari'");
    $teslimatlarTablosuVar = $tabloKontrol->rowCount() > 0;
    
    if ($teslimatlarTablosuVar) {
        $teslimatlar_sql = "SELECT st.*, s.siparis_no, s.parca_no, s.tanim
                           FROM siparis_teslimatlari st
                           INNER JOIN siparisler s ON st.siparis_id = s.id
                           WHERE s.tedarikci_id = ?
                           ORDER BY st.teslimat_tarihi DESC";
        $teslimatlar_stmt = $db->prepare($teslimatlar_sql);
        $teslimatlar_stmt->execute([$tedarikci_id]);
        $teslimatlar = $teslimatlar_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $teslimatlar = [];
    }
} catch (Exception $e) {
    // Tablo yoksa boş dizi döndür
    $teslimatlar = [];
}

// Okunmamış bildirimleri al
$okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $kullanici_id);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Teslimatlarım</h2>
    <a href="yeni_teslimat.php" class="btn btn-success"><i class="bi bi-plus-lg"></i> Yeni Teslimat</a>
</div>

<!-- Filtreleme ve arama formu -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="card-title mb-0">Filtreleme</h5>
    </div>
    <div class="card-body">
        <form method="get" action="" class="row g-3">
            <div class="col-md-3">
                <label for="proje_id" class="form-label">Proje</label>
                <select name="proje_id" id="proje_id" class="form-select">
                    <option value="0">Tüm Projeler</option>
                    <?php foreach ($projeler as $proje): ?>
                        <option value="<?= $proje['id'] ?>" <?= $proje_id == $proje['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proje['proje_adi']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="tarih_baslangic" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="tarih_baslangic" name="tarih_baslangic" value="<?= $tarih_baslangic ?>">
            </div>
            <div class="col-md-3">
                <label for="tarih_bitis" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="tarih_bitis" name="tarih_bitis" value="<?= $tarih_bitis ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Filtrele</button>
            </div>
        </form>
    </div>
</div>

<!-- Teslimatlar tablosu -->
<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="card-title mb-0">Teslimat Listesi</h5>
    </div>
    <div class="card-body">
        <?php if (empty($teslimatlar)): ?>
            <div class="alert alert-info">
                Herhangi bir teslimat kaydı bulunamadı.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Sipariş No</th>
                            <th>Parça No</th>
                            <th>Proje</th>
                            <th>Teslimat Tarihi</th>
                            <th>Miktar</th>
                            <th>İrsaliye No</th>
                            <th>Not</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teslimatlar as $teslimat): ?>
                            <tr>
                                <td>
                                    <a href="siparis_detay.php?id=<?= $teslimat['siparis_id'] ?>">
                                        <?= htmlspecialchars($teslimat['siparis_no']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($teslimat['parca_no']) ?></td>
                                <td><?= htmlspecialchars($teslimat['proje_adi']) ?></td>
                                <td><?= date('d.m.Y', strtotime($teslimat['teslimat_tarihi'])) ?></td>
                                <td><?= $teslimat['teslim_edilen'] ?> <?= htmlspecialchars($teslimat['birim']) ?></td>
                                <td><?= htmlspecialchars($teslimat['irsaliye_no'] ?? '-') ?></td>
                                <td><?= htmlspecialchars(mb_substr($teslimat['teslimat_notu'] ?? '', 0, 30)) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="siparis_detay.php?id=<?= $teslimat['siparis_id'] ?>" class="btn btn-info" title="Sipariş Detayı">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="teslimat_detay.php?id=<?= $teslimat['id'] ?>" class="btn btn-primary" title="Teslimat Detayı">
                                            <i class="bi bi-info-circle"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sayfalama -->
            <?php if ($toplam_sayfa > 1): ?>
                <nav aria-label="Teslimatlar sayfalama" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= $sayfa <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?sayfa=<?= $sayfa - 1 ?><?= $filtered_query ?>" aria-label="Önceki">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($i = 1; $i <= $toplam_sayfa; $i++): ?>
                            <li class="page-item <?= $i == $sayfa ? 'active' : '' ?>">
                                <a class="page-link" href="?sayfa=<?= $i ?><?= $filtered_query ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= $sayfa >= $toplam_sayfa ? 'disabled' : '' ?>">
                            <a class="page-link" href="?sayfa=<?= $sayfa + 1 ?><?= $filtered_query ?>" aria-label="Sonraki">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php
include 'footer.php';
?> 