-- Ayarlar tablosu
CREATE TABLE IF NOT EXISTS `ayarlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_basligi` varchar(255) NOT NULL,
  `site_aciklamasi` text,
  `email` varchar(255) NOT NULL,
  `telefon` varchar(50) DEFAULT NULL,
  `adres` text,
  `tema_renk` varchar(20) DEFAULT '#4e73df',
  `logo_url` varchar(255) DEFAULT NULL,
  `favicon_url` varchar(255) DEFAULT NULL,
  `olusturma_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `guncelleme_tarihi` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan ayarları ekle
INSERT INTO `ayarlar` (`id`, `site_basligi`, `site_aciklamasi`, `email`, `telefon`, `adres`, `tema_renk`, `logo_url`, `favicon_url`) VALUES
(1, 'Tedarik Portalı', 'Tedarikçi ve Sipariş Yönetim Sistemi', 'info@tedarikportali.com', '0212 123 45 67', 'İstanbul, Türkiye', '#4e73df', '', ''); 