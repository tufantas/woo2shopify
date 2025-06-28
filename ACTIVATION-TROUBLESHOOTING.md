# Woo2Shopify Activation Troubleshooting

Plugin aktivasyonunda sorun yaşıyorsanız bu adımları takip edin.

## 🔍 Hata Tespiti

### 1. Test Dosyasını Çalıştırın
Browser'da şu URL'yi açın:
```
http://yoursite.com/wp-content/plugins/woo2shopify/test-activation.php
```

Bu dosya:
- ✅ Sistem gereksinimlerini kontrol eder
- ✅ Plugin dosyalarının varlığını kontrol eder  
- ✅ Database bağlantısını test eder
- ✅ Manuel aktivasyon testi yapar
- ✅ Error loglarını gösterir

### 2. WordPress Debug Modunu Açın
`wp-config.php` dosyasına ekleyin:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

### 3. Error Loglarını Kontrol Edin
Şu konumlarda error loglarını kontrol edin:
- `/wp-content/debug.log`
- Server error logs (cPanel/hosting panel)
- PHP error logs

## 🛠️ Yaygın Sorunlar ve Çözümleri

### Problem 1: "Commands out of sync" Hatası
Bu hata MySQL bağlantısı ile ilgilidir ve genellikle WooCommerce'nin kendi sistemi ile ilgilidir.

**Çözüm:**
```php
// wp-config.php dosyasına ekleyin
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
```

### Problem 2: Memory Limit Hatası
Plugin aktivasyonu sırasında memory limit aşılıyor.

**Çözüm:**
```php
// wp-config.php dosyasına ekleyin
ini_set('memory_limit', '256M');
```

### Problem 3: Database Table Oluşturma Hatası
Database tabloları oluşturulamıyor.

**Çözüm:**
1. Database kullanıcısının CREATE TABLE yetkisi olduğunu kontrol edin
2. Manuel olarak tabloları oluşturun:

```sql
-- Progress table
CREATE TABLE wp_woo2shopify_progress (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    migration_id varchar(100) NOT NULL,
    total_products int(11) DEFAULT 0,
    processed_products int(11) DEFAULT 0,
    successful_products int(11) DEFAULT 0,
    failed_products int(11) DEFAULT 0,
    skipped_products int(11) DEFAULT 0,
    status varchar(20) DEFAULT 'running',
    batch_size int(11) DEFAULT 5,
    current_batch int(11) DEFAULT 0,
    total_batches int(11) DEFAULT 0,
    percentage decimal(5,2) DEFAULT 0.00,
    status_message text,
    started_at datetime DEFAULT NULL,
    completed_at datetime DEFAULT NULL,
    estimated_completion datetime DEFAULT NULL,
    memory_usage bigint(20) DEFAULT 0,
    peak_memory bigint(20) DEFAULT 0,
    options longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY migration_id (migration_id)
);

-- Logs table
CREATE TABLE wp_woo2shopify_logs (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    migration_id varchar(100) DEFAULT NULL,
    product_id bigint(20) DEFAULT NULL,
    action varchar(50) NOT NULL,
    level varchar(20) DEFAULT 'info',
    message text NOT NULL,
    data longtext,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY migration_id (migration_id)
);

-- Mappings table
CREATE TABLE wp_woo2shopify_mappings (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    wc_id bigint(20) NOT NULL,
    wc_type varchar(50) NOT NULL,
    shopify_id varchar(100) NOT NULL,
    shopify_type varchar(50) NOT NULL,
    migration_id varchar(100) DEFAULT NULL,
    status varchar(20) DEFAULT 'active',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY wc_mapping (wc_id, wc_type)
);
```

### Problem 4: File Permission Hatası
Plugin dosyaları okunamıyor.

**Çözüm:**
```bash
# Plugin klasörü için doğru izinleri ayarlayın
chmod -R 755 /wp-content/plugins/woo2shopify/
chown -R www-data:www-data /wp-content/plugins/woo2shopify/
```

### Problem 5: WooCommerce Bulunamadı
WooCommerce plugin'i aktif değil.

**Çözüm:**
1. WooCommerce'i yükleyin ve aktif edin
2. WooCommerce'in düzgün çalıştığını kontrol edin

## 🔧 Manuel Aktivasyon

Eğer normal aktivasyon çalışmıyorsa, manuel aktivasyon yapabilirsiniz:

### 1. Database Tablolarını Manuel Oluşturun
Yukarıdaki SQL komutlarını çalıştırın.

### 2. Plugin Seçeneklerini Manuel Ayarlayın
```php
// WordPress admin panelinde Tools > Site Health > Info > Database
// Veya phpMyAdmin'de şu komutu çalıştırın:

INSERT INTO wp_options (option_name, option_value) VALUES 
('woo2shopify_settings', 'a:6:{s:10:"batch_size";i:5;s:14:"include_images";b:1;s:14:"include_videos";b:1;s:18:"include_variations";b:1;s:18:"include_categories";b:1;s:20:"include_translations";b:0;}');
```

### 3. Plugin'i Aktif Edin
WordPress admin panelinden normal şekilde aktif edin.

## 📞 Destek

Sorun devam ediyorsa:

1. **Test dosyası sonuçlarını** kaydedin
2. **Error log'larını** toplayın  
3. **Server bilgilerini** (PHP version, MySQL version, etc.) not edin
4. **Hosting sağlayıcınızla** iletişime geçin

### Sistem Gereksinimleri
- ✅ PHP 7.4+
- ✅ WordPress 5.0+
- ✅ WooCommerce 3.0+
- ✅ MySQL 5.6+
- ✅ Memory: 128MB+
- ✅ cURL extension

### İletişim
- Email: tufantas@gmail.com
- Plugin desteği için error log'ları ve test sonuçlarını ekleyin

## 🚀 Başarılı Aktivasyon Sonrası

Plugin başarıyla aktif olduktan sonra:

1. **Admin panelinde** Woo2Shopify menüsünü göreceksiniz
2. **Settings** sekmesinden Shopify API bilgilerini girin
3. **Test Connection** ile bağlantıyı test edin
4. **Selective Migration** ile istediğiniz ürünleri seçin
5. **Migration** işlemini başlatın

Plugin artık hazır! 🎉
