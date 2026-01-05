# Core System Documentation

**Document Version**: 2.0
**Date**: 2025-12-28
**Status**: Production

---

## Table of Contents

1. [CSV Import System](#csv-import-system)
2. [Payment Categorization](#payment-categorization)
3. [Zahlungskategorie System](#zahlungskategorie-system)
4. [Authentication & Authorization](#authentication--authorization)
5. [Fixture Strategy](#fixture-strategy)
6. [Rücklagenzuführung (Reserve Allocation)](#rücklagenzuführung-reserve-allocation)
7. [Document Storage](#document-storage)

---

## CSV Import System

### Overview

The CSV import system handles bank statement imports (Kontoauszüge) in SEPA format, with intelligent categorization and duplicate detection.

**Supported Formats:**
- Sparkasse SEPA CSV (primary)
- Generic CSV with mapping

### Import Flow

```
1. Upload CSV File
   ↓
2. Parse & Validate
   ↓
3. Duplicate Detection (3-stage fallback)
   ↓
4. Auto-Categorization (Pattern matching + AI)
   ↓
5. Manual Review & Adjustment
   ↓
6. Save to Database
```

### Duplicate Detection Strategy

**3-Level Fallback System:**

#### Level 1: SEPA Transaction ID (Primary)
```php
// Check if reference_id (Mandatsreferenz) already exists
$existing = $em->getRepository(Zahlung::class)
    ->findOneBy(['referenceId' => $sepaReferenceId]);
```

**When it works:** Modern SEPA payments with unique reference IDs
**Coverage:** ~80% of payments

#### Level 2: Amount + Date + Partner (Secondary)
```php
// Match by exact combination
$existing = $em->getRepository(Zahlung::class)->findOneBy([
    'betrag' => $betrag,
    'buchungsdatum' => $buchungsdatum,
    'buchungspartner' => $buchungspartner
]);
```

**When it works:** Duplicate CSV imports, manual entry errors
**Coverage:** ~15% of remaining payments

#### Level 3: Fuzzy Matching (Fallback)
```php
// Similar amount within ±0.01 EUR, same date, similar partner name
$similar = $this->findSimilarPayment($betrag, $buchungsdatum, $buchungspartner);
```

**When it works:** Name variations ("SWM" vs "Stadtwerke München GmbH")
**Coverage:** ~5% edge cases

### Auto-Categorization

**Pattern-Based Categorization:**

```yaml
patterns:
  - pattern: "hausgeld|vorauszahlung"
    category: "Hausgeld-Zahlung"
    confidence: 0.95

  - pattern: "abschlag|vorauszahlung"
    category: "Rechnung von Dienstleister"
    confidence: 0.70

  - pattern: "stadtwerke|swm"
    category: "Rechnung von Dienstleister"
    confidence: 0.85
```

**Success Rate:** ~70% with pattern matching alone (95%+ with AI enhancement)

### CSV Column Mapping

**Sparkasse SEPA Format:**

| CSV Column | Database Field | Transformation |
|------------|----------------|----------------|
| Buchungstag | `buchungsdatum` | Date (DD.MM.YYYY → YYYY-MM-DD) |
| Wertstellung | `wertstellung` | Date |
| Umsatzart | `umsatzart` | String |
| Buchungstext | `verwendungszweck` | String |
| Betrag | `betrag` | Decimal (1.234,56 → 1234.56) |
| Währung | - | Validation (must be EUR) |
| Auftraggeber / Begünstigter | `buchungspartner` | String |
| Kontonummer | - | Ignored |
| BLZ | - | Ignored |
| Mandatsreferenz | `referenceId` | String (duplicate detection) |

### Import Commands

```bash
# Import CSV file
docker compose exec web php bin/console app:import-csv /path/to/kontoauszug.csv

# Import with WEG specification
docker compose exec web php bin/console app:import-csv kontoauszug.csv --weg=3

# Dry run (validate only, don't save)
docker compose exec web php bin/console app:import-csv kontoauszug.csv --dry-run
```

### Error Handling

**Common Issues:**

1. **Invalid date format**
   - Error: "Could not parse date: '32.13.2024'"
   - Fix: Ensure DD.MM.YYYY format

2. **Non-EUR currency**
   - Error: "Unsupported currency: USD"
   - Fix: Only EUR is supported

3. **Missing required fields**
   - Error: "Missing column: Buchungstag"
   - Fix: Ensure CSV has all required columns

4. **Duplicate payments**
   - Warning: "Skipping duplicate payment (Reference ID: DE12345)"
   - Action: No action needed, duplicate is prevented

---

## Payment Categorization

### Overview

Payments (Zahlungen) are categorized to assign them to cost accounts (Kostenkonten) for proper financial reporting and HGA generation.

### Kategorisierung Service

**Location:** `src/Service/ZahlungKategorisierungService.php`

**Main Methods:**

```php
// Auto-categorize a single payment
public function kategorisiereZahlung(Zahlung $zahlung): void

// Suggest category based on text patterns
public function suggestCategory(string $verwendungszweck, string $partner): ?array

// Learn from user corrections
public function recordCorrection(Zahlung $zahlung, string $oldCategory, string $newCategory): void
```

### Pattern Configuration

**Database-Driven Patterns:**

```sql
-- Pattern definition table
CREATE TABLE zahlung_pattern (
    id INT PRIMARY KEY,
    pattern VARCHAR(255),
    kategorie_id INT,
    priority INT,
    confidence DECIMAL(3,2),
    is_active BOOLEAN
);

-- Example patterns
INSERT INTO zahlung_pattern VALUES
(1, 'hausgeld|vorauszahlung', 6, 1, 0.95, 1),  -- Hausgeld-Zahlung
(2, 'stadtwerke.*gas|swm.*gas', 1, 2, 0.90, 1), -- Gas Kostenkonto
(3, 'müllabfuhr|entsorgung', 1, 3, 0.85, 1);    -- Müllentsorgung
```

### Categorization Algorithm

```
1. Check exact partner matches (Dienstleister linkage)
   ↓ Not found
2. Apply pattern matching (sorted by priority)
   ↓ Low confidence (<0.7)
3. AI analysis (if enabled)
   ↓ Still uncertain
4. Flag for manual review
```

### Admin Interface

**Route:** `/zahlung/{id}/kategorisieren`

**Features:**
- View suggested category with confidence score
- Accept/reject suggestion
- Manual category selection
- Historical patterns display
- Similar payments reference

---

## Zahlungskategorie System

### Database-Driven Configuration

The system was rewritten in 2025 to be fully database-driven instead of hardcoded JavaScript logic.

**Schema Enhancement:**

```sql
ALTER TABLE zahlungskategorie ADD
  field_config JSON DEFAULT NULL,
  validation_rules JSON DEFAULT NULL,
  help_text LONGTEXT DEFAULT NULL,
  sort_order INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  allows_zero_amount TINYINT(1) NOT NULL DEFAULT 0;
```

### Field Configuration System

**Example Configuration:**

```json
{
  "show": ["kostenkonto", "dienstleister", "rechnung", "mehrwertsteuer"],
  "required": ["kostenkonto", "dienstleister"],
  "auto_set": {}
}
```

**Available Fields:**
- `kostenkonto` - Cost account selection
- `dienstleister` - Service provider
- `rechnung` - Invoice reference
- `mehrwertsteuer` - VAT amount
- `eigentuemer` - Owner (for owner payments)
- `kostenkontoTo` - Target cost account (for transfers)

### Categories

**EXPENSES (Negative amounts):**
1. Rechnung von Dienstleister - Service provider invoices
2. Direktbuchung Kostenkonto - Direct cost account entries
3. Auslagenerstattung Eigentümer - Owner expense reimbursements
4. Rückzahlung an Eigentümer - Owner refunds
5. Bankgebühren - Bank fees

**INCOME (Positive amounts):**
6. Hausgeld-Zahlung - Owner payments
7. Sonderumlage - Special assessments
8. Gutschrift Dienstleister - Service provider credits
9. Zinserträge - Interest income
10. Sonstige Einnahme - Other income

**NEUTRAL (Zero amounts allowed):**
11. Umbuchung - Internal transfers
12. Korrektur - Corrections

### JavaScript Integration

**Data Flow:**

```
PHP (Symfony Form)
  → Generates HTML with data attributes
    → JavaScript reads configuration
      → Dynamic field visibility/validation
```

**Example Template:**

```twig
<form data-controller="zahlung-form">
  <select id="hauptkategorie">
    <option
      value="1"
      data-field-config='{"show":["kostenkonto","dienstleister"]}'
      data-help-text="Für Rechnungen von externen Dienstleistern">
      Rechnung von Dienstleister
    </option>
  </select>
</form>
```

---

## Authentication & Authorization

### User Roles

**Role Hierarchy:**

```
ROLE_SUPER_ADMIN
  └─ ROLE_ADMIN
       └─ ROLE_ACCOUNTANT
            └─ ROLE_VIEWER
                 └─ ROLE_USER (authenticated)
                      └─ IS_AUTHENTICATED_ANONYMOUSLY
```

### Permissions Matrix

| Feature | VIEWER | ACCOUNTANT | ADMIN | SUPER_ADMIN |
|---------|--------|------------|-------|-------------|
| **View WEG data** | ✅ | ✅ | ✅ | ✅ |
| **View payments** | ✅ | ✅ | ✅ | ✅ |
| **Create payments** | ❌ | ✅ | ✅ | ✅ |
| **Edit payments** | ❌ | ✅ | ✅ | ✅ |
| **Delete payments** | ❌ | ❌ | ✅ | ✅ |
| **Manage users** | ❌ | ❌ | ✅ | ✅ |
| **System config** | ❌ | ❌ | ❌ | ✅ |

### Security Configuration

**Location:** `config/packages/security.yaml`

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: app_login
                check_path: app_login
            logout:
                path: app_logout
                target: app_login
```

### Demo Users

**Available in local/demo environments:**

```yaml
users:
  - email: wegadmin@demo.local
    role: ROLE_ADMIN
    password: demo123

  - email: buchhalter@demo.local
    role: ROLE_ACCOUNTANT
    password: demo123

  - email: viewer@demo.local
    role: ROLE_VIEWER
    password: demo123
```

---

## Fixture Strategy

### Fixture Groups

**1. System Configuration (Always Load)**
```bash
docker compose exec web php bin/console doctrine:fixtures:load --group=system-config
```

**Includes:**
- Zahlungskategorie definitions
- Default cost accounts (Kostenkonten)
- System settings

**2. Demo Data (Development/Demo Only)**
```bash
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data
```

**Includes:**
- 3 WEG entities (Musterhausen, Berlin, Hamburg)
- 12 WegEinheit (property units)
- 6 demo users
- 145 Zahlungen (payments)
- 8 Dienstleister (service providers)
- 22 Rechnungen (invoices)

### Fixture Order

**Load Order (via DependentFixtureInterface):**

```
1. UserFixtures
2. WegFixtures
3. WegEinheitFixtures
4. DienstleisterFixtures
5. RechnungFixtures
6. ZahlungFixtures
7. ZahlungskategorieFixtures
```

### Production Setup

**DO NOT load demo-data in production:**

```bash
# Production fixture load (system config only)
docker compose exec web php bin/console doctrine:fixtures:load \
  --group=system-config \
  --no-interaction
```

### Automated Setup Script

**Location:** `setup.sh`

```bash
#!/bin/bash
set -e

# Start Docker containers
docker compose up -d

# Wait for MySQL
sleep 10

# Install dependencies
docker compose exec web composer install

# Create database and schema
docker compose exec web php bin/console doctrine:database:create --if-not-exists
docker compose exec web php bin/console doctrine:schema:update --force

# Load demo data
docker compose exec web php bin/console doctrine:fixtures:load --group=demo-data --no-interaction

# Clear cache
docker compose exec web php bin/console cache:clear
```

---

## Rücklagenzuführung (Reserve Allocation)

### Overview

German WEG law (§21 WEG) requires property communities to maintain reserves (Rücklagen) for future maintenance and repairs.

### Calculation Method

**Annual Reserve Allocation per Unit:**

```
Rücklagenzuführung = (
    Total WEG Contributions
    - Operating Costs (Bewirtschaftungskosten)
    - External Costs (Heizung/Wasser)
) × MEA Percentage
```

### Business Logic

**Cost Classification:**

| Cost Type | Kategorisierung | Included in Reserve? |
|-----------|----------------|----------------------|
| **Operating Costs** | UMLAGEFAEHIG_* | ❌ No (deducted) |
| **Heating** | EXTERN_HEIZKOSTEN | ❌ No (deducted) |
| **Water** | EXTERN_WASSERKOSTEN | ❌ No (deducted) |
| **Management Fee** | NICHT_UMLAGEFAEHIG | ❌ No (deducted) |
| **Reserve** | NICHT_UMLAGEFAEHIG_RUECKLAGE | ✅ Yes (calculated) |

**Example Calculation:**

```
WEG Total Annual Contributions:    10.000,00 EUR
  - Operating Costs (Strom, etc.): -2.500,00 EUR
  - External Heating:               -3.000,00 EUR
  - External Water:                   -500,00 EUR
  - Management Fee:                 -1.200,00 EUR
  = Reserve Allocation:              2.800,00 EUR

Unit MEA: 29% (0.29)
Unit Reserve: 2.800,00 × 0.29 = 812,00 EUR
```

### Implementation

**Location:** `src/Service/CalculationService.php`

```php
public function calculateRuecklagenzufuehrung(
    Weg $weg,
    WegEinheit $einheit,
    int $year
): float {
    // Get total contributions
    $totalContributions = $this->getTotalHausgeld($weg, $year);

    // Deduct operating costs
    $operatingCosts = $this->getOperatingCosts($weg, $year);

    // Deduct external costs
    $externalCosts = $this->getExternalCosts($weg, $year);

    // Calculate reserve pool
    $reservePool = $totalContributions - $operatingCosts - $externalCosts;

    // Allocate by MEA
    return $reservePool * $einheit->getMiteigentumsanteile();
}
```

### HGA Display

**In Hausgeldabrechnung (Annual Statement):**

```
RÜCKLAGENZUFÜHRUNG:
Gesamteinnahmen:                  10.000,00 EUR
Bewirtschaftungskosten:           -2.500,00 EUR
Externe Heizkosten:               -3.000,00 EUR
Externe Wasserkosten:               -500,00 EUR
Verwaltervergütung:               -1.200,00 EUR
─────────────────────────────────────────────
Rücklagenzuführung (gesamt):       2.800,00 EUR

Ihr Anteil (29%):                    812,00 EUR
```

---

## Document Storage

### Current Implementation

**Local File Storage** (Simple, effective)

```
/data/dokumente/
├── rechnungen/
│   ├── 2024/
│   │   ├── rechnung_123_wartung.pdf
│   │   └── rechnung_124_reinigung.pdf
├── vertraege/
├── protokolle/
└── uploads/
```

### Database Schema

```sql
CREATE TABLE dokument (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dateiname VARCHAR(255) NOT NULL,
    dateipfad VARCHAR(500) NOT NULL,
    dateityp VARCHAR(50),
    dategroesse INT,
    upload_datum DATETIME NOT NULL,
    kategorie VARCHAR(100),
    beschreibung TEXT,
    rechnung_id INT NULL,
    dienstleister_id INT NULL,
    FOREIGN KEY (rechnung_id) REFERENCES rechnung(id),
    FOREIGN KEY (dienstleister_id) REFERENCES dienstleister(id)
);
```

### Entity

```php
class Dokument
{
    private ?int $id;
    private string $dateiname;
    private string $dateipfad;
    private string $dateityp;
    private int $dategroesse;
    private \DateTime $uploadDatum;
    private string $kategorie;
    private ?string $beschreibung;
    private ?Rechnung $rechnung = null;
    private ?Dienstleister $dienstleister = null;
}
```

### Document Categories

- `rechnungen` - Invoices
- `vertraege` - Contracts
- `protokolle` - Meeting minutes
- `eigentuemer` - Owner documents
- `umlaufbeschluss` - WEG resolutions
- `jahresabschluss` - Annual financial statements
- `sonstiges` - Other documents

### Upload Flow

```
1. User uploads file via form
   ↓
2. Validate file type/size
   ↓
3. Generate unique filename
   ↓
4. Move to storage directory
   ↓
5. Create Dokument entity
   ↓
6. Link to Rechnung/Dienstleister (if applicable)
   ↓
7. Save metadata to database
```

### Future: MinIO Migration (Deprecated)

**Status:** MinIO implementation was planned but **deprecated** in favor of simpler local storage.

**Reason:** For small WEG management, local file storage is sufficient and simpler to maintain. MinIO adds unnecessary complexity for the current use case.

---

## Related Documentation

- [AI Integration](ai_integration.md) - AI-powered payment categorization
- [Local Setup](setup_local.md) - Docker development environment
- [Production Deployment](setup_production.md) - Deployment options

---

**Document Status**: Production
**Last Updated**: 2025-12-28
**Next Review**: 2026-01-28
