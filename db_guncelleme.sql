-- Tedarik Portalı Database Güncellemeleri

-- 1. 'aktif' sütunlarını ekle 
-- (kullanıcılar, tedarikciler ve projeler tablolarına)
ALTER TABLE kullanicilar ADD COLUMN IF NOT EXISTS aktif TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE tedarikciler ADD COLUMN IF NOT EXISTS aktif TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE projeler ADD COLUMN IF NOT EXISTS aktif TINYINT(1) NOT NULL DEFAULT 1;

-- 2. 'firma_id' sütununu kullanicilar tablosuna ekle
ALTER TABLE kullanicilar ADD COLUMN IF NOT EXISTS firma_id INT NULL AFTER rol;
ALTER TABLE kullanicilar ADD INDEX IF NOT EXISTS (firma_id);

-- 3. kullanici_tedarikci_iliskileri tablosunu oluştur
CREATE TABLE IF NOT EXISTS kullanici_tedarikci_iliskileri (
    id INT(11) NOT NULL AUTO_INCREMENT,
    kullanici_id INT NOT NULL,
    tedarikci_id INT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY kullanici_tedarikci (kullanici_id, tedarikci_id),
    INDEX (kullanici_id),
    INDEX (tedarikci_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tedarikçi kullanıcılarını ve firmalarını ilişkilendir
-- Bu sorgu tedarikçi rolündeki kullanıcıları aynı email adresine sahip tedarikçilerle eşleştirir
-- Önce firma_id alanını günceller
UPDATE kullanicilar k
JOIN tedarikciler t ON k.email = t.email
SET k.firma_id = t.id
WHERE k.rol = 'Tedarikci' AND k.firma_id IS NULL;

-- Sonra ilişki tablosuna ekler
INSERT IGNORE INTO kullanici_tedarikci_iliskileri (kullanici_id, tedarikci_id) 
SELECT k.id, t.id
FROM kullanicilar k
JOIN tedarikciler t ON k.email = t.email
WHERE k.rol = 'Tedarikci'; 