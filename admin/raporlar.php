<?php
// admin/raporlar.php - Raporlar sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Sayfa başlığını ayarla
$page_title = "Raporlar";

// Sipariş durumlarına göre istatistikler
$siparis_durum_sql = "SELECT sd.id, sd.durum_adi, COUNT(s.id) as sayi
                     FROM siparis_durumlari sd
                     LEFT JOIN siparisler s ON sd.id = s.durum_id
                     GROUP BY sd.id, sd.durum_adi
                     ORDER BY sd.id";
$siparis_durum_stmt = $db->prepare($siparis_durum_sql);
$siparis_durum_stmt->execute();
$siparis_durum_istatistikleri = $siparis_durum_stmt->fetchAll(PDO::FETCH_ASSOC);

// Tedarikçi sayısı ve aktif/pasif durumu
$tedarikci_sql = "SELECT COUNT(*) as toplam, 
                 SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif,
                 SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) as pasif
                 FROM tedarikciler";
$tedarikci_stmt = $db->prepare($tedarikci_sql);
$tedarikci_stmt->execute();
$tedarikci_istatistikleri = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);

// Proje sayısı ve aktif/pasif durumu
$proje_sql = "SELECT COUNT(*) as toplam, 
             SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) as aktif,
             SUM(CASE WHEN aktif = 0 THEN 1 ELSE 0 END) as pasif
             FROM projeler";
$proje_stmt = $db->prepare($proje_sql);
$proje_stmt->execute();
$proje_istatistikleri = $proje_stmt->fetch(PDO::FETCH_ASSOC);

// Kullanıcı rolleri dağılımı
$kullanici_rol_sql = "SELECT rol, COUNT(*) as sayi
                     FROM kullanicilar
                     GROUP BY rol
                     ORDER BY sayi DESC";
$kullanici_rol_stmt = $db->prepare($kullanici_rol_sql);
$kullanici_rol_stmt->execute();
$kullanici_rol_istatistikleri = $kullanici_rol_stmt->fetchAll(PDO::FETCH_ASSOC);

// En aktif tedarikçiler (sipariş sayısına göre)
$aktif_tedarikciler_sql = "SELECT t.firma_adi, COUNT(s.id) as siparis_sayisi
                          FROM tedarikciler t
                          INNER JOIN siparisler s ON t.id = s.tedarikci_id
                          GROUP BY t.id, t.firma_adi
                          ORDER BY siparis_sayisi DESC
                          LIMIT 5";
$aktif_tedarikciler_stmt = $db->prepare($aktif_tedarikciler_sql);
$aktif_tedarikciler_stmt->execute();
$aktif_tedarikciler = $aktif_tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Duruma göre renk sınıfı belirle
function durumRengiGetir($durum_id) {
    switch ($durum_id) {
        case 1: return "warning"; // Açık
        case 2: return "success"; // Kapalı/Tamamlanmış
        case 3: return "info";    // Beklemede
        case 4: return "danger";  // İptal
        default: return "secondary";
    }
}

// Header'ı dahil et
include 'header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Raporlar ve İstatistikler</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="rapor_olustur.php" class="btn btn-primary">
                <i class="bi bi-file-earmark-text"></i> Detaylı Rapor Oluştur
            </a>
            <button type="button" class="btn btn-outline-secondary" onclick="window.print();">
                <i class="bi bi-printer"></i> Yazdır
            </button>
        </div>
    </div>
</div>

