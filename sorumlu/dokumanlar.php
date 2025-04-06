<?php
// sorumlu/dokumanlar.php - Sipariş belgeleri yönetim sayfası
require_once '../config.php';
sorumluYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Sipariş Belgeleri";

$sorumlu_id = $_SESSION['kullanici_id'];
$siparis_id = isset($_POST['siparis_id']) ? intval($_POST['siparis_id']) : (isset($_GET['siparis_id']) ? intval($_GET['siparis_id']) : 0);
$hata_mesaji = '';
$basari_mesaji = '';

// Kullanıcının erişebildiği tüm siparişleri al
$siparisler_sql = "SELECT s.id, s.siparis_no, s.parca_no, s.tanim, t.firma_adi, p.proje_adi
                  FROM siparisler s
                  LEFT JOIN projeler p ON s.proje_id = p.id
                  LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                  WHERE s.sorumlu_id = ? OR s.tedarikci_id IN (
                      SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                  )
                  ORDER BY s.id DESC";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute([$sorumlu_id, $sorumlu_id]);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

$siparis = null;
if ($siparis_id > 0) {
    // Seçilen siparişi kontrol et
    $siparis_sql = "SELECT s.*, p.proje_adi, t.firma_adi
                   FROM siparisler s
                   LEFT JOIN projeler p ON s.proje_id = p.id
                   LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                   WHERE s.id = ? AND (s.sorumlu_id = ? OR s.tedarikci_id IN (
                       SELECT tedarikci_id FROM sorumluluklar WHERE sorumlu_id = ?
                   ))";
    $siparis_stmt = $db->prepare($siparis_sql);
    $siparis_stmt->execute([$siparis_id, $sorumlu_id, $sorumlu_id]);
    $siparis = $siparis_stmt->fetch(PDO::FETCH_ASSOC);
}

