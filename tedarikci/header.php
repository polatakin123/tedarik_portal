<?php
// header.php - Tedarikçi paneli için ortak başlık
if (!isset($_SESSION)) {
    session_start();
}

// Giriş kontrolü
if (!isset($_SESSION['giris']) || $_SESSION['giris'] !== true || $_SESSION['rol'] !== 'Tedarikci') {
    header("Location: ../giris.php");
    exit;
}

// Aktif sayfayı tespit et
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Tedarikçi Paneli' ?> - Tedarik Portalı</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/panel.css">
    <?php if (isset($extra_css)): ?>
    <style>
        <?= $extra_css ?>
    </style>
    <?php endif; ?>
</head>
<body>
    <!-- Sidebar Toggle Button (Mobile) -->
    <button type="button" id="sidebarToggle" class="sidebar-toggle d-md-none">
        <i class="bi bi-list"></i>
    </button>

    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-heading">
            <h4>Tedarik Portalı</h4>
            <p>Tedarikçi Paneli</p>
        </div>
        <div class="sidebar-sticky">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="index.php">
                        <i class="bi bi-house-door"></i> Ana Sayfa
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'siparislerim.php' ? 'active' : '' ?>" href="siparislerim.php">
                        <i class="bi bi-list-check"></i> Siparişlerim
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'dokumanlar.php' ? 'active' : '' ?>" href="dokumanlar.php">
                        <i class="bi bi-file-earmark-text"></i> Dokümanlar
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'bildirimler.php' ? 'active' : '' ?>" href="bildirimler.php">
                        <i class="bi bi-bell"></i> Bildirimler
                        <?php 
                        require_once '../config.php';
                        $okunmamis_bildirim_sayisi = okunmamisBildirimSayisi($db, $_SESSION['kullanici_id']);
                        if ($okunmamis_bildirim_sayisi > 0): 
                        ?>
                        <span class="badge rounded-pill bg-danger ms-1"><?= $okunmamis_bildirim_sayisi ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $current_page == 'profil.php' ? 'active' : '' ?>" href="profil.php">
                        <i class="bi bi-person"></i> Profil
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../cikis.php">
                        <i class="bi bi-box-arrow-right"></i> Çıkış Yap
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white" id="navbar">
        <div class="container-fluid">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle position-relative" href="#" id="navbarDropdownNotif" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-bell"></i>
                            <?php 
                            if ($okunmamis_bildirim_sayisi > 0): 
                            ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= $okunmamis_bildirim_sayisi ?>
                                <span class="visually-hidden">Okunmamış bildirimler</span>
                            </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <?php 
                            $bildirimler_sql = "SELECT b.*, s.siparis_no
                                              FROM bildirimler b
                                              LEFT JOIN siparisler s ON b.ilgili_siparis_id = s.id
                                              WHERE b.kullanici_id = ? AND b.okundu = 0
                                              ORDER BY b.bildirim_tarihi DESC
                                              LIMIT 5";
                            $bildirimler_stmt = $db->prepare($bildirimler_sql);
                            $bildirimler_stmt->execute([$_SESSION['kullanici_id']]);
                            $bildirimler = $bildirimler_stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($bildirimler) > 0): 
                                foreach ($bildirimler as $bildirim): 
                            ?>
                            <li>
                                <a class="dropdown-item" href="bildirim_detay.php?id=<?= $bildirim['id'] ?>">
                                    <small class="text-muted"><?= date('d.m.Y H:i', strtotime($bildirim['bildirim_tarihi'])) ?></small><br>
                                    <?= guvenli($bildirim['mesaj']) ?>
                                </a>
                            </li>
                            <?php 
                                endforeach;
                            ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-center" href="bildirimler.php">Tüm Bildirimleri Gör</a></li>
                            <?php else: ?>
                            <li><a class="dropdown-item text-center" href="#">Bildirim Yok</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownUser" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= guvenli($_SESSION['ad_soyad']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profil.php"><i class="bi bi-person me-2"></i> Profil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../cikis.php"><i class="bi bi-box-arrow-right me-2"></i> Çıkış Yap</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Ana içerik -->
    <main id="main">