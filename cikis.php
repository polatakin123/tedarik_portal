<?php
// cikis.php - Oturum sonlandırma sayfası
session_start();

// Oturum bilgilerini kaydet (çıkış zamanı vb.)
if (isset($_SESSION['kullanici_id'])) {
    // Veritabanı bağlantısı
    $db_host = 'localhost';
    $db_name = 'tedarik_portal';
    $db_user = 'root';
    $db_pass = '';

    try {
        $db = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Son çıkış tarihini güncelle (isteğe bağlı)
        // $stmt = $db->prepare("UPDATE kullanicilar SET son_cikis = NOW() WHERE id = ?");
        // $stmt->execute([$_SESSION['kullanici_id']]);
    } catch(PDOException $e) {
        // Hata durumunda sesiz kal, oturumu sonlandırmaya devam et
    }
}

// Tüm oturum değişkenlerini temizle
$_SESSION = array();

// Oturum çerezini sil
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Oturumu sonlandır
session_destroy();

// Giriş sayfasına yönlendir
header("Location: giris.php");
exit;
?> 