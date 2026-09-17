# Clean URL Implementation Guide

## Overview
CampMart now supports SEO-friendly clean URLs for better user experience and search engine optimization.

## Clean URL Structure

### Before (Old URLs):
- Products: `http://localhost/campmartv2/product.php?slug=iphone-13-pro-max`
- Services: `http://localhost/campmartv2/service.php?slug=maths-physics-tutoring`
- Categories: `http://localhost/campmartv2/category.php?slug=electronics`
- Sellers: `http://localhost/campmartv2/seller-profile.php?username=johndoe`

### After (Clean URLs):
- Products: `http://localhost/campmartv2/product/iphone-13-pro-max`
- Services: `http://localhost/campmartv2/service/maths-physics-tutoring`
- Categories: `http://localhost/campmartv2/category/electronics`
- Sellers: `http://localhost/campmartv2/seller/johndoe`

## Implementation Details

### 1. .htaccess Configuration
**File:** `.htaccess`

Added rewrite rules for clean URLs:
```apache
# Service page with clean URLs
RewriteRule ^service/([a-zA-Z0-9\-]+)$ service.php?slug=$1 [L,QSA]

# Product page with clean URLs
RewriteRule ^product/([a-zA-Z0-9\-]+)$ product.php?slug=$1 [L,QSA]

# Category page with clean URLs
RewriteRule ^category/([a-zA-Z0-9\-]+)$ category.php?slug=$1 [L,QSA]

# Seller profile with clean URLs
RewriteRule ^seller/([a-zA-Z0-9\-_]+)$ seller-profile.php?username=$1 [L,QSA]
```

**Note:** Make sure `mod_rewrite` is enabled in Apache:
```bash
# On Linux/Mac
sudo a2enmod rewrite
sudo service apache2 restart

# On XAMPP Windows
# mod_rewrite is usually enabled by default
```

### 2. Dynamic Base URL Detection
**File:** `includes/constant.php`

The base URL is now automatically detected instead of hardcoded:

```php
// Auto-detect base URL
if (!defined('SITE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'];
    $path = str_replace(basename($script), '', $script);
    define("SITE_URL", $protocol . $host . $path);
}
```

**Benefits:**
- Works on any environment (localhost, staging, production)
- Automatically uses HTTPS when available
- No manual configuration needed when deploying

### 3. URL Helper Functions
**File:** `includes/function.php`

New helper functions for generating clean URLs:

```php
// Generate product URL
productUrl($slug);          // Returns: http://localhost/campmartv2/product/iphone-13

// Generate service URL
serviceUrl($slug);          // Returns: http://localhost/campmartv2/service/tutoring

// Generate category URL
categoryUrl($slug);         // Returns: http://localhost/campmartv2/category/electronics

// Generate seller URL
sellerUrl($username);       // Returns: http://localhost/campmartv2/seller/johndoe
```

### 4. Updated Files

All links have been updated to use clean URLs:

**Updated Files:**
- ✅ `service.php` - Related services links
- ✅ `my-services.php` - JavaScript view function
- ✅ `index.php` - Service cards
- ✅ `services.php` - Service listings
- ✅ `seller-profile.php` - Product and service links
- ✅ `manage-products.php` - Admin product links
- ✅ `admin-dashboard.php` - Dashboard product links

## Usage Examples

### In PHP Files:
```php
// Link to a product
<a href="<?php echo productUrl($product['slug']); ?>">View Product</a>

// Link to a service
<a href="<?php echo serviceUrl($service['slug']); ?>">View Service</a>

// Link to a category
<a href="<?php echo categoryUrl($category['slug']); ?>">Browse Category</a>

// Link to a seller
<a href="<?php echo sellerUrl($user['username']); ?>">View Profile</a>
```

### In JavaScript:
```javascript
// Redirect to a service
window.location.href = '<?php echo SITE_URL; ?>service/' + slug;

// Redirect to a product
window.location.href = '<?php echo SITE_URL; ?>product/' + slug;
```

## Testing Clean URLs

