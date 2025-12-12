# UKM Catalog - E-Katalog & Pemesanan Sederhana

Sistem E-Katalog dan Pemesanan Sederhana untuk UKM/UMKM dengan fitur lengkap dan keamanan terjamin.

## Fitur Utama

### 👥 Manajemen Pengguna
- ✅ Registrasi dan Login dengan validasi
- ✅ Role-based Access Control (Admin & User)
- ✅ Session Management dengan regenerasi ID
- ✅ Remember Me dengan cookie aman
- ✅ Password hashing dan verifikasi

### 🛍️ Manajemen Produk
- ✅ CRUD lengkap untuk produk dan kategori
- ✅ Upload gambar produk dengan validasi
- ✅ Stok management dan low stock alert
- ✅ Pagination pada daftar produk
- ✅ Pencarian dan filtering produk

### 🛒 Keranjang Belanja
- ✅ Session-based cart management
- ✅ Update quantity dan remove items
- ✅ Validasi stok sebelum checkout
- ✅ Hitung otomatis total dan subtotal

### 📦 Pemesanan
- ✅ Checkout dengan validasi lengkap
- ✅ Order tracking dan status management
- ✅ Email notification (placeholder)
- ✅ Invoice dan order history
- ✅ Multiple payment methods

### 🛡️ Keamanan
- ✅ CSRF Protection pada semua form
- ✅ Prepared Statements (PDO) untuk SQL Injection
- ✅ XSS Protection dengan output escaping
- ✅ Input validation client & server side
- ✅ File upload validation (MIME, size)
- ✅ Session hijacking protection
- ✅ HTTPS-ready dengan secure cookie attributes

### 📊 Admin Dashboard
- ✅ Statistik penjualan dan inventory
- ✅ Manajemen user dan roles
- ✅ Order management
- ✅ Product dan category management
- ✅ Reports dan export data

### 🎨 UI/UX
- ✅ Responsive Design (Mobile, Tablet, Desktop)
- ✅ Loading animations dan progress indicators
- ✅ Flash messages dan notifications
- ✅ Search dengan real-time filtering
- ✅ Smooth transitions dan hover effects

## Teknologi yang Digunakan

### Backend
- **PHP 7.4+** - Bahasa pemrograman utama
- **MySQL/MariaDB** - Database
- **PDO** - Database abstraction layer
- **Session Management** - Native PHP sessions

### Frontend
- **Vanilla CSS** - Tanpa framework CSS
- **Vanilla JavaScript** - Tanpa library JS
- **Responsive Design** - Mobile-first approach
- **Font Awesome** - Icons (placeholder)

### Security
- **Password Hashing** - bcrypt algorithm
- **CSRF Tokens** - Form protection
- **Input Validation** - Client & server side
- **Output Escaping** - XSS prevention
- **File Validation** - Upload security

## Instalasi

### Persyaratan Sistem
- PHP 7.4 atau lebih tinggi
- MySQL 5.7 atau lebih tinggi
- Web Server (Apache/Nginx)
- GD Extension untuk image processing

### Langkah Instalasi

1. **Clone atau download project**
```bash
git clone [repository-url]
```

2. **Setup Database**
```sql
-- Buat database
CREATE DATABASE ukm_catalog;

-- Import schema
mysql -u root -p ukm_catalog < database/schema.sql
```

3. **Konfigurasi Database**
Edit file `config/database.php`:
```php
private $host = "localhost";
private $db_name = "ukm_catalog";
private $username = "root";
private $password = ""; // Sesuaikan dengan password MySQL Anda
```

4. **Setup Web Server**
- Document root harus mengarah ke folder project
- Pastikan mod_rewrite enabled untuk Apache
- Atur permissions untuk folder uploads (777)

5. **Testing**
- Buka browser dan akses `http://localhost/ukm-catalog/`
- Login dengan akun admin default:
  - Email: `admin@ukm.com`
  - Password: `password`

## Struktur Folder

```
ukm-catalog/
├── admin/              # Halaman admin
├── api/                # API endpoints
├── assets/             # Static assets
│   ├── css/           # Stylesheets
│   ├── js/            # JavaScript files
│   └── images/        # Images
├── config/            # Configuration files
├── database/          # Database schema
├── includes/          # Shared components
├── uploads/           # Uploaded files
├── index.php          # Homepage
├── products.php       # Product listing
├── product-detail.php # Product detail
├── cart.php          # Shopping cart
├── checkout.php      # Checkout page
├── login.php         # Login page
├── register.php      # Registration page
├── profile.php       # User profile
└── logout.php        # Logout
```

## Konfigurasi

### Environment Variables
Untuk production, sesuaikan konfigurasi di `config/config.php`:

```php
// Base URL
define('BASE_URL', 'https://yourdomain.com/');

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour

// Upload settings
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', BASE_URL . 'uploads/');
```

### Security Configuration
```php
// CSRF Token Name
define('CSRF_TOKEN_NAME', 'csrf_token');

// Cookie settings for production
define('COOKIE_SECURE', true);
define('COOKIE_HTTPONLY', true);
define('COOKIE_SAMESITE', 'Strict');
```

## API Endpoints

### Cart API
- `GET /api/cart.php?action=count` - Get cart item count
- `POST /api/cart.php` - Manage cart items
  - Action: `add`, `update`, `remove`, `clear`

## Maintenance

### Backup Database
```bash
mysqldump -u root -p ukm_catalog > backup_ukm_catalog.sql
```

### Update Dependencies
Pastikan untuk selalu update PHP dan MySQL ke versi terbaru untuk keamanan.

### Monitoring
- Cek error logs secara berkala
- Monitor disk space untuk uploads
- Review access logs untuk aktivitas mencurigakan

## Troubleshooting

### Common Issues

1. **"Connection failed"**
   - Periksa konfigurasi database di `config/database.php`
   - Pastikan MySQL service berjalan

2. **"Upload failed"**
   - Cek permissions folder uploads (harus 777)
   - Pastikan GD extension terinstall

3. **"Session expired"**
   - Cek cookie settings di browser
   - Pastikan session save path writable

4. **"CSRF token mismatch"**
   - Clear browser cache
   - Pastikan tidak ada multiple tab

### Performance Optimization

1. **Database**
   - Tambahkan index pada kolom yang sering diquery
   - Optimize queries dengan EXPLAIN

2. **Caching**
   - Implementasi browser caching untuk static assets
   - Consider using Redis untuk session storage

3. **Images**
   - Compress images sebelum upload
   - Implementasi lazy loading untuk product images

## Contributing

1. Fork repository
2. Create feature branch
3. Commit changes
4. Push to branch
5. Create Pull Request

## Changelog

### v1.0.0 (Initial Release)
- ✅ Basic CRUD functionality
- ✅ User authentication
- ✅ Shopping cart
- ✅ Order management
- ✅ Admin dashboard
- ✅ Security features
- ✅ Responsive design

---

**UKM Catalog** - Solusi E-Commerce sederhana untuk UKM/UMKM Indonesia 🇮🇩
