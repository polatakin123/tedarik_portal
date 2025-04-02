<?php
// siparis_detay.php - Sipariş detay sayfası
require 'config.php';
kullaniciGirisKontrol();

// Sipariş ID'si kontrol edilir
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$siparis_id = intval($_GET['id']);

// Sipariş bilgisini getir
$sql = "SELECT s.*, sd.durum_adi, mt.montaj_adi, r.renk_kodu, r.renk_adi, k.ad_soyad as olusturan
        FROM siparisler s
        LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
        LEFT JOIN montaj_tipleri mt ON s.montaj_id = mt.id
        LEFT JOIN renkler r ON s.renk_id = r.id
        LEFT JOIN kullanicilar k ON s.olusturan_id = k.id
        WHERE s.id = ?";
        
$stmt = $db->prepare($sql);
$stmt->execute([$siparis_id]);
$siparis = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$siparis) {
    header("Location: index.php");
    exit;
}

// Sipariş geçmişini getir
$log_sql = "SELECT sg.*, k.ad_soyad as kullanici
            FROM siparis_gecmisi sg
            LEFT JOIN kullanicilar k ON sg.kullanici_id = k.id
            WHERE sg.siparis_id = ?
            ORDER BY sg.islem_tarihi DESC";
            
$log_stmt = $db->prepare($log_sql);
$log_stmt->execute([$siparis_id]);
$siparis_gecmisi = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savunma Sistemi - Sipariş Detayı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Savunma Sistemi</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Siparişler</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="yeni_siparis.php">Yeni Sipariş</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="raporlar.php">Raporlar</a>
                    </li>
                    <?php if ($_SESSION['yetki_seviyesi'] >= 3): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="yonetim.php">Yönetim</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <?= guvenli($_SESSION['ad_soyad']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profil.php">Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="cikis.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h3>Sipariş Detayı: <?= guvenli($siparis['siparis_no']) ?></h3>
                    <div>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Geri
                        </a>
                        <a href="siparis_duzenle.php?id=<?= $siparis_id ?>" class="btn btn-warning">
                            <i class="bi bi-pencil"></i> Düzenle
                        </a>
                        <?php if ($_SESSION['yetki_seviyesi'] >= 2): ?>
                        <a href="javascript:void(0)" onclick="siparisDurumDegistir(<?= $siparis_id ?>)" class="btn btn-danger">
                            <i class="bi bi-x-circle"></i> İptal Et
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Sipariş Bilgileri</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th width="35%">Sipariş No</th>
                                    <td><?= guvenli($siparis['siparis_no']) ?></td>
                                </tr>
                                <tr>
                                    <th>Parça No</th>
                                    <td><?= guvenli($siparis['parca_no']) ?></td>
                                </tr>
                                <tr>
                                    <th>Durum</th>
                                    <td><?= guvenli($siparis['durum_adi']) ?></td>
                                </tr>
                                <tr>
                                    <th>Profil</th>
                                    <td><?= guvenli($siparis['profil']) ?></td>
                                </tr>
                                <tr>
                                    <th>Montaj</th>
                                    <td><?= guvenli($siparis['montaj_adi'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Tarih</th>
                                    <td><?= date('d.m.Y H:i', strtotime($siparis['tarih'] . ' ' . $siparis['saat'])) ?></td>
                                </tr>
                                <tr>
                                    <th>İşlem Tipi</th>
                                    <td><?= guvenli($siparis['islem_tipi'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Miktar</th>
                                    <td><?= guvenli($siparis['miktar']) ?></td>
                                </tr>
                                <tr>
                                    <th>İade Miktar</th>
                                    <td><?= guvenli($siparis['iade_miktar']) ?></td>
                                </tr>
                                <tr>
                                    <th>Teslim Miktar</th>
                                    <td><?= guvenli($siparis['teslim_miktar']) ?></td>
                                </tr>
                                <tr>
                                    <th>Bitiş Tarihi</th>
                                    <td><?= $siparis['bitis_tarihi'] ? date('d.m.Y', strtotime($siparis['bitis_tarihi'])) : '-' ?></td>
                                </tr>
                                <tr>
                                    <th>ACİL</th>
                                    <td><?= $siparis['acil'] ? '<span class="badge bg-danger">ACİL</span>' : 'Hayır' ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="card-title mb-0">Ek Bilgiler</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered">
                            <tbody>
                                <tr>
                                    <th width="35%">Renk</th>
                                    <td><?= guvenli($siparis['renk_kodu'] ?? '-') ?> <?= $siparis['renk_adi'] ? '(' . guvenli($siparis['renk_adi']) . ')' : '' ?></td>
                                </tr>
                                <tr>
                                    <th>Kasa Tipi</th>
                                    <td><?= guvenli($siparis['kasa_tipi'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Boya Kilidi</th>
                                    <td><?= guvenli($siparis['boya_kilidi'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>FAZ</th>
                                    <td><?= guvenli($siparis['faz'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Satış No</th>
                                    <td><?= guvenli($siparis['satis_no'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Paketleme</th>
                                    <td><?= guvenli($siparis['paketleme'] ?? '-') ?></td>
                                </tr>
                                <tr>
                                    <th>Oluşturan</th>
                                    <td><?= guvenli($siparis['olusturan']) ?></td>
                                </tr>
                                <tr>
                                    <th>Oluşturma Tarihi</th>
                                    <td><?= date('d.m.Y H:i', strtotime($siparis['olusturma_tarihi'])) ?></td>
                                </tr>
                                <tr>
                                    <th>Son Güncelleme</th>
                                    <td><?= $siparis['guncelleme_tarihi'] ? date('d.m.Y H:i', strtotime($siparis['guncelleme_tarihi'])) : '-' ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="card-title mb-0">İşlem Geçmişi</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Tarih</th>
                                    <th>İşlem Tipi</th>
                                    <th>Açıklama</th>
                                    <th>Kullanıcı</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($siparis_gecmisi as $log): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($log['islem_tarihi'])) ?></td>
                                    <td><?= guvenli($log['islem_tipi']) ?></td>
                                    <td><?= guvenli($log['aciklama']) ?></td>
                                    <td><?= guvenli($log['kullanici']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if (count($siparis_gecmisi) == 0): ?>
                                <tr>
                                    <td colspan="4" class="text-center">İşlem geçmişi bulunamadı.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function siparisDurumDegistir(id) {
        if (confirm('Bu siparişin durumunu değiştirmek istediğinize emin misiniz?')) {
            location.href = 'siparis_durum_degistir.php?id=' + id;
        }
    }
    </script>
</body>
</html> 