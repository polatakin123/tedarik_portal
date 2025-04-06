<?php
// admin/kullanicilar.php - Kullanıcı yönetim sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Kullanıcılar";

// Kullanıcı silme işlemi
if (isset($_GET['sil']) && !empty($_GET['sil'])) {
    $kullanici_id = intval($_GET['sil']);
    
    // Kendini silemez
    if ($kullanici_id == $_SESSION['kullanici_id']) {
        $hata = "Kendi hesabınızı silemezsiniz!";
        header("Location: kullanicilar.php?hata=" . urlencode($hata));
        exit;
    }
    
    // Admin yetkisine sahip son kullanıcıyı silemez
    $admin_sayisi = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'Admin' AND aktif = 1")->fetchColumn();
    $kullanici_rol = $db->query("SELECT rol FROM kullanicilar WHERE id = $kullanici_id")->fetchColumn();
    
    if ($admin_sayisi <= 1 && $kullanici_rol == 'Admin') {
        $hata = "Son aktif admin kullanıcısını silemezsiniz!";
        header("Location: kullanicilar.php?hata=" . urlencode($hata));
        exit;
    }
    
    try {
        // Önce kullanıcının ilişkilerini temizle
        $db->beginTransaction();
        
        // Kullanıcı-tedarikçi ilişkilerini temizle
        $iliskiSil = $db->prepare("DELETE FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?");
        $iliskiSil->execute([$kullanici_id]);
        
        // Sorumlulukları temizle
        $sorumlulukSil = $db->prepare("DELETE FROM sorumluluklar WHERE sorumlu_id = ?");
        $sorumlulukSil->execute([$kullanici_id]);
        
        // Bildirimleri temizle
        $bildirimSil = $db->prepare("DELETE FROM bildirimler WHERE kullanici_id = ?");
        $bildirimSil->execute([$kullanici_id]);
        
        // Kullanıcıyı sil
        $kullaniciSil = $db->prepare("DELETE FROM kullanicilar WHERE id = ?");
        $kullaniciSil->execute([$kullanici_id]);
        
        $db->commit();
        
        $mesaj = "Kullanıcı başarıyla silindi.";
        header("Location: kullanicilar.php?mesaj=" . urlencode($mesaj));
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $hata = "Kullanıcı silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: kullanicilar.php?hata=" . urlencode($hata));
        exit;
    }
}

// Aktif/pasif durumu değiştirme
if (isset($_GET['durum']) && !empty($_GET['durum']) && isset($_GET['id']) && !empty($_GET['id'])) {
    $kullanici_id = intval($_GET['id']);
    $yeni_durum = ($_GET['durum'] == 'aktif') ? 1 : 0;
    
    // Kendini pasif yapamaz
    if ($kullanici_id == $_SESSION['kullanici_id'] && $yeni_durum == 0) {
        $hata = "Kendi hesabınızı pasif yapamazsınız!";
        header("Location: kullanicilar.php?hata=" . urlencode($hata));
        exit;
    }
    
    // Admin yetkisine sahip son kullanıcıyı pasif yapamaz
    if ($yeni_durum == 0) {
        $admin_sayisi = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'Admin' AND aktif = 1")->fetchColumn();
        $kullanici_rol = $db->query("SELECT rol FROM kullanicilar WHERE id = $kullanici_id")->fetchColumn();
        
        if ($admin_sayisi <= 1 && $kullanici_rol == 'Admin') {
            $hata = "Son aktif admin kullanıcısını pasif yapamazsınız!";
            header("Location: kullanicilar.php?hata=" . urlencode($hata));
            exit;
        }
    }
    
    try {
        $sql = "UPDATE kullanicilar SET aktif = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$yeni_durum, $kullanici_id]);
        
        $durum_metin = ($yeni_durum == 1) ? "aktif" : "pasif";
        $mesaj = "Kullanıcı durumu $durum_metin olarak güncellendi.";
        header("Location: kullanicilar.php?mesaj=" . urlencode($mesaj));
        exit;
    } catch (PDOException $e) {
        $hata = "Kullanıcı durumu güncellenirken bir hata oluştu: " . $e->getMessage();
        header("Location: kullanicilar.php?hata=" . urlencode($hata));
        exit;
    }
}

// Filtreleme parametreleri
$rol = isset($_GET['rol']) ? $_GET['rol'] : '';
$durum = isset($_GET['durum']) ? $_GET['durum'] : '';
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';

// SQL sorgusu ve parametreleri oluştur
$sql = "SELECT * FROM kullanicilar WHERE 1=1";
$params = [];

if (!empty($rol)) {
    $sql .= " AND rol = ?";
    $params[] = $rol;
}

if ($durum !== '') {
    $sql .= " AND aktif = ?";
    $params[] = ($durum == 'aktif') ? 1 : 0;
}

if (!empty($arama)) {
    $sql .= " AND (ad_soyad LIKE ? OR email LIKE ? OR kullanici_adi LIKE ? OR telefon LIKE ?)";
    $arama_param = "%$arama%";
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
    $params[] = $arama_param;
}

$sql .= " ORDER BY rol ASC, ad_soyad ASC";

