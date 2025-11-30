# homeadmin24 - Developer Documentation

**Technical Guidelines, Architecture & Development Workflow**

This document serves as the comprehensive guide for developers working on homeadmin24. It contains development guidelines, technical architecture documentation, and links to detailed subsystem documentation.

## 📁 Documentation Index

Documentation is organized into three main categories for better navigation:

### 🏢 **BusinessLogic/** - Domain & Business Rules
German WEG-specific business logic, financial calculations, and regulatory compliance:

- **[HGA Reference Output](doc/BusinessLogic/hga-ref.md)** - Complete 2024 reference outputs for units 0003 and 0004
- **[Rücklagenzuführung](doc/BusinessLogic/ruecklagenzufuehrung.md)** - Reserve contribution treatment and German WEG accounting standards
- **[Kostenkonto Usage](doc/BusinessLogic/kostenkonto-usage.md)** - Current cost account analysis and usage patterns
- **[§35a Tax Analysis](doc/BusinessLogic/tax-analysis.md)** - Historical tax-deductible services analysis (2020-2024)

### ⚙️ **CoreSystem/** - Application Features & User Interface
Core application functionality, user-facing features, and system workflows:

- **[CSV Import System](doc/CoreSystem/csv_import_system.md)** - Bank statement import with auto-categorization and duplicate detection
- **[Zahlungskategorie System](doc/CoreSystem/zahlungskategorie-system.md)** - Database-driven payment category system
- **[Admin Zahlungskategorie](doc/CoreSystem/admin_zahlungskategorie.md)** - Analysis of payment category editability
- **[Authentication System](doc/CoreSystem/auth_system_concept.md)** - User roles, permissions, and security implementation
- **[Fixture Strategy](doc/CoreSystem/fixture_strategy.md)** - Complete database seeding strategy for development and production

### 🏗️ **TechnicalArchitecture/** - Implementation & Infrastructure
Technical implementation details, architecture decisions, and development workflows:

- **[Database Schema](doc/TechnicalArchitecture/DATABASE_SCHEMA.md)** - Comprehensive database documentation with entity relationships, business logic, and SQL examples
- **[Parser Architecture](doc/TechnicalArchitecture/PARSER_ARCHITECTURE.md)** - PDF parsing system architecture
- **[PDF Parser Roadmap](doc/TechnicalArchitecture/pdf_parser_roadmap.md)** - Parser development roadmap and future enhancements
- **[Calculation Service Improvements](doc/TechnicalArchitecture/calculation_service_improvements.md)** - Service layer refactoring and optimization
- **[HGA Migration Guide](doc/TechnicalArchitecture/HGA_MIGRATION_GUIDE.md)** - Complete migration guide for new HGA architecture
- **[Recent Major Changes](doc/TechnicalArchitecture/RECENT_MAJOR_CHANGES.md)** - 2025 architectural improvements and database refactoring
- **[Cloud Migration](doc/TechnicalArchitecture/doc_cloud_migration.md)** - Cloud deployment planning and documentation
- **[Droplet Deployment](.droplet/README.md)** - DigitalOcean droplet deployment guide (production & demo)

> 💡 **Quick Navigation Tip**: Use your editor's file tree or `Ctrl+P` to quickly jump to any documentation file by typing `doc/Category/filename`

---

## Important Instruction Reminders

### **Development Guidelines**
- **Be Precise**: Do what has been asked; nothing more, nothing less
- **File Management**: NEVER create files unless absolutely necessary for your goal
- **🚨 NEVER HARDCODE VALUES**: NEVER hardcode numeric values like `$gesamtkosten = 7134.75`. ALWAYS calculate dynamically from data sources
- **⚠️ CRITICAL RULE**: After ANY entity/enum/field structure changes → immediately check and fix fixture files to prevent loading failures
- **⚠️ CRITICAL RULE**: After ANY code changes → run `composer phpstan` and `composer cs-fix` to maintain code quality
- **Edit First**: ALWAYS prefer editing an existing file to creating a new one
- **No Proactive Docs**: NEVER create documentation files (*.md) or README files unless explicitly requested

