# Parser Architecture Documentation

## Overview
The invoice parsing system uses a three-tier hierarchy to determine which parser to use for each Dienstleister (service provider).

## Parser Selection Priority

### 1. `parser_class` (Highest Priority) - Database Field
**When to use:** Custom parser classes for specific service providers
**Purpose:** Override all other logic with a specific parser implementation
**Example:** `App\Service\Parser\CustomVendorParser`

```sql
UPDATE dienstleister SET parser_class = 'App\Service\Parser\CustomVendorParser' WHERE id = 5;
```

### 2. Hardcoded Parser Classes (Middle Priority) - Name-based Detection
**When to use:** Well-known service providers with stable, complex parsing requirements
**Purpose:** Dedicated parser classes with custom business logic
**Example:** MaassParser for "Maaß Gebäudemanagement GmbH"

```php
// In ParserFactory.php
if (stripos($bezeichnung, 'maaß') !== false || stripos($bezeichnung, 'maass') !== false) {
    return new MaassParser($this->projectDir);
}
```

### 3. `parser_config` (Lowest Priority) - JSON Configuration
**When to use:** Simple regex-based parsing for most service providers
**Purpose:** Flexible configuration without requiring code changes
**Example:** JSON field mappings for standard invoice formats

```json
{
    "parser_type": "regex",
    "field_mappings": {
        "rechnungsnummer": {
            "pattern": "/Invoice\\s*#?\\s*([A-Z0-9\\-]+)/i"
        },
        "betrag_mit_steuern": {
            "pattern": "/Total\\s+([0-9.,]+)\\s*EUR/i",
            "transform": "german_decimal"
        }
    }
}
```

## Decision Matrix: Database vs. Class Implementation

| Parsing Logic | Database (JSON Config) | Hardcoded Class | Reasoning |
|---------------|------------------------|-----------------|-----------|
| **Simple Regex Patterns** | ✅ **Yes** | ❌ No | Easy to modify without deployment |
| **Complex Business Logic** | ❌ No | ✅ **Yes** | Better maintainability and testing |
| **Date Transformations** | ✅ **Yes** | ✅ **Yes** | Both support transforms |
| **Multi-step Calculations** | ❌ No | ✅ **Yes** | Requires custom logic |
| **Error Handling** | ❌ Limited | ✅ **Yes** | Custom exception handling |
| **Validation Rules** | ❌ Limited | ✅ **Yes** | Complex validation logic |
| **PDF Structure Analysis** | ❌ No | ✅ **Yes** | Advanced text processing |

## Specific Examples

### Database Config Suitable For:
- **Standard invoice formats** with predictable field locations
- **Simple field extraction** using regex patterns
- **Common vendors** with consistent invoice templates
- **Quick prototyping** and testing new patterns

```json
{
    "field_mappings": {
        "rechnungsnummer": {"pattern": "/Invoice\\s*([0-9]+)/i"},
        "betrag_mit_steuern": {"pattern": "/Total\\s+([0-9.,]+)/i", "transform": "german_decimal"}
    }
}
```

### Hardcoded Class Required For:

#### 1. **Complex Date Processing** (MaassParser)
```php
// Convert "1/2024" format to last day of month
if (preg_match('/([0-9]+)\/([0-9]{4})/', $leistungszeitraum, $matches)) {
    $month = (int) $matches[1];
    $year = (int) $matches[2];
    $datumLeistung = new \DateTime("$year-$month-01");
    $datumLeistung->modify('last day of this month');
}
```

#### 2. **§35a EStG Tax Calculations** (MaassParser)
```php
// Extract actual labor costs from §35a section, fallback to net amount
if ($arbeitskostenAnteil) {
    $rechnung->setArbeitsFahrtkosten($this->parseGermanDecimal($arbeitskostenAnteil));
} elseif ($nettobetrag) {
    $rechnung->setArbeitsFahrtkosten($this->parseGermanDecimal($nettobetrag));
}
```

#### 3. **Multi-page Invoice Processing**
```php
// Handle invoices spanning multiple PDF pages
$pages = $this->splitPdfPages($pdfPath);
foreach ($pages as $page) {
    $pageData = $this->extractPageData($page);
    $totalData = array_merge($totalData, $pageData);
}
```

#### 4. **Custom Validation Logic**
```php
// Validate required fields with custom error messages
if (!$rechnungsnummer || !$gesamtbetrag) {
    throw new \Exception('Required fields missing: Rechnungsnummer or Gesamtbetrag');
}
```

## Current Implementation: Maaß Gebäudemanagement

**Status:** Uses hardcoded `MaassParser` class (priority level 1: `parser_class` field set)

**Why hardcoded class is appropriate:**
1. **Complex date parsing**: "1/2024" → last day of January 2024
2. **§35a EStG extraction**: Specific German tax regulation parsing
3. **Fallback logic**: Multiple strategies for labor cost calculation
4. **Business-specific logic**: Hausmeister service assumptions

**Database config would be insufficient because:**
- JSON regex cannot handle date calculations
- Cannot implement fallback strategies
- Cannot validate business rules
- Cannot handle multi-step transformations

## Recommendations

### Use Database Config For:
- **New/unknown vendors** (quick setup)
- **Simple invoice formats** (predictable layouts)
- **Prototyping** (testing patterns before coding)
- **Maintenance** (pattern updates without deployment)

### Use Hardcoded Classes For:
- **Established vendors** with complex requirements
- **High-volume processing** (performance critical)
- **Business-critical parsing** (requires testing)
- **Complex transformations** beyond simple regex

### Migration Path:
1. **Start with database config** for new vendors
2. **Monitor parsing success rate** and edge cases
3. **Create hardcoded class** when complexity grows
4. **Set `parser_class` field** to override database config

## Code Quality Benefits

### Database Config:
- ✅ **Rapid iteration** without code deployment
- ✅ **Non-technical users** can modify patterns
- ✅ **A/B testing** different patterns
- ❌ **Limited validation** of configuration correctness

### Hardcoded Classes:
- ✅ **Unit testing** of parsing logic
- ✅ **IDE support** (autocomplete, refactoring)
- ✅ **Type safety** and error handling
- ✅ **Performance optimization** opportunities
- ❌ **Requires deployment** for changes

## Best Practices

### Avoid Configuration Confusion
**Rule:** Only one parsing method should be configured per Dienstleister
- ✅ **`parser_class` set, `parser_config` NULL** - Uses hardcoded class
- ✅ **`parser_class` NULL, `parser_config` set** - Uses JSON configuration  
- ❌ **Both set** - Confusing (parser_class wins, but parser_config is ignored)

```sql
-- Clean up confusion: Clear parser_config when parser_class is used
UPDATE dienstleister SET parser_config = NULL WHERE parser_class IS NOT NULL;
```

### Migration Strategy
1. **New vendor** → Start with `parser_config` JSON
2. **Growing complexity** → Create hardcoded class, set `parser_class`, clear `parser_config`
3. **Special requirements** → Custom parser class with `parser_class` override

## Conclusion

The three-tier architecture provides the right balance:
- **Database config** for flexibility and rapid development
- **Hardcoded classes** for reliability and complex business logic
- **Priority system** allows gradual migration from config to code as requirements mature
- **Clear separation** prevents confusion about which parser is actually used

The current Maaß implementation correctly uses a hardcoded class due to its complex §35a tax processing requirements that cannot be handled by simple regex patterns.