# Shopify Tema Entegrasyonu Kılavuzu

Bu kılavuz, WooCommerce'den aktarılan ürün videolarını mevcut Shopify temanızda nasıl göstereceğinizi açıklar.

## 🎯 Mevcut Tema Yapınız

Temanızda `video.liquid` dosyası mevcut ve şu yapıyı kullanıyor:
- `vela-section video-section` sınıfları
- `media-video` render snippet'i
- Aspect ratio desteği (`sixteen-nine`, `four-three`, vb.)
- Video kontrolleri (autoplay, controls, loop, muted)

## 📁 Entegrasyon Dosyaları

### 1. Product Video Integration
`snippets/product-video-integration.liquid` dosyasını oluşturun:

```liquid
{%- liquid
  assign product_video_url = product.metafields.custom.product_video_url
  assign product_video_urls = product.metafields.custom.product_video_urls
  assign video_description = product.metafields.custom.product_video_description
-%}

{%- if product_video_url != blank -%}
  <div class="vela-section video-section product-video-section">
    <div class="container">
      <div class="video-section__content rounded-3">
        <div class="video-section__media">
          {%- render 'media-video', 
              video: product_video_url, 
              type: 'external', 
              aspect_ratio: 'sixteen-nine', 
              video_title: product.title, 
              autoplay: false, 
              controls: true, 
              loop: false, 
              muted: false 
          -%}
        </div>
        
        {%- if video_description != blank -%}
          <div class="video-section__caption">
            <div class="video-section__text video-section__element text">
              {{ video_description }}
            </div>
          </div>
        {%- endif -%}
      </div>
    </div>
  </div>
{%- endif -%}
```

### 2. Media Video Snippet (Eğer yoksa)
`snippets/media-video.liquid` dosyasını oluşturun veya güncelleyin:

```liquid
{%- liquid
  assign video_url = video
  assign video_type = type | default: 'external'
  assign aspect_ratio = aspect_ratio | default: 'sixteen-nine'
  assign video_title = video_title | default: 'Product Video'
  assign autoplay = autoplay | default: false
  assign controls = controls | default: true
  assign loop = loop | default: false
  assign muted = muted | default: false
  
  assign aspect_class = 'aspect-ratio-' | append: aspect_ratio
-%}

<div class="media-video {{ aspect_class }}">
  <div class="media-video__wrapper">
    {%- if video_url contains 'youtube.com' or video_url contains 'youtu.be' -%}
      {%- liquid
        assign youtube_id = video_url | split: 'v=' | last | split: '&' | first
        if video_url contains 'youtu.be'
          assign youtube_id = video_url | split: 'youtu.be/' | last | split: '?' | first
        endif
      -%}
      <div class="video-embed youtube-embed">
        <iframe
          src="https://www.youtube.com/embed/{{ youtube_id }}?rel=0&showinfo=0&modestbranding=1{% if autoplay %}&autoplay=1{% endif %}{% if loop %}&loop=1&playlist={{ youtube_id }}{% endif %}{% if muted %}&mute=1{% endif %}"
          frameborder="0"
          allowfullscreen
          loading="lazy"
        ></iframe>
      </div>
    {%- elsif video_url contains 'vimeo.com' -%}
      {%- assign vimeo_id = video_url | split: 'vimeo.com/' | last | split: '?' | first -%}
      <div class="video-embed vimeo-embed">
        <iframe
          src="https://player.vimeo.com/video/{{ vimeo_id }}?title=0&byline=0&portrait=0{% if autoplay %}&autoplay=1{% endif %}{% if loop %}&loop=1{% endif %}{% if muted %}&muted=1{% endif %}"
          frameborder="0"
          allowfullscreen
          loading="lazy"
        ></iframe>
      </div>
    {%- else -%}
      <div class="video-direct">
        <video
          class="video-element"
          {% if controls %}controls{% endif %}
          {% if autoplay %}autoplay{% endif %}
          {% if loop %}loop{% endif %}
          {% if muted %}muted{% endif %}
          preload="metadata"
          poster="{{ product.featured_image | img_url: '800x450' }}"
        >
          <source src="{{ video_url }}" type="video/mp4">
          <p>Your browser does not support the video tag.</p>
        </video>
      </div>
    {%- endif -%}
  </div>
</div>
```

## 🔧 Product Template Entegrasyonu

### Yöntem 1: Snippet Kullanımı (Önerilen)
`templates/product.liquid` dosyanızda uygun yere ekleyin:

```liquid
{%- comment -%} Ürün açıklamasından sonra video bölümü {%- endcomment -%}
<div class="product-description">
  {{ product.description }}
</div>

{%- comment -%} WooCommerce Video Entegrasyonu {%- endcomment -%}
{%- render 'product-video-integration' -%}

{%- comment -%} Diğer ürün bilgileri {%- endcomment -%}
```