// Belge yükleme işlemi - eğer sipariş seçilmişse
if ($siparis && isset($_POST['yukle']) && isset($_FILES['dokuman'])) {
    $izin_verilen_uzantilar = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
    $dokuman_adi = $_POST['dokuman_adi'];
    
    $dosya = $_FILES['dokuman'];
    $dosya_adi = $dosya['name'];
    $dosya_boyutu = $dosya['size'];
    $dosya_tmp = $dosya['tmp_name'];
    $dosya_hata = $dosya['error'];
    
    // Hata kontrolü
    if ($dosya_hata !== UPLOAD_ERR_OK) {
        switch ($dosya_hata) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $hata_mesaji = "Dosya boyutu izin verilenden büyük.";
                break;
            case UPLOAD_ERR_PARTIAL:
                $hata_mesaji = "Dosya yalnızca kısmen yüklenmiş.";
                break;
            case UPLOAD_ERR_NO_FILE:
                $hata_mesaji = "Hiçbir dosya yüklenmedi.";
                break;
            default:
                $hata_mesaji = "Bilinmeyen bir hata oluştu.";
        }
    } else {
        $uzanti = strtolower(pathinfo($dosya_adi, PATHINFO_EXTENSION));
        
        // Uzantıyı kontrol et
        if (!in_array($uzanti, $izin_verilen_uzantilar)) {
            $hata_mesaji = "Desteklenmeyen dosya formatı. İzin verilen formatlar: " . implode(', ', $izin_verilen_uzantilar);
        } else {
            // Dosyayı kaydetme
            // temizle() fonksiyonu olmadığı için güvenli bir yöntem kullanıyoruz
            $guvenliFonksiyon = function($metin) {
                // Türkçe karakterleri ve diğer özel karakterleri temizle
                $tr = array('ç','Ç','ğ','Ğ','ı','İ','ö','Ö','ş','Ş','ü','Ü',' ');
                $eng = array('c','C','g','G','i','I','o','O','s','S','u','U','_');
                $metin = str_replace($tr, $eng, $metin);
                // Sadece alfanumerik karakterleri, nokta ve alt çizgiyi bırak
                $metin = preg_replace('/[^a-zA-Z0-9\.\-_]/', '', $metin);
                return $metin;
            };
            
            $yeni_dosya_adi = uniqid() . "_" . $guvenliFonksiyon($dosya_adi);
            $upload_dir = "../uploads/";
            
            // Upload klasörünün varlığını kontrol et, yoksa oluştur
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $yuklenecek_yol = $upload_dir . $yeni_dosya_adi;
            
            if (move_uploaded_file($dosya_tmp, $yuklenecek_yol)) {
                // Dosya türünü belirle
                $dosya_turu = '';
                if (in_array($uzanti, ['pdf'])) $dosya_turu = 'PDF';
                elseif (in_array($uzanti, ['doc', 'docx'])) $dosya_turu = 'Word';
                elseif (in_array($uzanti, ['xls', 'xlsx'])) $dosya_turu = 'Excel';
                elseif (in_array($uzanti, ['jpg', 'jpeg', 'png'])) $dosya_turu = 'Image';
                elseif (in_array($uzanti, ['zip', 'rar'])) $dosya_turu = 'Archive';
                else $dosya_turu = strtoupper($uzanti);
                
                try {
                    // Veritabanına belge kaydı ekleme
                    $ekle_sql = "INSERT INTO siparis_dokumanlari (siparis_id, dokuman_adi, dosya_yolu, dosya_turu, yukleme_tarihi, yukleyen_id, dosya_boyutu) 
                                 VALUES (?, ?, ?, ?, NOW(), ?, ?)";
                    $ekle_stmt = $db->prepare($ekle_sql);
                    $ekle_stmt->execute([$siparis_id, $dokuman_adi, $yeni_dosya_adi, $dosya_turu, $sorumlu_id, $dosya_boyutu]);
                    
                    // Sipariş güncellemesi log kaydı
                    $log_sql = "INSERT INTO siparis_log (siparis_id, islem_turu, islem_yapan_id, islem_tarihi, aciklama) 
                               VALUES (?, 'Belge Ekleme', ?, NOW(), ?)";
                    $log_stmt = $db->prepare($log_sql);
                    $log_stmt->execute([$siparis_id, $sorumlu_id, "'$dokuman_adi' belgesi eklendi."]);
                    
                    // Güncelleme tablosuna kayıt ekle
                    $guncelleme_sql = "INSERT INTO siparis_guncellemeleri (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleme_tarihi, guncelleyen_id) 
                                      VALUES (?, 'Belge Ekleme', ?, NOW(), ?)";
                    $guncelleme_stmt = $db->prepare($guncelleme_sql);
                    $guncelleme_stmt->execute([$siparis_id, "Belge: $dokuman_adi eklendi.", $sorumlu_id]);
                    
                    $basari_mesaji = "Belge başarıyla yüklendi.";
                } catch (PDOException $e) {
                    $hata_mesaji = "Belge veritabanına kaydedilirken bir hata oluştu: " . $e->getMessage();
                    // Yüklenen dosyayı sil
                    unlink($yuklenecek_yol);
                }
            } else {
                $hata_mesaji = "Dosya yüklenirken bir hata oluştu.";
            }
        }
    }
}

