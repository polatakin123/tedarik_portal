<?php
// veritabani_guncelle.php - Veritabanı yapısını güncelleyen script
require_once 'config.php';

// Admin yetkisini kontrol et
adminYetkisiKontrol();

// Yapılacak işlemleri tut
$mesajlar = [];

// Kullanıcılar tablosuna firma_id sütunu ekle
$firma_id_kontrol = $db->query("SHOW COLUMNS FROM kullanicilar LIKE 'firma_id'");
if ($firma_id_kontrol->rowCount() == 0) {
    try {
        $db->exec("ALTER TABLE kullanicilar ADD COLUMN firma_id INT NULL AFTER rol, ADD INDEX (firma_id)");
        $mesajlar[] = "kullanicilar tablosuna firma_id sütunu eklendi.";
    } catch (PDOException $e) {
        $mesajlar[] = "HATA: kullanicilar tablosuna firma_id sütunu eklenirken hata oluştu: " . $e->getMessage();
    }
}

// kullanici_tedarikci_iliskileri tablosunu oluştur
try {
    $db->exec("CREATE TABLE IF NOT EXISTS kullanici_tedarikci_iliskileri (
        id INT(11) NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        tedarikci_id INT NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY kullanici_tedarikci (kullanici_id, tedarikci_id),
        INDEX (kullanici_id),
        INDEX (tedarikci_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $mesajlar[] = "kullanici_tedarikci_iliskileri tablosu oluşturuldu veya mevcut.";
} catch (PDOException $e) {
    $mesajlar[] = "HATA: kullanici_tedarikci_iliskileri tablosu oluşturulurken hata: " . $e->getMessage();
}

// Tedarikçi kullanıcılarını bu tabloya ekle
try {
    $tedarikci_kullanicilari = $db->query("SELECT id, email FROM kullanicilar WHERE rol = 'Tedarikci'");
    $tedarikci_kullanicilari = $tedarikci_kullanicilari->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($tedarikci_kullanicilari as $kullanici) {
        // Kullanıcının emaili ile eşleşen tedarikçiyi bul
        $tedarikci_stmt = $db->prepare("SELECT id FROM tedarikciler WHERE email = ?");
        $tedarikci_stmt->execute([$kullanici['email']]);
        $tedarikci = $tedarikci_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($tedarikci) {
            // Kullanıcının firma_id'sini güncelle
            $guncelle_stmt = $db->prepare("UPDATE kullanicilar SET firma_id = ? WHERE id = ?");
            $guncelle_stmt->execute([$tedarikci['id'], $kullanici['id']]);
            $mesajlar[] = "Kullanıcı ID: {$kullanici['id']} için firma_id: {$tedarikci['id']} olarak güncellendi.";
            
            // İlişkiler tablosuna ekle
            $ilişki_stmt = $db->prepare("INSERT IGNORE INTO kullanici_tedarikci_iliskileri (kullanici_id, tedarikci_id) VALUES (?, ?)");
            $ilişki_stmt->execute([$kullanici['id'], $tedarikci['id']]);
            $mesajlar[] = "Kullanıcı ID: {$kullanici['id']} ve Tedarikçi ID: {$tedarikci['id']} ilişkisi oluşturuldu.";
        }
    }
} catch (PDOException $e) {
    $mesajlar[] = "HATA: Tedarikçi ilişkileri kurulurken hata: " . $e->getMessage();
}

// Aktif sütunlarını tablolara ekle
$tablolar = ['kullanicilar', 'tedarikciler', 'projeler'];

foreach ($tablolar as $tablo) {
    $aktif_kontrol = $db->query("SHOW COLUMNS FROM $tablo LIKE 'aktif'");
    if ($aktif_kontrol->rowCount() == 0) {
        try {
            $db->exec("ALTER TABLE $tablo ADD COLUMN aktif TINYINT(1) NOT NULL DEFAULT 1");
            $mesajlar[] = "$tablo tablosuna aktif sütunu eklendi.";
        } catch (PDOException $e) {
            $mesajlar[] = "HATA: $tablo tablosuna aktif sütunu eklenirken hata oluştu: " . $e->getMessage();
        }
    }
}

// Sonuçları göster
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Güncelleme - Tedarik Portalı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Veritabanı Güncelleme</h4>
                    </div>
                    <div class="card-body">
                        <h5>Yapılan Değişiklikler:</h5>
                        <ul class="list-group mb-4">
                            <?php foreach ($mesajlar as $mesaj): ?>
                                <?php $hata_mi = (strpos($mesaj, 'HATA:') === 0); ?>
                                <li class="list-group-item <?= $hata_mi ? 'list-group-item-danger' : 'list-group-item-success' ?>"><?= $mesaj ?></li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <div class="alert alert-info">
                            <p>Veritabanı güncellemesi tamamlandı. Aşağıdaki işlemleri yapabilirsiniz:</p>
                            <ul>
                                <li>Tedarikçi kullanıcılarını ilgili firmalarla ilişkilendirmek için kullanıcı yönetimini kullanın</li>
                                <li>Eksik kayıtları manuel olarak eklemek veya düzenlemek için veritabanı yönetim arayüzünü kullanın</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <a href="admin/index.php" class="btn btn-primary">Admin Paneline Dön</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 