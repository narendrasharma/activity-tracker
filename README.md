# Mini Activity Tracking & Audit System

A production-quality activity tracking and audit log system built with **Core PHP** (no frameworks) and **MySQL**. Features anomaly detection, file-based caching, rate limiting, and a clean dark-mode dashboard.

---

## Features

| Feature | Details |
|---|---|
| **Activity Logger** | Reusable `ActivityLogger::log()` static method |
| **REST API** | 4 JSON endpoints with full error handling |
| **Dynamic Filtering** | Prepared statements, multi-filter SQL queries |
| **Anomaly Detection** | High-frequency bursts + multi-IP access |
| **File Caching** | 2-minute TTL cache for top-users endpoint |
| **Rate Limiting** | 100 requests/IP/hour, file-based |
| **CSV Export** | Download filtered logs as CSV |
| **Frontend Dashboard** | Vanilla JS + HTML, no frameworks |
| **Seed CLI** | Generate 10k+ dummy records in seconds |
| **Security** | Prepared statements, input validation, CORS, security headers |

---

## Project Structure

```
activity-tracker/
├── api/
│   ├── log.php            # POST  — log an activity
│   ├── logs.php           # GET   — fetch logs (filter, paginate, sort, export)
│   ├── top-users.php      # GET   — top 5 active users (cached)
│   └── anomalies.php      # GET   — anomaly detection report
├── classes/
│   ├── ActivityLogger.php  # Core logger + query engine
│   ├── Database.php        # MySQLi singleton wrapper
│   ├── RateLimiter.php     # File-based IP rate limiter
│   ├── Cache.php           # File-based TTL cache
│   └── ApiResponse.php     # JSON response helpers
├── config/
│   ├── database.php        # DB credentials (constants)
│   ├── app.php             # App-wide constants
│   └── bootstrap.php       # Autoloader + shared middleware helpers
├── cache/
│   ├── data/               # Cached API responses
│   └── rate_limits/        # Per-IP rate limit tracking
├── public/
│   └── index.html          # Dashboard UI
├── sql/
│   └── schema.sql          # Database schema + indexes
├── cli/
│   └── seed.php            # CLI seed script
├── .htaccess
├── .gitignore
└── README.md
```

---

## Requirements

- PHP **8.0+** with `mysqli` extension enabled
- MySQL **5.7+** or MariaDB **10.3+**
- Apache with `mod_rewrite` (or Nginx — see config below)
- Write permission on the `cache/` directory

---

### 3. Configure the database connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'activity_tracker');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

### 4. Set cache directory permissions

```bash
chmod -R 775 cache/
```


### 6. Open the dashboard

Navigate to `http://localhost/activity-tracker/public/index.html`.

---

## Seed Dummy Data (CLI)

Generate test records from the command line:

```bash
# 10,000 logs across 50 users
php cli/seed.php --count=10000 --users=50

# Include injected anomaly patterns (for testing anomaly detection)
php cli/seed.php --count=10000 --users=50 --anomalies

# Custom count
php cli/seed.php --count=50000 --users=100
```

---

## API Reference

All endpoints return JSON. Errors include a `message` field. Successful responses wrap data in a `data` field.

### `POST /api/log.php` — Log an Activity

**Request body** (JSON or form-data):

| Field | Type | Required | Description |
|---|---|---|---|
| `user_id` | integer | ✅ | ID of the user |
| `action` | string | ✅ | Action name (max 100 chars) |
| `metadata` | object/JSON | — | Arbitrary context data |
| `ip_address` | string | — | Override detected IP |
| `user_agent` | string | — | Override detected user agent |

**Example:**

```bash
curl -X POST http://localhost/activity-tracker/api/log.php \
  -H "Content-Type: application/json" \
  -d '{"user_id": 7, "action": "login", "metadata": {"browser": "Chrome"}}'
```

**Response:**

```json
{
  "success": true,
  "message": "Activity logged successfully.",
  "data": { "log_id": 1423 }
}
```

---

### `GET /api/logs.php` — Fetch Logs

**Query parameters:**

| Param | Type | Description |
|---|---|---|
| `user_id` | int | Filter by user |
| `action` | string | Filter by action name |
| `date_from` | date | `YYYY-MM-DD` or `YYYY-MM-DD HH:MM:SS` |
| `date_to` | date | `YYYY-MM-DD` or `YYYY-MM-DD HH:MM:SS` |
| `ip_address` | string | Filter by IP |
| `limit` | int | Records per page (default: 50, max: 500) |
| `offset` | int | Pagination offset (default: 0) |
| `sort` | string | `ASC` or `DESC` (default: `DESC`) |
| `export` | string | Pass `csv` to download as CSV file |

**Example:**

```bash
curl "http://localhost/activity-tracker/api/logs.php?user_id=7&action=login&limit=25&sort=ASC"
```

---

### `GET /api/top-users.php` — Top Active Users

**Query parameters:**

| Param | Type | Description |
|---|---|---|
| `n` | int | Number of users to return (default: 5, max: 50) |

Response is **cached for 2 minutes** (file-based). Check the `X-Cache: HIT/MISS` header.

---

### `GET /api/anomalies.php` — Anomaly Report

Returns two anomaly categories:

- **`high_frequency`** — users with >10 actions within any 1-minute window (last hour)
- **`multi_ip`** — users accessing from multiple IPs within 5 minutes

---

## 🔐 Rate Limiting

- **100 requests per IP per hour**
- Tracked in `cache/rate_limits/` (file-based, no Redis needed)
- Returns HTTP `429` with `Retry-After` header when exceeded
- Response headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`

---

## 🔑 Optional API Authentication

Disabled by default. To enable:

1. Set `define('API_AUTH_ENABLED', true)` in `config/app.php`
2. Set your key: `define('API_KEY', 'your-secret-key')`
3. Include the header in every request: `X-API-Key: your-secret-key`

---

## 🛡️ Security Practices

- All database queries use **prepared statements** 
- Input is validated and sanitized before use
- `metadata` is stored as JSON (never interpolated into SQL)
- IP addresses are validated with `filter_var(FILTER_VALIDATE_IP)`
- Cache files are stored outside the web root's public path

---