// Kullanıcıları getir
$stmt = $db->prepare($sql);
$stmt->execute($params);
$kullanicilar = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Kullanıcı rollerini getir
$roller = $db->query("SELECT DISTINCT rol FROM kullanicilar ORDER BY rol")->fetchAll(PDO::FETCH_COLUMN);

// Header'ı dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kullanıcılar</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="kullanici_ekle.php" class="btn btn-primary">
            <i class="bi bi-person-plus"></i> Yeni Kullanıcı Ekle
        </a>
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

<div class="card mb-4">
    <div class="card-body">
        <form action="" method="get" class="row g-3">
            <div class="col-md-3">
                <label for="rol" class="form-label">Kullanıcı Rolü</label>
                <select class="form-select" id="rol" name="rol">
                    <option value="">Tümü</option>
                    <?php foreach ($roller as $r): ?>
                        <option value="<?= guvenli($r) ?>" <?= ($rol == $r) ? 'selected' : '' ?>>
                            <?= guvenli($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="durum" class="form-label">Durum</label>
                <select class="form-select" id="durum" name="durum">
                    <option value="">Tümü</option>
                    <option value="aktif" <?= ($durum == 'aktif') ? 'selected' : '' ?>>Aktif</option>
                    <option value="pasif" <?= ($durum == 'pasif') ? 'selected' : '' ?>>Pasif</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="arama" class="form-label">Ara</label>
                <input type="text" class="form-control" id="arama" name="arama" placeholder="Ad, e-posta, kullanıcı adı veya telefon" value="<?= guvenli($arama) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filtrele
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Tüm Kullanıcılar (<?= count($kullanicilar) ?>)</h5>
            <?php if (!empty($rol) || !empty($durum) || !empty($arama)): ?>
                <a href="kullanicilar.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Filtreleri Temizle
                </a>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if (count($kullanicilar) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ad Soyad</th>
                            <th>Kullanıcı Adı</th>
                            <th>E-posta</th>
                            <th>Telefon</th>
                            <th>Rol</th>
                            <th>Son Giriş</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kullanicilar as $kullanici): ?>
                            <tr>
                                <td><?= $kullanici['id'] ?></td>
                                <td>
                                    <a href="kullanici_detay.php?id=<?= $kullanici['id'] ?>" class="text-decoration-none">
                                        <?= guvenli($kullanici['ad_soyad']) ?>
                                    </a>
                                </td>
                                <td><?= guvenli($kullanici['kullanici_adi']) ?></td>
                                <td><?= guvenli($kullanici['email']) ?></td>
                                <td><?= guvenli($kullanici['telefon']) ?></td>
                                <td>
                                    <?php
                                    $rol_renk = '';
                                    switch ($kullanici['rol']) {
                                        case 'Admin': $rol_renk = 'danger'; break;
                                        case 'Sorumlu': $rol_renk = 'warning'; break;
                                        case 'Tedarikci': $rol_renk = 'success'; break;
                                        default: $rol_renk = 'secondary';
                                    }
                                    ?>
                                    <span class="badge bg-<?= $rol_renk ?>"><?= guvenli($kullanici['rol']) ?></span>
                                </td>
                                <td><?= $kullanici['son_giris'] ? tarihFormatla($kullanici['son_giris']) : '-' ?></td>
                                <td>
                                    <?php if ($kullanici['aktif']): ?>
                                        <span class="badge bg-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="kullanici_detay.php?id=<?= $kullanici['id'] ?>" class="btn btn-sm btn-info" title="Görüntüle">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="kullanici_duzenle.php?id=<?= $kullanici['id'] ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        
                                        <?php if ($kullanici['id'] != $_SESSION['kullanici_id']): ?>
                                            <?php if ($kullanici['aktif']): ?>
                                                <a href="kullanicilar.php?durum=pasif&id=<?= $kullanici['id'] ?>" class="btn btn-sm btn-secondary" 
                                                  onclick="return confirm('<?= guvenli($kullanici['ad_soyad']) ?> kullanıcısını pasif yapmak istediğinize emin misiniz?');" 
                                                  title="Pasif Yap">
                                                    <i class="bi bi-toggle-off"></i>
                                                </a>
                                            <?php else: ?>
                                                <a href="kullanicilar.php?durum=aktif&id=<?= $kullanici['id'] ?>" class="btn btn-sm btn-success" 
                                                  onclick="return confirm('<?= guvenli($kullanici['ad_soyad']) ?> kullanıcısını aktif yapmak istediğinize emin misiniz?');" 
                                                  title="Aktif Yap">
                                                    <i class="bi bi-toggle-on"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <a href="kullanicilar.php?sil=<?= $kullanici['id'] ?>" class="btn btn-sm btn-danger" 
                                              onclick="return confirm('<?= guvenli($kullanici['ad_soyad']) ?> kullanıcısını silmek istediğinize emin misiniz? Bu işlem geri alınamaz!');" 
                                              title="Sil">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <?php if (!empty($rol) || !empty($durum) || !empty($arama)): ?>
                    <i class="bi bi-info-circle"></i> Arama kriterlerinize uygun kullanıcı bulunamadı.
                <?php else: ?>
                    <i class="bi bi-info-circle"></i> Henüz kullanıcı bulunmamaktadır.
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?> 