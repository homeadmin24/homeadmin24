# homeadmin24 Multi-Droplet Deployment Guide

This directory contains deployment scripts for running homeadmin24 on DigitalOcean Droplets with a two-environment architecture: Production and Auto-Resetting Demo.

## 🏗️ Architecture Overview

### Domains
- `example.com` → Redirects to `https://www.example.com`
- `www.example.com` → Landing page with links to production and demo
- `prod.example.com` → **Production** system (persistent data)
- `demo.example.com` → **Demo** system (auto-resets every 30 minutes)

**Note:** Replace with your actual domains. See `.env.droplet.example` for configuration.

### Droplet Configuration

| Environment | Domain | Directory | Auto-Reset | Data Persistence | Purpose |
|------------|--------|-----------|------------|------------------|---------|
| **Production** | prod.example.com | `/opt/homeadmin24` | ❌ No | ✅ Full | Live system for real usage |
| **Demo** | demo.example.com | `/opt/homeadmin24-demo` | ✅ Every 30min | ❌ None | Testing and demonstration |
| **Landing** | www.example.com | `/var/www/homeadmin24-landing` | N/A | Static HTML | Information and navigation |

## 📦 Deployment Scripts

### Production Droplet Scripts

**`setup-production.sh`** - Initial production droplet setup
```bash
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-production.sh
chmod +x setup-production.sh
sudo ./setup-production.sh prod.example.com admin@example.com
```

**`deploy-production.sh`** - Deploy/update production application
```bash
cd /opt/homeadmin24
sudo bash .droplet/deploy-production.sh prod.example.com admin@example.com
```

### Demo Droplet Scripts

**`setup-demo.sh`** - Initial demo droplet setup
```bash
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
chmod +x setup-demo.sh
sudo ./setup-demo.sh demo.example.com admin@example.com
```

**`deploy-demo.sh`** - Deploy/update demo application with auto-reset
```bash
cd /opt/homeadmin24-demo
sudo bash .droplet/deploy-demo.sh demo.example.com admin@example.com
```

### Landing Page Setup

**`setup-www-redirect.sh`** - Configure domain redirects and landing page
```bash
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-www-redirect.sh
chmod +x setup-www-redirect.sh
sudo ./setup-www-redirect.sh admin@example.com example.com www.example.com prod.example.com demo.example.com
```

## 🚀 Complete Deployment Process

### Step 1: Create and Configure Droplets

Create 3 droplets on DigitalOcean (or 2 if combining landing with production):

1. **Production Droplet** - Ubuntu 22.04/24.04, 2GB+ RAM, 2 vCPUs
2. **Demo Droplet** - Ubuntu 22.04/24.04, 1GB+ RAM, 1 vCPU
3. **Landing Droplet** (optional) - Ubuntu 22.04/24.04, 512MB RAM, 1 vCPU

### Step 2: Configure DNS

Point these A records to your droplet IPs:

```
example.com        → Landing droplet IP
www.example.com    → Landing droplet IP
prod.example.com   → Production droplet IP
demo.example.com   → Demo droplet IP
```

Wait for DNS propagation (can take up to 48 hours, usually 5-10 minutes).

### Step 3: Setup Production Droplet

SSH into your production droplet:

```bash
ssh root@<production-droplet-ip>

# Download and run setup script
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-production.sh
chmod +x setup-production.sh
./setup-production.sh prod.example.com admin@example.com

# Clone repository
cd /opt/homeadmin24
git clone https://github.com/homeadmin24/homeadmin24.git .

# Configure environment
cp .env.example .env
nano .env  # Set APP_ENV=prod, secure APP_SECRET, etc.

# Deploy application
bash .droplet/deploy-production.sh prod.example.com admin@example.com

# Create admin user
docker-compose exec web php bin/console app:create-admin
```

### Step 4: Setup Demo Droplet

SSH into your demo droplet:

```bash
ssh root@<demo-droplet-ip>

# Download and run setup script
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
chmod +x setup-demo.sh
./setup-demo.sh demo.example.com admin@example.com

# Clone repository
cd /opt/homeadmin24-demo
git clone https://github.com/homeadmin24/homeadmin24.git .

# Environment will be auto-configured for demo
# Deploy application
bash .droplet/deploy-demo.sh demo.example.com admin@example.com
```

### Step 5: Setup Landing Page

SSH into your landing droplet (or production droplet if combining):

```bash
ssh root@<landing-droplet-ip>

wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-www-redirect.sh
chmod +x setup-www-redirect.sh
./setup-www-redirect.sh admin@example.com example.com www.example.com prod.example.com demo.example.com
```

### Step 6: Configure GitHub Secrets

Add these secrets to your GitHub repository (Settings → Secrets and variables → Actions):

- `PRODUCTION_DROPLET_HOST` - IP address of production droplet
- `DEMO_DROPLET_HOST` - IP address of demo droplet
- `DROPLET_USER` - SSH username (usually `root`)
- `DROPLET_SSH_KEY` - Private SSH key for authentication

### Step 7: Test Deployment

Push to main branch or manually trigger deployment:

```bash
# GitHub UI: Actions tab → Deploy to Droplets → Run workflow
# Or push to main branch (auto-deploys to both)
```

## 🔧 Management Commands

### Production Droplet

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
sudo bash .droplet/deploy-production.sh prod.homeadmin24.de admin@homeadmin24.de
```

### Demo Droplet

```bash
# View logs
cd /opt/homeadmin24-demo
docker-compose logs -f web

