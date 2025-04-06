<?php
// Veritabanı kurulum ve güncelleme scripti
require_once 'config.php';

// Başlık
echo "<h1>Veritabanı Kurulum ve Güncelleme Scripti</h1>";

try {
    // Sipariş durum tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS siparis_durumlari (
        id INT AUTO_INCREMENT PRIMARY KEY,
        durum_adi VARCHAR(50) NOT NULL,
        aciklama TEXT
    )");
    echo "<p>siparis_durumlari tablosu oluşturuldu veya mevcut.</p>";

    // Kullanıcılar tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS kullanicilar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
        sifre VARCHAR(255) NOT NULL,
        ad_soyad VARCHAR(100) NOT NULL,
        yetki_seviyesi TINYINT NOT NULL DEFAULT 1,
        son_giris DATETIME,
        aktif BOOLEAN NOT NULL DEFAULT TRUE,
        olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<p>kullanicilar tablosu oluşturuldu veya mevcut.</p>";

    // Montaj tipleri tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS montaj_tipleri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        montaj_adi VARCHAR(100) NOT NULL,
        aciklama TEXT
    )");
    echo "<p>montaj_tipleri tablosu oluşturuldu veya mevcut.</p>";

    // Renkler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS renkler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        renk_kodu VARCHAR(50) NOT NULL,
        renk_adi VARCHAR(100),
        aciklama TEXT
    )");
    echo "<p>renkler tablosu oluşturuldu veya mevcut.</p>";

    // Ana sipariş tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS siparisler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        siparis_no VARCHAR(50) NOT NULL UNIQUE,
        parca_no VARCHAR(50) NOT NULL,
        durum_id INT NOT NULL,
        profil VARCHAR(100),
        montaj_id INT,
        tarih DATE NOT NULL,
        saat TIME NOT NULL,
        islem_tipi VARCHAR(20),
        miktar INT DEFAULT 0,
        iade_miktar INT DEFAULT 0,
        teslim_miktar INT DEFAULT 0,
        bitis_tarihi DATE,
        acil BOOLEAN DEFAULT FALSE,
        renk_id INT,
        kasa_tipi VARCHAR(100),
        boya_kilidi VARCHAR(100),
        faz VARCHAR(20),
        firma_id INT,
        satis_no VARCHAR(50),
        paketleme VARCHAR(100),
        olusturan_id INT NOT NULL,
        olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (durum_id) REFERENCES siparis_durumlari(id),
        FOREIGN KEY (montaj_id) REFERENCES montaj_tipleri(id),
        FOREIGN KEY (renk_id) REFERENCES renkler(id),
        FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id)
    )");
    echo "<p>siparisler tablosu oluşturuldu veya mevcut.</p>";

    // Sipariş işlem geçmişi
    $db->exec("CREATE TABLE IF NOT EXISTS siparis_gecmisi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        siparis_id INT NOT NULL,
        islem_tipi VARCHAR(50) NOT NULL,
        aciklama TEXT,
        kullanici_id INT NOT NULL,
        islem_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (siparis_id) REFERENCES siparisler(id),
        FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
    )");
    echo "<p>siparis_gecmisi tablosu oluşturuldu veya mevcut.</p>";

    // Örnek verileri kontrol et ve ekle
    $stmt = $db->query("SELECT COUNT(*) FROM siparis_durumlari");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO siparis_durumlari (durum_adi, aciklama) VALUES 
            ('Açık', 'Devam eden siparişler'),
            ('Kapalı', 'Tamamlanmış siparişler'),
            ('Kaldırılmış', 'İptal edilmiş siparişler')");
        echo "<p>siparis_durumlari için örnek veriler eklendi.</p>";
    }

    $stmt = $db->query("SELECT COUNT(*) FROM montaj_tipleri");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO montaj_tipleri (montaj_adi) VALUES 
            ('MONTAJLI'),
            ('MONTAJSIZ')");
        echo "<p>montaj_tipleri için örnek veriler eklendi.</p>";
    }

    $stmt = $db->query("SELECT COUNT(*) FROM renkler");
    if ($stmt->fetchColumn() == 0) {
        $db->exec("INSERT INTO renkler (renk_kodu, renk_adi) VALUES 
            ('ColorDoc:301507 ExtEarthYellow33245', 'Sarı'),
            ('ColorDoc:301506 ExtGreen363', 'Yeşil')");
        echo "<p>renkler için örnek veriler eklendi.</p>";
    }

    $stmt = $db->query("SELECT COUNT(*) FROM kullanicilar");
    if ($stmt->fetchColumn() == 0) {
        // Varsayılan admin kullanıcısı ekle (şifre: admin123)
        $db->exec("INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, yetki_seviyesi) VALUES 
            ('admin', '\$2y\$10\$Q8/DZjnP3MF7RDgzk1.0aeMGzGhZFtf2HAyQ6VgB1DoiEFbG.oMQa', 'Sistem Yöneticisi', 3)");
        echo "<p>Varsayılan admin kullanıcısı eklendi (Kullanıcı adı: admin, Şifre: admin123).</p>";
    }

    echo "<h2>Veritabanı kurulumu başarıyla tamamlandı!</h2>";
    echo "<a href='index.php'>Ana Sayfaya Dön</a>";

} catch (PDOException $e) {
    echo "<div style='color: red; font-weight: bold;'>HATA: " . $e->getMessage() . "</div>";
}
?> 