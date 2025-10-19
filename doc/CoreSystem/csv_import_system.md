# CSV Import System - Bank Statement Processing

## Overview

The CSV Import System provides automated import and processing of bank statements (Kontoauszüge) from German banks, specifically designed for the Kreissparkasse/Sparkasse SEPA CSV format. The system parses transactions, automatically categorizes payments, detects duplicates, and creates new service providers (Dienstleister) as needed.

**Feature Status:** ✅ Production-ready
**Added:** October 2025
**Location:** `/csv-import` route

---

## Key Features

### 1. **Intelligent CSV Parsing**
- Supports Kreissparkasse/Sparkasse SEPA CSV format with 17 columns
- Automatic encoding detection (UTF-8, ISO-8859-1, Windows-1252)
- Extracts full transaction details including SEPA data (IBAN, BIC, Mandate Reference, Creditor ID)

### 2. **Automatic Payment Categorization**
- Pattern-based category detection (Hausgeld, bank fees, service invoices, etc.)
- Automatic Kostenkonto assignment based on business rules
- Fuzzy name matching for property owner (Eigentümer) assignment
- See [Zahlungskategorie System](zahlungskategorie-system.md) for categorization logic

### 3. **Advanced Duplicate Detection**
Uses three-level fallback detection:
1. **Exact match**: date + amount + bezeichnung
2. **Dienstleister match**: date + amount + dienstleister (for expense payments)
3. **Eigentümer match**: date + amount + eigentuemer with fuzzy name matching (for income payments)

### 4. **Interactive Preview**
Before importing, users see:
- Transaction summary (count, date range, income/expenses)
- List of new service providers to be created
- Full transaction table with duplicate status
- Categorization preview

### 5. **Flexible Import Options**
- Import all transactions (including duplicates)
- Import only new transactions (skip duplicates)
- Optionally create new service providers automatically

---

## Architecture

### Components

#### **Controller: `CsvImportController`**
Location: `src/Controller/CsvImportController.php`

Routes:
- `GET /csv-import` - Upload form
- `POST /csv-import/upload` - Parse CSV and return preview (AJAX)
- `POST /csv-import/import` - Execute import with user-selected options

#### **Service: `BankStatementParsingService`**
Location: `src/Service/BankStatementParsingService.php`

Key methods:
```php
public function parseCSVPreview(Dokument $dokument): array
// Returns preview data with statistics and transaction list

public function importTransactions(Dokument $dokument, array $options): array
// Executes the import and returns results

private function isDuplicate(array $transaction): bool
// Three-level duplicate detection (exact, dienstleister, eigentuemer)

private function findEigentuemer(string $partnerName): ?WegEinheit
// Fuzzy name matching for property owners (60% threshold)
```

#### **Service: `ZahlungKategorisierungService`**
Location: `src/Service/ZahlungKategorisierungService.php`

Provides auto-categorization logic:
```php
public function kategorisieren(Zahlung $zahlung): bool
// Attempts to auto-categorize a payment
// Returns true if both kategorie AND kostenkonto were assigned
```

#### **Frontend: Stimulus Controller**
Location: `assets/controllers/csv-import_controller.js`

Handles:
- File selection UI feedback
- AJAX upload with progress indication
- Preview rendering with statistics cards
- Form submission for import

---

## CSV Format Specification

### Expected Format
**Sparkasse SEPA CSV** with semicolon delimiter (`;`)

### Column Structure (17 columns)
```
0:  Auftragskonto (Account number)
1:  Buchungstag (Booking date) - DD.MM.YY or DD.MM.YYYY
2:  Valutadatum (Value date)
3:  Buchungstext (Booking type: GUTSCHRIFT, LASTSCHRIFT, etc.)
4:  Verwendungszweck (Purpose/Reference)
5:  Glaeubiger ID (Creditor ID for SEPA)
6:  Mandatsreferenz (Mandate reference)
7:  Kundenreferenz (Customer reference)
8:  Sammlerreferenz (Collector reference)
9:  Lastschrift Ursprungsbetrag (Original debit amount)
10: Auslagenersatz Ruecklastschrift (Return debit fee)
11: Beguenstigter/Zahlungspflichtiger (Partner name)
12: Kontonummer/IBAN (Partner account)
13: BIC (SWIFT/BIC code)
14: Betrag (Amount) - German format (1.234,56)
15: Waehrung (Currency: EUR)
16: Info (Additional info)
```