# Manual reset
/usr/local/bin/homeadmin24-demo-reset.sh

# View reset logs
tail -f /var/log/homeadmin24-demo-reset.log

# Check auto-reset schedule
crontab -l | grep homeadmin24

# Disable auto-reset temporarily
crontab -l | grep -v homeadmin24-demo-reset | crontab -

# Re-enable auto-reset
(crontab -l; echo "0,30 * * * * /usr/local/bin/homeadmin24-demo-reset.sh") | crontab -

# Update application
cd /opt/homeadmin24-demo
git pull origin main
sudo bash .droplet/deploy-demo.sh demo.homeadmin24.de admin@homeadmin24.de
```

### Both Droplets

```bash
# Check SSL certificates
certbot certificates

# Renew SSL certificates (runs automatically)
certbot renew

# View Nginx configuration
cat /etc/nginx/sites-available/homeadmin24-production  # or homeadmin24-demo
nginx -t  # Test configuration
systemctl reload nginx

# Check firewall status
ufw status

# Monitor system resources
htop
df -h  # Disk usage
docker system df  # Docker disk usage
```

## 🔄 Auto-Reset Configuration (Demo Only)

The demo system automatically resets every 30 minutes using a cron job:

**Schedule**: 00:00, 00:30, 01:00, 01:30, ... (48 times per day)

**What happens during reset**:
1. Stops Docker containers
2. Removes database volume (deletes all data)
3. Starts containers with fresh database
4. Runs migrations
5. Loads system-config fixtures
6. Loads demo-data fixtures
7. Clears cache

**Customize reset interval**:

```bash
# Edit cron schedule (current: 0,30 * * * *)
crontab -e

# Examples:
# Every 15 minutes: */15 * * * * /usr/local/bin/homeadmin24-demo-reset.sh
# Every hour: 0 * * * * /usr/local/bin/homeadmin24-demo-reset.sh
# Every 2 hours: 0 */2 * * * /usr/local/bin/homeadmin24-demo-reset.sh
```

## 🔐 Security Considerations

### Production Droplet
- ✅ Secure `APP_SECRET` in `.env`
- ✅ Daily automated backups at 3 AM
- ✅ Firewall enabled (SSH + HTTPS only)
- ✅ SSL/TLS certificates auto-renewed
- ✅ Data persistence with Docker volumes
- ⚠️ Never commit `.env` to git
- ⚠️ Regularly review and update dependencies

### Demo Droplet
- ✅ Data resets every 30 minutes (no persistent user data)
- ✅ Demo mode indicated in HTTP headers
- ✅ Separate database from production
- ⚠️ Users should know data is not saved
- ⚠️ Don't use real/sensitive data

## 📊 Monitoring

### Health Checks

```bash
# Check if application is running
curl -I https://prod.example.com
curl -I https://demo.example.com

# Check database connectivity
docker-compose exec web php bin/console doctrine:query:sql "SELECT 1"

# Check disk space
df -h

# Check Docker resource usage
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
| Certbot | `/var/log/letsencrypt/` |

## 🐛 Troubleshooting

### Application Won't Start

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

### SSL Certificate Issues

```bash
# Check certificate status
certbot certificates

# Force certificate renewal
certbot renew --force-renewal

# Debug certificate issues
certbot renew --dry-run --verbose
```

### Database Issues

```bash
# Production: Restore from backup
cd /opt/homeadmin24
ls -lh backups/
# Use restore procedure from Management Commands above

# Demo: Force reset
/usr/local/bin/homeadmin24-demo-reset.sh
```

### Nginx Configuration Errors

```bash
# Test configuration
nginx -t

# View error log
tail -f /var/log/nginx/error.log

# Reload after fixing
systemctl reload nginx
```

### Demo Not Resetting

```bash
# Check if cron job exists
crontab -l | grep homeadmin24

# Check reset logs
tail -n 100 /var/log/homeadmin24-demo-reset.log

# Run reset manually to test
/usr/local/bin/homeadmin24-demo-reset.sh

# Check if cron service is running
systemctl status cron
```

## 🔄 CI/CD with GitHub Actions

The workflow `.github/workflows/deploy-droplet.yml` handles automated deployments.

### Automatic Deployment
- Triggers on push to `main` branch
- Deploys to both production and demo droplets
- Runs in parallel for faster deployment

### Manual Deployment
- Go to Actions → Deploy to Droplets → Run workflow
- Choose target: `both`, `production`, or `demo`
- Useful for selective updates or rollbacks

### Workflow Requirements
- GitHub Secrets configured (see Step 6)
- Droplets must be set up and accessible via SSH
- Repository must be public or have proper access tokens

## 📝 Maintenance Checklist

### Weekly
- [ ] Check application logs for errors
- [ ] Verify demo auto-reset is working
- [ ] Review disk space usage

### Monthly
- [ ] Test backup restoration (production)
- [ ] Review and clean old backups
- [ ] Update dependencies (`composer update`, `npm update`)
- [ ] Check for security updates (`apt update && apt upgrade`)

### Quarterly
- [ ] Review and update SSL certificates (auto-renewed, just verify)
- [ ] Audit user accounts and permissions
- [ ] Performance review and optimization
- [ ] Documentation updates

## 🆘 Support

For issues or questions:
- GitHub Issues: https://github.com/homeadmin24/homeadmin24/issues
- Documentation: `/doc/` directory in repository
- Configuration: `.env.droplet.example` for deployment settings
- System logs: See "Log Locations" section above

## 📄 License

homeadmin24 is open-source software. See LICENSE file for details.
