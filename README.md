# Tedarik Portalı Projesi

Bu proje, savunma sanayii için geliştirilmiş bir tedarikçi ve sipariş yönetim sistemidir. Tedarikçiler, sorumlular ve yöneticiler arasında koordinasyonu sağlayan web tabanlı bir portaldır.

## Özellikler

- **Çoklu Kullanıcı Rolleri**: Admin, Sorumlu ve Tedarikçi rolleri
- **Sipariş Yönetimi**: Siparişlerin açılması, takibi ve kapatılması
- **Tedarikçi Yönetimi**: Tedarikçilerin sisteme dahil edilmesi ve izlenmesi
- **Proje Takibi**: Projeler bazında sipariş durumlarının izlenmesi
- **Bildirim Sistemi**: Önemli durum değişikliklerinde otomatik bildirimler
- **Raporlama**: Çeşitli kriterlere göre rapor oluşturma

## Kurulum

1. Projeyi bilgisayarınıza klonlayın:
```bash
git clone https://github.com/kullaniciadi/tedarik-portal.git
```

2. `config.php.example` dosyasını `config.php` olarak kopyalayın ve düzenleyin:
```bash
cp config.php.example config.php
```

3. Veritabanını oluşturun:
```bash
mysql -u username -p database_name < tedarik_portal.sql
```

4. Web sunucusu yapılandırması:
   - Web kök dizini olarak projenin kök dizinini ayarlayın
   - PHP 7.4 veya üstü gereklidir
   - Gerekli PHP eklentileri: PDO, MySQL, mbstring, session

## Kullanım

1. `/giris.php` sayfasından sisteme giriş yapın
2. Rol bazlı paneller:
   - Admin: `/admin/index.php`
   - Sorumlu: `/sorumlu/index.php`
   - Tedarikçi: `/tedarikci/index.php`

## Geliştirme

Projeyi geliştirmek için:

1. Bir fork oluşturun
2. Feature branch'i oluşturun (`git checkout -b yeni-ozellik`)
3. Değişikliklerinizi commit edin (`git commit -am 'Yeni özellik: Açıklama'`)
4. Branch'inizi push edin (`git push origin yeni-ozellik`)
5. Bir Pull Request oluşturun

## Lisans

Bu proje [MIT Lisansı](LICENSE) altında lisanslanmıştır. 