### 1. Test Service URLs:
```
Old: http://localhost/campmartv2/service.php?slug=maths-tutoring
New: http://localhost/campmartv2/service/maths-tutoring
```

### 2. Test Product URLs:
```
Old: http://localhost/campmartv2/product.php?slug=textbook-biology
New: http://localhost/campmartv2/product/textbook-biology
```

### 3. Verify Both Work:
Both the old and new URLs should work:
- Clean URL: User-friendly, SEO-optimized
- Old URL: Still functional for backward compatibility

## .htaccess Features

### Security Headers:
- ✅ X-Frame-Options (Clickjacking protection)
- ✅ X-Content-Type-Options (MIME sniffing protection)
- ✅ X-XSS-Protection (XSS protection)
- ✅ Referrer-Policy (Privacy protection)

### Performance Optimization:
- ✅ GZIP compression for text files
- ✅ Browser caching for images and assets
- ✅ Removed trailing slashes

### Directory Protection:
- ✅ Blocks direct access to `includes/` directory
- ✅ Blocks direct access to `database/` directory
- ✅ Blocks direct access to `api/admin/` directory
- ✅ Prevents access to sensitive files (.env, .log, .sql, .bak)

### Custom Error Pages:
- 404 Error → `/campmartv2/404.php`
- 403 Error → `/campmartv2/403.php`
- 500 Error → `/campmartv2/500.php`

## SEO Benefits

1. **Clean URLs are more readable:**
   - ❌ Bad: `service.php?slug=maths-tutoring`
   - ✅ Good: `service/maths-tutoring`

2. **Better for sharing:**
   - Users can easily remember and share links
   - Social media displays cleaner URLs

3. **Search Engine Friendly:**
   - Keywords in URL path
   - No query parameters
   - Better crawlability

4. **Professional appearance:**
   - Looks more trustworthy
   - Modern web standard

## Deployment Notes

### Local Development (XAMPP):
- ✅ Works out of the box
- RewriteBase is set to `/campmartv2/`

### Production Deployment:
1. Update `.htaccess` RewriteBase:
   ```apache
   RewriteBase /
   ```

2. Enable HTTPS redirect (uncomment in .htaccess):
   ```apache
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

3. Update error document paths if needed

### Subdomain Deployment:
If deploying to subdomain (e.g., `marketplace.university.com`):
1. Change RewriteBase to `/`:
   ```apache
   RewriteBase /
   ```

2. SITE_URL will auto-detect correctly

## Troubleshooting

### Clean URLs Not Working?

1. **Check if mod_rewrite is enabled:**
   ```bash
   # Test in PHP
   <?php phpinfo(); ?>
   # Look for "mod_rewrite" in loaded modules
   ```

2. **Verify .htaccess is being read:**
   - Add a syntax error to .htaccess
   - If you get a 500 error, it's working
   - Remove the error after testing

3. **Check AllowOverride setting:**
   In Apache config (httpd.conf or apache2.conf):
   ```apache
   <Directory "/xampp/htdocs">
       AllowOverride All
   </Directory>
   ```

4. **Check RewriteBase path:**
   - Must match your installation folder
   - For root: `RewriteBase /`
   - For subfolder: `RewriteBase /campmartv2/`

### 404 Errors on Clean URLs?

1. Verify the slug exists in database
2. Check file permissions on .htaccess (644)
3. Clear browser cache
4. Test with old URL format first

## Additional Features

### Phone Number Formatting
Also implemented Nigeria phone number formatting:

```php
formatNigeriaPhone('08012345678');   // Returns: 2348012345678
formatNigeriaPhone('+2347012345678'); // Returns: 2347012345678
formatNigeriaPhone('2349012345678');  // Returns: 2349012345678
```

Used for:
- Call buttons: `tel:+2348012345678`
- WhatsApp links: `https://wa.me/2348012345678`

## Version History

- **v1.0** (2026-02-18): Initial clean URL implementation
  - Service URLs
  - Product URLs (already existed, enhanced)
  - Dynamic base URL detection
  - Helper functions
  - Updated all links across the platform

---

**Last Updated:** February 18, 2026
**Author:** CampMart Development Team
