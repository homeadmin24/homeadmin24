# homeadmin24 Deployment Guide

Complete guide to deploying homeadmin24 on DigitalOcean with automated workflows.

## 📋 Table of Contents

1. [Environment Files Explained](#environment-files-explained)
2. [Quick Start](#quick-start)
3. [GitHub Actions Deployment](#github-actions-deployment)
4. [Manual Deployment](#manual-deployment)
5. [Configuration Management](#configuration-management)

---

## Environment Files Explained

### Overview

homeadmin24 uses **two types** of environment files with different purposes:

| File | Purpose | Used By | Tracked in Git? |
|------|---------|---------|-----------------|
| `.env.example` | Application config template | Symfony app | ✅ Yes (template) |
| `.env` | Actual application config | Symfony app | ❌ No (secrets) |
| `.env.droplet.example` | Deployment config template | Documentation only | ✅ Yes (template) |
| `.env.droplet` | Actual deployment config | You (for reference) | ❌ No (secrets) |

### 1. Application Environment Files (`.env*`)

**Purpose:** Configure the **Symfony application** (database, secrets, mailer, etc.)

#### `.env.example` (tracked in git)
Template for creating your `.env` file. Contains placeholders.

```bash
APP_ENV=dev
APP_SECRET=your-secret-key-here
DATABASE_URL="mysql://username:password@127.0.0.1:3306/homeadmin24?..."
```

#### `.env` (NOT tracked, auto-configured)
Actual configuration used by the application. **Created automatically by deploy scripts.**

For **Demo deployment**, `deploy-demo.sh` creates this from `.env.example` with:
```bash
APP_ENV=dev                                    # Development mode (error pages visible)
APP_SECRET=9d7239a8d9bbf2c779f561151fcb2e10   # Demo secret key
DATABASE_URL="mysql://root:rootpassword@mysql:3306/homeadmin24?..."  # Docker MySQL
MAILER_DSN=null://null                         # No email sending
```

For **Production deployment**, you create this manually with secure values.

### 2. Deployment Configuration Files (`.env.droplet*`)

**Purpose:** Document your **infrastructure setup** (domains, IPs, SSH keys, etc.)

#### `.env.droplet.example` (tracked in git)
Template showing what configuration you need. Contains generic examples.

```bash
PRODUCTION_DOMAIN=prod.example.com
DEMO_DOMAIN=demo.example.com
PRODUCTION_DROPLET_HOST=123.45.67.89
GITHUB_REPO=homeadmin24
```

#### `.env.droplet` (NOT tracked, optional)
Your actual infrastructure details. **Only used for your reference** - not read by any scripts!

You can create this for documentation:
```bash
cp .env.droplet.example .env.droplet
nano .env.droplet  # Fill in your actual values
```

**Important:** These values go in **GitHub Secrets** for automated deployment, not in this file!

---

## Quick Start

### Demo Deployment

1. **Create DigitalOcean Droplet:**
   ```bash
   # Ubuntu 24.04, 512MB RAM minimum, note the IP address
   ```

2. **Configure DNS:**
   ```bash
   # In Cloudflare/DigitalOcean DNS:
   # A record: demo.example.com → <droplet-ip>
   ```

3. **Run setup script:**
   ```bash
   ssh root@<droplet-ip>

   wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
   chmod +x setup-demo.sh
   sudo ./setup-demo.sh demo.example.com admin@example.com
   ```

4. **Deploy application:**
   ```bash
   cd /opt/homeadmin24-demo
   git clone https://github.com/homeadmin24/homeadmin24.git .
   bash .droplet/deploy-demo.sh demo.example.com admin@example.com
   ```

**That's it!** The `.env` file is created and configured automatically.

---

## GitHub Actions Deployment

### How It Works

The `.github/workflows/deploy-droplet.yml` workflow automates deployment to your droplets.

**Triggers:**
- ✅ Automatic: Push to `main` branch (deploys to both production and demo)
- ✅ Manual: GitHub Actions → "Run workflow" (choose target)

**What it does:**
1. SSHs into your droplet
2. Pulls latest code from GitHub
3. Rebuilds Docker containers
4. Runs migrations
5. Loads fixtures (demo only)

### Required GitHub Secrets

Navigate to: **GitHub repo → Settings → Secrets and variables → Actions**

Create these secrets:

| Secret Name | Value | Example |
|-------------|-------|---------|
| `PRODUCTION_DROPLET_HOST` | Production droplet IP | `164.92.243.199` |
| `DEMO_DROPLET_HOST` | Demo droplet IP | `164.92.243.200` |
| `DROPLET_USER` | SSH username | `root` |
| `DROPLET_SSH_KEY` | Private SSH key | `-----BEGIN OPENSSH PRIVATE KEY-----...` |

**Getting your SSH key:**
```bash
# If you don't have one yet
ssh-keygen -t ed25519 -C "github-actions@homeadmin24"

# Copy private key for GitHub Secret
cat ~/.ssh/id_ed25519

# Copy public key to droplets
ssh-copy-id -i ~/.ssh/id_ed25519.pub root@<droplet-ip>
```

### Relationship to `.env.droplet`

`.env.droplet.example` documents what you need to configure:
- **Domains** → Used in manual deployment commands
- **IP addresses** → Go in `PRODUCTION_DROPLET_HOST` and `DEMO_DROPLET_HOST` secrets
- **SSH key** → Goes in `DROPLET_SSH_KEY` secret
- **GitHub repo** → Already in workflow file

The workflow **does not read** `.env.droplet` - it uses GitHub Secrets!

---

## Manual Deployment

### When to Use Manual Deployment

- Initial setup before GitHub Actions is configured
- Emergency fixes that need immediate deployment
- Testing deployment changes locally

### Production Manual Deployment

```bash
# SSH to production droplet
ssh root@<production-ip>
cd /opt/homeadmin24

# Pull latest code
git pull origin main

# Rebuild and restart
docker-compose -f docker-compose.yaml -f docker-compose.prod.yml down
docker-compose -f docker-compose.yaml -f docker-compose.prod.yml build --no-cache
docker-compose -f docker-compose.yaml -f docker-compose.prod.yml up -d

# Wait for startup
sleep 15

# Run migrations
docker-compose exec -T web php bin/console doctrine:migrations:migrate --no-interaction
docker-compose exec -T web php bin/console cache:clear
```

### Demo Manual Deployment

```bash
# SSH to demo droplet
ssh root@<demo-ip>
cd /opt/homeadmin24-demo

# Run the deployment script
bash .droplet/deploy-demo.sh demo.example.com admin@example.com
```

The script handles everything automatically (including `.env` configuration).

---

## Configuration Management

### Application Configuration (`.env`)

**Demo (Automated):**
- Created automatically by `deploy-demo.sh`
- Uses Docker-compatible settings
- No manual configuration needed

**Production (Manual):**
1. Copy template:
   ```bash
   cp .env.example .env
   ```

2. Edit for production:
   ```bash
   nano .env
   ```

3. Required changes:
   ```bash
   APP_ENV=prod                              # Production mode
   APP_SECRET=<generate-secure-random-key>   # Use: openssl rand -hex 32
   DATABASE_URL="mysql://root:rootpassword@mysql:3306/homeadmin24?..."  # Keep Docker settings
   ```

### Infrastructure Documentation (`.env.droplet`)

**Optional:** Create for your reference:

```bash
cp .env.droplet.example .env.droplet
nano .env.droplet
```

Fill in your actual:
- Domain names
- Droplet IP addresses
- Email addresses
- Backup schedules

**This file is for your documentation only** - scripts don't read it!

### What Goes Where?

| Information | `.env` | `.env.droplet` | GitHub Secrets |
|-------------|--------|----------------|----------------|
| App environment (dev/prod) | ✅ | ❌ | ❌ |
| Database password | ✅ | ❌ | ❌ |
| APP_SECRET | ✅ | ❌ | ❌ |
| Domain names | ❌ | ✅ (reference) | ❌ |
| Droplet IPs | ❌ | ✅ (reference) | ✅ |
| SSH keys | ❌ | ✅ (path reference) | ✅ |

---

## Troubleshooting

### "Database connection failed"

**Problem:** `.env` uses `127.0.0.1` instead of `mysql`

**Solution:**
```bash
# For demo (auto-fix)
bash .droplet/deploy-demo.sh demo.example.com admin@example.com

# For production (manual fix)
nano .env
# Change: 127.0.0.1 → mysql
```

### "GitHub Actions deployment fails"

**Check:**
1. All 4 secrets are configured in GitHub
2. SSH key has access to droplets: `ssh root@<ip> echo "works"`
3. Droplet has the app directory: `ssh root@<ip> ls /opt/homeadmin24-demo`

### ".env.droplet not found"

**This is normal!** The file is optional and only for your reference. Scripts don't need it.

### "Which .env file should I edit?"

**For demo:** Don't edit anything - `deploy-demo.sh` handles it automatically!

**For production:** Only edit `/opt/homeadmin24/.env` on the production droplet.

**Never edit:**
- `.env.example` (it's a template)
- `.env.droplet.example` (it's documentation)

---

## Summary

### Quick Reference

**Demo deployment:**
```bash
# Setup
wget https://raw.githubusercontent.com/homeadmin24/homeadmin24/main/.droplet/setup-demo.sh
chmod +x setup-demo.sh && sudo ./setup-demo.sh demo.example.com admin@example.com

# Deploy
cd /opt/homeadmin24-demo
git clone https://github.com/homeadmin24/homeadmin24.git .
bash .droplet/deploy-demo.sh demo.example.com admin@example.com
```

**GitHub Actions:**
1. Add 4 secrets: `PRODUCTION_DROPLET_HOST`, `DEMO_DROPLET_HOST`, `DROPLET_USER`, `DROPLET_SSH_KEY`
2. Push to main → automatic deployment
3. Or: Actions tab → "Run workflow"

**File purposes:**
- `.env` = App config (database, secrets) - **auto-configured for demo**
- `.env.droplet` = Infrastructure documentation - **optional, for your reference**
- GitHub Secrets = Automation credentials - **required for CI/CD**

---

## Need Help?

- 📚 Full deployment guide: `.droplet/README.md`
- 🔧 Private configuration reference: `.droplet/how-to.md` (if exists)
- 🐛 Issues: https://github.com/homeadmin24/homeadmin24/issues
