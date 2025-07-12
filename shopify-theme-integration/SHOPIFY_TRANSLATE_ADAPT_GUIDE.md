# Shopify Translate & Adapt Integration Guide

## Problems Solved:
1. **"Translation missing"** errors eliminated
2. **Text layout preserved** - Proper paragraph structure maintained
3. **Complete translation** - Product titles, descriptions, and all content
4. **Free solution** - No subscription fees

## Why Shopify Translate & Adapt:
- **Official Shopify app** - Most reliable and stable
- **Completely free** - No usage limits or fees
- **Auto-detection** - Automatically finds and uses translation metafields
- **SEO optimized** - Proper URL structure for each language

## Setup Steps:

### 1. Install Shopify Translate & Adapt
- Go to **Shopify Admin > Apps > Shopify App Store**
- Search for **"Translate & Adapt"**
- Click **Install** (it's free and official)
- Configure languages: **English (default), Turkish, German**

### 2. Configure Languages
1. Open **Translate & Adapt** app
2. Go to **Settings > Languages**
3. Add languages:
   - **English** (default) ✅
   - **Turkish (tr)** ➕
   - **German (de)** ➕
4. Enable **Auto-translate** for new content
5. Enable **Import from metafields**

### 3. Run Migration
- Migration will create metafields in `translations` namespace
- Shopify Translate & Adapt will automatically detect them
- No manual theme editing required!

### 4. Verify Translation Metafields
After migration, check in Shopify Admin:
1. Go to **Products > [Any Product] > Metafields**
2. Look for **translations** namespace with keys like:
   - `title_tr`: Turkish product title
   - `body_html_tr`: Turkish product description  
   - `title_de`: German product title
   - `body_html_de`: German product description
3. Also check **shopify_translate** namespace:
   - `translations`: Complete JSON translation data
   - `languages`: Available language codes

### 5. Theme Integration (CRITICAL STEP)
**Important:** Metafields alone don't automatically display translations. You need to integrate them into your theme.

#### Option A: Use Our Ready-Made Snippet
1. **Copy the snippet file:**
   - Copy `multilingual-product-snippet.liquid` to your theme's `snippets` folder

2. **Edit your product template:**
   - Go to **Online Store > Themes > Actions > Edit Code**
   - Find your product template (usually `sections/product-form.liquid` or `templates/product.liquid`)
   - Replace the existing product title and description with:
   ```liquid
   {% render 'multilingual-product-snippet', product: product %}
   ```

#### Option B: Manual Integration
Add this code to your product template:
```liquid
{% assign current_locale = request.locale.iso_code %}

<!-- Translated Title -->
{% case current_locale %}
  {% when 'tr' %}
    {% assign translated_title = product.metafields.custom.title_tr %}
  {% when 'de' %}
    {% assign translated_title = product.metafields.custom.title_de %}
  {% else %}
    {% assign translated_title = product.title %}
{% endcase %}

{% if translated_title != blank %}
  <h1>{{ translated_title }}</h1>
{% else %}
  <h1>{{ product.title }}</h1>
{% endif %}

<!-- Translated Description -->
{% case current_locale %}
  {% when 'tr' %}
    {% assign translated_description = product.metafields.custom.description_tr %}
  {% when 'de' %}
    {% assign translated_description = product.metafields.custom.description_de %}
  {% else %}
    {% assign translated_description = product.description %}
{% endcase %}

{% if translated_description != blank %}
  <div>{{ translated_description }}</div>
{% else %}
  <div>{{ product.description }}</div>
{% endif %}
```

### 6. Enable Language Switching URLs
1. **Shopify Admin > Online Store > Navigation**
2. **Create language switcher menu:**
   - English: `/en/products/product-handle`
   - Turkish: `/tr/products/product-handle`
   - German: `/de/products/product-handle`

### 7. Test Language Switching
1. Go to your store frontend
2. Test URLs with language codes:
   - `yourstore.com/tr/products/product-name` (Turkish)
   - `yourstore.com/de/products/product-name` (German)
   - `yourstore.com/en/products/product-name` (English)
3. Verify content changes:
   - **Turkish**: Turkish titles and descriptions from metafields
   - **German**: German titles and descriptions from metafields
   - **English**: Original content

## Multi-Currency Setup:

### Automatic Currency Conversion:
Migration creates currency-specific metafields:
- **Turkish (tr)**: TRY (₺) - Base currency
- **German (de)**: EUR (€) - Auto-converted
- **English (en)**: USD ($) - Auto-converted

### Theme Integration for Prices:
```liquid
<!-- Show localized price -->
{{ product.metafields.pricing['formatted_price_' | append: request.locale.iso_code] }}

<!-- Show currency code -->
{{ product.metafields.pricing['currency_code_' | append: request.locale.iso_code] }}
```

### EASIER SOLUTION: Use Shopify Markets (Recommended)
Instead of manual theme editing, use Shopify's built-in solution:

1. **Shopify Admin > Settings > Markets**
2. **Create markets for each country:**
   - **Turkey Market**: Turkish language + TRY currency
   - **Germany Market**: German language + EUR currency
   - **International Market**: English language + USD currency
3. **Enable auto-language detection**
4. **Shopify will automatically:**
   - Create language switcher
   - Handle URL routing (/tr/, /de/, /en/)
   - Use metafields for translations
   - Manage currency conversion

## Expected Result:
- **Turkish**: Product titles/descriptions in Turkish + TRY prices
- **German**: Product titles/descriptions in German + EUR prices  
- **English**: Original content + USD prices
- No more "Translation missing" errors
- Proper currency display per language

## Troubleshooting:
1. **Still seeing English**: Check language switcher, verify metafields exist
2. **Missing translations**: Re-run migration, check WooCommerce WPML data
3. **Broken formatting**: Check HTML preservation in metafields
4. **App conflicts**: Disable other translation apps

## Advantages over Langify:
- ✅ **Free forever** (vs Langify subscription)
- ✅ **Official support** (vs third-party app)
- ✅ **Auto-detection** (vs manual theme editing)
- ✅ **SEO optimized** (vs basic translation)
- ✅ **No limits** (vs usage restrictions)