### Example CSV Row
```csv
1084571213;01.10.25;01.10.25;GUTSCHRIFT;GUTSCHR. UEBERW. DAUERAUFTR;;;;;;;Max Mustermann;DE89XXXXXXXXXXXXXXXX;BYLADEM1XXX;285,00;EUR;
```

---

## Auto-Categorization Logic

### Pattern Matching Priority

The system checks patterns in this order (see `ZahlungKategorisierungService::findKategorie()`):

1. **Hausmeister** (check BEFORE Hausgeld to avoid false matches)
   - Keywords: `hausmeister` in bezeichnung or dienstleisterArt
   - Kategorie: "Rechnung von Dienstleister"
   - Kostenkonto: 040100 (Hausmeisterkosten)

2. **Hausgeld/Wohngeld** (Income from property owners)
   - Keywords: `wohngeld`, `hausgeld`, `nachzahlung`, `weg ballauf`
   - OR: dienstleisterArt = "eigentümer"
   - Kategorie: "Hausgeld-Zahlung"
   - Kostenkonto: 099900 (Wohngeld)
   - **Auto-assigns Eigentümer** using fuzzy name matching

3. **Bank Interest**
   - Keywords: `habenzins`
   - Kategorie: "Zinserträge"

4. **Bank Account Closure**
   - Keywords: `kontoauflösung`, `ausgleich des kontosaldos`
   - Kategorie: "Sonstige Einnahme"
   - Kostenkonto: 049100 (Kontoübertragung)

5. **Refunds/Credits**
   - Keywords: `gutschr`, `rückerstattung`
   - Kategorie: "Gutschrift Dienstleister"

6. **Bank Fees**
   - Keywords: `pauschalen`, `entgelte`, `porto`, `kapitalertragsteuer`
   - Kategorie: "Bankgebühren"
   - Kostenkonto: 049000 (Nebenkosten Geldverkehr)

7. **Property Management Fees**
   - Keywords: `hausverwaltung` in bezeichnung or dienstleisterArt
   - Kategorie: "Rechnung von Dienstleister"
   - Kostenkonto: 050000 (Verwaltervergütung)

8. **Utility Bills** (checked in order: Heizung → Abwasser → Water → Strom → Müll)
   - **Heating/Gas**: `heizung`, `gas` → Kostenkonto 006000
   - **Sewage**: `abwasser`, `stadtentwasserung`, `kanalgebühr` → Kostenkonto 042200
   - **Water**: `wasser` (after gas/sewage check) → Kostenkonto 042000
   - **Electricity**: `strom`, dienstleister "SWM" → Kostenkonto 043000
   - **Waste**: `abfall`, `müll`, dienstleister "AWM" → Kostenkonto 043200

9. **Other Services**
   - **Insurance**: `versicherung` → Kostenkonto 013000
   - **Fire Protection**: `brandschutz` → Kostenkonto 044000
   - **Sun Protection**: `sonnenschutz` → Kostenkonto 045100 (Instandhaltung)
   - **Maintenance**: Generic service invoices → Kostenkonto 045100

### Fuzzy Name Matching for Eigentümer

**Algorithm:** Word-based similarity with minimum word count scoring

```php
// Example: "Max Mustermann" vs "Erika Beispiel & Max Mustermann"
// Traditional: 2 matched words / 5 total words = 40% (fails at 60% threshold)
// Improved: 2 matched words / 2 minimum words = 100% (passes!)

$minWords = min(count($words1), count($words2));
$score = $matchCount / $minWords;

// Threshold: 60% (0.6)
// Min word length: 3 characters (skip short words like "de", "von")
```

This approach handles:
- Single owner names matching joint owner names
- Name variations (with/without middle names)
- Different word orders

---

## Duplicate Detection

### Three-Level Fallback System

#### Level 1: Exact Match
```php
// date + amount + bezeichnung
WHERE datum = :date
  AND betrag = :amount
  AND bezeichnung = :purpose
```

**Use case:** Standard duplicate check for regular transactions

#### Level 2: Dienstleister Match
```php
// date + amount + dienstleister (for expenses)
WHERE datum = :date
  AND betrag = :amount
  AND dienstleister = :dienstleister
```

