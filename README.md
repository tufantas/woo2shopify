# Woo2Shopify - WooCommerce to Shopify Migration Tool

Kapsamlı bir WordPress eklentisi ile WooCommerce mağazanızı Shopify'a kolayca taşıyın. Ürünler, görseller, kategoriler ve tüm meta veriler dahil olmak üzere eksiksiz veri aktarımı.

## 🚀 Özellikler

### ✅ Tam Ürün Aktarımı
- Ürün başlıkları ve açıklamaları
- Fiyatlar (normal ve indirimli)
- SKU kodları ve stok bilgileri
- Ürün durumları (yayınlanmış/taslak)
- Ağırlık ve boyut bilgileri

### 🖼️ Görsel ve Video Aktarımı
- Ürün ana görselleri
- Galeri görselleri
- **Ürün videoları (YENİ!)**
  - HTML içeriğinden otomatik video tespiti
  - Video dosyaları (MP4, WebM, OGG, MOV, AVI)
  - Duplicate video kontrolü (aynı video tekrar aktarılmaz)
  - HTML5 video player desteği
  - Video cache sistemi
- YouTube/Vimeo embed desteği
- Otomatik görsel optimizasyonu
- Alt text ve başlık korunması
- Çoklu format desteği (JPEG, PNG, GIF, WebP)

### 📂 Kategori ve Etiket Desteği
- WooCommerce kategorileri → Shopify koleksiyonları
- Ürün etiketleri korunması
- Hiyerarşik kategori yapısı

### 🌍 Çoklu Dil ve Para Birimi Desteği (YENİ!)
- **WPML** entegrasyonu
- **Polylang** desteği
- **WooCommerce Multi-Currency** uyumluluğu
- **WPML WooCommerce Multi-Currency** desteği
- Çoklu para birimi fiyatları metafield olarak aktarım
- Dil çevirileri metafield olarak saklama

### 🔄 Varyasyon Desteği
- Değişken ürünler ve varyasyonları
- Varyasyon öznitelikleri
- Varyasyon görselleri
- Stok ve fiyat bilgileri

### ⚡ Gelişmiş İşleme
- Batch (toplu) işleme sistemi
- Gerçek zamanlı ilerleme takibi
- Bellek optimizasyonu
- Hata yönetimi ve yeniden deneme

### 📊 Detaylı Raporlama
- Kapsamlı log sistemi
- Hata raporları
- İstatistikler ve özet bilgiler
- Debug modu

## 📋 Gereksinimler

- WordPress 5.0 veya üzeri
- WooCommerce 5.0 veya üzeri
- PHP 7.4 veya üzeri
- Aktif Shopify mağazası
- Shopify Admin API erişimi
- Yeterli sunucu belleği (toplu işleme için)

## 🛠️ Kurulum

### 1. Eklenti Kurulumu
```bash
# WordPress eklentiler dizinine yükleyin
wp-content/plugins/woo2shopify/
```

### 2. Shopify Ayarları

#### Yöntem 1: Custom App (Önerilen)
1. **Shopify Admin** → Settings → Apps and sales channels
2. **"Develop apps"** → **"Create an app"**
3. Uygulama adını girin: **"WooCommerce Migration"**
4. **"Configure Admin API scopes"** tıklayın
5. Aşağıdaki izinleri aktifleştirin:
   - ✅ **write_products**
   - ✅ **write_product_listings**
   - ✅ **write_inventory**
   - ✅ **write_orders** (opsiyonel)
6. **"Save"** → **"Install app"**
7. **"Admin API access token"** kopyalayın (shpat_ ile başlar)

#### Yöntem 2: Private App (Eski Sistem)
1. **Shopify Admin** → Apps → Manage private apps
2. **"Create private app"** tıklayın
3. Uygulama adını girin ve API izinlerini aktifleştirin
4. **API Key** ve **API Secret** kopyalayın

### 3. WordPress Ayarları
1. WordPress admin panelinde **WooCommerce** > **Woo2Shopify** bölümüne gidin
2. **Settings** sekmesinde:
   - Shopify Store URL'nizi girin (örn: `https://your-store.myshopify.com`)
   - Admin API Access Token'ınızı girin
   - Diğer ayarları ihtiyacınıza göre yapılandırın

### 4. Bağlantı Testi
1. **Dashboard** sekmesinde **Test Shopify Connection** butonuna tıklayın
2. Bağlantının başarılı olduğunu doğrulayın

## 🎯 Kullanım

