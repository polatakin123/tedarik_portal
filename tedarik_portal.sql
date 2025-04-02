-- tedarik_portal.sql

-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS tedarik_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
USE tedarik_portal;

-- Kullanıcılar tablosu (admin, sorumlu, tedarikçi rollerini içerir)
CREATE TABLE IF NOT EXISTS kullanicilar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
    sifre VARCHAR(255) NOT NULL,
    ad_soyad VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefon VARCHAR(20),
    rol ENUM('Admin', 'Sorumlu', 'Tedarikci') NOT NULL DEFAULT 'Tedarikci',
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    son_giris DATETIME,
    olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Projeler tablosu
CREATE TABLE IF NOT EXISTS projeler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    proje_adi VARCHAR(255) NOT NULL,
    proje_aciklama TEXT,
    baslangic_tarihi DATE,
    bitis_tarihi DATE,
    durum ENUM('Aktif', 'Tamamlandi', 'Beklemede', 'Iptal') NOT NULL DEFAULT 'Aktif',
    olusturan_id INT NOT NULL,
    olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id)
);

-- Tedarikçiler tablosu
CREATE TABLE IF NOT EXISTS tedarikciler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    firma_adi VARCHAR(255) NOT NULL,
    firma_kodu VARCHAR(50),
    adres TEXT,
    telefon VARCHAR(20),
    email VARCHAR(100),
    yetkili_kisi VARCHAR(100),
    vergi_no VARCHAR(20),
    aktif BOOLEAN NOT NULL DEFAULT TRUE,
    olusturan_id INT NOT NULL,
    olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    kullanici_id INT,
    FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id)
);

-- Sorumluluklar tablosu (sorumlu-tedarikçi ilişkisi)
CREATE TABLE IF NOT EXISTS sorumluluklar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sorumlu_id INT NOT NULL,
    tedarikci_id INT NOT NULL,
    atama_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    olusturan_id INT NOT NULL,
    FOREIGN KEY (sorumlu_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (tedarikci_id) REFERENCES tedarikciler(id),
    FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id),
    UNIQUE KEY (sorumlu_id, tedarikci_id)
);

-- Sipariş durumları tablosu
CREATE TABLE IF NOT EXISTS siparis_durumlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    durum_adi VARCHAR(50) NOT NULL,
    aciklama TEXT
);

-- Siparişler tablosu (genişletilmiş)
CREATE TABLE IF NOT EXISTS siparisler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siparis_no VARCHAR(50) NOT NULL UNIQUE,
    parca_no VARCHAR(50) NOT NULL,
    tanim VARCHAR(255),
    proje_id INT,
    tedarikci_id INT,
    sorumlu_id INT,
    acilis_tarihi DATE NOT NULL,
    teslim_tarihi DATE,
    miktar INT DEFAULT 0,
    birim VARCHAR(20),
    kalan_miktar INT DEFAULT 0,
    durum_id INT NOT NULL,
    fai BOOLEAN DEFAULT FALSE,
    paketleme VARCHAR(100),
    satinalmaci VARCHAR(100),
    alt_malzeme TEXT,
    tedarikci_tarihi DATE,
    tedarikci_notu TEXT,
    onaylanan_revizyon VARCHAR(50),
    tedarikci_parca_no VARCHAR(50),
    vehicle_id VARCHAR(50),
    olusturan_id INT NOT NULL,
    olusturma_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (durum_id) REFERENCES siparis_durumlari(id),
    FOREIGN KEY (proje_id) REFERENCES projeler(id),
    FOREIGN KEY (tedarikci_id) REFERENCES tedarikciler(id),
    FOREIGN KEY (sorumlu_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id)
);

-- Sipariş dokümanları tablosu
CREATE TABLE IF NOT EXISTS siparis_dokumanlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siparis_id INT NOT NULL,
    dokuman_adi VARCHAR(255) NOT NULL,
    dosya_yolu VARCHAR(255) NOT NULL,
    dosya_turu VARCHAR(50),
    yukleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    yukleyen_id INT NOT NULL,
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id),
    FOREIGN KEY (yukleyen_id) REFERENCES kullanicilar(id)
);

-- Sipariş kalemler tablosu
CREATE TABLE IF NOT EXISTS siparis_kalemleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siparis_id INT NOT NULL,
    parca_no VARCHAR(50) NOT NULL,
    miktar INT NOT NULL DEFAULT 0,
    teslim_edilen INT DEFAULT 0,
    birim_fiyat DECIMAL(10, 2),
    para_birimi VARCHAR(10),
    aciklama TEXT,
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id)
);

-- Sipariş güncellemeleri tablosu
CREATE TABLE IF NOT EXISTS siparis_guncellemeleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    siparis_id INT NOT NULL,
    guncelleme_tipi VARCHAR(100) NOT NULL,
    guncelleme_detay TEXT,
    guncelleme_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    guncelleyen_id INT NOT NULL,
    FOREIGN KEY (siparis_id) REFERENCES siparisler(id),
    FOREIGN KEY (guncelleyen_id) REFERENCES kullanicilar(id)
);

