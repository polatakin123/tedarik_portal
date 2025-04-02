-- savunma_sistemi.sql

-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS savunma_sistemi CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE savunma_sistemi;

-- Sipariş durum tablosu
CREATE TABLE IF NOT EXISTS siparis_durumlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    durum_adi VARCHAR(50) NOT NULL,
    aciklama TEXT
);

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
    sifre VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(100) NOT NULL,
    yetki_seviyesi TINYINT NOT NULL DEFAULT 1,
    son_giris DATETIME,
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Montaj tipleri tablosu
CREATE TABLE IF NOT EXISTS montaj_tipleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    montaj_adi VARCHAR(100) NOT NULL,
    aciklama TEXT
);

-- Renkler tablosu
CREATE TABLE IF NOT EXISTS renkler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    renk_kodu VARCHAR(50) NOT NULL,
    renk_adi VARCHAR(100),
    aciklama TEXT
);

-- Ana sipariş tablosu
CREATE TABLE IF NOT EXISTS siparisler (
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
);

-- Sipariş işlem geçmişi
CREATE TABLE IF NOT EXISTS siparis_gecmisi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siparis_id INT NOT NULL,
    islem_tipi VARCHAR(50) NOT NULL,
    aciklama TEXT,
    kullanici_id INT NOT NULL,
    islem_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
);

-- Örnek verileri ekle
INSERT INTO siparis_durumlari (durum_adi, aciklama) VALUES 
('Açık', 'Devam eden siparişler'),
('Kapalı', 'Tamamlanmış siparişler'),
('Kaldırılmış', 'İptal edilmiş siparişler');

INSERT INTO montaj_tipleri (montaj_adi) VALUES 
('MONTAJLI'),
('MONTAJSIZ');

INSERT INTO renkler (renk_kodu, renk_adi) VALUES 
('ColorDoc:301507 ExtEarthYellow33245', 'Sarı'),
('ColorDoc:301506 ExtGreen363', 'Yeşil');

-- Varsayılan admin kullanıcısı ekle (şifre: admin123)
INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, yetki_seviyesi) VALUES 
('admin', '$2y$10$Q8/DZjnP3MF7RDgzk1.0aeMGzGhZFtf2HAyQ6VgB1DoiEFbG.oMQa', 'Sistem Yöneticisi', 3);