**Use case:** Generic booking texts like "LASTSCHRIFT" where bezeichnung varies but transaction is the same

#### Level 3: Eigentümer Match
```php
// date + amount + eigentuemer (for income)
WHERE datum = :date
  AND betrag = :amount
  AND eigentuemer = :eigentuemer
```

**Use case:** Owner payments with generic text like "GUTSCHR. UEBERW. DAUERAUFTR"

### Critical Implementation Details

1. **Amount Formatting**: Must use `number_format($amount, 2, '.', '')` to match database DECIMAL(10,2) storage
2. **Date Normalization**: Set time to midnight (`$date->setTime(0, 0, 0)`) for DATE column comparison
3. **Fuzzy Matching**: Use same algorithm as auto-categorization (60% threshold, min-word scoring)

---

## User Interface

### Import Flow

1. **Upload Page** (`/csv-import`)
   - Drag-and-drop or file picker
   - Accepts .csv files only
   - Shows file info after selection

2. **Preview (AJAX Response)**
   - **Statistics Cards:**
     - Total transactions with date range
     - Income count and sum
     - Expense count and sum
     - New providers count and duplicate count

   - **New Providers List:**
     - Shows all new Dienstleister to be created
     - Helps user understand what will be added

   - **Transaction Table:**
     - Date, Partner, Purpose (truncated), Amount, Status
     - Color-coded: Green = Neu, Yellow = Duplikat
     - Scrollable with max height

3. **Import Options**
   - Radio: Import all transactions / Import only new
   - Checkbox: Create new providers automatically
   - Cancel or Confirm buttons

4. **Import Results**
   - Flash messages with statistics
   - Success: "X Transaktionen importiert, Y automatisch kategorisiert"
   - Warning: "Z Transaktionen benötigen manuelle Kategorisierung"
   - Redirects to `/zahlung` list

### Zahlung List Enhancements

New features on `/zahlung`:

1. **"Kontoauszug importieren" Button** (Orange)
   - Prominent placement next to "Auto-Kategorisieren"
   - Direct link to CSV import

2. **"Auto-Kategorisieren" Button** (Green)
   - Bulk categorizes all uncategorized payments
   - Confirmation dialog before execution
   - Shows results in flash messages

3. **"Nur unkategorisierte" Filter**
   - Checkbox to show only payments missing kategorie OR kostenkonto
   - Uses `ZahlungRepository::findUncategorized()`

4. **"Zahler/Empfänger" Column**
   - Shows Eigentümer for income (betrag > 0)
   - Shows Dienstleister for expenses (betrag < 0)
   - Helps identify payment parties at a glance

---

## Database Impact

### New Repositories Methods

**`ZahlungRepository::findUncategorized()`**
```php
WHERE hauptkategorie IS NULL OR kostenkonto IS NULL
ORDER BY datum DESC
```

### Created Entities

- **Zahlung** - Payment records from CSV
- **Dienstleister** - New service providers (if create_providers = true)

### Updated Relationships

- Zahlung → Dienstleister (many-to-one)
- Zahlung → WegEinheit (many-to-one, for eigentuemer)
- Zahlung → Zahlungskategorie (many-to-one, for hauptkategorie)
- Zahlung → Kostenkonto (many-to-one)

---

## Configuration

### Services Configuration

**`config/services.yaml`:**
```yaml
App\Service\BankStatementParsingService:
    arguments:
        $projectDir: '%kernel.project_dir%'

App\Controller\CsvImportController:
    arguments:
        $projectDir: '%kernel.project_dir%'
```

### File Storage

**Location:** `data/dokumente/bank-statements/`

**Naming Convention:** `{slugified-original-name}-{uniqid}.csv`

**Example:** `kontoauszug-2025-10-676156a2b8f45.csv`

---

## Security Considerations

1. **CSRF Protection:**
   - Upload form: `csrf_token('csv_upload')`
   - Import form: `csrf_token('csv_import')`
   - Bulk kategorisieren: `csrf_token('bulk_kategorisieren')`

2. **File Validation:**
   - MIME type check: `text/csv`, `text/plain`, `application/csv`
   - Extension check: `.csv` only
   - No execution permissions on uploaded files

3. **Input Sanitization:**
   - Filename slugging to prevent directory traversal
   - Encoding detection and conversion to UTF-8
   - HTML escaping in preview output