### **Quality Assurance Commands**
Use these composer scripts for code quality and testing:

```bash
# Code Style
composer cs-fix         # Fix coding standards
composer cs-fix-dry      # Check coding standards (dry-run)

# Static Analysis  
composer phpstan         # Run PHPStan with 512M memory
composer phpstan-full    # Run PHPStan without baseline
composer phpstan-baseline # Generate new PHPStan baseline

# Testing
composer test            # Run all PHPUnit tests
composer test-coverage   # Run tests with HTML coverage report

# Quality Workflows
composer quality         # Full quality check: cs-fix-dry + phpstan + test
composer quality-services # Service-only tests: cs-fix-dry + phpstan + service tests
composer quality-fix     # Auto-fix and validate: cs-fix + phpstan + test
```

**Quality Gates**: Always run `composer quality` before committing changes to ensure code meets project standards.

### **homeadmin24 Project Context**
- **WEG Management System**: German property management for residential buildings
- **Authentication**: Role-based system with 5 levels (VIEWER → SUPER_ADMIN)
- **Backup Strategy**: Use `./bin/backup_db.sh` for reliable database backups
- **Fixtures**: Three groups - system-config, demo-data, opensource
- **Login**: `admin@hausman.local` / `admin123` for system administration

### **Technical Standards**
- **PHP/Symfony**: Follow PSR-12, use strict typing `declare(strict_types=1)`
- **Architecture**: SOLID principles, dependency injection, service containers
- **Error Handling**: Use Symfony's exception handling and logging features
- **Database**: Doctrine ORM with migrations, proper entity relationships
- **Frontend**: Tailwind CSS with Flowbite components, Turbo/Hotwire
- **Security**: Symfony Security bundle with bcrypt password hashing
- **Quality Assurance**: Run `composer quality-services` after all changes (CS Fixer + PHPStan + Service Tests)

### **Key Commands**
```bash
# Database backup
./bin/backup_db.sh "description"

# Fixture loading
php bin/console doctrine:fixtures:load --group=opensource --no-interaction

# Admin user creation
php bin/console app:create-admin

# Database restoration  
mysql -h127.0.0.1 -uroot hausman < backup/your_backup.sql

# Quality assurance (ALWAYS run after changes)
composer quality-services   # CS Fixer + PHPStan + Service Tests
composer cs-fix             # Auto-fix code style issues
composer phpstan            # Check for type errors (shows only NEW issues)
composer phpstan-full       # See all errors including baseline

# CRITICAL: After ANY entity/enum/field changes - check fixture compatibility
find src/DataFixtures -name "*.php" -exec grep -l "old_value\|removed_case" {} \;
php bin/console doctrine:fixtures:load --group=system-config --no-interaction
```

---

## Quick Start Guide

### **Initial Setup**
```bash
# Clone and setup
composer install
npm install && npm run build

# Database setup
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load --group=system-config

# Create admin user
php bin/console app:create-admin
```

### **Development Workflow**
```bash
# Before making changes
./bin/backup_db.sh "before_feature_x"

# After making changes (ALWAYS run)
composer quality-services

# Testing specific features
php bin/console app:hga-generate 3 2024 --format=txt
```

### **Common Issues**
- **Fixture errors**: Check for removed enum cases or entity fields
- **PHPStan errors**: Run `composer phpstan-full` to see all issues
- **HGA calculation errors**: Verify kostenkonto isActive status

### **Development Notes**
- **Service Refactoring**: Create new `App\Service\Hga` with clean structure based on `calculation_service_improvements`
  - Focus on creating a comprehensive service with full test coverage
  - Avoid hardcoded values
  - Ensure clean, modular design following SOLID principles