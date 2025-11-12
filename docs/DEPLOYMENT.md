# 🚀 AI Reading Module - Deployment Guide

**Version:** 1.0.0
**Last Updated:** November 12, 2025
**Moodle Version:** 4.5+
**PHP Version:** 8.1+

---

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Performance Tuning](#performance-tuning)
5. [Troubleshooting](#troubleshooting)
6. [Monitoring](#monitoring)
7. [Backup & Recovery](#backup--recovery)
8. [Upgrade Procedures](#upgrade-procedures)

---

## 🔧 Prerequisites

### System Requirements

**Minimum:**
- Moodle 4.5.0+
- PHP 8.1+
- PostgreSQL 13+ or MySQL 8.0+
- 2 CPU cores
- 4 GB RAM
- 10 GB storage

**Recommended (Production):**
- Moodle 4.5.0+
- PHP 8.2+
- PostgreSQL 15+ or MySQL 8.0+
- 4+ CPU cores
- 8+ GB RAM
- 50+ GB storage (SSD)
- Redis or Memcached for caching

### Required Dependencies

1. **local_ai_manager** plugin v1.0.0+
   - Provides Speech-to-Text (STT) integration
   - Must be configured with Whisper API credentials

2. **PHP Extensions:**
   - `mbstring` (multi-byte string handling)
   - `intl` (internationalization)
   - `json` (JSON parsing)
   - `pdo_mysql` or `pdo_pgsql` (database)

3. **Server Configuration:**
   - `max_execution_time` ≥ 120 seconds (for audio processing)
   - `upload_max_filesize` ≥ 10M (for audio files)
   - `post_max_size` ≥ 12M
   - `memory_limit` ≥ 256M

---

## 📦 Installation

### Method 1: Via Moodle Plugin Installer (Recommended)

1. **Download** the plugin ZIP file from the official repository
2. **Navigate** to `Site administration → Plugins → Install plugins`
3. **Upload** the ZIP file
4. **Click** "Install plugin from the ZIP file"
5. **Follow** the on-screen prompts
6. **Complete** the installation by clicking "Upgrade Moodle database now"

### Method 2: Manual Installation via CLI

```bash
# Navigate to Moodle directory
cd /var/www/moodle

# Create plugin directory
mkdir -p mod/aireading

# Extract plugin files
unzip /path/to/mod_aireading-1.0.0.zip -d mod/aireading

# Set correct permissions
chown -R www-data:www-data mod/aireading
chmod -R 755 mod/aireading

# Run Moodle CLI upgrade
sudo -u www-data php admin/cli/upgrade.php --non-interactive
```

### Method 3: Git Installation (Development)

```bash
cd /var/www/moodle/mod
git clone https://github.com/your-org/moodle-mod_aireading.git aireading
cd aireading
git checkout v1.0.0

# Upgrade Moodle
sudo -u www-data php ../../admin/cli/upgrade.php --non-interactive
```

### Post-Installation Verification

```bash
# Check plugin is installed
php admin/cli/plugin_info.php --plugin mod_aireading

# Expected output:
# mod_aireading: AI Reading v2025111101
```

---

## ⚙️ Configuration

### 1. AI Manager Integration

**Navigate to:** `Site administration → Plugins → Local plugins → AI Manager`

1. **Configure STT Connector:**
   - Provider: Whisper API
   - API Key: [Your OpenAI API Key]
   - Model: `whisper-1`
   - Language Detection: Enabled

2. **Test Connection:**
   ```bash
   php admin/cli/scheduled_task.php --execute=\\local_ai_manager\\task\\test_stt_connection
   ```

### 2. Plugin Settings

**Navigate to:** `Site administration → Plugins → Activity modules → AI Reading`

| Setting | Default | Description |
|---------|---------|-------------|
| **Batch Size** | 50 | Number of attempts processed per cron run |
| **Max File Size** | 10 MB | Maximum audio file upload size |
| **Default Language** | German (de) | Default reading language |
| **Default Target WPM** | 100 | Default words per minute target |
| **Enable Caching** | Yes | Use Moodle cache API (recommended) |
| **Cache TTL** | 3600s | Time-to-live for cached data |
| **Silence Threshold** | 10s | Auto-stop recording after silence |

### 3. Cron Configuration

**Ensure Moodle cron is running every minute:**

```bash
# Add to crontab
* * * * * /usr/bin/php /var/www/moodle/admin/cli/cron.php >/dev/null 2>&1
```

**Verify scheduled tasks:**

```bash
php admin/cli/scheduled_task.php --list | grep aireading
```

Expected output:
```
mod_aireading\task\analyze_attempt_task (* * * * *) [Enabled]
```

### 4. File Storage Configuration

**Recommended:** Use external file storage (e.g., S3, Azure Blob) for production.

**Navigate to:** `Site administration → Server → File storage`

Configure:
- **Repository:** Amazon S3 / Azure Blob
- **Bucket/Container:** moodle-audio-files
- **Region:** eu-central-1 (or nearest)
- **Lifecycle Policy:** Delete files older than 365 days

---

## 🚀 Performance Tuning

### 1. Database Optimization

**Indexes (already created by plugin):**
```sql
-- Verify indexes exist
SHOW INDEX FROM mdl_aireading_attempts;

-- Expected indexes:
-- - aireading_id
-- - userid
-- - status
-- - timeanalyzed
-- - UNIQUE (aireading_id, userid, attempt)
```

**Query Performance:**
```sql
-- Enable slow query log (MySQL)
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Monitor slow queries
tail -f /var/log/mysql/mysql-slow.log | grep aireading
```

### 2. Caching Strategy

**Recommended Cache Stores:**

| Cache | Store | TTL | Why |
|-------|-------|-----|-----|
| `user_attempts` | Redis | 1h | Frequently accessed, user-specific |
| `course_stats` | Redis | 2h | Teacher reports, less frequent writes |
| `analysis_results` | Memcached | 24h | Large JSON, expensive to recompute |
| `chart_data` | Redis | 1h | Real-time visualization |

**Configure Redis:**

```php
// config.php
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = '127.0.0.1';
$CFG->session_redis_port = 6379;
$CFG->session_redis_database = 0;
$CFG->session_redis_prefix = 'moodle_';
```

**Test cache performance:**
```bash
php admin/cli/cache_helper.php --list
php admin/cli/purge_caches.php
```

### 3. Audio Processing Optimization

**Background Task Queue:**

- Attempts are queued with `status=1` (submitted)
- Cron task processes 50 attempts per run (every minute)
- Expected throughput: ~3000 attempts/hour

**Scale if needed:**
```bash
# Increase batch size (in plugin settings)
# Or run multiple cron workers

# Worker 1
php admin/cli/scheduled_task.php --execute=\\mod_aireading\\task\\analyze_attempt_task

# Worker 2 (different server/container)
php admin/cli/scheduled_task.php --execute=\\mod_aireading\\task\\analyze_attempt_task
```

### 4. Frontend Optimization

**JavaScript Minification:**
```bash
# Grunt build (already configured)
npx grunt amd --force
```

**Browser Caching:**
```apache
# .htaccess (if using Apache)
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/javascript "access plus 1 year"
    ExpiresByType text/css "access plus 1 year"
</IfModule>
```

---

## 🐛 Troubleshooting

### Issue 1: Attempts Stuck in "Submitted" Status

**Symptoms:** Attempts remain with `status=1` for hours.

**Diagnosis:**
```bash
# Check cron is running
php admin/cli/scheduled_task.php --showsql --execute=\\mod_aireading\\task\\analyze_attempt_task

# Check for errors
tail -f /var/www/moodledata/admin/cli/cron.log | grep aireading
```

**Solutions:**
1. Verify `local_ai_manager` is installed and configured
2. Check STT API credentials and quota
3. Increase `max_execution_time` in php.ini
4. Reduce batch size in plugin settings

### Issue 2: High Memory Usage

**Symptoms:** PHP Fatal Error: Allowed memory size exhausted.

**Diagnosis:**
```bash
# Check memory usage during cron
php -d memory_limit=512M admin/cli/scheduled_task.php --execute=\\mod_aireading\\task\\analyze_attempt_task
```

**Solutions:**
1. Increase `memory_limit` in php.ini (recommended: 512M)
2. Reduce batch size (e.g., 20 instead of 50)
3. Enable opcode caching (OPcache)

### Issue 3: Slow Report Loading

**Symptoms:** Teacher reports take >10 seconds to load.

**Diagnosis:**
```sql
-- Check database query performance
EXPLAIN SELECT * FROM mdl_aireading_attempts WHERE aireading_id = 123;
```

**Solutions:**
1. Enable all cache stores (Redis/Memcached)
2. Verify indexes exist on `aireading_attempts` table
3. Use pagination for large course reports
4. Precompute statistics via scheduled task

### Issue 4: Audio Upload Failures

**Symptoms:** "Invalid audio format" or "File too large" errors.

**Diagnosis:**
```bash
# Check PHP upload limits
php -i | grep -E "upload_max_filesize|post_max_size|max_execution_time"
```

**Solutions:**
1. Increase `upload_max_filesize` to 10M+
2. Increase `post_max_size` to 12M+
3. Verify supported formats: MP3, WAV, M4A, WEBM
4. Check client-side JavaScript recorder settings

---

## 📊 Monitoring

### Key Metrics to Track

1. **Attempt Processing Rate:**
   ```sql
   SELECT COUNT(*) as pending FROM mdl_aireading_attempts WHERE status = 1;
   ```

2. **Average Analysis Time:**
   ```sql
   SELECT AVG(timeanalyzed - timefinished) as avg_processing_time
   FROM mdl_aireading_attempts
   WHERE status = 2 AND timeanalyzed > 0;
   ```

3. **Error Rate:**
   ```sql
   SELECT COUNT(*) * 100.0 / (SELECT COUNT(*) FROM mdl_aireading_attempts) as error_percentage
   FROM mdl_aireading_attempts
   WHERE status = 3;
   ```

4. **Cache Hit Rate:**
   ```bash
   redis-cli INFO stats | grep keyspace_hits
   ```

### Recommended Monitoring Tools

- **Moodle Admin Report:** `Site administration → Reports → Performance overview`
- **Database:** pgBadger (PostgreSQL) or pt-query-digest (MySQL)
- **Server:** Prometheus + Grafana
- **Application:** New Relic / Datadog APM

---

## 💾 Backup & Recovery

### What to Backup

1. **Database Tables:**
   - `mdl_aireading`
   - `mdl_aireading_attempts`

2. **Files:**
   - `moodledata/filedir/` (audio files)
   - `mod/aireading/` (plugin code)

3. **Configuration:**
   - `config.php` (AI Manager settings)
   - Plugin settings export

### Backup Procedures

**Full Backup (Daily):**
```bash
#!/bin/bash
# /usr/local/bin/backup_aireading.sh

DATE=$(date +%Y%m%d)
BACKUP_DIR="/backups/moodle/aireading"

# Database
pg_dump -U moodle -t mdl_aireading -t mdl_aireading_attempts moodle > "$BACKUP_DIR/db_$DATE.sql"

# Files (audio)
rsync -av /var/www/moodledata/filedir/ "$BACKUP_DIR/files_$DATE/" --include="**/attemptaudio/**" --exclude="*"

# Compress
tar -czf "$BACKUP_DIR/aireading_backup_$DATE.tar.gz" "$BACKUP_DIR/db_$DATE.sql" "$BACKUP_DIR/files_$DATE/"

# Cleanup old backups (keep 30 days)
find "$BACKUP_DIR" -name "*.tar.gz" -mtime +30 -delete
```

**Incremental Backup (Hourly):**
```bash
# Only backup new attempts since last backup
pg_dump -U moodle -t mdl_aireading_attempts --data-only \
  --where="timecreated > EXTRACT(EPOCH FROM NOW() - INTERVAL '1 hour')" \
  moodle > /backups/moodle/aireading/incremental_$(date +%Y%m%d_%H).sql
```

### Recovery Procedures

**Restore from Backup:**
```bash
# Database restore
psql -U moodle moodle < /backups/moodle/aireading/db_20251112.sql

# File restore
rsync -av /backups/moodle/aireading/files_20251112/ /var/www/moodledata/filedir/

# Purge caches
php admin/cli/purge_caches.php
```

---

## 🔄 Upgrade Procedures

### From v1.0.0 to v1.1.0 (Example)

1. **Backup:** Create full backup (see above)

2. **Enable Maintenance Mode:**
   ```bash
   php admin/cli/maintenance.php --enable
   ```

3. **Update Plugin:**
   ```bash
   cd /var/www/moodle/mod/aireading
   git fetch --tags
   git checkout v1.1.0
   ```

4. **Run Upgrade:**
   ```bash
   sudo -u www-data php admin/cli/upgrade.php --non-interactive
   ```

5. **Purge Caches:**
   ```bash
   php admin/cli/purge_caches.php
   ```

6. **Verify:**
   ```bash
   php admin/cli/plugin_info.php --plugin mod_aireading
   # Check version number
   ```

7. **Disable Maintenance Mode:**
   ```bash
   php admin/cli/maintenance.php --disable
   ```

8. **Test:** Submit a test reading attempt

### Rolling Back

If upgrade fails:
```bash
# Restore database
psql -U moodle moodle < /backups/moodle/aireading/db_pre_upgrade.sql

# Restore plugin code
cd /var/www/moodle/mod
rm -rf aireading
git clone https://github.com/your-org/moodle-mod_aireading.git aireading
cd aireading
git checkout v1.0.0

# Disable maintenance mode
php ../../admin/cli/maintenance.php --disable
```

---

## 📞 Support

**Documentation:** https://docs.moodle.org/en/mod/aireading
**Issue Tracker:** https://github.com/your-org/moodle-mod_aireading/issues
**Email:** support@your-organization.de

---

## 📄 License

GNU GPL v3 or later - See COPYING.txt for details.
