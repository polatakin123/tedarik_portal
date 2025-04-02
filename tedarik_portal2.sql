-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 03 Nis 2025, 00:48:21
-- Sunucu sürümü: 10.4.28-MariaDB
-- PHP Sürümü: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `tedarik_portal`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bildirimler`
--

CREATE TABLE `bildirimler` (
  `id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `mesaj` text NOT NULL,
  `okundu` tinyint(1) DEFAULT 0,
  `bildirim_tarihi` datetime DEFAULT current_timestamp(),
  `ilgili_siparis_id` int(11) DEFAULT NULL,
  `gonderen_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `bildirimler`
--

INSERT INTO `bildirimler` (`id`, `kullanici_id`, `mesaj`, `okundu`, `bildirim_tarihi`, `ilgili_siparis_id`, `gonderen_id`) VALUES
(1, 2, 'S2023-001 numaralı siparişin teslimat tarihi yaklaşıyor', 1, '2025-04-02 23:21:07', 1, 0),
(2, 3, 'S2023-001 numaralı sipariş size atandı', 0, '2025-04-02 23:21:07', 1, 0),
(3, 2, 'ABC Metal A.Ş. firması sorumluluğunuza atandı.', 1, '2025-04-03 00:02:23', NULL, 0),
(4, 5, 'XYZ Elektronik Ltd. firması sorumluluğunuza atandı.', 0, '2025-04-03 00:10:19', NULL, 0),
(5, 2, 'Yeni bir sipariş oluşturuldu: SIP-2025-0001', 1, '2025-04-03 00:25:38', 3, 0),
(6, 2, 'Yeni bir sipariş oluşturuldu: SIP-2025-0002', 1, '2025-04-03 00:26:21', 4, 0);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanicilar`
--

