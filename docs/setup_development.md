# homeadmin24 - Developer Documentation

**Technical Guidelines, Architecture & Development Workflow**

This document serves as the comprehensive guide for developers working on homeadmin24. It contains development guidelines, technical architecture documentation, and links to detailed subsystem documentation.

## 📁 Documentation Index

Documentation is organized into three main categories for better navigation:

### 🏢 **business-logic/** - Domain & Business Rules
German WEG-specific business logic, financial calculations, and regulatory compliance:

- **[Rücklagenzuführung](business-logic/ruecklagenzufuehrung.md)** - Reserve contribution treatment and German WEG accounting standards

### ⚙️ **core-system/** - Application Features & User Interface
Core application functionality, user-facing features, and system workflows:

- **[CSV Import System](core-system/csv_import_system.md)** - Bank statement import with auto-categorization and duplicate detection
- **[Zahlungskategorie System](core-system/zahlungskategorie-system.md)** - Database-driven payment category system
- **[Admin Zahlungskategorie](core-system/admin_zahlungskategorie.md)** - Analysis of payment category editability
- **[Authentication System](core-system/auth_system_concept.md)** - User roles, permissions, and security implementation
- **[Fixture Strategy](core-system/fixture_strategy.md)** - Complete database seeding strategy for development and production

### 🏗️ **technical/** - Implementation & Infrastructure
Technical implementation details, architecture decisions, and development workflows:

- **[Database Schema](technical/DATABASE_SCHEMA.md)** - Comprehensive database documentation with entity relationships, business logic, and SQL examples
- **[Parser Architecture](technical/PARSER_ARCHITECTURE.md)** - PDF parsing system architecture
- **[PDF Parser Roadmap](technical/pdf_parser_roadmap.md)** - Parser development roadmap and future enhancements
- **[Calculation Service Improvements](technical/calculation_service_improvements.md)** - Service layer refactoring and optimization
- **[HGA Migration Guide](technical/HGA_MIGRATION_GUIDE.md)** - Complete migration guide for new HGA architecture
- **[Recent Major Changes](technical/RECENT_MAJOR_CHANGES.md)** - 2025 architectural improvements and database refactoring
- **[Cloud Migration](technical/doc_cloud_migration.md)** - Cloud deployment planning and documentation
- **[Production Deployment](production.md)** - DigitalOcean App Platform, Droplet deployment (production & demo)

> 💡 **Quick Navigation Tip**: Use your editor's file tree or `Ctrl+P` to quickly jump to any documentation file by typing `docs/category/filename`

---

## Quick Start Guide

### **Local Development Setup**

📖 **Complete Setup Instructions**: See [local-setup.md](local-setup.md) for detailed local development setup

**Quick Start:**
```bash
git clone https://github.com/homeadmin24/homeadmin24.git
cd homeadmin24
./setup.sh
```

This will automatically:
- Start Docker containers
- Install dependencies
- Create database with schema
- Load demo data (3 WEG, 145 transactions, 6 users)
- Configure the application

**Access:**
- Web: http://127.0.0.1:8000
- Login: `wegadmin@demo.local` / `ChangeMe123!`

### **Development Workflow**

```bash
# Before making changes
./bin/backup_db.sh "before_feature_x"

# Make your changes...

# Code quality checks
docker-compose exec web composer cs-fix      # Fix code style
docker-compose exec web composer phpstan     # Static analysis
docker-compose exec web composer test        # Run tests

# Testing specific features
docker-compose exec web php bin/console app:hga-generate 3 2024 --format=txt
```

### **Common Development Commands**

```bash
# Cache management
docker-compose exec web php bin/console cache:clear

# Database operations
docker-compose exec web php bin/console doctrine:schema:update --force
docker-compose exec web php bin/console doctrine:fixtures:load --group=demo-data

# Create admin user
docker-compose exec web php bin/console app:create-admin
```