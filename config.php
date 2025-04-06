<?php
// config.php - Veritabanı bağlantı ayarları
$db_host = 'localhost';
$db_name = 'tedarik_portal';
$db_user = 'root';
$db_pass = '';

try {
    $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
}

// Oturum başlat
session_start();

// Kullanıcı giriş kontrolü
function girisKontrol() {
    if (!isset($_SESSION['giris']) || $_SESSION['giris'] !== true) {
        header("Location: ./savunma/giris.php");
        exit;
    }
}

// Admin yetkisi kontrolü
function adminYetkisiKontrol() {
    girisKontrol();
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Admin') {
        header("Location: ./savunma/yetki_yok.php");
        exit;
    }
}

// Sorumlu yetkisi kontrolü
function sorumluYetkisiKontrol() {
    girisKontrol();
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Sorumlu') {
        header("Location: ./savunma/yetki_yok.php");
        exit;
    }
}

// Tedarikçi yetkisi kontrolü
function tedarikciYetkisiKontrol() {
    girisKontrol();
    if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Tedarikci') {
        header("Location: ./savunma/yetki_yok.php");
        exit;
    }
}

// XSS koruması için
function guvenli($data) {
    if (is_null($data)) {
        return '';
    }
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Tedarikçinin sorumlusunu kontrol etme
function tedarikcininSorumlusuMu($db, $tedarikci_id, $sorumlu_id) {
    $sql = "SELECT COUNT(*) FROM sorumluluklar WHERE tedarikci_id = ? AND sorumlu_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$tedarikci_id, $sorumlu_id]);
    return $stmt->fetchColumn() > 0;
}

// Tarih formatını düzenleme
function tarihFormatla($tarih) {
    if (empty($tarih)) return '-';
    $tarihObj = new DateTime($tarih);
    return $tarihObj->format('d.m.Y');
}

// Bildirim oluşturma fonksiyonu
function bildirimOlustur($db, $kullanici_id, $mesaj, $siparis_id = null) {
    $sql = "INSERT INTO bildirimler (kullanici_id, mesaj, ilgili_siparis_id, bildirim_tarihi) 
            VALUES (?, ?, ?, NOW())";
    $stmt = $db->prepare($sql);
    return $stmt->execute([$kullanici_id, $mesaj, $siparis_id]);
}

// Sipariş geçmişine kayıt ekleme
function siparisGecmisiEkle($db, $siparis_id, $guncelleme_tipi, $guncelleme_detay, $kullanici_id) {
    $sql = "INSERT INTO siparis_guncellemeleri (siparis_id, guncelleme_tipi, guncelleme_detay, guncelleyen_id) 
            VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    return $stmt->execute([$siparis_id, $guncelleme_tipi, $guncelleme_detay, $kullanici_id]);
}

// Okunmamış bildirim sayısını alma
function okunmamisBildirimSayisi($db, $kullanici_id) {
    $sql = "SELECT COUNT(*) FROM bildirimler WHERE kullanici_id = ? AND okundu = 0";
    $stmt = $db->prepare($sql);
    $stmt->execute([$kullanici_id]);
    return $stmt->fetchColumn();
}
?> 