// Belge silme işlemi
if ($siparis && isset($_GET['sil']) && !empty($_GET['sil'])) {
    $dokuman_id = intval($_GET['sil']);
    
    // Belgenin bu siparişe ait olduğundan emin ol
    $dokuman_kontrol_sql = "SELECT * FROM siparis_dokumanlari WHERE id = ? AND siparis_id = ?";
    $dokuman_kontrol_stmt = $db->prepare($dokuman_kontrol_sql);
    $dokuman_kontrol_stmt->execute([$dokuman_id, $siparis_id]);
    $dokuman = $dokuman_kontrol_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($dokuman) {
        try {
            $db->beginTransaction();
            
            // Dosyayı fiziksel olarak sil
            $dosya_yolu = "../uploads/" . $dokuman['dosya_yolu'];
            if (file_exists($dosya_yolu)) {
                unlink($dosya_yolu);
            }
            
            // Veritabanından kaydı sil
            $sil_sql = "DELETE FROM siparis_dokumanlari WHERE id = ?";
            $sil_stmt = $db->prepare($sil_sql);
            $sil_stmt->execute([$dokuman_id]);
            
            // Sipariş log kaydı ekle
            $log_sql = "INSERT INTO siparis_log (siparis_id, islem_turu, islem_yapan_id, islem_tarihi, aciklama) 
                       VALUES (?, 'Belge Silme', ?, NOW(), ?)";
            $log_stmt = $db->prepare($log_sql);
            $log_stmt->execute([$siparis_id, $sorumlu_id, "'{$dokuman['dokuman_adi']}' belgesi silindi."]);
            
            // Güncelleme tablosuna kayıt ekle
            $guncelleme_sql = "INSERT INTO siparis_guncellemeleri (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleme_tarihi, guncelleyen_id) 
                              VALUES (?, 'Belge Silme', ?, NOW(), ?)";
            $guncelleme_stmt = $db->prepare($guncelleme_sql);
            $guncelleme_stmt->execute([$siparis_id, "Belge: {$dokuman['dokuman_adi']} silindi.", $sorumlu_id]);
            
            $db->commit();
            $basari_mesaji = "Belge başarıyla silindi.";
        } catch (PDOException $e) {
            $db->rollBack();
            $hata_mesaji = "Belge silinirken bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $hata_mesaji = "Belge bulunamadı veya bu siparişe ait değil.";
    }
}

$dokumanlar = [];
$yukleyenler = [];

// Eğer bir sipariş seçilmişse belgeleri al
if ($siparis) {
    // Belgeleri al
    $dokuman_sql = "SELECT * FROM siparis_dokumanlari WHERE siparis_id = ? ORDER BY yukleme_tarihi DESC";
    $dokuman_stmt = $db->prepare($dokuman_sql);
    $dokuman_stmt->execute([$siparis_id]);
    $dokumanlar = $dokuman_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Kullanıcı bilgilerini çek (yükleyenleri göstermek için)
    $yukleyenler_ids = array_unique(array_column($dokumanlar, 'yukleyen_id'));
    
    if (!empty($yukleyenler_ids)) {
        $yukleyenler_sql = "SELECT id, ad_soyad FROM kullanicilar WHERE id IN (" . implode(',', $yukleyenler_ids) . ")";
        $yukleyenler_stmt = $db->prepare($yukleyenler_sql);
        $yukleyenler_stmt->execute();
        
        foreach ($yukleyenler_stmt->fetchAll(PDO::FETCH_ASSOC) as $kullanici) {
            $yukleyenler[$kullanici['id']] = $kullanici['ad_soyad'];
        }
    }
}

// Özel CSS
$extra_css = "
.document-card {
    transition: all 0.3s ease;
}
.document-card:hover {
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.15);
}
.document-icon {
    font-size: 2rem;
    padding: 1rem;
    border-radius: 50%;
    margin-bottom: 1rem;
}
.pdf-bg { background-color: #f5dddd; color: #dc3545; }
.word-bg { background-color: #dbe8f5; color: #0d6efd; }
.excel-bg { background-color: #ddf5e0; color: #198754; }
.image-bg { background-color: #f5eedd; color: #ffc107; }
.archive-bg { background-color: #e8ddf5; color: #6f42c1; }
.other-bg { background-color: #f5f5f5; color: #6c757d; }
";

// Header'ı dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Sipariş Belgeleri</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <?php if ($siparis): ?>
            <a href="siparis_detay.php?id=<?= $siparis_id ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i> Sipariş Detayını Görüntüle
            </a>
            <?php endif; ?>
            <a href="siparisler.php" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-list"></i> Tüm Siparişler
            </a>
        </div>
    </div>
</div>

<?php if (!empty($hata_mesaji)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $hata_mesaji ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<?php if (!empty($basari_mesaji)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $basari_mesaji ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
<?php endif; ?>

<!-- Sipariş Seçimi -->
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-search"></i> Sipariş Seçimi</h5>
    </div>
    <div class="card-body">
        <form method="post" action="dokumanlar.php">
            <div class="row">
                <div class="col-md-8">
                    <div class="form-group">
                        <label for="siparis_id" class="form-label">Sipariş Seçin</label>
                        <select class="form-select" id="siparis_id" name="siparis_id" required>
                            <option value="">-- Sipariş Seçin --</option>
                            <?php foreach ($siparisler as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ($siparis_id == $s['id']) ? 'selected' : '' ?>>
                                    <?= guvenli($s['siparis_no']) ?> - <?= guvenli($s['parca_no']) ?> (<?= guvenli($s['firma_adi']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Belgeleri Göster
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($siparis): ?>
<!-- Seçilen Sipariş Bilgileri -->
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Sipariş Bilgileri</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <strong>Sipariş No:</strong> <?= guvenli($siparis['siparis_no']) ?>
            </div>
            <div class="col-md-3">
                <strong>Parça No:</strong> <?= guvenli($siparis['parca_no']) ?>
            </div>
            <div class="col-md-3">
                <strong>Tedarikçi:</strong> <?= guvenli($siparis['firma_adi']) ?>
            </div>
            <div class="col-md-3">
                <strong>Proje:</strong> <?= guvenli($siparis['proje_adi']) ?>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-md-6">
                <strong>Tanım:</strong> <?= guvenli($siparis['tanim']) ?>
            </div>
            <div class="col-md-3">
                <strong>Miktar:</strong> <?= guvenli($siparis['miktar']) ?> <?= guvenli($siparis['birim']) ?>
            </div>
            <div class="col-md-3">
                <strong>Açılış Tarihi:</strong> <?= date('d.m.Y', strtotime($siparis['acilis_tarihi'])) ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Yeni Belge Yükleme Formu -->
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-upload"></i> Yeni Belge Yükle</h5>
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="siparis_id" value="<?= $siparis_id ?>">
                    <div class="mb-3">
                        <label for="dokuman_adi" class="form-label">Belge Adı</label>
                        <input type="text" class="form-control" id="dokuman_adi" name="dokuman_adi" required>
                    </div>
                    <div class="mb-3">
                        <label for="dokuman" class="form-label">Dosya Seçin</label>
                        <input type="file" class="form-control" id="dokuman" name="dokuman" required>
                        <div class="form-text">İzin verilen formatlar: pdf, doc, docx, xls, xlsx, jpg, jpeg, png, zip, rar</div>
                    </div>
                    <div class="d-grid">
                        <button type="submit" name="yukle" class="btn btn-success">
                            <i class="bi bi-cloud-upload"></i> Belgeyi Yükle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Belge Listesi -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="bi bi-file-earmark"></i> Mevcut Belgeler</h5>
            </div>
            <div class="card-body">
                <?php if (count($dokumanlar) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Belge Adı</th>
                                    <th>Tür</th>
                                    <th>Boyut</th>
                                    <th>Yükleyen</th>
                                    <th>Tarih</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dokumanlar as $dokuman): ?>
                                    <tr>
                                        <td><?= guvenli($dokuman['dokuman_adi']) ?></td>
                                        <td>
                                            <?php
                                            $icon = 'file-earmark';
                                            $dosya_turu = strtolower($dokuman['dosya_turu']);
                                            if (strpos($dosya_turu, 'pdf') !== false) $icon = 'file-earmark-pdf';
                                            elseif (strpos($dosya_turu, 'word') !== false || strpos($dosya_turu, 'doc') !== false) $icon = 'file-earmark-word';
                                            elseif (strpos($dosya_turu, 'excel') !== false || strpos($dosya_turu, 'xls') !== false) $icon = 'file-earmark-excel';
                                            elseif (strpos($dosya_turu, 'image') !== false || strpos($dosya_turu, 'jpg') !== false || strpos($dosya_turu, 'png') !== false) $icon = 'file-earmark-image';
                                            elseif (strpos($dosya_turu, 'archive') !== false || strpos($dosya_turu, 'zip') !== false || strpos($dosya_turu, 'rar') !== false) $icon = 'file-earmark-zip';
                                            ?>
                                            <i class="bi bi-<?= $icon ?>"></i> <?= guvenli($dokuman['dosya_turu']) ?>
                                        </td>
                                        <td>
                                            <?php if (isset($dokuman['dosya_boyutu'])): ?>
                                                <?= round($dokuman['dosya_boyutu'] / 1024, 2) ?> KB
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= isset($yukleyenler[$dokuman['yukleyen_id']]) ? guvenli($yukleyenler[$dokuman['yukleyen_id']]) : 'Bilinmiyor' ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i', strtotime($dokuman['yukleme_tarihi'])) ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="../uploads/<?= $dokuman['dosya_yolu'] ?>" target="_blank" class="btn btn-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="dokumanlar.php?siparis_id=<?= $siparis_id ?>&sil=<?= $dokuman['id'] ?>" class="btn btn-danger" onclick="return confirm('Bu belgeyi silmek istediğinizden emin misiniz?')">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Bu siparişe ait belge bulunmamaktadır.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <!-- Eğer sipariş seçilmemişse bilgi mesajı -->
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> Belgeleri görüntülemek için lütfen yukarıdan bir sipariş seçin.
    </div>
<?php endif; ?>

<?php include 'footer.php'; ?> 