### Temel Aktarım
1. **Dashboard** sekmesinde aktarım seçeneklerini belirleyin:
   - ✅ Ürün görsellerini dahil et
   - ✅ **Ürün videolarını dahil et (YENİ!)**
   - ✅ Ürün varyasyonlarını dahil et
   - ✅ Kategorileri koleksiyon olarak dahil et

2. **Start Migration** butonuna tıklayın
3. İlerleme çubuğunu takip edin
4. Aktarım tamamlandığında sonuçları inceleyin

### 🎬 Video İşleme Özellikleri

#### Otomatik Video Tespiti
Plugin, ürün açıklamalarındaki video URL'lerini otomatik olarak tespit eder:
- MP4, WebM, OGG, MOV, AVI formatları desteklenir
- HTML içeriğindeki video linklerini bulur
- `<video>` ve `<source>` etiketlerindeki videoları tespit eder

#### Duplicate Video Kontrolü
- Aynı video URL'si birden fazla üründe varsa sadece bir kez işlenir
- Video cache sistemi ile performans optimizasyonu
- MD5 hash ile video benzersizliği kontrolü

#### HTML İçerik Temizleme
- Problematik SVG ikonları kaldırır (büyük emoji görünümünü önler)
- Tekrarlayan içerik bölümlerini temizler
- Boş HTML etiketlerini kaldırır
- Video URL'lerini HTML5 video player'a dönüştürür

#### Shopify Entegrasyonu
- Videolar Shopify metafield olarak saklanır
- Tema dosyalarında kolay erişim için `product.metafields.custom.product_video_url`
- HTML5 video player template'i dahil
- Responsive video desteği

### Gelişmiş Ayarlar
- **Batch Size**: Aynı anda işlenecek ürün sayısı (1-50)
- **Image Quality**: Görsel sıkıştırma kalitesi (10-100%)
- **Include Videos**: Ürün videolarını aktarım dahil et
- **Video as Metafield**: Videoları metafield olarak sakla (önerilen)
- **Debug Mode**: Detaylı log kaydı için aktifleştirin

## 🎥 Video Desteği

### Desteklenen Video Kaynakları
- **Doğrudan Video Dosyaları**: MP4, WebM, OGG, MOV
- **YouTube Videoları**: Ürün açıklamalarından otomatik tespit
- **Vimeo Videoları**: Embed URL'leri otomatik çıkarılır
- **Custom Meta Fields**: `_product_video_url`, `_wc_product_video` vb.

### Video Aktarım Yöntemi
Shopify'ın video upload API'si karmaşık olduğu için, videolar şu şekilde aktarılır:
1. **Metafield olarak saklama** (varsayılan ve önerilen)
2. Video URL'leri `custom.product_video_url` metafield'ında saklanır
3. Shopify temanızda bu metafield'ları kullanarak videoları gösterebilirsiniz

### Video Tema Entegrasyonu
Shopify temanızda videoları göstermek için:

#### Basit Video Gösterimi
```liquid
{% if product.metafields.custom.product_video_url %}
  <div class="product-video">
    <video controls preload="metadata" style="width: 100%; max-width: 600px;">
      <source src="{{ product.metafields.custom.product_video_url }}" type="video/mp4">
      <p>Your browser does not support the video tag.</p>
    </video>
  </div>
{% endif %}
```

#### Gelişmiş Video Player (Önerilen)
```liquid
{% assign video_url = product.metafields.custom.product_video_url %}
{% if video_url != blank %}
  <div class="product-video-section">
    <h3>Product Video</h3>
    <div class="video-container">
      <video
        controls
        preload="metadata"
        poster="{{ product.featured_image | img_url: '600x400' }}"
        style="width: 100%; border-radius: 8px;"
      >
        <source src="{{ video_url }}" type="video/mp4">
        <p>Your browser does not support the video tag.</p>
      </video>
    </div>
  </div>
{% endif %}
```

#### Video Cache Yönetimi
Admin panelinde **Dashboard** sekmesinde:
- Video cache istatistiklerini görüntüleyin
- Toplam video sayısı, aktarılan ve bekleyen videolar
- **Clear Video Cache** butonu ile cache'i temizleyin

### 🎨 Mevcut Tema Entegrasyonu

Eğer temanızda `video.liquid` dosyası varsa (Vela, Dawn, vb.), plugin otomatik olarak tema yapısına uyum sağlar:

#### Otomatik Tema Uyumu
```liquid
{%- comment -%} Plugin otomatik olarak şu yapıyı kullanır {%- endcomment -%}
<div class="vela-section video-section product-video-section">
  <div class="container">
    <div class="video-section__content rounded-3">
      <div class="video-section__media">
        {%- render 'media-video',
            video: product_video_url,
            type: 'external',
            aspect_ratio: 'sixteen-nine'
        -%}
      </div>
    </div>
  </div>
</div>
```

#### Manuel Entegrasyon
Product template'inize ekleyin:
```liquid
{%- render 'product-video-integration' -%}
```

Detaylı entegrasyon kılavuzu için: [THEME-INTEGRATION-GUIDE.md](THEME-INTEGRATION-GUIDE.md)

## � Seçici Aktarım ve Sayfa Aktarımı

### Seçici Ürün Aktarımı
- **Ürün Filtreleme**: Kategori, durum, arama ile ürün filtreleme
- **Toplu Seçim**: Tüm ürünleri seç/seçimi kaldır
- **Tekil Seçim**: İstediğiniz ürünleri tek tek seçin
- **Aktarım Seçenekleri**: Görseller, videolar, varyasyonlar, kategoriler
- **Gerçek Zamanlı İlerleme**: Detaylı ilerleme takibi

### Sayfa Aktarımı
- **WordPress Sayfaları**: Tüm WordPress sayfalarını Shopify'a aktarın
- **İçerik Temizleme**: HTML içerik otomatik temizlenir
- **SEO Korunması**: Meta title ve description korunur
- **Durum Filtreleme**: Yayınlanan, taslak, özel sayfalar

### Gelişmiş İlerleme Takibi
- **Batch İşleme**: Otomatik optimal batch boyutu
- **Bellek Yönetimi**: Gerçek zamanlı bellek kullanımı
- **Tahmini Süre**: Tamamlanma süresi tahmini
- **Detaylı İstatistikler**: Başarılı, başarısız, atlanan ürünler
- **Hata Yönetimi**: Detaylı hata raporlama

## 📊 Kullanım Adımları

### 1. Seçici Aktarım
1. **Selective Migration** sekmesine gidin
2. Filtreleri kullanarak ürünleri bulun
3. Aktarmak istediğiniz ürünleri seçin
4. Aktarım seçeneklerini belirleyin
5. **Migrate Selected** butonuna tıklayın

### 2. Sayfa Aktarımı
1. **Page Migration** sekmesine gidin
2. Aktarmak istediğiniz sayfaları seçin
3. **Migrate Selected** butonuna tıklayın

### 3. Toplu Aktarım (Gelişmiş)
1. **Dashboard** sekmesinde **Start Migration** butonuna tıklayın
2. Gelişmiş ilerleme takibi ile süreci izleyin
3. Batch işleme otomatik olarak optimize edilir

## �🌍 Çoklu Dil ve Para Birimi Rehberi

### Desteklenen Çoklu Dil Eklentileri
- **WPML (WordPress Multilingual Plugin)**
- **Polylang**
- **TranslatePress** (kısmi destek)

### Desteklenen Para Birimi Eklentileri
- **WooCommerce Multi-Currency**
- **WPML WooCommerce Multi-Currency**
- **Currency Switcher for WooCommerce**

### Çoklu Dil Aktarımı

#### WooCommerce'de Tespit Edilen Veriler:
- Ana dil ürün bilgileri
- Çeviri dilleri ve içerikleri
- Dil kodları (tr, en, de, vb.)

#### Shopify'da Saklama:
Çeviriler metafield olarak saklanır:
```
translations.tr_title = "Türkçe Başlık"
translations.tr_description = "Türkçe Açıklama"
translations.en_title = "English Title"
translations.en_description = "English Description"
```

#### Tema Entegrasyonu:
```liquid
{% assign current_lang = request.locale.iso_code %}
{% assign translated_title = product.metafields.translations[current_lang]_title %}

{% if translated_title %}
  <h1>{{ translated_title }}</h1>
{% else %}
  <h1>{{ product.title }}</h1>
{% endif %}
```

### Çoklu Para Birimi Aktarımı

#### WooCommerce'de Tespit Edilen Veriler:
- Ana para birimi fiyatları
- Alternatif para birimi fiyatları
- Döviz kurları
- Para birimi sembolleri

#### Shopify'da Saklama:
Para birimi fiyatları metafield olarak saklanır:
```
currencies.usd_price = "19.99"
currencies.eur_price = "17.50"
currencies.try_price = "599.00"
```

