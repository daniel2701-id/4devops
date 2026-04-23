# CareConnect – PHP Healthcare Portal

Portal Kesehatan Terpadu berbasis PHP untuk Ubuntu 22.04.

---

## Persyaratan Sistem

| Komponen | Versi Minimum |
|----------|---------------|
| Ubuntu   | 22.04 LTS     |
| PHP      | 8.1+          |
| MySQL    | 8.0+ / MariaDB 10.6+ |
| Apache   | 2.4+          |
| mod_rewrite | Aktif      |

---

## Instalasi di Ubuntu 22.04

### 1. Install LAMP Stack

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install apache2 mysql-server php8.1 php8.1-mysql php8.1-mbstring \
     php8.1-xml php8.1-curl php8.1-gd libapache2-mod-php8.1 -y

sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 2. Konfigurasi MySQL

```bash
sudo mysql_secure_installation
sudo mysql -u root -p
```

```sql
CREATE USER 'careconnect'@'localhost' IDENTIFIED BY 'GantiPasswordKuat123!';
GRANT ALL PRIVILEGES ON careconnect.* TO 'careconnect'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 3. Import Database

```bash
mysql -u careconnect -p < database/schema.sql
```

### 4. Letakkan File Proyek

```bash
sudo cp -r careconnect/ /var/www/html/
sudo chown -R www-data:www-data /var/www/html/careconnect
sudo chmod -R 755 /var/www/html/careconnect
sudo chmod -R 750 /var/www/html/careconnect/config
sudo chmod -R 750 /var/www/html/careconnect/includes
```

### 5. Konfigurasi Apache VirtualHost

```apache
<VirtualHost *:80>
    ServerName careconnect.local
    DocumentRoot /var/www/html/careconnect/public
    
    <Directory /var/www/html/careconnect/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Block access to sensitive dirs
    <Directory /var/www/html/careconnect/config>
        Require all denied
    </Directory>
    <Directory /var/www/html/careconnect/includes>
        Require all denied
    </Directory>
    <Directory /var/www/html/careconnect/database>
        Require all denied
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/careconnect_error.log
    CustomLog ${APACHE_LOG_DIR}/careconnect_access.log combined
</VirtualHost>
```

```bash
sudo a2ensite careconnect.local
sudo systemctl reload apache2
```

### 6. Edit Konfigurasi

Buka `config/config.php` dan sesuaikan:

```php
define('APP_URL',  'http://careconnect.local');
define('DB_USER',  'careconnect');
define('DB_PASS',  'GantiPasswordKuat123!');
define('APP_ENV',  'production');   // ganti dari 'development'
```

---

## Akun Demo (seed data)

| Role    | Email                     | Password     |
|---------|---------------------------|--------------|
| Admin   | admin@careconnect.id      | Admin123!    |
| Dokter  | dr.andi@careconnect.id    | Doctor123!   |
| Pasien  | sarah@careconnect.id      | Patient123!  |

> **Penting:** Ganti semua password demo setelah instalasi!

---

## Struktur Proyek

```
careconnect/
├── config/
│   ├── config.php          # Konfigurasi utama
│   └── database.php        # Koneksi PDO
├── includes/
│   ├── security.php        # CSRF, rate limit, OTP, headers
│   ├── auth.php            # Login, logout, session guard
│   └── functions.php       # Bootstrap & helpers
├── database/
│   └── schema.sql          # Skema & seed data
├── public/                 # Document root (Apache)
│   ├── index.php           # Intro screen
│   ├── landing.php         # Landing page
│   ├── patient/
│   │   ├── login.php       # Login + Register pasien
│   │   ├── dashboard.php   # Dashboard pasien
│   │   └── logout.php
│   ├── doctor/
│   │   ├── login.php       # Login dokter
│   │   ├── dashboard.php   # Dashboard dokter
│   │   └── logout.php
│   └── admin/
│       ├── login.php       # Login admin
│       ├── verify.php      # 2FA OTP
│       ├── dashboard.php   # Dashboard admin
│       └── logout.php
└── .htaccess               # Security rules
```

---

## Fitur Keamanan

| Fitur | Detail |
|-------|--------|
| SQL Injection | PDO Prepared Statements |
| XSS | `htmlspecialchars()` di semua output |
| CSRF | Token di setiap form POST |
| Brute Force | Rate limiting 5x / 15 menit per IP+email |
| Password | bcrypt cost-12, validasi kekuatan |
| Session | Regenerate ID, timeout 30 menit, httpOnly, SameSite=Strict |
| 2FA Admin | OTP 6-digit, berlaku 10 menit, hashed bcrypt |
| Headers | X-Frame-Options, CSP, X-XSS-Protection, Referrer-Policy |
| Directory | .htaccess blocks config/includes/database access |
| Audit Log | Semua login/logout tercatat di DB |
| Role-based | Setiap halaman punya guard `require_role()` |

---

## Produksi – Checklist

- [ ] Ganti semua password demo
- [ ] Set `APP_ENV = 'production'` di config.php
- [ ] Aktifkan HTTPS & uncomment HSTS
- [ ] Set `secure=true` untuk session cookie
- [ ] Konfigurasi SMTP email untuk OTP (ganti `generate_otp` agar kirim email)
- [ ] Jalankan `mysql_secure_installation`
- [ ] Set permission file: `chmod 640 config/config.php`
- [ ] Hapus konten `admin_otp_demo` dari verify.php
- [ ] Aktifkan firewall: `ufw allow 80,443/tcp`