4. **Access Control:**
   - Requires authenticated user (uses `AbstractController`)
   - Should be restricted to VERWALTER or SUPER_ADMIN roles (TODO: add role check)

---

## Testing Recommendations

### Manual Testing Checklist

- [ ] Upload valid Sparkasse CSV (17 columns)
- [ ] Upload CSV with encoding issues (ISO-8859-1, Windows-1252)
- [ ] Upload CSV with umlauts (ä, ö, ü) in partner names
- [ ] Upload same CSV twice (verify duplicate detection)
- [ ] Upload CSV with new Dienstleister names
- [ ] Upload CSV with existing owner payments (verify eigentuemer matching)
- [ ] Test "Import all" vs "Import only new"
- [ ] Test "Create providers" checkbox behavior
- [ ] Verify auto-categorization accuracy
- [ ] Test bulk kategorisieren button on uncategorized payments

### Edge Cases

1. **Empty CSV or header only** - Should handle gracefully
2. **Malformed rows (< 17 columns)** - Should skip incomplete rows
3. **Invalid date format** - Should throw exception with clear message
4. **Invalid amount format** - Should handle German decimal format (1.234,56)
5. **Missing partner name** - Should assign to default bank (Kreissparkasse MSE)
6. **Duplicate with different dienstleister** - Should detect via eigentuemer match

---

## Future Enhancements

### Planned Improvements

1. **Multi-Bank Support**
   - Add parser for other German banks (Volksbank, Deutsche Bank, etc.)
   - Factory pattern for bank-specific CSV parsers

2. **Import History**
   - Track which CSV files have been imported
   - Prevent re-import of same file
   - Show import audit log

3. **Enhanced Auto-Categorization**
   - Machine learning for pattern detection
   - User feedback loop (correct mis-categorizations)
   - Export/import categorization rules

4. **Batch Processing**
   - Upload multiple CSV files at once
   - Background processing for large files
   - Email notification on completion

5. **Validation Rules**
   - Configurable min/max amounts
   - Date range restrictions
   - Required field validation

### Known Limitations

1. **Single Bank Format:** Currently only supports Kreissparkasse/Sparkasse SEPA CSV
2. **No Import Rollback:** Once imported, must manually delete payments (TODO: add transaction rollback)
3. **Limited Error Recovery:** Parsing errors abort entire import (TODO: add partial import)
4. **Performance:** Large CSV files (>1000 rows) may be slow (TODO: add batch processing)

---

## Code Quality

### PHPStan Compliance

Current status: **Level 5** (with baseline)

Known issues:
- Missing iterable type hints for `array` parameters (planned fix)
- `$zahlungskategorieRepository` property only written (acceptable for DI)

### Coding Standards

- **PSR-12** compliant (verified with `composer cs-fix`)
- **Strict typing:** `declare(strict_types=1)` in all files
- **Dependency Injection:** All services properly injected
- **Single Responsibility:** Clear separation of parsing, categorization, and import logic

---

## Related Documentation

- [Zahlungskategorie System](zahlungskategorie-system.md) - Payment category configuration
- [Database Schema](../TechnicalArchitecture/DATABASE_SCHEMA.md) - Entity relationships
- [Authentication System](auth_system_concept.md) - User roles and permissions

---

## Support & Troubleshooting

### Common Issues

**Issue:** "Ungültiges Datumsformat"
**Solution:** Ensure CSV uses DD.MM.YY or DD.MM.YYYY format

**Issue:** Duplicate not detected
**Solution:** Check date normalization and amount formatting in `isDuplicate()`

**Issue:** Auto-categorization fails
**Solution:** Review pattern matching order in `ZahlungKategorisierungService`

**Issue:** Eigentümer not assigned
**Solution:** Check fuzzy matching threshold (60%) and WegEinheit name spelling

### Debug Mode

Enable error logging:
```php
// In BankStatementParsingService.php
error_log('Transaction: ' . json_encode($transaction));
error_log('Duplicate check: partner=' . $transaction['partner']);
```

Check logs:
```bash
docker logs hausman-web-1 | grep "Duplicate check"
tail -f var/log/dev.log
```

---

**Last Updated:** October 5, 2025
**Maintained By:** homeadmin24 Development Team
