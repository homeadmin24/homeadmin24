# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**homeadmin24** is a comprehensive property management system (WEG-Verwaltungssystem) for German condominium associations (Wohnungseigentümergemeinschaften). It handles financial tracking, payment management, invoice processing, and automated annual statements (Hausgeldabrechnungen - HGA).

**Tech Stack:**
- Backend: Symfony 8.0 (PHP 8.4+)
- Database: MySQL 9
- Frontend: Tailwind CSS, Flowbite, Stimulus.js, Webpack Encore
- PDF Generation: Puppeteer (Headless Chrome)
- AI: Ollama (local) + Claude API (optional)

## Essential Commands

### Development Setup
```bash
# Initial setup (starts Docker, installs deps, creates DB, loads fixtures)
./setup.sh

# Access: http://127.0.0.1:8000
# Login: wegadmin@demo.local / demo123
```

### Code Quality & Testing
```bash
# Fix code style (PHP-CS-Fixer)
docker compose exec web composer cs-fix

# Static analysis (PHPStan)
docker compose exec web composer phpstan

# Run all tests
docker compose exec web composer test

# Run specific test directory
docker compose exec web phpunit tests/Service/

# Run all quality checks (style + analysis + tests)
docker compose exec web composer quality
```

### Database Operations
```bash
# Update schema
docker compose exec web php bin/console doctrine:schema:update --force

# Load demo data (development only)
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Load system config only (for production)
docker compose exec web php bin/console doctrine:fixtures:load --group=system-config --no-interaction

# Create migrations
docker compose exec web php bin/console make:migration

# Execute migrations
docker compose exec web php bin/console doctrine:migrations:migrate
```

### HGA (Hausgeldabrechnung) Generation
```bash
# Generate HGA for WEG ID 3, year 2024
docker compose exec web php bin/console app:hga-generate 3 2024

# Generate for specific unit only
docker compose exec web php bin/console app:hga-generate 3 2024 --unit=0003

# Validate without generating
docker compose exec web php bin/console app:hga-generate 3 2024 --validate-only

# Verbose error output
docker compose exec web php bin/console app:hga-generate 3 2024 --verbose-errors
```

### CSV Import (Bank Statements)
```bash
# Import Sparkasse SEPA CSV
docker compose exec web php bin/console app:import-csv /path/to/kontoauszug.csv

# Import for specific WEG
docker compose exec web php bin/console app:import-csv kontoauszug.csv --weg=3

# Dry run (validate without saving)
docker compose exec web php bin/console app:import-csv kontoauszug.csv --dry-run
```

### Frontend Assets
```bash
# Development mode (watch for changes)
npm run watch

# Build for production
npm run build

# Development build
npm run dev
```

### Utility Commands
```bash
# Clear cache
docker compose exec web php bin/console cache:clear

# Create admin user interactively
docker compose exec web php bin/console app:create-admin

# Backup database before changes
./bin/backup_db.sh "before_feature_x"

# Access MySQL directly
docker compose exec db mysql -u root -p hausmandb
```

## Architecture Overview

### Domain Model - Core Entities

The system revolves around these key domain concepts:

**WEG (Wohnungseigentümergemeinschaft)** - Condominium association
- Contains multiple WegEinheit (property units)
- Has Kostenkonten (cost accounts) for expense tracking
- Central entity for all financial operations

**WegEinheit** - Individual property unit
- Belongs to a WEG
- Has Miteigentumsanteile (MEA) - ownership percentage (e.g., 0.29 = 29%)
- Links to Eigentuemer (owner) information
- Receives Hausgeldabrechnung (annual statements)

**Zahlung** - Payment transaction
- Can be income (Hausgeld payments) or expense (invoices, fees)
- Linked to Zahlungskategorie for categorization
- May reference Kostenkonto, Dienstleister, or Rechnung
- Auto-categorized via pattern matching + AI

**Rechnung** - Invoice
- Linked to Dienstleister (service provider)
- Can have attached Dokument (PDF)
- Contains tax information (§35a EStG for tax deductions)
- May be parsed automatically from PDF

**Hausgeldabrechnung (HGA)** - Annual statement
- Generated per WegEinheit for a specific year
- Shows all costs distributed by Miteigentumsanteile
- Includes §35a tax deductions
- Calculates Rücklagenzuführung (reserve allocation)
- Outputs PDF report via Puppeteer

### Service Layer Architecture

**HGA Service Structure** (`src/Service/Hga/`)
The HGA system follows a clean, interface-driven architecture:

```
HgaService (Orchestrator)
├── Configuration/
│   └── HgaConfiguration - Database-driven config (no hardcoded values)
├── Calculation/
│   ├── DistributionService - Cost distribution by MEA
│   ├── CostCalculationService - Total cost calculations
│   ├── BalanceCalculationService - Owner balances (Soll/Ist)
│   ├── TaxCalculationService - §35a EStG deductions
│   ├── ExternalCostService - Heating/water from external providers
│   └── PaymentCalculationService - Payment aggregations
└── Report/
    └── PdfReportGenerator - Puppeteer-based PDF generation
```