<!-- Özet İstatistikler -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Toplam Sipariş</h6>
                        <?php
                        $toplam_siparis = 0;
                        foreach ($siparis_durum_istatistikleri as $istatistik) {
                            $toplam_siparis += $istatistik['sayi'];
                        }
                        ?>
                        <h3 class="mb-0"><?= $toplam_siparis ?></h3>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-list-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Tedarikçiler</h6>
                        <h3 class="mb-0"><?= $tedarikci_istatistikleri['toplam'] ?></h3>
                        <small><?= $tedarikci_istatistikleri['aktif'] ?> Aktif / <?= $tedarikci_istatistikleri['pasif'] ?> Pasif</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-warning text-dark">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Projeler</h6>
                        <h3 class="mb-0"><?= $proje_istatistikleri['toplam'] ?></h3>
                        <small><?= $proje_istatistikleri['aktif'] ?> Aktif / <?= $proje_istatistikleri['pasif'] ?> Pasif</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-diagram-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Tamamlanan Siparişler</h6>
                        <?php
                        $tamamlanan = 0;
                        foreach ($siparis_durum_istatistikleri as $istatistik) {
                            if ($istatistik['id'] == 2) { // Tamamlandı durumu
                                $tamamlanan = $istatistik['sayi'];
                                break;
                            }
                        }
                        $oran = $toplam_siparis > 0 ? round(($tamamlanan / $toplam_siparis) * 100) : 0;
                        ?>
                        <h3 class="mb-0"><?= $tamamlanan ?></h3>
                        <small>Toplam Siparişlerin %<?= $oran ?>'i</small>
                    </div>
                    <div class="stat-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6">
        <!-- Sipariş Durumları Kartı -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sipariş Durumları</h5>
                <a href="siparisler.php" class="btn btn-sm btn-outline-primary">Tüm Siparişler</a>
            </div>
            <div class="card-body">
                <?php if ($toplam_siparis > 0): ?>
                    <?php foreach ($siparis_durum_istatistikleri as $istatistik): ?>
                        <?php 
                        $durum_rengi = durumRengiGetir($istatistik['id']);
                        $yuzde = $toplam_siparis > 0 ? round(($istatistik['sayi'] / $toplam_siparis) * 100) : 0;
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span><?= guvenli($istatistik['durum_adi']) ?></span>
                                <span><?= $istatistik['sayi'] ?> (<?= $yuzde ?>%)</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-<?= $durum_rengi ?>" role="progressbar" 
                                    style="width: <?= $yuzde ?>%;" aria-valuenow="<?= $yuzde ?>" 
                                    aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-muted my-3">Henüz sipariş bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Kullanıcı Rolleri Kartı -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Kullanıcı Rolleri</h5>
                <a href="kullanicilar.php" class="btn btn-sm btn-outline-primary">Tüm Kullanıcılar</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rol</th>
                                <th>Kullanıcı Sayısı</th>
                                <th>Oran</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $toplam_kullanici = 0;
                            foreach ($kullanici_rol_istatistikleri as $rol) {
                                $toplam_kullanici += $rol['sayi'];
                            }
                            ?>
                            <?php foreach ($kullanici_rol_istatistikleri as $rol): ?>
                                <?php 
                                $rol_yuzde = $toplam_kullanici > 0 ? round(($rol['sayi'] / $toplam_kullanici) * 100) : 0;
                                $rol_renk = "";
                                switch ($rol['rol']) {
                                    case 'Admin': $rol_renk = "danger"; break;
                                    case 'Tedarikci': $rol_renk = "success"; break;
                                    case 'Sorumlu': $rol_renk = "primary"; break;
                                    default: $rol_renk = "secondary";
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?= $rol_renk ?>"><?= guvenli($rol['rol']) ?></span>
                                    </td>
                                    <td><?= $rol['sayi'] ?></td>
                                    <td>
                                        <div class="progress" style="height: 5px;">
                                            <div class="progress-bar bg-<?= $rol_renk ?>" role="progressbar" 
                                                style="width: <?= $rol_yuzde ?>%;" aria-valuenow="<?= $rol_yuzde ?>" 
                                                aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                        <small class="text-muted"><?= $rol_yuzde ?>%</small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- En Aktif Tedarikçiler Kartı -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">En Aktif Tedarikçiler</h5>
                <a href="tedarikciler.php" class="btn btn-sm btn-outline-primary">Tüm Tedarikçiler</a>
            </div>
            <div class="card-body">
                <?php if (count($aktif_tedarikciler) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Firma</th>
                                    <th>Sipariş Sayısı</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aktif_tedarikciler as $tedarikci): ?>
                                    <tr>
                                        <td><?= guvenli($tedarikci['firma_adi']) ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?= $tedarikci['siparis_sayisi'] ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted my-3">Henüz aktif tedarikçi bulunmamaktadır.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Geliştirilebilecek Özellikler -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Daha Fazla Rapor</h5>
            </div>
            <div class="card-body">
                <p class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    Bu sayfada yalnızca temel istatistikler gösterilmektedir. Daha kapsamlı raporlar için aşağıdaki seçeneklere göz atabilirsiniz.
                </p>
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-calendar-check me-2"></i>
                            Zaman Bazlı Sipariş Analizi
                        </div>
                        <span class="badge bg-primary rounded-pill">Yakında</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-bar-chart-line me-2"></i>
                            Tedarikçi Performans Raporu
                        </div>
                        <span class="badge bg-primary rounded-pill">Yakında</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-clock-history me-2"></i>
                            Teslimat Süresi Analizi
                        </div>
                        <span class="badge bg-primary rounded-pill">Yakında</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-pie-chart me-2"></i>
                            Proje Bazlı Sipariş Dağılımı
                        </div>
                        <span class="badge bg-primary rounded-pill">Yakında</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?> 