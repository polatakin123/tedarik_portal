<?php
// admin/tedarikciler.php - Admin paneli tedarikçi yönetimi sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Tedarikçiler";

// Tedarikçi silme işlemi
if (isset($_GET['sil']) && !empty($_GET['sil'])) {
    $tedarikci_id = intval($_GET['sil']);
    try {
        // İlişkili siparişleri kontrol et
        $siparis_kontrol = "SELECT COUNT(*) FROM siparisler WHERE tedarikci_id = ?";
        $siparis_stmt = $db->prepare($siparis_kontrol);
        $siparis_stmt->execute([$tedarikci_id]);
        
        if ($siparis_stmt->fetchColumn() > 0) {
            $hata = "Bu tedarikçiye ait siparişler bulunduğu için silinemez.";
            header("Location: tedarikciler.php?hata=" . urlencode($hata));
            exit;
        }
        
        // İlişkili sorumlulukları sil
        $sorumluluk_sql = "DELETE FROM sorumluluklar WHERE tedarikci_id = ?";
        $sorumluluk_stmt = $db->prepare($sorumluluk_sql);
        $sorumluluk_stmt->execute([$tedarikci_id]);
        
        // İlişkili kullanıcı-tedarikçi ilişkilerini sil
        $iliskileri_sil = "DELETE FROM kullanici_tedarikci_iliskileri WHERE tedarikci_id = ?";
        $iliskiler_stmt = $db->prepare($iliskileri_sil);
        $iliskiler_stmt->execute([$tedarikci_id]);
        
        // Tedarikçiyi sil
        $tedarikci_sql = "DELETE FROM tedarikciler WHERE id = ?";
        $tedarikci_stmt = $db->prepare($tedarikci_sql);
        $tedarikci_stmt->execute([$tedarikci_id]);
        
        $mesaj = "Tedarikçi başarıyla silindi.";
        header("Location: tedarikciler.php?mesaj=" . urlencode($mesaj));
        exit;
    } catch (PDOException $e) {
        $hata = "Tedarikçi silinirken bir hata oluştu: " . $e->getMessage();
        header("Location: tedarikciler.php?hata=" . urlencode($hata));
        exit;
    }
}

// Arama parametresi
$arama = isset($_GET['arama']) ? trim($_GET['arama']) : '';

// Tedarikçileri getir
$sql_params = [];
$sql = "SELECT t.*, 
       (SELECT COUNT(id) FROM siparisler WHERE tedarikci_id = t.id) as siparis_sayisi,
       (SELECT COUNT(id) FROM sorumluluklar WHERE tedarikci_id = t.id) as sorumlu_sayisi
       FROM tedarikciler t 
       WHERE 1=1";

if ($arama) {
    $sql .= " AND (t.firma_adi LIKE ? OR t.firma_kodu LIKE ? OR t.yetkili_kisi LIKE ? OR t.email LIKE ? OR t.telefon LIKE ?)";
    $arama_param = "%$arama%";
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
    $sql_params[] = $arama_param;
}

$sql .= " ORDER BY t.firma_adi";

$stmt = $db->prepare($sql);
$stmt->execute($sql_params);
$tedarikciler = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <h2>Tedarikçiler</h2>
        <a href="tedarikci_ekle.php" class="btn btn-success">
            <i class="bi bi-plus-circle"></i> Yeni Tedarikçi Ekle
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
                        <input type="text" class="form-control" name="arama" placeholder="Firma adı, yetkili kişi, telefon veya e-posta ile arama yapın..." value="<?= htmlspecialchars($arama) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Ara</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tedarikçi Listesi -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Firma Adı</th>
                            <th>Yetkili Kişi</th>
                            <th>Telefon</th>
                            <th>E-posta</th>
                            <th>Siparişler</th>
                            <th>Sorumlular</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($tedarikciler) > 0): ?>
                            <?php foreach ($tedarikciler as $tedarikci): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($tedarikci['firma_adi']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($tedarikci['firma_kodu']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($tedarikci['yetkili_kisi']) ?></td>
                                    <td><?= htmlspecialchars($tedarikci['telefon']) ?></td>
                                    <td><?= htmlspecialchars($tedarikci['email']) ?></td>
                                    <td><span class="badge bg-primary"><?= $tedarikci['siparis_sayisi'] ?></span></td>
                                    <td><span class="badge bg-info"><?= $tedarikci['sorumlu_sayisi'] ?></span></td>
                                    <td>
                                        <?php if ($tedarikci['aktif'] == 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="btn btn-info" title="Detay">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="tedarikci_duzenle.php?id=<?= $tedarikci['id'] ?>" class="btn btn-warning" title="Düzenle">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($tedarikci['siparis_sayisi'] == 0): ?>
                                                <button type="button" class="btn btn-danger delete-btn" data-id="<?= $tedarikci['id'] ?>" data-name="<?= htmlspecialchars($tedarikci['firma_adi']) ?>" title="Sil">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">Tedarikçi bulunamadı.</td>
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
                <h5 class="modal-title">Tedarikçi Sil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmText">Bu tedarikçiyi silmek istediğinize emin misiniz?</p>
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
                
                deleteConfirmText.textContent = `"${name}" adlı tedarikçiyi silmek istediğinize emin misiniz?`;
                deleteConfirmBtn.href = `tedarikciler.php?sil=${id}`;
                
                deleteModal.show();
            });
        });
    });
</script>

<?php include 'footer.php'; ?> 