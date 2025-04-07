<?php
// admin/kullanici_detay.php - Kullanıcı detay sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Kullanıcı ID kontrol
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: kullanicilar.php?hata=" . urlencode("Kullanıcı ID belirtilmedi"));
    exit;
}

$kullanici_id = intval($_GET['id']);

// Kullanıcı bilgilerini getir
$kullanici_sql = "SELECT * FROM kullanicilar WHERE id = ?";
$kullanici_stmt = $db->prepare($kullanici_sql);
$kullanici_stmt->execute([$kullanici_id]);
$kullanici = $kullanici_stmt->fetch(PDO::FETCH_ASSOC);

if (!$kullanici) {
    header("Location: kullanicilar.php?hata=" . urlencode("Kullanıcı bulunamadı"));
    exit;
}

// Tedarikçi kullanıcısıysa firma bilgisini al
$firma_bilgisi = null;
if ($kullanici['rol'] == 'Tedarikci') {
    $firma_sql = "SELECT t.* 
                 FROM tedarikciler t
                 INNER JOIN kullanici_tedarikci_iliskileri kti ON t.id = kti.tedarikci_id
                 WHERE kti.kullanici_id = ?";
    $firma_stmt = $db->prepare($firma_sql);
    $firma_stmt->execute([$kullanici_id]);
    $firma_bilgisi = $firma_stmt->fetch(PDO::FETCH_ASSOC);
}