CREATE TABLE `kullanicilar` (
  `id` int(11) NOT NULL,
  `kullanici_adi` varchar(50) NOT NULL,
  `sifre` varchar(255) NOT NULL,
  `ad_soyad` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `rol` enum('Admin','Sorumlu','Tedarikci') NOT NULL DEFAULT 'Tedarikci',
  `firma_id` int(11) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `son_giris` datetime DEFAULT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` date NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `kullanicilar`
--

INSERT INTO `kullanicilar` (`id`, `kullanici_adi`, `sifre`, `ad_soyad`, `email`, `telefon`, `rol`, `firma_id`, `aktif`, `son_giris`, `olusturma_tarihi`, `guncelleme_tarihi`) VALUES
(1, 'admin', '$2y$10$R66MmPMc6hX18uOog0I7/.2YXqvNVk10gRzShk27ZYkWuuCGMX8yq', 'Sistem Yöneticisi', 'admin@ornek.com', NULL, 'Admin', NULL, 1, '2025-04-02 23:51:48', '2025-04-02 23:21:07', '2025-04-03'),
(2, 'sorumlu', '$2y$10$R66MmPMc6hX18uOog0I7/.2YXqvNVk10gRzShk27ZYkWuuCGMX8yq', 'Sorumlu Kullanıcı', 'sorumlu@ornek.com', NULL, 'Sorumlu', NULL, 1, '2025-04-03 00:48:32', '2025-04-02 23:21:07', '2025-04-03'),
(3, 'tedarikci', '$2y$10$R66MmPMc6hX18uOog0I7/.2YXqvNVk10gRzShk27ZYkWuuCGMX8yq', 'Tedarikçi Firma', 'tedarikci@ornek.com', '', 'Tedarikci', NULL, 1, '2025-04-02 23:47:17', '2025-04-02 23:21:07', '2025-04-03'),
(4, 'polat', '$2y$10$Q.vHTnxRdlkRmLLuHNoX6.phDP3LJJxS3bvPGJJoO3lcfqt0Xx3bi', 'polat', 'pol.akin@hotmail.com', '05052797954', 'Admin', NULL, 1, NULL, '2025-04-03 00:07:34', '2025-04-03'),
(5, 'sorumlu2', '$2y$10$I3yRo4BnBADlfxJNDKpcx.L4bwAf7nn4eps98pYbcKvvKGeEGtmhy', 'polat sorumlu', 'polatakin1@gmail.com', '05052797954', 'Sorumlu', NULL, 1, '2025-04-03 00:11:33', '2025-04-03 00:08:03', '2025-04-03');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kullanici_tedarikci_iliskileri`
--

CREATE TABLE `kullanici_tedarikci_iliskileri` (
  `id` int(11) NOT NULL,
  `kullanici_id` int(11) NOT NULL,
  `tedarikci_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Tablo döküm verisi `kullanici_tedarikci_iliskileri`
--

INSERT INTO `kullanici_tedarikci_iliskileri` (`id`, `kullanici_id`, `tedarikci_id`) VALUES
(1, 3, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `projeler`
--

CREATE TABLE `projeler` (
  `id` int(11) NOT NULL,
  `proje_kodu` varchar(255) NOT NULL,
  `proje_adi` varchar(255) NOT NULL,
  `proje_aciklama` text DEFAULT NULL,
  `baslangic_tarihi` date DEFAULT NULL,
  `bitis_tarihi` date DEFAULT NULL,
  `durum` enum('Aktif','Tamamlandi','Beklemede','Iptal') NOT NULL DEFAULT 'Aktif',
  `olusturan_id` int(11) NOT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `aktif` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `projeler`
--

INSERT INTO `projeler` (`id`, `proje_kodu`, `proje_adi`, `proje_aciklama`, `baslangic_tarihi`, `bitis_tarihi`, `durum`, `olusturan_id`, `olusturma_tarihi`, `guncelleme_tarihi`, `aktif`) VALUES
(1, '', 'Araç Modernizasyonu', 'Zırhlı araçların modernizasyonu projesi', '2023-01-01', '2023-12-31', 'Aktif', 1, '2025-04-02 23:21:07', NULL, 1),
(2, '', 'İHA Geliştirme', 'İnsansız hava aracı geliştirme projesi', '2023-02-15', '2024-06-30', 'Aktif', 1, '2025-04-02 23:21:07', NULL, 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparisler`
--

CREATE TABLE `siparisler` (
  `id` int(11) NOT NULL,
  `siparis_no` varchar(50) NOT NULL,
  `parca_no` varchar(50) NOT NULL,
  `tanim` varchar(255) DEFAULT NULL,
  `proje_id` int(11) DEFAULT NULL,
  `tedarikci_id` int(11) DEFAULT NULL,
  `sorumlu_id` int(11) DEFAULT NULL,
  `acilis_tarihi` date NOT NULL,
  `parca_adi` varchar(255) NOT NULL,
  `teslim_tarihi` date DEFAULT NULL,
  `miktar` int(11) DEFAULT 0,
  `birim` varchar(20) DEFAULT NULL,
  `kalan_miktar` int(11) DEFAULT 0,
  `durum_id` int(11) NOT NULL,
  `fai` tinyint(1) DEFAULT 0,
  `paketleme` varchar(100) DEFAULT NULL,
  `satinalmaci` varchar(100) DEFAULT NULL,
  `alt_malzeme` text DEFAULT NULL,
  `tedarikci_tarihi` date DEFAULT NULL,
  `tedarikci_notu` text DEFAULT NULL,
  `onaylanan_revizyon` varchar(50) DEFAULT NULL,
  `tedarikci_parca_no` varchar(50) DEFAULT NULL,
  `vehicle_id` varchar(50) DEFAULT NULL,
  `olusturan_id` int(11) NOT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `aciklama` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `siparisler`
--

INSERT INTO `siparisler` (`id`, `siparis_no`, `parca_no`, `tanim`, `proje_id`, `tedarikci_id`, `sorumlu_id`, `acilis_tarihi`, `parca_adi`, `teslim_tarihi`, `miktar`, `birim`, `kalan_miktar`, `durum_id`, `fai`, `paketleme`, `satinalmaci`, `alt_malzeme`, `tedarikci_tarihi`, `tedarikci_notu`, `onaylanan_revizyon`, `tedarikci_parca_no`, `vehicle_id`, `olusturan_id`, `olusturma_tarihi`, `guncelleme_tarihi`, `aciklama`) VALUES
(1, 'S2023-001', 'P1001', 'Zırh Plakası', 1, 1, 2, '2023-03-01', '', '2023-04-15', 100, 'Adet', 100, 1, 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-04-02 23:21:07', NULL, ''),
(2, 'S2023-002', 'E2001', 'Elektronik Kontrol Ünitesi', 2, 2, 2, '2023-03-10', '', '2023-05-01', 50, 'Adet', 50, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-04-02 23:21:07', NULL, ''),
(3, 'SIP-2025-0001', 'asd', NULL, 1, 1, 2, '0000-00-00', 'denemeasd', '2025-04-23', 1000, 'Adet', 0, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-04-03 00:25:38', NULL, 'asdasdasdasd'),
(4, 'SIP-2025-0002', 'asd', NULL, 1, 1, 2, '0000-00-00', 'asdsadasdsad', '2025-04-23', 1000, 'Adet', 0, 1, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2025-04-03 00:26:21', NULL, 'asdasdasdasd');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis_dokumanlari`
--

CREATE TABLE `siparis_dokumanlari` (
  `id` int(11) NOT NULL,
  `siparis_id` int(11) NOT NULL,
  `dokuman_adi` varchar(255) NOT NULL,
  `dosya_yolu` varchar(255) NOT NULL,
  `dosya_turu` varchar(50) DEFAULT NULL,
  `yukleme_tarihi` datetime DEFAULT current_timestamp(),
  `yukleyen_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `siparis_dokumanlari`
--

INSERT INTO `siparis_dokumanlari` (`id`, `siparis_id`, `dokuman_adi`, `dosya_yolu`, `dosya_turu`, `yukleme_tarihi`, `yukleyen_id`) VALUES
(1, 1, 'Teknik Çizim', '/dosyalar/teknik_cizim.pdf', 'PDF', '2025-04-02 23:21:07', 1),
(2, 1, 'Malzeme Listesi', '/dosyalar/malzeme_listesi.xlsx', 'XLSX', '2025-04-02 23:21:07', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis_durumlari`
--

CREATE TABLE `siparis_durumlari` (
  `id` int(11) NOT NULL,
  `durum_adi` varchar(50) NOT NULL,
  `aciklama` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `siparis_durumlari`
--

INSERT INTO `siparis_durumlari` (`id`, `durum_adi`, `aciklama`) VALUES
(1, 'Açık', 'Aktif sipariş'),
(2, 'Kapalı', 'Tamamlanmış sipariş'),
(3, 'Beklemede', 'Bekleyen sipariş'),
(4, 'İptal', 'İptal edilmiş sipariş');

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis_guncellemeleri`
--

CREATE TABLE `siparis_guncellemeleri` (
  `id` int(11) NOT NULL,
  `siparis_id` int(11) NOT NULL,
  `guncelleme_tipi` varchar(100) NOT NULL,
  `guncelleme_detay` text DEFAULT NULL,
  `guncelleme_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleyen_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `siparis_guncellemeleri`
--

INSERT INTO `siparis_guncellemeleri` (`id`, `siparis_id`, `guncelleme_tipi`, `guncelleme_detay`, `guncelleme_tarihi`, `guncelleyen_id`) VALUES
(1, 1, 'Durum Değişikliği', 'Sipariş durumu Açık olarak güncellendi', '2025-04-02 23:21:07', 1),
(2, 2, 'Teslimat Tarihi Değişikliği', 'Teslimat tarihi 2023-05-01 olarak güncellendi', '2025-04-02 23:21:07', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis_kalemleri`
--

CREATE TABLE `siparis_kalemleri` (
  `id` int(11) NOT NULL,
  `siparis_id` int(11) NOT NULL,
  `parca_no` varchar(50) NOT NULL,
  `miktar` int(11) NOT NULL DEFAULT 0,
  `teslim_edilen` int(11) DEFAULT 0,
  `birim_fiyat` decimal(10,2) DEFAULT NULL,
  `para_birimi` varchar(10) DEFAULT NULL,
  `aciklama` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `siparis_kalemleri`
--

INSERT INTO `siparis_kalemleri` (`id`, `siparis_id`, `parca_no`, `miktar`, `teslim_edilen`, `birim_fiyat`, `para_birimi`, `aciklama`) VALUES
(1, 1, 'P1001-A', 50, 0, NULL, NULL, NULL),
(2, 1, 'P1001-B', 50, 0, NULL, NULL, NULL),
(3, 2, 'E2001-X', 50, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis_log`
--

CREATE TABLE `siparis_log` (
  `id` int(11) NOT NULL,
  `siparis_id` int(11) NOT NULL,
  `islem_turu` varchar(255) NOT NULL,
  `islem_yapan_id` int(11) NOT NULL,
  `islem_tarihi` date NOT NULL DEFAULT current_timestamp(),
  `durum_id` int(11) NOT NULL,
  `aciklama` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sorumluluklar`
--

CREATE TABLE `sorumluluklar` (
  `id` int(11) NOT NULL,
  `sorumlu_id` int(11) NOT NULL,
  `tedarikci_id` int(11) NOT NULL,
  `atama_tarihi` datetime DEFAULT current_timestamp(),
  `olusturan_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `sorumluluklar`
--

INSERT INTO `sorumluluklar` (`id`, `sorumlu_id`, `tedarikci_id`, `atama_tarihi`, `olusturan_id`) VALUES
(2, 2, 1, '2025-04-03 00:02:23', 1),
(3, 5, 2, '2025-04-03 00:10:19', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `tedarikciler`
--

CREATE TABLE `tedarikciler` (
  `id` int(11) NOT NULL,
  `firma_adi` varchar(255) NOT NULL,
  `firma_kodu` varchar(50) DEFAULT NULL,
  `adres` text DEFAULT NULL,
  `telefon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `yetkili_kisi` varchar(100) DEFAULT NULL,
  `vergi_dairesi` varchar(255) NOT NULL,
  `vergi_no` varchar(20) DEFAULT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `olusturan_id` int(11) NOT NULL,
  `olusturma_tarihi` datetime DEFAULT current_timestamp(),
  `guncelleme_tarihi` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `kullanici_id` int(11) DEFAULT NULL,
  `aciklama` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

--
-- Tablo döküm verisi `tedarikciler`
--

INSERT INTO `tedarikciler` (`id`, `firma_adi`, `firma_kodu`, `adres`, `telefon`, `email`, `yetkili_kisi`, `vergi_dairesi`, `vergi_no`, `aktif`, `olusturan_id`, `olusturma_tarihi`, `guncelleme_tarihi`, `kullanici_id`, `aciklama`) VALUES
(1, 'ABC Metal A.Ş.', 'ABC001', 'Ankara Organize Sanayi Bölgesi', '0312 555 5555', 'info@abcmetal.com', 'Ahmet Yılmaz', 'Liman', '123213123', 1, 1, '2025-04-02 23:21:07', '2025-04-03 00:34:39', 3, 'açıklamaaaaaa'),
(2, 'XYZ Elektronik Ltd.', 'XYZ002', 'İstanbul Teknopark', '0212 444 4444', 'info@xyzelektronik.com', 'Mehmet Demir', '', NULL, 1, 1, '2025-04-02 23:21:07', NULL, NULL, '');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `bildirimler`
--
ALTER TABLE `bildirimler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kullanici_id` (`kullanici_id`),
  ADD KEY `ilgili_siparis_id` (`ilgili_siparis_id`);

--
-- Tablo için indeksler `kullanicilar`
--
ALTER TABLE `kullanicilar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kullanici_adi` (`kullanici_adi`),
  ADD KEY `firma_id` (`firma_id`);

--
-- Tablo için indeksler `kullanici_tedarikci_iliskileri`
--
ALTER TABLE `kullanici_tedarikci_iliskileri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kullanici_tedarikci` (`kullanici_id`,`tedarikci_id`),
  ADD KEY `kullanici_id` (`kullanici_id`),
  ADD KEY `tedarikci_id` (`tedarikci_id`);

--
-- Tablo için indeksler `projeler`
--
ALTER TABLE `projeler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `olusturan_id` (`olusturan_id`);

--
-- Tablo için indeksler `siparisler`
--
ALTER TABLE `siparisler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `siparis_no` (`siparis_no`),
  ADD KEY `durum_id` (`durum_id`),
  ADD KEY `proje_id` (`proje_id`),
  ADD KEY `tedarikci_id` (`tedarikci_id`),
  ADD KEY `sorumlu_id` (`sorumlu_id`),
  ADD KEY `olusturan_id` (`olusturan_id`);

--
-- Tablo için indeksler `siparis_dokumanlari`
--
ALTER TABLE `siparis_dokumanlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siparis_id` (`siparis_id`),
  ADD KEY `yukleyen_id` (`yukleyen_id`);

--
-- Tablo için indeksler `siparis_durumlari`
--
ALTER TABLE `siparis_durumlari`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `siparis_guncellemeleri`
--
ALTER TABLE `siparis_guncellemeleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siparis_id` (`siparis_id`),
  ADD KEY `guncelleyen_id` (`guncelleyen_id`);

--
-- Tablo için indeksler `siparis_kalemleri`
--
ALTER TABLE `siparis_kalemleri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `siparis_id` (`siparis_id`);

--
-- Tablo için indeksler `siparis_log`
--
ALTER TABLE `siparis_log`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `sorumluluklar`
--
ALTER TABLE `sorumluluklar`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sorumlu_id` (`sorumlu_id`,`tedarikci_id`),
  ADD KEY `tedarikci_id` (`tedarikci_id`),
  ADD KEY `olusturan_id` (`olusturan_id`);

--
-- Tablo için indeksler `tedarikciler`
--
ALTER TABLE `tedarikciler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `olusturan_id` (`olusturan_id`),
  ADD KEY `kullanici_id` (`kullanici_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `bildirimler`
--
ALTER TABLE `bildirimler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Tablo için AUTO_INCREMENT değeri `kullanicilar`
--
ALTER TABLE `kullanicilar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Tablo için AUTO_INCREMENT değeri `kullanici_tedarikci_iliskileri`
--
ALTER TABLE `kullanici_tedarikci_iliskileri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Tablo için AUTO_INCREMENT değeri `projeler`
--
ALTER TABLE `projeler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `siparisler`
--
ALTER TABLE `siparisler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `siparis_dokumanlari`
--
ALTER TABLE `siparis_dokumanlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `siparis_durumlari`
--
ALTER TABLE `siparis_durumlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Tablo için AUTO_INCREMENT değeri `siparis_guncellemeleri`
--
ALTER TABLE `siparis_guncellemeleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Tablo için AUTO_INCREMENT değeri `siparis_kalemleri`
--
ALTER TABLE `siparis_kalemleri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `siparis_log`
--
ALTER TABLE `siparis_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sorumluluklar`
--
ALTER TABLE `sorumluluklar`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Tablo için AUTO_INCREMENT değeri `tedarikciler`
--
ALTER TABLE `tedarikciler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `bildirimler`
--
ALTER TABLE `bildirimler`
  ADD CONSTRAINT `bildirimler_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`),
  ADD CONSTRAINT `bildirimler_ibfk_2` FOREIGN KEY (`ilgili_siparis_id`) REFERENCES `siparisler` (`id`);

--
-- Tablo kısıtlamaları `projeler`
--
ALTER TABLE `projeler`
  ADD CONSTRAINT `projeler_ibfk_1` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`);

--
-- Tablo kısıtlamaları `siparisler`
--
ALTER TABLE `siparisler`
  ADD CONSTRAINT `siparisler_ibfk_1` FOREIGN KEY (`durum_id`) REFERENCES `siparis_durumlari` (`id`),
  ADD CONSTRAINT `siparisler_ibfk_2` FOREIGN KEY (`proje_id`) REFERENCES `projeler` (`id`),
  ADD CONSTRAINT `siparisler_ibfk_3` FOREIGN KEY (`tedarikci_id`) REFERENCES `tedarikciler` (`id`),
  ADD CONSTRAINT `siparisler_ibfk_4` FOREIGN KEY (`sorumlu_id`) REFERENCES `kullanicilar` (`id`),
  ADD CONSTRAINT `siparisler_ibfk_5` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`);

--
-- Tablo kısıtlamaları `siparis_dokumanlari`
--
ALTER TABLE `siparis_dokumanlari`
  ADD CONSTRAINT `siparis_dokumanlari_ibfk_1` FOREIGN KEY (`siparis_id`) REFERENCES `siparisler` (`id`),
  ADD CONSTRAINT `siparis_dokumanlari_ibfk_2` FOREIGN KEY (`yukleyen_id`) REFERENCES `kullanicilar` (`id`);

--
-- Tablo kısıtlamaları `siparis_guncellemeleri`
--
ALTER TABLE `siparis_guncellemeleri`
  ADD CONSTRAINT `siparis_guncellemeleri_ibfk_1` FOREIGN KEY (`siparis_id`) REFERENCES `siparisler` (`id`),
  ADD CONSTRAINT `siparis_guncellemeleri_ibfk_2` FOREIGN KEY (`guncelleyen_id`) REFERENCES `kullanicilar` (`id`);

--
-- Tablo kısıtlamaları `siparis_kalemleri`
--
ALTER TABLE `siparis_kalemleri`
  ADD CONSTRAINT `siparis_kalemleri_ibfk_1` FOREIGN KEY (`siparis_id`) REFERENCES `siparisler` (`id`);

--
-- Tablo kısıtlamaları `sorumluluklar`
--
ALTER TABLE `sorumluluklar`
  ADD CONSTRAINT `sorumluluklar_ibfk_1` FOREIGN KEY (`sorumlu_id`) REFERENCES `kullanicilar` (`id`),
  ADD CONSTRAINT `sorumluluklar_ibfk_2` FOREIGN KEY (`tedarikci_id`) REFERENCES `tedarikciler` (`id`),
  ADD CONSTRAINT `sorumluluklar_ibfk_3` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`);

--
-- Tablo kısıtlamaları `tedarikciler`
--
ALTER TABLE `tedarikciler`
  ADD CONSTRAINT `tedarikciler_ibfk_1` FOREIGN KEY (`olusturan_id`) REFERENCES `kullanicilar` (`id`),
  ADD CONSTRAINT `tedarikciler_ibfk_2` FOREIGN KEY (`kullanici_id`) REFERENCES `kullanicilar` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