**Key Design Principles:**
- Interface-based dependency injection (see `config/services.yaml` for aliases)
- No hardcoded MEA percentages or unit counts (database-driven)
- Single source of truth for calculations (no duplicate calculation paths)
- Full test coverage for calculation services

**Payment Categorization** (`src/Service/ZahlungKategorisierungService.php`)
- Pattern-based auto-categorization (regex matching)
- AI-enhanced categorization via Ollama/Claude
- Learning from user corrections
- 3-level duplicate detection (SEPA ID → Amount+Date+Partner → Fuzzy matching)

**CSV Parsing** (`src/Service/BankStatementParsingService.php`)
- Supports Sparkasse SEPA format
- Handles German number formats (1.234,56 → 1234.56)
- Date conversion (DD.MM.YYYY → YYYY-MM-DD)
- Reference ID extraction for duplicate prevention

**Invoice Parsing** (`src/Service/Parser/`)
- 3-tier parser selection hierarchy:
  1. `parser_class` field (highest priority) - Custom parser implementation
  2. Hardcoded parser classes (middle) - Name-based detection for complex vendors
  3. `parser_config` JSON (lowest) - Regex-based parsing for simple cases
- Example: `MaassParser` for complex §35a calculations
- Factory pattern: `ParserFactory` selects appropriate parser

**AI Integration** (`src/Service/AI/`, `src/Service/OllamaService.php`)
- Payment categorization enhancement
- HGA quality checks (detect calculation errors, missing data)
- Dual provider support: Ollama (local, DSGVO-compliant) + Claude API (premium)
- User feedback loop for continuous improvement

### Frontend Architecture

**Stimulus Controllers** (`assets/controllers/`)
- `zahlung_form_controller.js` - Dynamic payment form (category-based field visibility)
- `hga_quality_check_controller.js` - AI quality checks for HGA documents
- Turbo-enabled for SPA-like navigation

**Templates** (`templates/`)
- Twig-based server-side rendering
- Tailwind CSS + Flowbite components
- PDF templates rendered via Puppeteer (`templates/hga/pdf_report.html.twig`)

## Important Domain Logic

### Miteigentumsanteile (MEA)
Ownership percentages that determine cost distribution. Example:
- Unit 1: 25% (0.25)
- Unit 2: 29% (0.29)
- Unit 3: 27% (0.27)
- Unit 4: 19% (0.19)

**Critical:** Never hardcode MEA values. Always read from `WegEinheit.miteigentumsanteile`.

### Rücklagenzuführung (Reserve Allocation)
German WEG law (§21 WEG) requires annual reserve allocations:

```
Reserve Pool = Total Contributions
             - Operating Costs (umlagefähig)
             - External Costs (heating/water)
             - Management Fees

Per Unit Reserve = Reserve Pool × MEA
```

**Implementation:** `src/Service/Hga/Calculation/BalanceCalculationService.php`

### §35a EStG Tax Deductions
German tax law allows homeowners to deduct 20% of labor/maintenance costs (max €1,200/year).

**Rules:**
- Only labor costs (Arbeitskosten), not materials
- Maximum €6,000 eligible → €1,200 deduction (20%)
- Must be correctly documented in HGA
- Tracked in `Rechnung.arbeits_fahrtkosten` field

### Zahlungskategorie System
Database-driven category configuration (rewritten in 2025 to eliminate hardcoded JavaScript):

**Key Fields:**
- `field_config` (JSON) - Which fields to show/require (kostenkonto, dienstleister, rechnung, etc.)
- `allows_zero_amount` - For transfers/corrections
- `validation_rules` (JSON) - Field-level validation

**Categories:**
- Expenses: Rechnung von Dienstleister, Direktbuchung, Auslagenerstattung, etc.
- Income: Hausgeld-Zahlung, Sonderumlage, Gutschrift, etc.
- Neutral: Umbuchung, Korrektur (zero amounts allowed)

### HGA Quality Checks
AI-powered validation before sending statements to owners:

**Check Types:**
1. Data Completeness - Missing external costs, payments
2. Calculation Plausibility - MEA distribution (±10% tolerance), heating costs (€5-€25/m²/year)
3. Compliance - §35a limits, distribution consistency
4. AI Pattern Detection - Unusual patterns, year-over-year anomalies

**Implementation:** `src/Service/Hga/HgaQualityCheckService.php`

## Development Patterns

### Making Changes to HGA Calculations
1. **Never** modify calculation logic without tests
2. Always use `DistributionService` for cost distribution (single source of truth)
3. Avoid hardcoding distribution keys, MEA values, or unit counts
4. Test against historical HGA outputs (2020-2024) to prevent regressions
5. Use `./bin/backup_db.sh` before structural changes

### Adding New Payment Categories
1. Insert into `zahlungskategorie` table (or use fixtures)
2. Define `field_config` JSON (which fields to show/require)
3. Set `validation_rules` if needed
4. No JavaScript changes needed (form is data-driven)

