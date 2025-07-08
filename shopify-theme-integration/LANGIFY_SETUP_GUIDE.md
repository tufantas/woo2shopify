# Langify Theme Integration Guide

## Problem: "Translation missing: tr.products.general.include_taxes"

This error occurs because Langify needs proper theme integration to display translated content.

## Solution Steps:

### 1. Install Langify App
- Go to Shopify App Store
- Install "Langify" app
- Configure languages: English (default), Turkish, German

### 2. Theme Integration

#### Option A: Use Our Custom Snippet
1. Go to **Online Store > Themes > Actions > Edit Code**
2. In **Snippets** folder, create new file: `langify-product.liquid`
3. Copy content from `langify-product-integration.liquid` file
4. In your product template, replace:
   ```liquid
   {{ product.title }}
   {{ product.description }}
   ```
   With:
   ```liquid
   {% render 'langify-product', product: product %}
   ```

#### Option B: Direct Metafield Access
Replace theme elements with:
```liquid
<!-- Product Title -->
{{ product.metafields.langify['title_' | append: request.locale.iso_code] | default: product.title }}

<!-- Product Description -->
{{ product.metafields.langify['body_html_' | append: request.locale.iso_code] | default: product.description }}

<!-- Product Excerpt -->
{{ product.metafields.langify['excerpt_' | append: request.locale.iso_code] }}
```

### 3. Verify Metafields
After migration, check in Shopify Admin:
1. Go to **Products > [Any Product] > Metafields**
2. Look for **langify** namespace with keys like:
   - `title_tr`
   - `body_html_tr`
   - `excerpt_tr`
   - `title_de`
   - `body_html_de`
   - `excerpt_de`

### 4. Language Switcher
Langify should automatically detect metafields and switch content.

If not working:
1. Check Langify app settings
2. Ensure language codes match (tr, de, en)
3. Verify theme compatibility

### 5. Debug Mode
Add to theme settings:
```json
{
  "name": "Langify Debug",
  "id": "langify_debug",
  "type": "checkbox",
  "default": false,
  "info": "Show debug information for Langify translations"
}
```

## Expected Result:
- Turkish: Product titles and descriptions in Turkish
- German: Product titles and descriptions in German  
- English: Original content (default)
- No more "Translation missing" errors

## Troubleshooting:
1. **Still seeing English**: Check language switcher, verify metafields exist
2. **Missing translations**: Re-run migration, check WooCommerce WPML data
3. **Broken formatting**: Check HTML preservation in metafields
4. **Theme conflicts**: Use Option B (direct metafield access)
