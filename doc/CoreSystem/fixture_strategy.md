# Fixture Strategy: Open Source vs Production Versions

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

### **Option C: Fresh Production Start**
```bash
# Clean production install (no demo data)
php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Result: Empty system with core configuration
# Login: admin@hausman.local / admin123
# Next: Add your real WEGs, units, payments manually
```

### **📦 Backup Management**

#### **Create Backup**
```bash
# Create timestamped backup
./bin/backup_db.sh

# Create backup with description
./bin/backup_db.sh "before_upgrade"
```

#### **Restore Backup**
```bash
# Restore specific backup
mysql -h127.0.0.1 -uroot hausman < backup/backup_20250723_204935_production_working.sql

# Full database recreation + restore
php bin/console doctrine:database:drop --force
php bin/console doctrine:database:create
mysql -h127.0.0.1 -uroot hausman < backup/your_backup.sql
```

---