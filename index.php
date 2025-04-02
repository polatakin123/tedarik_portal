<?php
// index.php - Ana panel sayfası
require 'config.php';
girisKontrol();

// Sipariş durumu filtresi
$durum_filtresi = $_GET['durum'] ?? 'Açık';
$durum_sorgusu = "";

if ($durum_filtresi == 'Açık') {
    $durum_sorgusu = "WHERE sd.durum_adi = 'Açık'";
} elseif ($durum_filtresi == 'Kapalı') {
    $durum_sorgusu = "WHERE sd.durum_adi = 'Kapalı'";
} elseif ($durum_filtresi == 'Kaldırılmış') {
    $durum_sorgusu = "WHERE sd.durum_adi = 'Kaldırılmış'";
}

// Siparişleri getir
$sql = "SELECT s.*, sd.durum_adi, mt.montaj_adi, r.renk_kodu
        FROM siparisler s
        LEFT JOIN siparis_durumlari sd ON s.durum_id = sd.id
        LEFT JOIN montaj_tipleri mt ON s.montaj_id = mt.id
        LEFT JOIN renkler r ON s.renk_id = r.id
        $durum_sorgusu
        ORDER BY s.olusturma_tarihi DESC";
        
$stmt = $db->query($sql);
$siparisler = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savunma Sistemi - Sipariş Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .table th {
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .table td {
            font-size: 0.8rem;
            white-space: nowrap;
        }
        .acil {
            background-color: #ffcccc;
        }
    </style>
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

    <div class="container-fluid mt-3">
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Sipariş Durumu</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-auto">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="durum" id="durum_acik" value="Açık" 
                                        <?= ($durum_filtresi == 'Açık') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="durum_acik">Açık</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="durum" id="durum_kapali" value="Kapalı" 
                                        <?= ($durum_filtresi == 'Kapalı') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="durum_kapali">Kapalı</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="durum" id="durum_kaldirilmis" value="Kaldırılmış" 
                                        <?= ($durum_filtresi == 'Kaldırılmış') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="durum_kaldirilmis">Kaldırılmış</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="durum" id="durum_tumu" value="Tümü" 
                                        <?= ($durum_filtresi == 'Tümü') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="durum_tumu">Tümü</label>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <label for="siparis_no" class="col-form-label">Sipariş No:</label>
                                    </div>
                                    <div class="col-auto">
                                        <input type="text" id="siparis_no" name="siparis_no" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <div class="row g-3">
                                    <div class="col-auto">
                                        <label for="parca_no" class="col-form-label">Parça No:</label>
                                    </div>
                                    <div class="col-auto">
                                        <input type="text" id="parca_no" name="parca_no" class="form-control form-control-sm">
                                    </div>
                                </div>
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-success btn-sm">Sorgula</button>
                                <a href="index.php" class="btn btn-secondary btn-sm">Temizle</a>
                            </div>
                        </form>
                        
                        <div class="alert alert-warning mt-3">
                            <ul class="mb-0">
                                <li>FAT olarak gönderilen sipariş PASS Kartlar'dan reddedilmişse bir sonraki sipariş tekrar FAT hazırlanmalıdır.</li>
                                <li>Resmini dijital olarak yüklenen uygunsuzluklar için VGMS yazılması durumunda o sipariş FAT olarak değerlendirilmemelidir. Bir sonraki uygun sipariştekrar FAT hazırlanmalıdır.</li>
                                <li>Soya-bağlama firmasında sevk edilecek olan parçalar için "Ait Tedarikciye Gönder" butonu tıklanarak iş emri formu dolduruluğu ilgili tedarikçiye parçalar ile birlikte sevk edilmelidir.</li>
                                <li>Fason imalat için FNSS'ten sevk edilen alt malzemelerin iadesinde mutlakak FNSS Talimatlar altındaki "Malzeme İade Formu" doldurulmalıdır ve parça ile birlikte sevki yapılmalıdır.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Seç</th>
                        <th>Parça</th>
                        <th>Sipariş No</th>
                        <th>Profil</th>
                        <th>Tarih</th>
                        <th>İşlem Tipi</th>
                        <th>Miktar</th>
                        <th>İade</th>
                        <th>Teslim</th>
                        <th>Bitiş Tarihi</th>
                        <th>Renk</th>
                        <th>Acil</th>
                        <th>Durum</th>
                        <th>İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($siparisler as $siparis): ?>
                    <tr <?= ($siparis['acil']) ? 'class="acil"' : '' ?>>
                        <td><input type="checkbox" class="siparis-sec" value="<?= $siparis['id'] ?>"></td>
                        <td><?= guvenli($siparis['parca_no']) ?></td>
                        <td><?= guvenli($siparis['siparis_no']) ?></td>
                        <td><?= guvenli($siparis['profil']) ?></td>
                        <td><?= guvenli(date('d.m.Y', strtotime($siparis['tarih']))) ?> <?= guvenli(date('H:i', strtotime($siparis['saat']))) ?></td>
                        <td><?= guvenli($siparis['islem_tipi']) ?></td>
                        <td><?= guvenli($siparis['miktar']) ?></td>
                        <td><?= guvenli($siparis['iade_miktar']) ?></td>
                        <td><?= guvenli($siparis['teslim_miktar']) ?></td>
                        <td><?= $siparis['bitis_tarihi'] ? guvenli(date('d.m.Y', strtotime($siparis['bitis_tarihi']))) : '' ?></td>
                        <td><?= guvenli($siparis['renk_kodu']) ?></td>
                        <td><?= $siparis['acil'] ? 'ACİL' : '' ?></td>
                        <td><?= guvenli($siparis['durum_adi']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="siparis_detay.php?id=<?= $siparis['id'] ?>" class="btn btn-info btn-sm">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="siparis_duzenle.php?id=<?= $siparis['id'] ?>" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($_SESSION['yetki_seviyesi'] >= 2): ?>
                                <a href="javascript:void(0)" onclick="siparisDurumDegistir(<?= $siparis['id'] ?>)" class="btn btn-danger btn-sm">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="d-flex justify-content-between">
            <div>
                <button class="btn btn-secondary" id="btn-copy">Kopyala</button>
                <button class="btn btn-success" id="btn-excel">Excel</button>
                <button class="btn btn-primary" id="btn-print">Yazdır</button>
            </div>
            <div>
                <span>Toplam: <?= count($siparisler) ?> kayıt</span>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Durum filtresi otomatik submit
        document.querySelectorAll('input[name="durum"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                this.form.submit();
            });
        });
        
        // Sipariş durum değiştirme
        window.siparisDurumDegistir = function(id) {
            if (confirm('Bu siparişin durumunu değiştirmek istediğinize emin misiniz?')) {
                location.href = 'siparis_durum_degistir.php?id=' + id;
            }
        };
        
        // Excel, Print ve Copy işlemleri
        document.getElementById('btn-excel').addEventListener('click', function() {
            window.location.href = 'export_excel.php?' + new URLSearchParams(window.location.search);
        });
        
        document.getElementById('btn-print').addEventListener('click', function() {
            window.print();
        });
        
        document.getElementById('btn-copy').addEventListener('click', function() {
            // Tabloyu kopyalama işlemi
            const table = document.querySelector('table');
            const range = document.createRange();
            range.selectNode(table);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            document.execCommand('copy');
            window.getSelection().removeAllRanges();
            alert('Tablo kopyalandı!');
        });
    });
    </script>
</body>
</html> 