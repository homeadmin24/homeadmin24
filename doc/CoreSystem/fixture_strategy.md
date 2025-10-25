# Fixture Strategy: Development, Demo, and Production Deployments

## 🌍 **Environment Overview**

| Environment | Purpose | Data Source | Fixtures? |
|-------------|---------|-------------|-----------|
| **Local Development** | Development & testing | Demo fixtures OR backups | ✅ Yes (demo-data) |
| **Demo Droplet** | Public demo site | Demo fixtures (auto-reset) | ✅ Yes (demo-data) |
| **Production Droplet** | Live WEG management | **Real backup files** | ❌ **NO** |

**⚠️ IMPORTANT:** Production deployments should **NEVER** use fixtures. Always restore from real backup files.

---

## 🔧 **Setup Commands**

### **Option A: Demo System (Development)**
```bash
# Complete demo environment with all dependencies 
# (includes system-config from Option C below)
php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Docker environment:
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Loading order (handled automatically):
# 1. System Config: 5 roles, payment categories, cost accounts
# 2. Admin user (system-config group)
# 3. Demo users: 5 demo users with different roles  
# 4. Demo business: 3 WEGs, 12 units, sample transactions
#
# Result: Full demo system ready to use
# Login: wegadmin@demo.local / [DEMO_PASSWORD from .env]
# Docker access: http://127.0.0.1:8000
```

### **Option B: Production with Backup**
```bash
# Reset database and restore from backup
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
mysql -h127.0.0.1 -uroot hausman < backup/your_backup.sql

# Result: Your production data restored
# Login: Use your existing credentials
```

### **Option C: Fresh Production Start (Local Only)**
```bash
# Clean production install (no demo data)
# ⚠️ Only for local development - NOT for production droplets!
php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Result: Empty system with core configuration
# Login: admin@hausman.local / admin123
# Next: Add your real WEGs, units, payments manually
```

### **Option D: Production Droplet Deployment** ⭐
```bash
# For production deployment on DigitalOcean droplets, see:
# .droplet/README.md  - Generic deployment guide (in git)
# .droplet/how-to.md  - Your actual values (gitignored)

# Quick summary:
# 1. Run migrations (creates tables)
# 2. Restore from backup (NOT fixtures!)
# 3. Never use demo-data fixtures on production

# Result: Production with real WEG data
# Login: Use credentials from your backup
```

### **📦 Backup Management**

#### **Create Backup**
```bash
# Create timestamped backup
./bin/backup_db.sh

# Create backup with description
./bin/backup_db.sh "before_upgrade"
```

#### **Restore Backup (Local Development)**
```bash
# Restore specific backup
mysql -h127.0.0.1 -uroot hausman < backup/backup_20250723_204935_production_working.sql

# Full database recreation + restore
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
mysql -h127.0.0.1 -uroot hausman < backup/your_backup.sql
```

#### **Restore Backup (Production Droplet)**
```bash
# See .droplet/README.md for generic guide
# See .droplet/how-to.md for your actual commands
```

---

## 🚨 **Critical Rules**

### **Production Deployments**
- ✅ **DO**: Use real database backups from `backup/` directory
- ✅ **DO**: Follow `.droplet/README.md` (generic) or `.droplet/how-to.md` (your values)
- ✅ **DO**: Restore via Docker container commands on droplet
- ❌ **DON'T**: Use fixtures on production droplets
- ❌ **DON'T**: Load demo-data on production
- ❌ **DON'T**: Manually enter WEG data (always restore from backup)

**Full deployment guide:** See `.droplet/README.md` in repository

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