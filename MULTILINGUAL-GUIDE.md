# 🌍 Çoklu Dil ve Para Birimi Rehberi

Bu rehber, WooCommerce'den Shopify'a çoklu dil ve para birimi verilerinin nasıl aktarılacağını ve Shopify'da nasıl kullanılacağını açıklar.

## 📋 İçindekiler

1. [Desteklenen Eklentiler](#desteklenen-eklentiler)
2. [Aktarım Süreci](#aktarım-süreci)
3. [Shopify Kurulumu](#shopify-kurulumu)
4. [Tema Entegrasyonu](#tema-entegrasyonu)
5. [Sorun Giderme](#sorun-giderme)

## 🔌 Desteklenen Eklentiler

### Çoklu Dil Eklentileri

#### ✅ WPML (WordPress Multilingual Plugin)
- **Tam Destek**: Ürün çevirileri otomatik tespit edilir
- **Veri Aktarımı**: Başlık, açıklama, kısa açıklama
- **Dil Kodları**: ISO standart kodlar (tr, en, de, fr, vb.)

#### ✅ Polylang
- **Tam Destek**: Çeviri bağlantıları takip edilir
- **Veri Aktarımı**: Tüm ürün içerikleri
- **Varsayılan Dil**: Otomatik tespit

#### ⚠️ TranslatePress
- **Kısmi Destek**: Manuel işlem gerekebilir
- **Not**: Aynı post ID kullandığı için özel yaklaşım

### Para Birimi Eklentileri

#### ✅ WooCommerce Multi-Currency
- **Tam Destek**: Tüm para birimleri ve fiyatlar
- **Meta Fields**: `_price_USD`, `_regular_price_EUR` vb.

#### ✅ WPML WooCommerce Multi-Currency
- **Tam Destek**: WPML entegrasyonu ile
- **Döviz Kurları**: Otomatik tespit

#### ✅ Currency Switcher for WooCommerce
- **Tam Destek**: Ayarlar ve fiyatlar
- **Özel Fiyatlar**: Ürün bazında fiyatlar

## 🔄 Aktarım Süreci

### 1. Tespit Aşaması

```php
// Dil tespiti
$translations = get_product_translations($product_id);
// Sonuç: ['tr' => [...], 'en' => [...], 'de' => [...]]

// Para birimi tespiti  
$currencies = get_product_currencies($product_id);
// Sonuç: ['USD' => [...], 'EUR' => [...], 'TRY' => [...]]
```

### 2. Dönüştürme Aşaması

**Dil Verileri → Metafields**:
```
translations.tr_title = "Türkçe Başlık"
translations.tr_description = "Türkçe Açıklama"
translations.en_title = "English Title"
translations.en_description = "English Description"
```

**Para Birimi Verileri → Metafields**:
```
currencies.usd_price = "19.99"
currencies.usd_regular_price = "24.99"
currencies.eur_price = "17.50"
currencies.try_price = "599.00"
```

### 3. Shopify'a Aktarım

- Ana dil → Shopify varsayılan alanları
- Diğer diller → Metafields
- Ana para birimi → Shopify fiyat alanları
- Diğer para birimleri → Metafields

## ⚙️ Shopify Kurulumu

### 1. Markets Ayarları

```
Shopify Admin → Settings → Markets
```

1. **Primary Market** (Ana Pazar):
   - Ülke: Ana ülkeniz
   - Dil: Ana diliniz
   - Para Birimi: Ana para biriminiz

2. **International Markets** (Uluslararası Pazarlar):
   - Her hedef ülke için ayrı market
   - Dil ve para birimi ayarları
   - Shipping ve tax ayarları

### 2. Metafield Tanımları

```
Settings → Metafields → Products
```

**Çeviri Metafields**:
```
Namespace: translations
Key: tr_title (Text)
Key: tr_description (Multi-line text)
Key: en_title (Text)
Key: en_description (Multi-line text)
```

**Para Birimi Metafields**:
```
Namespace: currencies  
Key: usd_price (Money)
Key: eur_price (Money)
Key: try_price (Money)
```

## 🎨 Tema Entegrasyonu

### Çoklu Dil Desteği

#### 1. Dil Tespiti
```liquid
{% assign current_lang = request.locale.iso_code %}
{% assign browser_lang = request.headers['Accept-Language'] | split: ',' | first | split: '-' | first %}
```

#### 2. Çeviri Gösterimi
```liquid
<!-- Ürün Başlığı -->
{% assign translated_title = product.metafields.translations[current_lang]_title %}
{% if translated_title and translated_title != blank %}
  <h1>{{ translated_title }}</h1>
{% else %}
  <h1>{{ product.title }}</h1>
{% endif %}

<!-- Ürün Açıklaması -->
{% assign translated_desc = product.metafields.translations[current_lang]_description %}
{% if translated_desc and translated_desc != blank %}
  <div class="product-description">{{ translated_desc }}</div>
{% else %}
  <div class="product-description">{{ product.description }}</div>
{% endif %}
```

#### 3. Dil Değiştirici
```liquid
<div class="language-switcher">
  <select onchange="changeLanguage(this.value)">
    <option value="tr" {% if current_lang == 'tr' %}selected{% endif %}>Türkçe</option>
    <option value="en" {% if current_lang == 'en' %}selected{% endif %}>English</option>
    <option value="de" {% if current_lang == 'de' %}selected{% endif %}>Deutsch</option>
  </select>
</div>

<script>
function changeLanguage(lang) {
  // Shopify Markets URL yapısına göre yönlendirme
  window.location.href = '/' + lang + window.location.pathname;
}
</script>
```

### Çoklu Para Birimi Desteği

#### 1. Para Birimi Tespiti
```liquid
{% assign current_currency = cart.currency.iso_code %}
{% assign shop_currency = shop.currency %}
```

#### 2. Fiyat Gösterimi
```liquid
<!-- Özel Para Birimi Fiyatı -->
{% assign currency_price = product.metafields.currencies[current_currency]_price %}
{% assign currency_compare_price = product.metafields.currencies[current_currency]_regular_price %}

{% if currency_price and currency_price != blank %}
  <div class="price">
    {% if currency_compare_price and currency_compare_price != currency_price %}
      <span class="price-compare">{{ currency_compare_price | money }}</span>
    {% endif %}
    <span class="price-current">{{ currency_price | money }}</span>
  </div>
{% else %}
  <!-- Varsayılan Shopify fiyatı -->
  <div class="price">
    {% if product.compare_at_price > product.price %}
      <span class="price-compare">{{ product.compare_at_price | money }}</span>
    {% endif %}
    <span class="price-current">{{ product.price | money }}</span>
  </div>
{% endif %}
```

#### 3. Para Birimi Değiştirici
```liquid
<div class="currency-switcher">
  <select onchange="changeCurrency(this.value)">
    <option value="USD" {% if current_currency == 'USD' %}selected{% endif %}>$ USD</option>
    <option value="EUR" {% if current_currency == 'EUR' %}selected{% endif %}>€ EUR</option>
    <option value="TRY" {% if current_currency == 'TRY' %}selected{% endif %}>₺ TRY</option>
  </select>
</div>
```

### Kombine Çözüm

```liquid
<!-- Dil ve Para Birimi Kombinasyonu -->
{% assign current_lang = request.locale.iso_code %}
{% assign current_currency = cart.currency.iso_code %}

<!-- Çeviri -->
{% assign title_key = current_lang | append: '_title' %}
{% assign translated_title = product.metafields.translations[title_key] %}

<!-- Fiyat -->
{% assign price_key = current_currency | downcase | append: '_price' %}
{% assign currency_price = product.metafields.currencies[price_key] %}

<div class="product-info" data-lang="{{ current_lang }}" data-currency="{{ current_currency }}">
  <h1>{{ translated_title | default: product.title }}</h1>
  <div class="price">{{ currency_price | default: product.price | money }}</div>
</div>
```

## 🔧 Sorun Giderme

### Yaygın Sorunlar

#### 1. Çeviriler Görünmüyor
```
Çözüm:
- Metafield tanımlarını kontrol edin
- Namespace ve key isimlerini doğrulayın
- Tema kodunda doğru metafield referansları kullanın
```

#### 2. Fiyatlar Yanlış Gösteriliyor
```
Çözüm:
- Para birimi metafield'larını kontrol edin
- Money formatını doğru kullanın
- Shopify Markets ayarlarını gözden geçirin
```

#### 3. Dil Değiştirici Çalışmıyor
```
Çözüm:
- Shopify Markets URL yapısını kontrol edin
- JavaScript kodunu browser console'da test edin
- Locale ayarlarını doğrulayın
```

### Debug İpuçları

#### Metafield Kontrolü
```liquid
<!-- Debug: Tüm metafield'ları listele -->
{% for metafield in product.metafields.translations %}
  <p>{{ metafield.first }}: {{ metafield.last }}</p>
{% endfor %}
```

#### Dil ve Para Birimi Bilgileri
```liquid
<!-- Debug: Mevcut ayarlar -->
<div style="display:none;">
  Current Language: {{ request.locale.iso_code }}
  Current Currency: {{ cart.currency.iso_code }}
  Shop Currency: {{ shop.currency }}
  Available Locales: {{ shop.published_locales | map: 'iso_code' | join: ', ' }}
</div>
```

## 📞 Destek

Çoklu dil ve para birimi konularında yardıma ihtiyacınız varsa:

- **E-posta**: tufantas@gmail.com
- **GitHub Issues**: Detaylı hata raporları için
- **Tema Özelleştirme**: Shopify Expert desteği önerilir

---

**Not**: Bu rehber sürekli güncellenmektedir. Yeni eklenti desteği ve özellikler eklendiğinde dokümantasyon güncellenecektir.
