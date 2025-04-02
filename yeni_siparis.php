<?php
// yeni_siparis.php - Yeni sipariş ekleme sayfası
require 'config.php';
kullaniciGirisKontrol();

$hata = '';
$basari = '';

// Durum listesini getir
$durum_query = $db->query("SELECT * FROM siparis_durumlari");
$durumlar = $durum_query->fetchAll(PDO::FETCH_ASSOC);

// Montaj tiplerini getir
$montaj_query = $db->query("SELECT * FROM montaj_tipleri");
$montaj_tipleri = $montaj_query->fetchAll(PDO::FETCH_ASSOC);

// Renkleri getir
$renk_query = $db->query("SELECT * FROM renkler");
$renkler = $renk_query->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form gönderildi, işlemleri yap
    $siparis_no = $_POST['siparis_no'] ?? '';
    $parca_no = $_POST['parca_no'] ?? '';
    $durum_id = $_POST['durum_id'] ?? '';
    $profil = $_POST['profil'] ?? '';
    $montaj_id = $_POST['montaj_id'] ?? '';
    $tarih = $_POST['tarih'] ?? '';
    $saat = $_POST['saat'] ?? '';
    $islem_tipi = $_POST['islem_tipi'] ?? '';
    $miktar = $_POST['miktar'] ?? 0;
    $bitis_tarihi = !empty($_POST['bitis_tarihi']) ? $_POST['bitis_tarihi'] : null;
    $acil = isset($_POST['acil']) ? 1 : 0;
    $renk_id = $_POST['renk_id'] ?? null;
    $kasa_tipi = $_POST['kasa_tipi'] ?? '';
    $boya_kilidi = $_POST['boya_kilidi'] ?? '';
    $faz = $_POST['faz'] ?? '';
    $firma_id = $_POST['firma_id'] ?? null;
    $satis_no = $_POST['satis_no'] ?? '';
    $paketleme = $_POST['paketleme'] ?? '';
    
    // Validasyon
    if (empty($siparis_no) || empty($parca_no) || empty($durum_id) || empty($tarih) || empty($saat)) {
        $hata = 'Lütfen gerekli alanları doldurun.';
    } else {
        try {
            // Siparişi ekle
            $sql = "INSERT INTO siparisler (siparis_no, parca_no, durum_id, profil, montaj_id, tarih, saat, 
                   islem_tipi, miktar, bitis_tarihi, acil, renk_id, kasa_tipi, boya_kilidi, faz, firma_id, 
                   satis_no, paketleme, olusturan_id) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                   
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $siparis_no, $parca_no, $durum_id, $profil, $montaj_id, $tarih, $saat, 
                $islem_tipi, $miktar, $bitis_tarihi, $acil, $renk_id, $kasa_tipi, $boya_kilidi, 
                $faz, $firma_id, $satis_no, $paketleme, $_SESSION['kullanici_id']
            ]);
            
            $siparis_id = $db->lastInsertId();
            
            // İşlem geçmişine ekle
            $log_sql = "INSERT INTO siparis_gecmisi (siparis_id, islem_tipi, aciklama, kullanici_id) 
                      VALUES (?, ?, ?, ?)";
            $log_stmt = $db->prepare($log_sql);
            $log_stmt->execute([
                $siparis_id, 'Oluşturma', 'Sipariş oluşturuldu', $_SESSION['kullanici_id']
            ]);
            
            $basari = 'Sipariş başarıyla eklendi.';
            
            // Formu temizle
            $siparis_no = $parca_no = $profil = $tarih = $saat = $islem_tipi = $kasa_tipi = $boya_kilidi = $faz = $satis_no = $paketleme = '';
            $miktar = 0;
            $acil = 0;
            $durum_id = $montaj_id = $renk_id = $firma_id = '';
            $bitis_tarihi = null;
            
        } catch (PDOException $e) {
            $hata = 'Sipariş eklenirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Yeni sipariş numarası için otomatik oluştur (F+Yıl+Ay+5 haneli sıra no)
$yil = date('y');
$ay = date('m');
$query = $db->query("SELECT MAX(SUBSTRING(siparis_no, 6)) as son_sira FROM siparisler WHERE siparis_no LIKE 'F{$yil}{$ay}%'");
$son_siparis = $query->fetch(PDO::FETCH_ASSOC);
$sira_no = (int)($son_siparis['son_sira'] ?? 0) + 1;
$yeni_siparis_no = 'F' . $yil . $ay . str_pad($sira_no, 5, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savunma Sistemi - Yeni Sipariş</title>
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
                        <a class="nav-link" href="index.php">Siparişler</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="yeni_siparis.php">Yeni Sipariş</a>
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
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="card-title mb-0">Yeni Sipariş Ekle</h5>
                    </div>
                    <div class="card-body">
                        <?php if ($hata): ?>
                            <div class="alert alert-danger"><?= guvenli($hata) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($basari): ?>
                            <div class="alert alert-success"><?= guvenli($basari) ?></div>
                        <?php endif; ?>
                        
                        <form method="post" class="row g-3">
                            <div class="col-md-4">
                                <label for="siparis_no" class="form-label">Sipariş No</label>
                                <input type="text" class="form-control" id="siparis_no" name="siparis_no" 
                                    value="<?= isset($siparis_no) ? guvenli($siparis_no) : guvenli($yeni_siparis_no) ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="parca_no" class="form-label">Parça No</label>
                                <input type="text" class="form-control" id="parca_no" name="parca_no" 
                                    value="<?= isset($parca_no) ? guvenli($parca_no) : '' ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="durum_id" class="form-label">Durum</label>
                                <select class="form-select" id="durum_id" name="durum_id" required>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($durumlar as $durum): ?>
                                        <option value="<?= $durum['id'] ?>" <?= (isset($durum_id) && $durum_id == $durum['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($durum['durum_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="profil" class="form-label">Profil</label>
                                <input type="text" class="form-control" id="profil" name="profil" 
                                    value="<?= isset($profil) ? guvenli($profil) : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="montaj_id" class="form-label">Montaj Tipi</label>
                                <select class="form-select" id="montaj_id" name="montaj_id">
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($montaj_tipleri as $montaj): ?>
                                        <option value="<?= $montaj['id'] ?>" <?= (isset($montaj_id) && $montaj_id == $montaj['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($montaj['montaj_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="tarih" class="form-label">Tarih</label>
                                <input type="date" class="form-control" id="tarih" name="tarih" 
                                    value="<?= isset($tarih) ? guvenli($tarih) : date('Y-m-d') ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="saat" class="form-label">Saat</label>
                                <input type="time" class="form-control" id="saat" name="saat" 
                                    value="<?= isset($saat) ? guvenli($saat) : date('H:i') ?>" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="islem_tipi" class="form-label">İşlem Tipi</label>
                                <select class="form-select" id="islem_tipi" name="islem_tipi">
                                    <option value="">Seçiniz</option>
                                    <option value="EA" <?= (isset($islem_tipi) && $islem_tipi == 'EA') ? 'selected' : '' ?>>EA</option>
                                    <option value="İA" <?= (isset($islem_tipi) && $islem_tipi == 'İA') ? 'selected' : '' ?>>İA</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="miktar" class="form-label">Miktar</label>
                                <input type="number" class="form-control" id="miktar" name="miktar" 
                                    value="<?= isset($miktar) ? guvenli($miktar) : '0' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" 
                                    value="<?= isset($bitis_tarihi) ? guvenli($bitis_tarihi) : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="renk_id" class="form-label">Renk</label>
                                <select class="form-select" id="renk_id" name="renk_id">
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($renkler as $renk): ?>
                                        <option value="<?= $renk['id'] ?>" <?= (isset($renk_id) && $renk_id == $renk['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($renk['renk_kodu']) ?> (<?= guvenli($renk['renk_adi']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="acil" name="acil" 
                                        <?= (isset($acil) && $acil) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="acil">
                                        ACİL
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <label for="kasa_tipi" class="form-label">Kasa Tipi</label>
                                <input type="text" class="form-control" id="kasa_tipi" name="kasa_tipi" 
                                    value="<?= isset($kasa_tipi) ? guvenli($kasa_tipi) : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="boya_kilidi" class="form-label">Boya Kilidi</label>
                                <input type="text" class="form-control" id="boya_kilidi" name="boya_kilidi" 
                                    value="<?= isset($boya_kilidi) ? guvenli($boya_kilidi) : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="faz" class="form-label">FAZ</label>
                                <input type="text" class="form-control" id="faz" name="faz" 
                                    value="<?= isset($faz) ? guvenli($faz) : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="satis_no" class="form-label">Satış No</label>
                                <input type="text" class="form-control" id="satis_no" name="satis_no" 
                                    value="<?= isset($satis_no) ? guvenli($satis_no) : '' ?>">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="paketleme" class="form-label">Paketleme</label>
                                <input type="text" class="form-control" id="paketleme" name="paketleme" 
                                    value="<?= isset($paketleme) ? guvenli($paketleme) : '' ?>">
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">Sipariş Ekle</button>
                                <a href="index.php" class="btn btn-secondary">İptal</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 