#### Tema Entegrasyonu:
```liquid
{% assign current_currency = cart.currency.iso_code %}
{% assign currency_price = product.metafields.currencies[current_currency]_price %}

{% if currency_price %}
  <span class="price">{{ currency_price | money }}</span>
{% else %}
  <span class="price">{{ product.price | money }}</span>
{% endif %}
```

### Shopify Markets Entegrasyonu

Shopify Markets kullanıyorsanız:

1. **Dil Ayarları**:
   - Shopify Admin > Settings > Markets
   - Her market için dil seçin
   - Metafield'lardan çevirileri kullanın

2. **Para Birimi Ayarları**:
   - Her market için para birimi belirleyin
   - Otomatik döviz kurları veya manuel fiyatlar
   - Metafield'lardan özel fiyatları kullanın

### Önemli Notlar

⚠️ **Shopify Sınırlamaları**:
- Shopify'da doğrudan çoklu dil desteği sınırlıdır
- Metafield yaklaşımı en güvenilir yöntemdir
- Tema özelleştirmesi gerekebilir

✅ **Önerilen Yaklaşım**:
1. Ana dili Shopify'ın varsayılan dili olarak ayarlayın
2. Diğer dilleri metafield olarak saklayın
3. Tema kodunu özelleştirin
4. Shopify Markets'i aktifleştirin

🔧 **Tema Geliştirme**:
- Metafield'ları tema kodunda kullanın
- Dil değiştirici ekleyin
- Para birimi seçici oluşturun
- SEO için hreflang etiketleri ekleyin

## 📈 İzleme ve Raporlama

### Logs Sekmesi
- Tüm aktarım işlemlerinin detaylı kayıtları
- Hata mesajları ve çözüm önerileri
- Filtreleme ve arama özellikleri

### İstatistikler
- Toplam işlenen ürün sayısı
- Başarılı/başarısız aktarım oranları
- Görsel aktarım istatistikleri
- İşlem süreleri

## 🔧 Sorun Giderme

### Yaygın Sorunlar

**Bağlantı Hatası**
```
Çözüm: Store URL ve Access Token'ı kontrol edin
Format: https://your-store.myshopify.com
```

**Bellek Hatası**
```
Çözüm: Batch size'ı azaltın (5-10 arası deneyin)
PHP memory_limit'i artırın
```

**Görsel Yükleme Hatası**
```
Çözüm: Görsel boyutlarını kontrol edin (max 20MB)
Desteklenen formatlar: JPEG, PNG, GIF, WebP
```

### Debug Modu
1. Settings > Debug Mode'u aktifleştirin
2. Logs sekmesinde detaylı bilgileri inceleyin
3. `wp-content/uploads/woo2shopify-debug.log` dosyasını kontrol edin

## 🔒 Güvenlik

- Tüm API çağrıları HTTPS üzerinden yapılır
- Access token'lar güvenli şekilde saklanır
- Sadece okuma yetkisi olan kullanıcılar erişebilir
- WooCommerce verileriniz değiştirilmez (sadece okunur)

## 🤝 Destek

### Dokümantasyon
- Plugin içi yardım sayfası
- Adım adım kurulum rehberi
- SSS bölümü

### Teknik Destek
- **GitHub Issues**: https://github.com/tufantas/woo2shopify/issues
- **E-posta**: tufantas@gmail.com
- **Geliştirici**: Tufan Taş
- WordPress.org destek forumu

## 📝 Changelog

### v1.0.0
- İlk sürüm yayınlandı
- Tam ürün aktarım özelliği
- Görsel aktarım sistemi
- Batch işleme
- İlerleme takibi
- Log sistemi
- Admin arayüzü

## 📄 Lisans

GPL v2 veya üzeri - Detaylar için `LICENSE` dosyasına bakın.

## 🙏 Katkıda Bulunma

1. Projeyi fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing-feature`)
3. Değişikliklerinizi commit edin (`git commit -m 'Add amazing feature'`)
4. Branch'inizi push edin (`git push origin feature/amazing-feature`)
5. Pull Request oluşturun

## ⭐ Teşekkürler

Bu proje WordPress ve WooCommerce topluluğu için geliştirilmiştir. Katkıda bulunan herkese teşekkürler!

---

**Not**: Bu eklenti WooCommerce verilerinizi okur ancak değiştirmez. Yine de aktarım öncesi tam yedek almanızı öneririz.
