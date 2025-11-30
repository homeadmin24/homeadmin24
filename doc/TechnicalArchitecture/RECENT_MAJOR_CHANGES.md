# Recent Major Changes (2025)

This document tracks significant architectural and data model changes made to the homeadmin24 project. For historical context and complete technical documentation, see [DEVELOPMENT.md](../../DEVELOPMENT.md).

---

## 🔧 Database Field Separation: `stimme` vs `hebeanlage` (July 26, 2025)

### **Problem Solved**
The `weg_einheit.stimme` field was incorrectly storing Hebeanlage (elevator pump) cost distribution fractions instead of voting rights data. This violated separation of concerns and prevented proper WEG voting functionality.

### **Changes Made**
- **New Field**: Added `weg_einheit.hebeanlage` VARCHAR(10) for cost distribution fractions
- **Data Migration**: Moved Hebeanlage data (2/6, 1/6) from `stimme` to `hebeanlage` field
- **Service Update**: `DistributionCalculationService` now uses database-driven fractions instead of hardcoded unit numbers
- **Code Quality**: Removed hardcoded logic like `'0001' === $unitNumber || '0002' === $unitNumber`

### **Migration Details**
```sql
-- Migration: Version20250726120000
ALTER TABLE weg_einheit ADD hebeanlage VARCHAR(10) DEFAULT NULL;
UPDATE weg_einheit SET hebeanlage = stimme WHERE stimme IN ("1/6", "2/6");
UPDATE weg_einheit SET stimme = NULL WHERE stimme IN ("1/6", "2/6");
```

### **Impact**
- ✅ `stimme` field now available for proper voting rights
- ✅ Hebeanlage distribution configurable via database
- ✅ No hardcoded unit numbers in code
- ✅ All existing calculations remain accurate

---

## 🏗️ Unit Monthly Payment System Refactor (July 26, 2025)

### **Problem Solved**
The `weg_einheit_konfiguration` table had confusing naming, complex structure, and hardcoded fallback values that made it difficult to maintain and understand.

### **Changes Made**
- **Table Rename**: `weg_einheit_konfiguration` → `unit_monthly_payment` (clearer English naming)
- **Entity Rename**: `WegEinheitKonfiguration` → `WegEinheitVorauszahlung` (proper German terminology)
- **Simplified Structure**: Removed unused `zusaetzliche_konfiguration` JSON field
- **Dynamic Calculation**: Removed hardcoded fallback values, now requires proper database configuration

### **Migration Details**
```sql
-- Migration: Version20250726122000
CREATE TABLE unit_monthly_payment (...);
INSERT INTO unit_monthly_payment (...) SELECT ... FROM weg_einheit_konfiguration;
DROP TABLE weg_einheit_konfiguration;
```

### **Benefits**
- ✅ Clearer table naming and purpose
- ✅ Simplified data structure
- ✅ Forces proper configuration instead of silent fallbacks
- ✅ Better separation of concerns

---

## 📊 Zahlungskategorie System Overhaul (2025)

### **Problem Solved**
The original payment category system had hardcoded JavaScript logic, confusing category names, and maintenance burden requiring code changes for category modifications.

### **Changes Made**
- **Database-Driven**: Category behavior now stored in database JSON fields
- **Clear Naming**: "Rechnung von Dienstleister" instead of "Klassische Ausgabe"
- **Dynamic Forms**: JavaScript reads configuration from data attributes
- **12 New Categories**: Logical grouping (Expenses, Income, Neutral)

### **Key Improvements**
- `field_config` JSON: Controls which form fields to show/hide
- `validation_rules` JSON: Custom validation per category
- `help_text`: Contextual guidance for users
- `allows_zero_amount`: Special handling for neutral categories

### **Impact**
- ✅ No hardcoded JavaScript logic
- ✅ Easy category modifications without deployments
- ✅ Better user experience with contextual help
- ✅ Eliminated category confusion

---

## 🧮 Fixed Equal Distribution Calculation (2025)

### **Problem Solved**
The `CalculationService` used hardcoded `/4.0` division for equal distribution instead of dynamic unit counting, breaking scalability for different WEG sizes.

### **Changes Made**
- **Dynamic Unit Counting**: Added `countWegUnits()` method using database queries
- **Method Signatures**: Updated to pass WEG parameter through calculation chain
- **Proper Distribution**: 03* (equal), 05* (MEA), 06* (Hebeanlage) correctly implemented

### **Impact**
- ✅ Works with any number of WEG units (not just 4)
- ✅ Proper equal distribution calculation
- ✅ Scalable architecture for future WEGs

---

## 💰 Rücklagenzuführung Treatment Documentation (2025)

### **Clarification Achieved**
Documented that the current approach of treating reserve contributions as "negative costs" is correct for German WEG accounting standards.

### **Key Points**
- Reserve contributions (-800€) reduce total burden correctly
- Follows German WEG accounting standards (Option A approach)
- Alternative display format documented for clarity

---

## 🔍 §35a Tax Analysis Enhancement (2025)

### **Historical Analysis**
Comprehensive analysis of tax-deductible services from 2020-2024, showing evolution from sophisticated dual categorization to simplified approaches.

### **Key Findings**
- 2020: Most complex (1,452.01 € deductible)
- 2024: Simplified 100% approach (~3,262.49 € deductible)
- Database enhancement with `arbeits_fahrtkosten` column for accurate tracking

---

## 📋 Development Guidelines

### **After Major Changes**
1. **Always run**: `composer quality-services` (CS Fixer + PHPStan + Tests)
2. **Migration safety**: Test both `up()` and `down()` methods
3. **Fixture updates**: Check that all fixtures load successfully
4. **Documentation**: Update relevant docs and reference files

### **Database Changes**
- Use descriptive migration names and comments
- Include proper rollback functionality
- Test with realistic data volumes
- Update entity annotations and relationships

### **Code Quality**
- Follow PSR-12 coding standards
- Use strict typing `declare(strict_types=1)`
- Write comprehensive tests for business logic
- Document complex calculations and business rules

---

## 🔗 Related Documentation

- **[DEVELOPMENT.md](../../DEVELOPMENT.md)** - Complete technical documentation
- **[HGA Reference Output](hga-ref.md)** - 2024 calculation reference values
- **[Zahlungskategorie System](zahlungskategorie-system.md)** - Payment category details
- **[Fixture Strategy](fixture_strategy.md)** - Database seeding approach

---

## 🛠️ KategorisierungsTyp Enum Simplification (July 26, 2025)

### **Problem Solved**
Redundant enum cases with overlapping functionality made the system confusing and harder to maintain.

### **Changes Made**
- **Enum Reduction**: Simplified from 7 to 5 enum cases
- **Removed Cases**:
  - `STEUER_ABZUGSFAEHIG` → replaced by `taxDeductible` field
  - `AUSGESCHLOSSEN` → replaced by `isActive` field
- **Updated Display Names**: Clearer terminology for remaining cases

### **Migration Details**
```sql
-- Migration: Version20250726081612
-- Migrated ausgeschlossen → nicht_umlagefaehig + isActive=false
-- Migrated steuer_abzugsfaehig → nicht_umlagefaehig + updated fixtures
```

### **Impact**
- ✅ Cleaner enum structure
- ✅ Proper separation of concerns (business logic vs flags)
- ✅ Updated fixtures maintain data integrity

---