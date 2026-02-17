# Fixture Strategy: Development, Demo, and Production Deployments


## Feature Behavior
- Scope: Definiert datenquellen- und umgebungsabhaengige Seed-Strategien fuer Dev, Demo und Production.
- Inputs/Outputs: Input ist Zielumgebung + gewünschter Datenzustand; Output ist eine konsistente DB mit passendem Realismusgrad.
- Invarianten: Demo-Fixtures niemals in Production; Production basiert auf echten Backups plus Migrationen.
- Akzeptanz: Jede Umgebung startet mit erwarteter Datenbasis und reproduzierbarem Setup ohne Mischzustaende.

## Operational Runbook
- Trigger: Bei Neuaufbau einer Umgebung, Demo-Reset, oder Production-Restore.
- Schritte: Passende Option (A/B/C/D) waehlen, Kommandos exakt in Reihenfolge ausfuehren, danach Login-/Datensatzcheck machen.
- Verifikation: Rollen, WEG-Daten und erwartete Demo-/Produktionsobjekte sind korrekt vorhanden.
- Recovery/Rollback: Bei Fehlseed DB leeren/wiederherstellen und mit korrekter Option erneut initialisieren.

## 🌍 **Environment Overview**

| Environment | Purpose | Data Source | Fixtures? |
|-------------|---------|-------------|-----------|
| **Local Development** | Development & testing | Demo fixtures OR backups | ✅ Yes (demo-data) |
| **Demo Droplet** | Public demo site | Demo fixtures (auto-reset) | ✅ Yes (demo-data) |
| **Production Droplet** | Live WEG management | **Real backup files** | ❌ **NO** |

**⚠️ IMPORTANT:** Production deployments should **NEVER** use fixtures. Always restore from real backup files.

---

## 🔧 **Setup Commands**

### **Option A: Demo System (Development)** ⭐ Recommended

```bash
# Manual setup:
docker compose up -d --build
sleep 10
docker compose exec web composer install --no-interaction
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
docker compose exec web php bin/console cache:clear

# Loading order (handled automatically):
# 1. System Config: Roles, payment categories, cost accounts, Umlageschlüssel
# 2. Admin user (system-config group)
# 3. Demo users: 6 demo users with different roles
# 4. Demo business: 3 WEGs, 12 units, 145 transactions, 22 invoices
#
# Result: Full demo system ready to use
# Login: wegadmin@demo.local / demo123
# Access: http://127.0.0.1:8000
```

### **Option B: Production with Backup**
```bash
# Import latest backup
docker exec -i homeadmin24-mysql-1 mysql -uroot -prootpassword homeadmin24 < backup/backup_YYYYMMDD_HHMMSS_description.sql

# Update schema for any new columns
docker compose exec web php bin/console doctrine:schema:update --force

# Result: Your production data restored
# Login: Use your existing credentials
```

### **Option C: Fresh Production Start (Local Only)**
```bash
# Clean production install (no demo data)
# ⚠️ Only for local development - NOT for production droplets!
docker compose exec web php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Create your first admin user
docker compose exec web php bin/console app:create-admin

# Result: Empty system with core configuration
# Next: Add your real WEGs, units, payments manually
```

### **Option D: Production Droplet Deployment** ⭐
```bash
# For production deployment on DigitalOcean droplets, see:
# docs/setup-production.md - Complete deployment guide

# Quick summary:
# 1. Run migrations (creates tables)
# 2. Restore from backup (NOT fixtures!)
# 3. Never use demo-data fixtures on production

# Result: Production with real WEG data
# Login: Use credentials from your backup
```

### **📦 Backup Management**

#### **Create Backup (Local Development)**
```bash
# Create timestamped backup
docker exec homeadmin24-mysql-1 mysqldump -uroot -prootpassword homeadmin24 > backup/backup_$(date +%Y%m%d_%H%M%S)_description.sql

# Using backup script (if available on host)
./bin/backup_db.sh "before_upgrade"
```

#### **Restore Backup (Local Development)**
```bash
# Quick restore (recommended)
docker exec -i homeadmin24-mysql-1 mysql -uroot -prootpassword homeadmin24 < backup/your_backup.sql
docker compose exec web php bin/console doctrine:schema:update --force

# Full database recreation + restore
docker-compose exec web php bin/console doctrine:database:drop --force
docker-compose exec web php bin/console doctrine:database:create
docker exec -i homeadmin24-mysql-1 mysql -uroot -prootpassword homeadmin24 < backup/your_backup.sql
docker compose exec web php bin/console doctrine:schema:update --force
```

#### **Restore Backup (Production Droplet)**
```bash
# See docs/setup-production.md for complete deployment guide
```

---

## 🚨 **Critical Rules**

### **Production Deployments**
- ✅ **DO**: Use real database backups from `backup/` directory
- ✅ **DO**: Follow `docs/setup-production.md` for complete deployment guide
- ✅ **DO**: Restore via Docker container commands on droplet
- ❌ **DON'T**: Use fixtures on production droplets
- ❌ **DON'T**: Load demo-data on production
- ❌ **DON'T**: Manually enter WEG data (always restore from backup)

**Full deployment guide:** See `docs/setup-production.md`

### **Demo Deployments**
- ✅ **DO**: Use fixtures (demo-data group)
- ✅ **DO**: Auto-reset every 30 minutes (via cron)
- ✅ **DO**: Load fresh fixtures on each deployment
- ❌ **DON'T**: Use real production data on demo site

### **Local Development**
- ✅ **DO**: Use either fixtures OR backups (your choice)
- ✅ **DO**: Test with demo-data fixtures for development
- ✅ **DO**: Test with real backups for realistic testing
- ✅ **DO**: Create backups before major changes

---
