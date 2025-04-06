<?php
// tedarikci/siparis_guncelle.php - Tedarikçinin sipariş güncelleme sayfası
require_once '../config.php';
tedarikciYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Sipariş Güncelle";

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

// Hata ve başarı mesajları için değişkenler
$hata_mesaji = '';
$basari_mesaji = '';

// Sipariş ID kontrolü
$siparis_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($siparis_id <= 0) {
    header("Location: siparislerim.php");
    exit;
}

// Tedarikçiye ait sipariş bilgisini getir
$siparis_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, u.ad_soyad as sorumlu_adi
                FROM siparisler s
                LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                LEFT JOIN projeler p ON s.proje_id = p.id
                LEFT JOIN kullanicilar u ON s.sorumlu_id = u.id
                WHERE s.id = ? AND s.tedarikci_id = ?";
$siparis_stmt = $db->prepare($siparis_sql);
$siparis_stmt->execute([$siparis_id, $tedarikci_id]);
$siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);

if (!$siparis) {
    // Sipariş bulunamadı veya bu tedarikçiye ait değil
    header("Location: siparislerim.php");
    exit;
}

// Mevcut dokümanları al
$dokumanlar_sql = "SELECT * FROM siparis_dokumanlari WHERE siparis_id = ? ORDER BY yukleme_tarihi DESC";
$dokumanlar_stmt = $db->prepare($dokumanlar_sql);
$dokumanlar_stmt->execute([$siparis_id]);
$mevcut_dokumanlar = $dokumanlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş güncellemelerini getir
$guncelleme_sql = "SELECT sg.*, k.ad_soyad
                  FROM siparis_guncellemeleri sg
                  LEFT JOIN kullanicilar k ON sg.guncelleyen_id = k.id
                  WHERE sg.siparis_id = ?
                  ORDER BY sg.guncelleme_tarihi DESC";
$guncelleme_stmt = $db->prepare($guncelleme_sql);
$guncelleme_stmt->execute([$siparis_id]);
$guncellemeler = $guncelleme_stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form verilerini al
    $aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : '';
    $not = isset($_POST['not']) ? trim($_POST['not']) : '';
    $durum_id = isset($_POST['durum_id']) ? intval($_POST['durum_id']) : 1;
    $tahmini_teslim_tarihi = isset($_POST['tahmini_teslim_tarihi']) ? $_POST['tahmini_teslim_tarihi'] : null;
    
    // Veri doğrulama
    if (empty($not)) {
        $hata_mesaji = 'Güncelleme notu boş olamaz.';
    } else {
        try {
            $db->beginTransaction();
            
            // Sipariş güncelleme notu ekle
            $guncelleme_sql = "INSERT INTO siparis_guncellemeleri (siparis_id, guncelleyen_id, guncelleme_aciklamasi, guncelleme_tarihi) 
                             VALUES (?, ?, ?, NOW())";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([$siparis_id, $kullanici_id, $not]);
            
            // Siparişi güncelle
            $siparis_guncelle_sql = "UPDATE siparisler SET 
                                   aciklama = ?,
                                   tahmini_teslim_tarihi = ?,
                                   durum_id = ?,
                                   guncelleme_tarihi = NOW() 
                                   WHERE id = ? AND tedarikci_id = ?";
            $siparis_guncelle_stmt = $db->prepare($siparis_guncelle_sql);
            $siparis_guncelle_stmt->execute([
                $aciklama,
                $tahmini_teslim_tarihi,
                $durum_id,
                $siparis_id,
                $tedarikci_id
            ]);
            
            // Doküman yükleme işlemi
            if (isset($_FILES['dokuman']) && $_FILES['dokuman']['error'][0] != UPLOAD_ERR_NO_FILE) {
                $dosya_sayisi = count($_FILES['dokuman']['name']);
                
                for ($i = 0; $i < $dosya_sayisi; $i++) {
                    if ($_FILES['dokuman']['error'][$i] == UPLOAD_ERR_OK) {
                        $tmp_name = $_FILES['dokuman']['tmp_name'][$i];
                        $name = $_FILES['dokuman']['name'][$i];
                        $type = $_FILES['dokuman']['type'][$i];
                        $size = $_FILES['dokuman']['size'][$i];
                        
                        // Dosya adını temizle ve benzersiz hale getir
                        $temiz_dosya_adi = preg_replace('/[^A-Za-z0-9_.-]/', '_', $name);
                        $benzersiz_dosya_adi = date('YmdHis') . '_' . $temiz_dosya_adi;
                        
                        // Dosya dizinini oluştur
                        $upload_dir = '../uploads/siparisler/' . $siparis_id . '/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $dosya_yolu = $upload_dir . $benzersiz_dosya_adi;
                        
                        // Dosyayı yükle
                        if (move_uploaded_file($tmp_name, $dosya_yolu)) {
                            // Doküman bilgisini veritabanına kaydet
                            $dokuman_sql = "INSERT INTO siparis_dokumanlari 
                                          (siparis_id, dosya_adi, dosya_yolu, dosya_boyutu, dosya_turu, yukleme_tarihi, yukleyen_id) 
                                          VALUES (?, ?, ?, ?, ?, NOW(), ?)";
                            $dokuman_stmt = $db->prepare($dokuman_sql);
                            $dokuman_stmt->execute([
                                $siparis_id,
                                $name,
                                'uploads/siparisler/' . $siparis_id . '/' . $benzersiz_dosya_adi,
                                $size,
                                $type,
                                $kullanici_id
                            ]);
                        } else {
                            throw new Exception('Dosya yükleme hatası: ' . $name);
                        }
                    }
                }
            }
            
            $db->commit();
            
            // Bildirimi gönder
            $bildirim_mesaji = $tedarikci['firma_adi'] . ' - ' . $siparis['siparis_no'] . ' nolu sipariş güncellendi.';
            $sorumlu_id = $siparis['sorumlu_id'];
            
            if ($sorumlu_id) {
                // Sorumluya bildirim gönder
                $bildirim_sql = "INSERT INTO bildirimler (kullanici_id, ilgili_siparis_id, mesaj, bildirim_tarihi, okundu) 
                               VALUES (?, ?, ?, NOW(), 0)";
                $bildirim_stmt = $db->prepare($bildirim_sql);
                $bildirim_stmt->execute([$sorumlu_id, $siparis_id, $bildirim_mesaji]);
            }
            
            $basari_mesaji = 'Sipariş başarıyla güncellendi.';
            
            // Sayfayı yenile (POST-Redirect-GET desenine uygun olarak)
            header("Location: siparis_detay.php?id=" . $siparis_id . "&basari=1");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $hata_mesaji = 'Hata oluştu: ' . $e->getMessage();
        }
    }
}

// Sipariş durumlarını al
$durumlar_sql = "SELECT * FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Header'ı dahil et
include 'header.php';

// Sipariş detay sayfasını ve sipariş güncelleme formunu buraya ekleyin
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Sipariş Güncelleme: <?= guvenli($siparis['siparis_no']) ?></h2>
    <a href="siparis_detay.php?id=<?= $siparis_id ?>" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Sipariş Detayına Dön</a>
</div>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 