# homeadmin24 - Developer Documentation

**Technical Guidelines, Architecture & Development Workflow**

This document serves as the comprehensive guide for developers working on homeadmin24. It contains development guidelines, technical architecture documentation, and links to detailed subsystem documentation.

## 📁 Documentation Index

### 📚 **Core Documentation**

- **[Core System](core_system.md)** - CSV import, payment categorization, zahlungskategorie, auth, fixtures, Rücklagenzuführung
- **[AI Integration](ai_integration.md)** - AI-powered payment categorization and natural language queries
- **[AI Invoice Extraction](ai_doc_extraction.md)** - Invoice parsing architecture + OCR/LLM roadmap
- **[Fixture Strategy](fixture_strategy.md)** - Complete database seeding strategy for development and production

### 🚀 **Setup Guides**

- **[Local Setup](setup_local.md)** - Docker development environment setup
- **[Production Deployment](setup_production.md)** - DigitalOcean App Platform, Droplet deployment (production & demo)

> 💡 **Quick Navigation Tip**: Use your editor's file tree or `Ctrl+P` to quickly jump to any documentation file by typing `docs/filename`

---

## Quick Start Guide

### **Local Development Setup**

📖 **Complete Setup Instructions**: See [setup_local.md](setup_local.md) for detailed local development setup

**Quick Start:**
```bash
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24
docker compose up -d --build
sleep 10
docker compose exec web composer install --no-interaction
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction
docker compose exec web php bin/console cache:clear
```

This will automatically:
- Start Docker containers
- Install dependencies
- Create database with schema
- Load demo data (3 WEG, 145 transactions, 6 users)
- Configure the application

### Local AI Services (Ollama + DocIntel)

Local development uses Ollama and (optionally) DocIntel. Demo/Prod are not used right now.

```bash
# Pull Ollama model once
docker exec -it hausman-ollama ollama pull llama3.1:8b

# Optional DocIntel config (in .env.local)
DOCINTEL_ENABLED=true
DOCINTEL_URL=http://doc-intel:8000
```

**Access:**
- Web: http://127.0.0.1:8000
- Login: `wegadmin@demo.local` / `demo123`

### **Development Workflow**

```bash
# Before making changes
./bin/backup_db.sh "before_feature_x"

# Make your changes...

# Code quality checks
docker compose exec web composer cs-fix      # Fix code style
docker compose exec web composer phpstan     # Static analysis
docker compose exec web composer test        # Run tests

# Testing specific features
docker compose exec web php bin/console app:hga-generate 3 2024
```

### **Common Development Commands**

```bash
# Cache management
docker compose exec web php bin/console cache:clear

# Database operations
docker compose exec web php bin/console doctrine:schema:update --force
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data

# Create admin user
docker compose exec web php bin/console app:create-admin
```
