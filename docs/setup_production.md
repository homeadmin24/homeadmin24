# Production Deployment Guide

Complete guide for deploying homeadmin24 to production. For local development setup, see [local-setup.md](local-setup.md).

## Table of Contents

- [Deployment Options Overview](#deployment-options-overview)
- [Option 1: DigitalOcean App Platform (Managed)](#option-1-digitalocean-app-platform-managed)
- [Option 2: DigitalOcean Droplet (VPS Self-Hosted)](#option-2-digitalocean-droplet-vps-self-hosted)
- [Multi-Droplet Architecture (Production + Demo)](#multi-droplet-architecture-production--demo)
- [Management & Troubleshooting](#management--troubleshooting)

---

## Deployment Options Overview

| Option | Cost | Setup Time | Management | Best For |
|--------|------|------------|------------|----------|
| **App Platform** | $12/mo | 10 min | Fully managed | Quick start, beginners |
| **Droplet (Single)** | $6/mo | 30 min | Self-managed | Cost-optimized, experienced users |
| **Multi-Droplet** | $12/mo | 60 min | Self-managed | Production + Demo sites |

---

## Option 1: DigitalOcean App Platform (Managed)

**One-Click Cloud Deployment** - Ideal for quick production deployment without server configuration.

[![Deploy to DigitalOcean](https://img.shields.io/badge/Deploy%20to-DigitalOcean-0080FF?logo=digitalocean&logoColor=white)](https://cloud.digitalocean.com/apps/new?repo=https://github.com/homeadmin24/homeadmin24/tree/main)

### Setup Steps

1. **Click "Deploy to DigitalOcean" button above**
2. **Connect your GitHub repository**
3. **DigitalOcean automatically creates:**
   - PHP-Web-Service with Nginx
   - MySQL 8.0 Production database
   - Automatic SSL certificates
   - HTTPS access with custom domain

4. **Initial configuration** (via App Platform Console):

```bash
# Load system configuration
php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Create admin user
php bin/console app:create-admin
```

### Benefits

- ✅ **No server configuration** - Everything auto-configured
- ✅ **Automatic backups** - Daily database backups included
- ✅ **SSL certificates** - Automatically configured and renewed
- ✅ **Scalable** - Add more resources on demand
- ✅ **Auto-deployments** - Automatic updates on git push
- ✅ **Zero downtime** - Automatic rolling deployments

### Costs

- **App Service:** $5/month (Basic)
- **MySQL Database:** $7/month (Production DB)
- **Total:** ~$12/month

### Management

```bash
# App Console
# https://cloud.digitalocean.com/apps

# View logs (in App Platform Console)
# Apps → Your App → Runtime Logs

# Change environment variables
# Apps → Your App → Settings → Environment Variables

# Trigger manual deployment
# Apps → Your App → Deploy
```

### When to Choose App Platform?

✅ **Recommended for:**
- Quick production deployment
- No server management experience
- Budget of $12+/month
- Automatic backups desired
- Scalability important

---

## Option 2: DigitalOcean Droplet (VPS Self-Hosted)

Most cost-effective option for production deployment with full control ($6/month).

### Quick Setup

```bash
# 1. Create Ubuntu 22.04/24.04 Droplet ($6/month Basic)
# 2. Set DNS A-record to droplet IP
# 3. SSH into droplet

# Download and run setup script
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-production.sh
chmod +x setup-production.sh
sudo ./setup-production.sh yourdomain.com your@email.com

# Clone repository
cd /opt/homeadmin24
git clone https://github.com/homeadmin24/homeadmin24.git .

# Configure environment
cp .env.example .env
nano .env  # Set database credentials and APP_SECRET

# Deploy application
sudo bash .droplet/deploy-production.sh yourdomain.com your@email.com

# Create admin user
docker-compose exec web php bin/console app:create-admin
```

### Benefits

- ✅ **Most affordable** ($6/month for unlimited apps)
- ✅ **Full root control** over server
- ✅ **Docker-based** (identical to local development)
- ✅ **Automatic SSL** (Let's Encrypt)
- ✅ **Auto-deployments** via GitHub Actions
- ✅ **Daily backups** (3 AM automatic)

### Automated Deployment via GitHub Actions

Configure GitHub Repository Secrets:
- `PRODUCTION_DROPLET_HOST`: Droplet IP address
- `DROPLET_USER`: SSH username (usually `root`)
- `DROPLET_SSH_KEY`: Private SSH key for access

Every push to `main` automatically deploys via GitHub Actions.

### Management Commands

```bash
# View logs
cd /opt/homeadmin24
docker-compose logs -f web

# Manual backup
/usr/local/bin/homeadmin24-backup.sh

# View backups
ls -lh /opt/homeadmin24/backups/

# Restore from backup
docker-compose down
docker volume rm homeadmin24_mysql_data
docker-compose up -d
sleep 10
zcat /opt/homeadmin24/backups/homeadmin24_prod_YYYYMMDD_HHMMSS.sql.gz | \
  docker exec -i homeadmin24-mysql-1 mysql -uroot -prootpassword homeadmin24

# Update application
cd /opt/homeadmin24
git pull origin main
sudo bash .droplet/deploy-production.sh yourdomain.com your@email.com

# SSL certificates
certbot certificates
certbot renew

# Monitor resources
htop
df -h
docker system df
```

### When to Choose Droplet?

✅ **Recommended for:**
- Cost-optimized deployment ($6/month)
- Full server control desired
- Server management experience available
- Multiple apps on one server
- Docker-based deployment preferred

---

## Multi-Droplet Architecture (Production + Demo)

Run production and auto-resetting demo environments on separate droplets.

### Architecture Overview

| Environment | Domain | Directory | Auto-Reset | Purpose |
|------------|--------|-----------|------------|---------|
| **Production** | prod.example.com | `/opt/homeadmin24` | ❌ No | Live system for real usage |
| **Demo** | demo.example.com | `/opt/homeadmin24-demo` | ✅ Every 30min | Testing and demonstration |

### Setup Production Droplet

```bash
ssh root@<production-droplet-ip>

# Download and run setup
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-production.sh
chmod +x setup-production.sh
./setup-production.sh prod.example.com admin@example.com

# Clone and configure
cd /opt/homeadmin24
git clone https://github.com/homeadmin24/homeadmin24.git .
cp .env.example .env
nano .env  # Set APP_ENV=prod, secure APP_SECRET

# Deploy
bash .droplet/deploy-production.sh prod.example.com admin@example.com

# Create admin
docker-compose exec web php bin/console app:create-admin
```

### Setup Demo Droplet

```bash
ssh root@<demo-droplet-ip>

# Download and run setup
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
chmod +x setup-demo.sh
./setup-demo.sh demo.example.com admin@example.com

# Clone and deploy
cd /opt/homeadmin24-demo
git clone https://github.com/homeadmin24/homeadmin24.git .
bash .droplet/deploy-demo.sh demo.example.com admin@example.com
```

### Auto-Reset Configuration (Demo Only)

The demo system automatically resets **every 30 minutes**:

**Schedule**: 00:00, 00:30, 01:00, 01:30, ... (48 times per day)

**What happens during reset:**
1. Stops Docker containers
2. Removes database volume (deletes all data)
3. Starts containers with fresh database
4. Runs migrations
5. Loads system-config and demo-data fixtures
6. Clears cache

**Customize reset interval:**

```bash
# Edit cron schedule (current: 0,30 * * * *)
crontab -e

# Examples:
# Every 15 minutes: */15 * * * * /usr/local/bin/homeadmin24-demo-reset.sh
# Every hour: 0 * * * * /usr/local/bin/homeadmin24-demo-reset.sh
# Every 2 hours: 0 */2 * * * /usr/local/bin/homeadmin24-demo-reset.sh
```

**Demo management:**

```bash
# Manual reset
/usr/local/bin/homeadmin24-demo-reset.sh

# View reset logs
tail -f /var/log/homeadmin24-demo-reset.log

# Disable auto-reset temporarily
crontab -l | grep -v homeadmin24-demo-reset | crontab -

# Re-enable auto-reset
(crontab -l; echo "0,30 * * * * /usr/local/bin/homeadmin24-demo-reset.sh") | crontab -
```

### GitHub Actions for Multi-Droplet

Configure GitHub Secrets:
- `PRODUCTION_DROPLET_HOST` - IP of production droplet
- `DEMO_DROPLET_HOST` - IP of demo droplet
- `DROPLET_USER` - SSH username (usually `root`)
- `DROPLET_SSH_KEY` - Private SSH key

Push to `main` automatically deploys to both droplets in parallel.

---

## Management & Troubleshooting

### Health Checks

```bash
# Check application status
curl -I https://yourdomain.com

# Check database connectivity
docker-compose exec web php bin/console doctrine:query:sql "SELECT 1"

# Check disk space
df -h

# Monitor Docker resources
docker stats --no-stream
```

### Log Locations

| Log Type | Location |
|----------|----------|
| Application | `docker-compose logs web` |
| Nginx Access | `/var/log/nginx/access.log` |
| Nginx Error | `/var/log/nginx/error.log` |
| Demo Reset | `/var/log/homeadmin24-demo-reset.log` |
| Production Backup | `/var/log/homeadmin24-backup.log` |
| SSL Certbot | `/var/log/letsencrypt/` |

### Common Issues

#### Application Won't Start

```bash
# Check logs
docker-compose logs web
docker-compose logs mysql

# Restart containers
docker-compose restart

# Rebuild from scratch
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

#### SSL Certificate Issues

```bash
# Check certificate status
certbot certificates

# Force renewal
certbot renew --force-renewal

# Debug certificate issues
certbot renew --dry-run --verbose
```

#### Database Issues

```bash
# Production: Restore from backup
cd /opt/homeadmin24
ls -lh backups/
# Choose latest backup and restore (see commands above)

# Demo: Force reset
/usr/local/bin/homeadmin24-demo-reset.sh
```

#### Nginx Configuration Errors

```bash
# Test configuration
nginx -t

# View error log
tail -f /var/log/nginx/error.log

# Reload after fixing
systemctl reload nginx
```

### Security Best Practices

#### Production Droplet
- ✅ Secure `APP_SECRET` in `.env`
- ✅ Daily automated backups at 3 AM
- ✅ Firewall enabled (SSH + HTTPS only)
- ✅ SSL/TLS certificates auto-renewed
- ✅ Data persistence with Docker volumes
- ⚠️ Never commit `.env` to git
- ⚠️ Regularly review and update dependencies

#### Demo Droplet
- ✅ Data resets every 30 minutes (no persistent user data)
- ✅ Demo mode indicated in HTTP headers
- ✅ Separate database from production
- ⚠️ Users should know data is not saved
- ⚠️ Don't use real/sensitive data

---

## Deployment Scripts Reference

All deployment scripts are located in `.droplet/` directory:

- **`setup-production.sh`** - Initial production droplet setup (installs Docker, Nginx, SSL)
- **`deploy-production.sh`** - Deploy/update production application
- **`setup-demo.sh`** - Initial demo droplet setup (includes auto-reset cron)
- **`deploy-demo.sh`** - Deploy/update demo application with fixtures

See `.droplet/` directory for detailed script documentation.

---

## Support & Further Documentation

- **Local Development:** [local-setup.md](local-setup.md)
- **Developer Guide:** [development.md](development.md)
- **GitHub Issues:** [homeadmin24/issues](https://github.com/homeadmin24/homeadmin24/issues)
- **License:** [GNU AGPL v3](../LICENSE)