-- Bildirimler tablosu
CREATE TABLE IF NOT EXISTS bildirimler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    mesaj TEXT NOT NULL,
    okundu BOOLEAN DEFAULT FALSE,
    bildirim_tarihi DATETIME DEFAULT CURRENT_TIMESTAMP,
    ilgili_siparis_id INT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (ilgili_siparis_id) REFERENCES siparisler(id)
);

-- Örnek veriler
-- Örnek sipariş durumları
INSERT INTO siparis_durumlari (durum_adi, aciklama) VALUES 
('Açık', 'Aktif sipariş'),
('Kapalı', 'Tamamlanmış sipariş'),
('Beklemede', 'Bekleyen sipariş'),
('İptal', 'İptal edilmiş sipariş');

-- Örnek kullanıcılar (şifre: 123456)
INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, email, rol) VALUES 
('admin', '$2y$10$92bJNMiiRe2vRPJ3xrcVGujbqCbQ8vp2y8hkwGpH3pPjmA0F5E7lW', 'Sistem Yöneticisi', 'admin@ornek.com', 'Admin'),
('sorumlu1', '$2y$10$92bJNMiiRe2vRPJ3xrcVGujbqCbQ8vp2y8hkwGpH3pPjmA0F5E7lW', 'Sorumlu Kullanıcı', 'sorumlu@ornek.com', 'Sorumlu'),
('tedarikci1', '$2y$10$92bJNMiiRe2vRPJ3xrcVGujbqCbQ8vp2y8hkwGpH3pPjmA0F5E7lW', 'Tedarikçi Firma', 'tedarikci@ornek.com', 'Tedarikci');

-- Örnek projeler
INSERT INTO projeler (proje_adi, proje_aciklama, baslangic_tarihi, bitis_tarihi, durum, olusturan_id) VALUES 
('Araç Modernizasyonu', 'Zırhlı araçların modernizasyonu projesi', '2023-01-01', '2023-12-31', 'Aktif', 1),
('İHA Geliştirme', 'İnsansız hava aracı geliştirme projesi', '2023-02-15', '2024-06-30', 'Aktif', 1);

-- Örnek tedarikçiler
INSERT INTO tedarikciler (firma_adi, firma_kodu, adres, telefon, email, yetkili_kisi, olusturan_id, kullanici_id) VALUES 
('ABC Metal A.Ş.', 'ABC001', 'Ankara Organize Sanayi Bölgesi', '0312 555 5555', 'info@abcmetal.com', 'Ahmet Yılmaz', 1, 3),
('XYZ Elektronik Ltd.', 'XYZ002', 'İstanbul Teknopark', '0212 444 4444', 'info@xyzelektronik.com', 'Mehmet Demir', 1, NULL);

-- Örnek sorumluluklar
INSERT INTO sorumluluklar (sorumlu_id, tedarikci_id, olusturan_id) VALUES 
(2, 1, 1); -- Sorumlu1, ABC Metal firmasından sorumlu

-- Örnek siparişler
INSERT INTO siparisler (siparis_no, parca_no, tanim, proje_id, tedarikci_id, sorumlu_id, acilis_tarihi, 
                      teslim_tarihi, miktar, birim, kalan_miktar, durum_id, fai, olusturan_id) VALUES 
('S2023-001', 'P1001', 'Zırh Plakası', 1, 1, 2, '2023-03-01', '2023-04-15', 100, 'Adet', 100, 1, TRUE, 1),
('S2023-002', 'E2001', 'Elektronik Kontrol Ünitesi', 2, 2, 2, '2023-03-10', '2023-05-01', 50, 'Adet', 50, 1, FALSE, 1);

-- Örnek sipariş dokümanları
INSERT INTO siparis_dokumanlari (siparis_id, dokuman_adi, dosya_yolu, dosya_turu, yukleyen_id) VALUES 
(1, 'Teknik Çizim', '/dosyalar/teknik_cizim.pdf', 'PDF', 1),
(1, 'Malzeme Listesi', '/dosyalar/malzeme_listesi.xlsx', 'XLSX', 1);

-- Örnek sipariş kalemleri
INSERT INTO siparis_kalemleri (siparis_id, parca_no, miktar, teslim_edilen) VALUES 
(1, 'P1001-A', 50, 0),
(1, 'P1001-B', 50, 0),
(2, 'E2001-X', 50, 0);

-- Örnek sipariş güncellemeleri
INSERT INTO siparis_guncellemeleri (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleyen_id) VALUES 
(1, 'Durum Değişikliği', 'Sipariş durumu Açık olarak güncellendi', 1),
(2, 'Teslimat Tarihi Değişikliği', 'Teslimat tarihi 2023-05-01 olarak güncellendi', 1);

-- Örnek bildirimler
INSERT INTO bildirimler (kullanici_id, mesaj, ilgili_siparis_id) VALUES 
(2, 'S2023-001 numaralı siparişin teslimat tarihi yaklaşıyor', 1),
(3, 'S2023-001 numaralı sipariş size atandı', 1); 