// Sorumlu olduğu tedarikçileri getir (rol Sorumlu ise)
$sorumlu_oldugu_tedarikciler = [];
if ($kullanici['rol'] == 'Sorumlu') {
    $sorumlu_tedarikciler_sql = "SELECT t.id, t.firma_adi, t.firma_kodu, t.aktif
                                FROM tedarikciler t
                                INNER JOIN sorumluluklar s ON t.id = s.tedarikci_id
                                WHERE s.sorumlu_id = ?
                                ORDER BY t.firma_adi";
    $sorumlu_tedarikciler_stmt = $db->prepare($sorumlu_tedarikciler_sql);
    $sorumlu_tedarikciler_stmt->execute([$kullanici_id]);
    $sorumlu_oldugu_tedarikciler = $sorumlu_tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Kullanıcının sipariş geçmişini getir (son 10 sipariş)
$siparisler_sql = "SELECT s.*, sd.durum_adi, p.proje_adi, p.proje_kodu, t.firma_adi as tedarikci_adi
                  FROM siparisler s
                  LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
                  LEFT JOIN projeler p ON s.proje_id = p.id
                  LEFT JOIN tedarikciler t ON s.tedarikci_id = t.id
                  WHERE ";

// Role göre sipariş filtreleme
if ($kullanici['rol'] == 'Tedarikci') {
    // Tedarikçi kullanıcısı ise firma siparişlerini getir
    $siparisler_sql .= "s.tedarikci_id = (SELECT tedarikci_id FROM kullanici_tedarikci_iliskileri WHERE kullanici_id = ?)";
    $siparis_param = [$kullanici_id];
} elseif ($kullanici['rol'] == 'Sorumlu') {
    // Sorumlu kullanıcısı ise sorumlu olduğu siparişleri getir
    $siparisler_sql .= "s.sorumlu_id = ?";
    $siparis_param = [$kullanici_id];
} else {
    // Admin veya diğer roller için oluşturduğu siparişleri getir
    $siparisler_sql .= "s.olusturan_id = ?";
    $siparis_param = [$kullanici_id];
}

$siparisler_sql .= " ORDER BY s.olusturma_tarihi DESC LIMIT 10";
$siparisler_stmt = $db->prepare($siparisler_sql);
$siparisler_stmt->execute($siparis_param);
$siparisler = $siparisler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Okunmamış bildirim sayısını al
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

// Rol değerini Türkçe'ye çevir
function rolTurkce($rol) {
    switch ($rol) {
        case 'Admin': return 'Yönetici';
        case 'Tedarikci': return 'Tedarikçi';
        case 'Sorumlu': return 'Sorumlu';
        default: return $rol;
    }
}

// Header'ı dahil et
include 'header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">
            Kullanıcı: <?= guvenli($kullanici['ad_soyad']) ?>
            <span class="badge <?= $kullanici['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                <?= $kullanici['aktif'] ? 'Aktif' : 'Pasif' ?>
            </span>
        </h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <a href="kullanicilar.php" class="btn btn-sm btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Kullanıcılara Dön
            </a>
            <a href="kullanici_duzenle.php?id=<?= $kullanici_id ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i> Düzenle
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

    <div class="row">
        <div class="col-md-6">
            <!-- Kullanıcı Bilgileri Kartı -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Kullanıcı Bilgileri</h5>
                    <div>
                        <span class="badge <?= $kullanici['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                            <?= $kullanici['aktif'] ? 'Aktif' : 'Pasif' ?>
                        </span>
                        <span class="badge bg-primary ms-1">
                            <?= rolTurkce($kullanici['rol']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Ad Soyad:</p>
                            <p><?= guvenli($kullanici['ad_soyad']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Kullanıcı Adı:</p>
                            <p><?= guvenli($kullanici['kullanici_adi']) ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">E-posta:</p>
                            <p><?= guvenli($kullanici['email']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Telefon:</p>
                            <p><?= !empty($kullanici['telefon']) ? guvenli($kullanici['telefon']) : '-' ?></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Rol:</p>
                            <p><?= rolTurkce($kullanici['rol']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Durum:</p>
                            <p>
                                <span class="badge <?= $kullanici['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $kullanici['aktif'] ? 'Aktif' : 'Pasif' ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Kayıt Tarihi:</p>
                            <p><?= date('d.m.Y', strtotime($kullanici['olusturma_tarihi'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="fw-bold mb-1">Son Giriş:</p>
                            <p><?= $kullanici['son_giris'] ? date('d.m.Y H:i', strtotime($kullanici['son_giris'])) : 'Henüz giriş yapmadı' ?></p>
                        </div>
                    </div>
                    
                    <?php if ($kullanici['rol'] == 'Tedarikci' && $firma_bilgisi): ?>
                        <div class="mt-4 mb-3">
                            <p class="fw-bold mb-2">Tedarikçi Firma Bilgisi:</p>
                            <div class="p-3 bg-light rounded">
                                <p class="mb-1">
                                    <span class="fw-bold">Firma Adı:</span> 
                                    <a href="tedarikci_detay.php?id=<?= $firma_bilgisi['id'] ?>" class="text-decoration-none">
                                        <?= guvenli($firma_bilgisi['firma_adi']) ?>
                                    </a>
                                    <span class="badge <?= $firma_bilgisi['aktif'] ? 'bg-success' : 'bg-danger' ?> ms-2">
                                        <?= $firma_bilgisi['aktif'] ? 'Aktif' : 'Pasif' ?>
                                    </span>
                                </p>
                                <p class="mb-1"><span class="fw-bold">Firma Kodu:</span> <?= guvenli($firma_bilgisi['firma_kodu']) ?></p>
                                <?php if (!empty($firma_bilgisi['yetkili_kisi'])): ?>
                                    <p class="mb-1"><span class="fw-bold">Yetkili Kişi:</span> <?= guvenli($firma_bilgisi['yetkili_kisi']) ?></p>
                                <?php endif; ?>
                                <?php if (!empty($firma_bilgisi['telefon'])): ?>
                                    <p class="mb-0"><span class="fw-bold">Telefon:</span> <?= guvenli($firma_bilgisi['telefon']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($kullanici['rol'] == 'Sorumlu' && count($sorumlu_oldugu_tedarikciler) > 0): ?>
                <!-- Sorumlu Olduğu Tedarikçiler Kartı -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Sorumlu Olduğu Tedarikçiler</h5>
                        <a href="sorumlu_tedarikciler.php?sorumlu_id=<?= $kullanici_id ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-pencil"></i> Düzenle
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Firma Adı</th>
                                        <th>Firma Kodu</th>
                                        <th>Durum</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sorumlu_oldugu_tedarikciler as $tedarikci): ?>
                                        <tr>
                                            <td><?= guvenli($tedarikci['firma_adi']) ?></td>
                                            <td><?= guvenli($tedarikci['firma_kodu']) ?></td>
                                            <td>
                                                <span class="badge <?= $tedarikci['aktif'] ? 'bg-success' : 'bg-danger' ?>">
                                                    <?= $tedarikci['aktif'] ? 'Aktif' : 'Pasif' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="tedarikci_detay.php?id=<?= $tedarikci['id'] ?>" class="btn btn-xs btn-outline-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <!-- Son Siparişler Kartı -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <?php if ($kullanici['rol'] == 'Tedarikci'): ?>
                            Firma Siparişleri
                        <?php elseif ($kullanici['rol'] == 'Sorumlu'): ?>
                            Sorumlu Olduğu Siparişler
                        <?php else: ?>
                            Oluşturduğu Siparişler
                        <?php endif; ?>
                    </h5>
                    <a href="siparisler.php?<?= $kullanici['rol'] == 'Sorumlu' ? 'sorumlu_id=' : 'olusturan_id=' ?><?= $kullanici_id ?>" class="btn btn-sm btn-primary">
                        Tümünü Gör
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($siparisler) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Sipariş No</th>
                                        <?php if ($kullanici['rol'] != 'Tedarikci'): ?>
                                            <th>Tedarikçi</th>
                                        <?php endif; ?>
                                        <th>Proje</th>
                                        <th>Durum</th>
                                        <th>Tarih</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($siparisler as $siparis): ?>
                                        <?php $durum_rengi = durumRengiGetir($siparis['durum_id']); ?>
                                        <tr>
                                            <td>
                                                <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="text-decoration-none">
                                                    <?= guvenli($siparis['siparis_no']) ?>
                                                </a>
                                            </td>
                                            <?php if ($kullanici['rol'] != 'Tedarikci'): ?>
                                                <td><?= guvenli($siparis['tedarikci_adi']) ?></td>
                                            <?php endif; ?>
                                            <td><?= guvenli($siparis['proje_kodu']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $durum_rengi ?>">
                                                    <?= guvenli($siparis['durum_adi']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('d.m.Y', strtotime($siparis['olusturma_tarihi'])) ?></td>
                                            <td>
                                                <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-xs btn-outline-info">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted my-3">
                            <?php if ($kullanici['rol'] == 'Tedarikci'): ?>
                                Bu tedarikçi firmanın henüz siparişi bulunmamaktadır.
                            <?php elseif ($kullanici['rol'] == 'Sorumlu'): ?>
                                Bu sorumluya atanmış sipariş bulunmamaktadır.
                            <?php else: ?>
                                Bu kullanıcı henüz sipariş oluşturmamış.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Giriş Geçmişi ve Sistem Aktiviteleri (Demo) -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Son Aktiviteler</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <?php if ($kullanici['son_giris']): ?>
                            <li class="list-group-item">
                                <i class="bi bi-box-arrow-in-right text-primary me-2"></i>
                                <strong>Sistem Girişi</strong>
                                <small class="text-muted float-end"><?= date('d.m.Y H:i', strtotime($kullanici['son_giris'])) ?></small>
                            </li>
                        <?php endif; ?>
                        <!-- Demo aktiviteler -->
                        <li class="list-group-item">
                            <i class="bi bi-eye text-info me-2"></i>
                            <strong>Sipariş Görüntüleme</strong> - #SP<?= date('Y') ?>001
                            <small class="text-muted float-end"><?= date('d.m.Y H:i', strtotime('-1 day')) ?></small>
                        </li>
                        <li class="list-group-item">
                            <i class="bi bi-file-earmark-text text-success me-2"></i>
                            <strong>Sipariş Dosya İndirildi</strong> - #SP<?= date('Y') ?>002.pdf
                            <small class="text-muted float-end"><?= date('d.m.Y H:i', strtotime('-3 day')) ?></small>
                        </li>
                        <li class="list-group-item">
                            <i class="bi bi-pen text-warning me-2"></i>
                            <strong>Profil Güncellemesi</strong>
                            <small class="text-muted float-end"><?= date('d.m.Y H:i', strtotime('-5 day')) ?></small>
                        </li>
                        <li class="list-group-item">
                            <i class="bi bi-box-arrow-in-right text-primary me-2"></i>
                            <strong>Sistem Girişi</strong>
                            <small class="text-muted float-end"><?= date('d.m.Y H:i', strtotime('-7 day')) ?></small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Footer'ı dahil et
include 'footer.php';
?> 