### Adding New Invoice Parsers
1. **Simple regex parsing:** Add `parser_config` JSON to `dienstleister` table
2. **Complex logic:** Create class in `src/Service/Parser/`, extend `AbstractPdfParser`
3. **Name-based detection:** Add to `ParserFactory::createParser()` hardcoded checks
4. Set `parser_class` field in database to override default behavior

### Testing Workflow
```bash
# Before making changes
./bin/backup_db.sh "before_my_changes"

# Make changes...

# Run quality checks
docker compose exec web composer quality

# Test HGA generation
docker compose exec web php bin/console app:hga-generate 3 2024 --unit=0003

# If issues, restore DB
docker compose exec db mysql -u root -p hausmandb < var/backups/before_my_changes.sql
```

## Critical Files

### Configuration
- `config/services.yaml` - Service container configuration (HGA interface aliases)
- `config/services_hga.yaml` - HGA-specific service configuration
- `config/packages/security.yaml` - Authentication & authorization
- `.env` - Environment variables (database credentials, AI settings)

### Database Schema
- `src/Entity/*.php` - Doctrine entities (ORM models)
- All entities use camelCase in PHP, snake_case in DB
- Key entities: Weg, WegEinheit, Zahlung, Rechnung, Dienstleister, Hausgeldabrechnung

### Core Services
- `src/Service/Hga/HgaService.php` - Main orchestrator for HGA generation
- `src/Service/Hga/Calculation/DistributionService.php` - Cost distribution engine
- `src/Service/ZahlungKategorisierungService.php` - Payment categorization
- `src/Service/BankStatementParsingService.php` - CSV import

### Controllers
- `src/Controller/HgaController.php` - HGA web interface (new, clean implementation)
- `src/Controller/AbrechnungController.php` - Legacy HGA controller (still in use)
- `src/Controller/CsvImportController.php` - Bank statement import
- `src/Controller/DokumentController.php` - Document management + quality checks

### Commands
- `src/Command/HgaGenerateCommand.php` - CLI for HGA generation
- Other commands in `src/Command/` for various batch operations

### Frontend
- `assets/controllers/*.js` - Stimulus controllers
- `assets/styles/app.css` - Tailwind CSS entry point
- `webpack.config.js` - Webpack Encore configuration
- `templates/**/*.html.twig` - Twig templates

## Fixtures & Demo Data

### Fixture Groups
- `system-config` - Always load (Zahlungskategorie definitions, default cost accounts)
- `demo-data` - Development/demo only (3 WEG, 12 units, 145 payments, 6 users)

### Demo Users
All passwords: `demo123`
- `wegadmin@demo.local` - ROLE_ADMIN (full access)
- `buchhalter@demo.local` - ROLE_ACCOUNTANT (create/edit payments)
- `viewer@demo.local` - ROLE_VIEWER (read-only)

## Security & Permissions

### Role Hierarchy
```
ROLE_SUPER_ADMIN
  └─ ROLE_ADMIN (manage users, delete payments)
       └─ ROLE_ACCOUNTANT (create/edit payments)
            └─ ROLE_VIEWER (read-only)
                 └─ ROLE_USER (authenticated)
```

### Sensitive Data
- **NEVER** commit production financial data to fixtures
- Use anonymized/synthetic data in `DataFixtures/Demo/`
- System config fixtures should contain templates/structure only, not real balances
- Production secrets in `.env.local` (not in version control)

## Documentation

Comprehensive documentation in `docs/`:
- **setup_local.md** - Local development setup, development workflows, troubleshooting
- **setup_production.md** - Deployment guides (DigitalOcean)
- **core_system.md** - CSV import, payment categorization, fixtures, Rücklagenzuführung
- **technical.md** - Parser architecture, HGA migration, calculation improvements
- **ai_01_integration.md** - AI overview, configuration, privacy
- **ai_02_payment-auto-categorize.md** - AI payment categorization
- **ai_03_financial-queries.md** - Natural language financial queries
- **ai_04_invoice-data-xtraction.md** - Invoice PDF data extraction
- **ai_05_hga_quality-checks.md** - HGA quality check system

## Common Pitfalls

1. **Hardcoding MEA percentages** - Always read from database
2. **Duplicate calculation paths** - Use single service (DistributionService)
3. **Mixing old/new HGA code** - Prefer new `HgaController` + `HgaGenerateCommand`
4. **Loading demo-data in production** - Use `--group=system-config` only
5. **Forgetting to populate hga_data** - Required for quality checks (done automatically in new generators)
6. **Not running quality checks** - Always run `composer quality` before committing

## Performance Notes

- HGA generation: ~200ms per unit (improved from 500ms in old implementation)
- PDF rendering: Uses Puppeteer (headless Chrome), requires Node.js
- AI quality checks: 5-10s with Ollama (local), 3-5s with Claude API
- CSV import: Handles thousands of rows with efficient duplicate detection
