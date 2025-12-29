# Database Backup & Restore

This guide explains how to backup and restore the homeadmin24 database.

## Quick Reference

```bash
# Create backup
./bin/backup_db.sh [description]

# Restore backup
./bin/restore_db.sh backup/backup_YYYYMMDD_HHMMSS_description.sql
```

## Creating Backups

### Local Development

```bash
# Manual backup
./bin/backup_db.sh manual

# Backup before migration
./bin/backup_db.sh before_migration

# Backup with custom description
./bin/backup_db.sh "before_user_changes"
```

The backup script:
- Creates a full database dump in `backup/` directory
- **Excludes** `doctrine_migration_versions` table to avoid migration conflicts
- Includes routines, triggers, and uses single transaction for consistency
- Automatically creates safety backup before restore operations

### Production Server

```bash
# SSH into production server
ssh root@164.92.239.128

# Create backup
cd /opt/homeadmin24-prod
./bin/backup_db.sh production_daily

# Or use the automated backup script (runs daily at 3 AM)
/usr/local/bin/homeadmin24-backup.sh
```

Production backups are stored in:
- **Auto backups:** `/opt/homeadmin24-prod/backups/`
- **Manual backups:** `/opt/homeadmin24-prod/backup/`

## Restoring Backups

### Local Development

```bash
# List available backups
ls -lah backup/

# Restore a specific backup
./bin/restore_db.sh backup/backup_20251228_153045_manual.sql
```

The restore process will:
1. Ask for confirmation (type `yes` to proceed)
2. Create a safety backup of current database
3. Restore the specified backup
4. Display post-restore steps

### After Restore - IMPORTANT!

**Always run these commands after restoring a backup:**

```bash
# 1. Update database schema to match current code
docker compose exec web php bin/console doctrine:schema:update --force

# 2. Clear Symfony cache
docker compose exec web php bin/console cache:clear

# 3. Fix file permissions (if needed)
docker compose exec web chown -R www-data:www-data /var/www/html/var
docker compose exec web chmod -R 775 /var/www/html/var
```

### Why Schema Update is Needed

When you restore an old backup, the database schema might be missing:
- New columns added after the backup was created
- New tables or indexes
- Modified field types

The `doctrine:schema:update` command compares the database schema with your Entity classes and applies any missing changes.

### Production Restore

```bash
# SSH into production
ssh root@164.92.239.128

# Navigate to project
cd /opt/homeadmin24-prod

# List available backups
ls -lah backups/

# Restore backup
./bin/restore_db.sh backups/backup_YYYYMMDD_HHMMSS.sql

# IMPORTANT: Run post-restore commands
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console cache:clear
docker compose exec web chown -R www-data:www-data /var/www/html/var
```

## Common Scenarios

### Before Major Changes

Always backup before:
- Database migrations
- Large data imports
- Schema modifications
- Testing new features

```bash
./bin/backup_db.sh before_feature_xyz
```

### After Deployment Issues

If a deployment causes issues:

```bash
# 1. Find the backup created before deployment
ls -lah backup/ | grep before

# 2. Restore the backup
./bin/restore_db.sh backup/backup_YYYYMMDD_before_deploy.sql

# 3. Run post-restore steps
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console cache:clear
```

### Copying Production to Local

```bash
# 1. On production: Create backup
ssh root@164.92.239.128 'cd /opt/homeadmin24-prod && ./bin/backup_db.sh prod_snapshot'

# 2. Download backup to local
scp root@164.92.239.128:/opt/homeadmin24-prod/backup/backup_*_prod_snapshot.sql backup/

# 3. Restore locally
./bin/restore_db.sh backup/backup_*_prod_snapshot.sql

# 4. Post-restore steps
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console cache:clear
```

## Troubleshooting

### Error: "Unknown column 'xyz' in field list"

This means the backup is from an older version. Run:

```bash
docker compose exec web php bin/console doctrine:schema:update --force
```

### Error: "Table 'xyz' already exists"

This happens when trying to run migrations after restore. Solution:

```bash
# Skip migrations, just update schema
docker compose exec web php bin/console doctrine:schema:update --force
```

### Permission Denied Errors

After restore, Symfony cache might have wrong permissions:

```bash
docker compose exec web chown -R www-data:www-data /var/www/html/var
docker compose exec web chmod -R 775 /var/www/html/var
docker compose exec web php bin/console cache:clear
```

### Backup File Too Large

For very large databases, compress the backup:

```bash
# Create compressed backup
mysqldump -h127.0.0.1 -uroot homeadmin24 | gzip > backup/backup_$(date +%Y%m%d).sql.gz

# Restore compressed backup
gunzip < backup/backup_20251228.sql.gz | mysql -h127.0.0.1 -uroot homeadmin24
```

## Automated Backups

### Production (Already Configured)

Production server has automated daily backups via cron:
- **Schedule:** Daily at 3:00 AM
- **Location:** `/opt/homeadmin24-prod/backups/`
- **Retention:** Last 30 days
- **Script:** `/usr/local/bin/homeadmin24-backup.sh`

To check cron status:
```bash
ssh root@164.92.239.128 'crontab -l | grep homeadmin24'
```

### Local Development (Optional)

You can set up automated local backups with cron:

```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * cd /Users/nikolas.shewlakow/Public/homeadmin24-workspace/homeadmin24 && ./bin/backup_db.sh daily_auto
```

## Best Practices

1. **Backup before deployments** - Always create a backup before deploying to production
2. **Test restores regularly** - Verify your backups work by testing restore process
3. **Keep multiple backups** - Don't rely on just one backup file
4. **Document special data** - If you have custom data configurations, document them
5. **Secure sensitive backups** - Production backups contain real user data
6. **Clean old backups** - Remove backups older than 30 days to save disk space

## Migration Versions Table

The backup script **excludes** the `doctrine_migration_versions` table because:
- Migration version numbers change as code evolves
- Including them causes conflicts when restoring to a different codebase version
- Schema is automatically updated with `doctrine:schema:update`

If you need to include migration versions (rare cases):

```bash
# Backup with migration versions
mysqldump -h127.0.0.1 -uroot --routines --triggers --single-transaction homeadmin24 > backup/full_with_migrations.sql
```

## See Also

- [Local Setup Guide](local-setup.md) - Setting up development environment
- [Production Deployment](production.md) - Production server management
- [Development Guide](development.md) - General development workflow
