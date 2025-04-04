<?php
// admin/siparis_ekle.php - Sipariş ekleme sayfası
require_once '../config.php';
adminYetkisiKontrol();

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tedarikci_id = isset($_POST['tedarikci_id']) ? intval($_POST['tedarikci_id']) : 0;
    $proje_id = isset($_POST['proje_id']) ? intval($_POST['proje_id']) : 0;
    $sorumlu_id = isset($_POST['sorumlu_id']) ? intval($_POST['sorumlu_id']) : 0;
    $durum_id = isset($_POST['durum_id']) ? intval($_POST['durum_id']) : 1; // Varsayılan: Açık
    $parca_no = isset($_POST['parca_no']) ? trim($_POST['parca_no']) : '';
    $parca_adi = isset($_POST['parca_adi']) ? trim($_POST['parca_adi']) : '';
    $miktar = isset($_POST['miktar']) ? trim($_POST['miktar']) : '';
    $birim = isset($_POST['birim']) ? trim($_POST['birim']) : '';
    $teslim_tarihi = isset($_POST['teslim_tarihi']) ? trim($_POST['teslim_tarihi']) : null;
    $aciklama = isset($_POST['aciklama']) ? trim($_POST['aciklama']) : '';
    
    // Sipariş numarası oluşturma (Örnek: SIP-2023-0001)
    $yil = date('Y');
    $siparis_kodu = "SIP-" . $yil . "-";
    
    // Son sipariş numarasını bul ve bir artır
    $son_siparis_sql = "SELECT MAX(CAST(SUBSTRING_INDEX(siparis_no, '-', -1) AS UNSIGNED)) as son_no 
                       FROM siparisler 
                       WHERE siparis_no LIKE ?";
    $son_siparis_stmt = $db->prepare($son_siparis_sql);
    $son_siparis_stmt->execute([$siparis_kodu . "%"]);
    $son_no = $son_siparis_stmt->fetch(PDO::FETCH_ASSOC)['son_no'];
    
    $yeni_no = $son_no ? $son_no + 1 : 1;
    $siparis_no = $siparis_kodu . str_pad($yeni_no, 4, "0", STR_PAD_LEFT);
    
    // Validasyon
    $hatalar = [];
    
    if (empty($tedarikci_id)) {
        $hatalar[] = "Tedarikçi seçilmelidir.";
    }
    
    if (empty($proje_id)) {
        $hatalar[] = "Proje seçilmelidir.";
    }
    
    if (empty($sorumlu_id)) {
        $hatalar[] = "Sorumlu kişi seçilmelidir.";
    }
    
    if (empty($parca_no)) {
        $hatalar[] = "Parça numarası girilmelidir.";
    }
    
    if (empty($miktar)) {
        $hatalar[] = "Miktar girilmelidir.";
    } elseif (!is_numeric($miktar) || $miktar <= 0) {
        $hatalar[] = "Miktar pozitif bir sayı olmalıdır.";
    }
    
    if (empty($birim)) {
        $hatalar[] = "Birim seçilmelidir.";
    }
    
    // Hata yoksa siparişi ekle
    if (empty($hatalar)) {
        try {
            $db->beginTransaction();
            
            $sql = "INSERT INTO siparisler (
                        siparis_no, tedarikci_id, proje_id, sorumlu_id, durum_id, 
                        parca_no, parca_adi, miktar, birim, teslim_tarihi, 
                        aciklama, olusturan_id, olusturma_tarihi
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $siparis_no, $tedarikci_id, $proje_id, $sorumlu_id, $durum_id,
                $parca_no, $parca_adi, $miktar, $birim, $teslim_tarihi,
                $aciklama, $_SESSION['kullanici_id']
            ]);
            
            $siparis_id = $db->lastInsertId();
            
            // Sorumluya bildirim gönder
            $bildirim_mesaji = "Yeni bir sipariş oluşturuldu: " . $siparis_no;
            bildirimOlustur($db, $sorumlu_id, $bildirim_mesaji, $siparis_id);
            
            // Tedarikçi kullanıcılarına bildirim gönder
            $tedarikci_kullanicilar_sql = "SELECT kullanici_id FROM kullanici_tedarikci_iliskileri WHERE tedarikci_id = ?";
            $tedarikci_kullanicilar_stmt = $db->prepare($tedarikci_kullanicilar_sql);
            $tedarikci_kullanicilar_stmt->execute([$tedarikci_id]);
            
            while ($kullanici = $tedarikci_kullanicilar_stmt->fetch(PDO::FETCH_ASSOC)) {
                $bildirim_mesaji = "Size yeni bir sipariş atandı: " . $siparis_no;
                bildirimOlustur($db, $kullanici['kullanici_id'], $bildirim_mesaji, $siparis_id);
            }
            
            $db->commit();
            
            // Başarılı mesajı ile yönlendir
            $mesaj = "Sipariş başarıyla oluşturuldu.";
            header("Location: siparis_detay.php?id=" . $siparis_id . "&mesaj=" . urlencode($mesaj));
            exit;
        } catch (PDOException $e) {
            $db->rollBack();
            $hatalar[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Tedarikçileri getir
$tedarikciler_sql = "SELECT id, firma_adi, firma_kodu FROM tedarikciler WHERE aktif = 1 ORDER BY firma_adi";
$tedarikciler_stmt = $db->prepare($tedarikciler_sql);
$tedarikciler_stmt->execute();
$tedarikciler = $tedarikciler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Projeleri getir
$projeler_sql = "SELECT id, proje_adi, proje_kodu FROM projeler WHERE aktif = 1 ORDER BY proje_adi";
$projeler_stmt = $db->prepare($projeler_sql);
$projeler_stmt->execute();
$projeler = $projeler_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sorumluları getir
$sorumlular_sql = "SELECT id, ad_soyad, email FROM kullanicilar WHERE rol = 'Sorumlu' AND aktif = 1 ORDER BY ad_soyad";
$sorumlular_stmt = $db->prepare($sorumlular_sql);
$sorumlular_stmt->execute();
$sorumlular = $sorumlular_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sipariş durumlarını getir
$durumlar_sql = "SELECT id, durum_adi FROM siparis_durumlari ORDER BY id";
$durumlar_stmt = $db->prepare($durumlar_sql);
$durumlar_stmt->execute();
$durumlar = $durumlar_stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Sipariş Ekle - Admin Paneli</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            background-color: #4e73df;
            transition: all 0.3s;
            width: 250px;
        }
        .sidebar-sticky {
            position: relative;
            top: 0;
            height: calc(100vh - 48px);
            padding-top: 0.5rem;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.2);
        }
        .sidebar .nav-link i {
            margin-right: 0.5rem;
        }
        main {
            margin-left: 250px;
            padding: 2rem;
            padding-top: 70px;
            transition: all 0.3s;
        }
        .navbar {
            position: fixed;
            top: 0;
            right: 0;
            left: 250px;
            z-index: 99;
            background-color: #fff !important;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            transition: all 0.3s;
            height: 60px;
        }
        .card {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .card-header {
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        .badge-notification {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar col-md-3 col-lg-2 d-md-block text-white">
        <div class="pt-3 text-center mb-4">
            <h4>Tedarik Portalı</h4>
            <p>Admin Paneli</p>
        </div>
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="siparisler.php">
                        <i class="bi bi-list-check"></i> Siparişler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="tedarikciler.php">
                        <i class="bi bi-building"></i> Tedarikçiler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="sorumlular.php">
                        <i class="bi bi-people"></i> Sorumlular
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="projeler.php">
                        <i class="bi bi-diagram-3"></i> Projeler
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="kullanicilar.php">
                        <i class="bi bi-person-badge"></i> Kullanıcılar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="raporlar.php">
                        <i class="bi bi-graph-up"></i> Raporlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="ayarlar.php">
                        <i class="bi bi-gear"></i> Ayarlar
                    </a>
                </li>
                <li class="nav-item mt-4">
                    <a class="nav-link" href="../cikis.php">
                        <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownBildirim" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php if ($okunmamis_bildirim_sayisi > 0): ?>
                                <span class="badge rounded-pill bg-danger badge-notification"><?= $okunmamis_bildirim_sayisi ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownBildirim">
                            <?php if (count($bildirimler) > 0): ?>
                                <?php foreach ($bildirimler as $bildirim): ?>
                                    <li>
                                        <a class="dropdown-item" href="bildirim_goruntule.php?id=<?= $bildirim['id'] ?>">
                                            <?= guvenli(mb_substr($bildirim['mesaj'], 0, 50)) ?>...
                                            <div class="small text-muted"><?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?></div>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="bildirimler.php">Tüm Bildirimleri Gör</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item text-center" href="#">Bildirim bulunmamaktadır</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownProfil" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= guvenli($_SESSION['ad_soyad']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdownProfil">
                            <li><a class="dropdown-item" href="profil.php">Profilim</a></li>
                            <li><a class="dropdown-item" href="ayarlar.php">Ayarlar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../cikis.php">Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Ana İçerik -->
    <main>
        <div class="container-fluid">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Yeni Sipariş Ekle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="siparisler.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Siparişlere Dön
                    </a>
                </div>
            </div>

            <?php if (!empty($hatalar)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        <?php foreach ($hatalar as $hata): ?>
                            <li><?= guvenli($hata) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Sipariş Bilgileri</h5>
                </div>
                <div class="card-body">
                    <form method="post" action="" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="tedarikci_id" class="form-label">Tedarikçi <span class="text-danger">*</span></label>
                                <select class="form-select" id="tedarikci_id" name="tedarikci_id" required>
                                    <option value="">-- Tedarikçi Seçin --</option>
                                    <?php foreach ($tedarikciler as $tedarikci): ?>
                                        <option value="<?= $tedarikci['id'] ?>" <?= (isset($tedarikci_id) && $tedarikci_id == $tedarikci['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($tedarikci['firma_adi']) ?> (<?= guvenli($tedarikci['firma_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Lütfen bir tedarikçi seçin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="proje_id" class="form-label">Proje <span class="text-danger">*</span></label>
                                <select class="form-select" id="proje_id" name="proje_id" required>
                                    <option value="">-- Proje Seçin --</option>
                                    <?php foreach ($projeler as $proje): ?>
                                        <option value="<?= $proje['id'] ?>" <?= (isset($proje_id) && $proje_id == $proje['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($proje['proje_adi']) ?> (<?= guvenli($proje['proje_kodu']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Lütfen bir proje seçin.</div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="sorumlu_id" class="form-label">Sorumlu <span class="text-danger">*</span></label>
                                <select class="form-select" id="sorumlu_id" name="sorumlu_id" required>
                                    <option value="">-- Sorumlu Seçin --</option>
                                    <?php foreach ($sorumlular as $sorumlu): ?>
                                        <option value="<?= $sorumlu['id'] ?>" <?= (isset($sorumlu_id) && $sorumlu_id == $sorumlu['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($sorumlu['ad_soyad']) ?> (<?= guvenli($sorumlu['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Lütfen bir sorumlu seçin.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="durum_id" class="form-label">Durum</label>
                                <select class="form-select" id="durum_id" name="durum_id">
                                    <?php foreach ($durumlar as $durum): ?>
                                        <option value="<?= $durum['id'] ?>" <?= (isset($durum_id) && $durum_id == $durum['id']) ? 'selected' : '' ?>>
                                            <?= guvenli($durum['durum_adi']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="parca_no" class="form-label">Parça Numarası <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="parca_no" name="parca_no" value="<?= isset($parca_no) ? guvenli($parca_no) : '' ?>" required>
                                <div class="invalid-feedback">Parça numarası girilmelidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="parca_adi" class="form-label">Parça Adı</label>
                                <input type="text" class="form-control" id="parca_adi" name="parca_adi" value="<?= isset($parca_adi) ? guvenli($parca_adi) : '' ?>">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="miktar" class="form-label">Miktar <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="miktar" name="miktar" value="<?= isset($miktar) ? guvenli($miktar) : '' ?>" min="0.01" step="0.01" required>
                                <div class="invalid-feedback">Geçerli bir miktar girilmelidir.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="birim" class="form-label">Birim <span class="text-danger">*</span></label>
                                <select class="form-select" id="birim" name="birim" required>
                                    <option value="">-- Birim Seçin --</option>
                                    <option value="Adet" <?= (isset($birim) && $birim == 'Adet') ? 'selected' : '' ?>>Adet</option>
                                    <option value="Kg" <?= (isset($birim) && $birim == 'Kg') ? 'selected' : '' ?>>Kilogram (Kg)</option>
                                    <option value="Lt" <?= (isset($birim) && $birim == 'Lt') ? 'selected' : '' ?>>Litre (Lt)</option>
                                    <option value="Mt" <?= (isset($birim) && $birim == 'Mt') ? 'selected' : '' ?>>Metre (Mt)</option>
                                    <option value="m²" <?= (isset($birim) && $birim == 'm²') ? 'selected' : '' ?>>Metrekare (m²)</option>
                                    <option value="m³" <?= (isset($birim) && $birim == 'm³') ? 'selected' : '' ?>>Metreküp (m³)</option>
                                    <option value="Paket" <?= (isset($birim) && $birim == 'Paket') ? 'selected' : '' ?>>Paket</option>
                                    <option value="Kutu" <?= (isset($birim) && $birim == 'Kutu') ? 'selected' : '' ?>>Kutu</option>
                                    <option value="Takım" <?= (isset($birim) && $birim == 'Takım') ? 'selected' : '' ?>>Takım</option>
                                </select>
                                <div class="invalid-feedback">Lütfen bir birim seçin.</div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="teslim_tarihi" class="form-label">Teslim Tarihi</label>
                            <input type="date" class="form-control" id="teslim_tarihi" name="teslim_tarihi" value="<?= isset($teslim_tarihi) ? guvenli($teslim_tarihi) : '' ?>">
                            <div class="form-text">Teslim tarihi belirlenmemişse boş bırakabilirsiniz.</div>
                        </div>
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="4"><?= isset($aciklama) ? guvenli($aciklama) : '' ?></textarea>
                        </div>
                        <div class="text-end">
                            <a href="siparisler.php" class="btn btn-secondary">İptal</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Siparişi Oluştur
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Bootstrap form validasyonu
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
        
        // Tedarikçi seçildiğinde ilgili sorumluları getir
        document.getElementById('tedarikci_id').addEventListener('change', function() {
            const tedarikciId = this.value;
            if (tedarikciId) {
                fetch('api/get_tedarikci_sorumlulari.php?tedarikci_id=' + tedarikciId)
                    .then(response => response.json())
                    .then(data => {
                        const sorumluSelect = document.getElementById('sorumlu_id');
                        const selectedValue = sorumluSelect.value;
                        
                        if (data.length > 0) {
                            // Sorumlu seçimi için öneri oluştur
                            const sorumlularOption = document.createElement('optgroup');
                            sorumlularOption.label = 'Bu tedarikçinin sorumluları';
                            
                            data.forEach(sorumlu => {
                                const option = document.createElement('option');
                                option.value = sorumlu.id;
                                option.text = sorumlu.ad_soyad + ' (' + sorumlu.email + ')';
                                option.selected = (selectedValue == sorumlu.id);
                                sorumlularOption.appendChild(option);
                            });
                            
                            // Diğer seçenekleri koruyarak yeni önerileri ekle
                            const currentOptions = Array.from(sorumluSelect.options);
                            sorumluSelect.innerHTML = '';
                            
                            // İlk seçenek (-- Sorumlu Seçin --)
                            if(currentOptions.length > 0 && currentOptions[0].value === '') {
                                sorumluSelect.add(currentOptions[0]);
                            }
                            
                            sorumluSelect.appendChild(sorumlularOption);
                        }
                    })
                    .catch(error => console.error('Sorumluları getirirken hata oluştu:', error));
            }
        });
    </script>
</body>
</html> 