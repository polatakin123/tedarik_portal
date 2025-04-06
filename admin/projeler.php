<?php
// admin/projeler.php - Proje yönetim sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Projeler";

// Proje silme işlemi
if (isset($_GET['sil']) && !empty($_GET['sil'])) {
    $proje_id = intval($_GET['sil']);
    
    // Önce projeye bağlı siparişleri kontrol et
    $siparis_kontrol = $db->prepare("SELECT COUNT(*) FROM siparisler WHERE proje_id = ?");
    $siparis_kontrol->execute([$proje_id]);
    $siparis_sayisi = $siparis_kontrol->fetchColumn();
    
    if ($siparis_sayisi > 0) {
        $hata = "Bu projeye ait " . $siparis_sayisi . " adet sipariş bulunduğu için silinemez! Önce bağlı siparişleri silmeniz veya başka bir projeye aktarmanız gerekiyor.";
        header("Location: projeler.php?hata=" . urlencode($hata));
        exit;
    }
    
    try {
        // Proje ile ilgili tüm ilişkileri temizle
        $db->beginTransaction();
        
        // Ana proje kaydını sil
        $sil = $db->prepare("DELETE FROM projeler WHERE id = ?");
        $sil->execute([$proje_id]);
        
        $db->commit();
        
        $mesaj = "Proje başarıyla silindi.";
        header("Location: projeler.php?mesaj=" . urlencode($mesaj));
        exit;
    } catch (PDOException $e) {
        $db->rollBack();
        $hata = "Proje silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: projeler.php?hata=" . urlencode($hata));
        exit;
    }
}

// Arama parametresi
$arama = isset($_GET['arama']) ? $_GET['arama'] : '';
$arama_sorgu = '';
$params = [];

if (!empty($arama)) {
    $arama_sorgu = " WHERE (p.proje_adi LIKE ? OR p.proje_kodu LIKE ? OR p.proje_aciklama LIKE ?)";
    $params = ["%$arama%", "%$arama%", "%$arama%"];
}

// Projeleri getir
$sql = "SELECT p.*, 
               (SELECT COUNT(*) FROM siparisler s WHERE s.proje_id = p.id) AS siparis_sayisi
        FROM projeler p
        $arama_sorgu
        ORDER BY p.proje_adi ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$projeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Projeler</h2>
        <a href="proje_ekle.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Yeni Proje Ekle
        </a>
    </div>

    <?php if (isset($_GET['mesaj'])): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['mesaj']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['hata'])): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($_GET['hata']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
    </div>
    <?php endif; ?>

    <!-- Arama Formu -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-10">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control" name="arama" placeholder="Proje adı, kodu veya yönetici adı ile arama yapın..." value="<?= htmlspecialchars($arama) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Ara</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Proje Listesi -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Proje Adı</th>
                            <th>Proje Kodu</th>
                            <th>Proje Yöneticisi</th>
                            <th>Başlangıç Tarihi</th>
                            <th>Bitiş Tarihi</th>
                            <th>Siparişler</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($projeler) > 0): ?>
                            <?php foreach ($projeler as $proje): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($proje['proje_adi']) ?></div>
                                        <small class="text-muted"><?= $proje['proje_aciklama'] ? htmlspecialchars(mb_substr($proje['proje_aciklama'], 0, 50)) . '...' : '-' ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($proje['proje_kodu']) ?></td>
                                    <td><?= isset($proje['proje_yoneticisi']) ? htmlspecialchars($proje['proje_yoneticisi']) : '-' ?></td>
                                    <td><?= $proje['baslangic_tarihi'] ? date('d.m.Y', strtotime($proje['baslangic_tarihi'])) : '-' ?></td>
                                    <td><?= $proje['bitis_tarihi'] ? date('d.m.Y', strtotime($proje['bitis_tarihi'])) : '-' ?></td>
                                    <td><span class="badge bg-primary"><?= $proje['siparis_sayisi'] ?></span></td>
                                    <td>
                                        <?php 
                                            $bugun = date('Y-m-d');
                                            $durum = '';
                                            $durum_renk = '';
                                            
                                            if ($proje['bitis_tarihi'] && $proje['bitis_tarihi'] < $bugun) {
                                                $durum = 'Tamamlandı';
                                                $durum_renk = 'success';
                                            } elseif ($proje['baslangic_tarihi'] && $proje['baslangic_tarihi'] > $bugun) {
                                                $durum = 'Planlandı';
                                                $durum_renk = 'warning';
                                            } else {
                                                $durum = 'Devam Ediyor';
                                                $durum_renk = 'info';
                                            }
                                        ?>
                                        <span class="badge bg-<?= $durum_renk ?>"><?= $durum ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="proje_detay.php?id=<?= $proje['id'] ?>" class="btn btn-info" title="Detay">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="proje_duzenle.php?id=<?= $proje['id'] ?>" class="btn btn-warning" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($proje['siparis_sayisi'] == 0): ?>
                                                <button type="button" class="btn btn-danger delete-btn" data-id="<?= $proje['id'] ?>" data-name="<?= htmlspecialchars($proje['proje_adi']) ?>" title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Proje bulunamadı.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Proje Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmText">Bu projeyi silmek istediğinize emin misiniz?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <a href="#" id="deleteConfirmBtn" class="btn btn-danger">Sil</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Silme işlemi için onay modalını ayarla
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const deleteConfirmBtn = document.getElementById('deleteConfirmBtn');
        const deleteConfirmText = document.getElementById('deleteConfirmText');
        
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                
                deleteConfirmText.textContent = `"${name}" adlı projeyi silmek istediğinize emin misiniz?`;
                deleteConfirmBtn.href = `projeler.php?sil=${id}`;
                
                deleteModal.show();
            });
        });
    });
</script>

<?php include 'footer.php'; ?> 