### Yöntem 2: Doğrudan Entegrasyon
Video kodunu doğrudan product template'ine ekleyin:

```liquid
{%- assign product_video_url = product.metafields.custom.product_video_url -%}
{%- if product_video_url != blank -%}
  <div class="product-video-section" style="margin: 40px 0;">
    <h3>Product Video</h3>
    {%- render 'media-video', 
        video: product_video_url, 
        type: 'external', 
        aspect_ratio: 'sixteen-nine', 
        controls: true 
    -%}
  </div>
{%- endif -%}
```

## 🎨 CSS Stilleri

Mevcut tema stillerinize ekleyin:

```css
/* Product Video Styles */
.product-video-section {
  margin: 40px 0;
}

.product-video-section h3 {
  margin-bottom: 20px;
  font-size: 1.5rem;
  font-weight: 600;
}

/* Video Container */
.media-video {
  position: relative;
  width: 100%;
  background: #000;
  border-radius: 8px;
  overflow: hidden;
}

.media-video__wrapper {
  position: relative;
  width: 100%;
  height: 0;
}

/* Aspect Ratios */
.aspect-ratio-sixteen-nine .media-video__wrapper {
  padding-bottom: 56.25%; /* 16:9 */
}

.aspect-ratio-four-three .media-video__wrapper {
  padding-bottom: 75%; /* 4:3 */
}

/* Video Elements */
.video-embed,
.video-direct {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.video-embed iframe,
.video-direct video {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .product-video-section {
    margin: 20px 0;
  }
}
```

## 📱 Responsive Tasarım

Mobil cihazlarda video görünümünü optimize edin:

```liquid
{%- comment -%} Mobil için farklı aspect ratio {%- endcomment -%}
{%- assign mobile_aspect = 'four-three' -%}
{%- assign desktop_aspect = 'sixteen-nine' -%}

<div class="media-video d-none d-md-block aspect-ratio-{{ desktop_aspect }}">
  {%- comment -%} Desktop video {%- endcomment -%}
</div>

<div class="media-video d-block d-md-none aspect-ratio-{{ mobile_aspect }}">
  {%- comment -%} Mobile video {%- endcomment -%}
</div>
```

## 🌍 Çoklu Dil Desteği

Tema dil dosyalarınıza ekleyin:

```json
{
  "products": {
    "product": {
      "video_title": "Product Video",
      "videos_title": "Product Videos",
      "video_not_supported": "Your browser does not support the video tag.",
      "download_video": "Download video"
    }
  }
}
```

## 🔍 Test ve Doğrulama

### 1. Video Metafield Kontrolü
Shopify Admin'de ürün sayfasında metafield'ları kontrol edin:
- `custom.product_video_url`
- `custom.product_video_urls`
- `custom.product_video_description`

### 2. Tema Preview
- Ürün sayfasında video görünümünü test edin
- Farklı cihazlarda responsive tasarımı kontrol edin
- Video kontrolleri çalışıyor mu test edin

### 3. Performance
- Video yükleme hızını kontrol edin
- Lazy loading çalışıyor mu test edin
- SEO etkilerini değerlendirin

## 🚀 Gelişmiş Özellikler

### Video Gallery
Birden fazla video için:

```liquid
{%- assign video_urls = product.metafields.custom.product_video_urls | split: ',' -%}
{%- if video_urls.size > 1 -%}
  <div class="product-videos-gallery">
    <h3>Product Videos</h3>
    <div class="videos-grid">
      {%- for video_url in video_urls -%}
        {%- assign trimmed_url = video_url | strip -%}
        {%- if trimmed_url != blank -%}
          <div class="video-item">
            {%- render 'media-video', video: trimmed_url, aspect_ratio: 'sixteen-nine' -%}
          </div>
        {%- endif -%}
      {%- endfor -%}
    </div>
  </div>
{%- endif -%}
```

### Video Modal
Video'yu modal'da açmak için:

```liquid
<button class="video-modal-trigger" data-video="{{ product_video_url }}">
  Play Video
</button>

<div id="video-modal" class="modal">
  <div class="modal-content">
    <span class="close">&times;</span>
    <div id="modal-video-container"></div>
  </div>
</div>
```

## 📞 Destek

Entegrasyon sırasında sorun yaşarsanız:
1. Browser console'da hata mesajlarını kontrol edin
2. Video URL'lerinin doğru olduğunu doğrulayın
3. Metafield'ların doğru namespace'de olduğunu kontrol edin
4. CSS çakışmalarını kontrol edin

**İletişim**: tufantas@gmail.com
