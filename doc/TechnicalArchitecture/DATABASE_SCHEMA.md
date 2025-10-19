# Database Schema Documentation

## Overview

The homeadmin24 system uses **MySQL 8.0** with **Doctrine ORM 3.3** for database management. The schema is designed to support German WEG (Wohnungseigentümergemeinschaft) property management requirements with comprehensive financial tracking, payment categorization, and automated report generation.

## Core Database Entities

### 1. **Weg** - Property Management Association
```sql
-- Wohnungseigentümergemeinschaft (WEG)
-- Central entity representing a property management association
```
**Purpose**: Represents a WEG (Wohnungseigentümergemeinschaft) - the main property management unit
**Key Fields**: 
- `id`, `name`, `adresse`, `telefon`
- Contains multiple ownership units (WegEinheit)

### 2. **WegEinheit** - Ownership Units
```sql
-- Individual ownership units within a WEG
-- Includes owner details, voting rights, and ownership shares (MEA)
```
**Purpose**: Individual ownership units within a WEG
**Key Fields**:
- `nummer` (unit number), `beschreibung` (description)
- `eigentuemer` (owner name), `adresse`, `telefon`
- `miteigentumsanteile` (ownership shares/MEA)
- `hebeanlage` (elevator pump access - boolean)
**Business Logic**: MEA determines cost distribution percentages

### 3. **Zahlung** - Financial Transactions
```sql
-- All financial transactions (income and expenses)
-- Core financial tracking with year assignment capability
```
**Purpose**: Central financial transaction tracking
**Key Fields**:
- `betrag` (amount), `datum` (date), `beschreibung` (description)
- `abrechnungsjahrZuordnung` (accounting year assignment)
- Foreign keys to: `kostenkonto`, `zahlungskategorie`, `dienstleister`, `rechnung`, `eigentuemer`
**Business Logic**: Supports cross-year transaction assignment for proper accounting periods

### 4. **Zahlungskategorie** - Payment Categories
```sql
-- Database-driven payment categorization system
-- Replaced hardcoded system in 2025 for better maintainability
```
**Purpose**: Categorizes payments as income, expenses, or neutral
**Key Fields**:
- `name`, `beschreibung`, `ist_positiver_betrag`
- `field_config` (JSON) - Dynamic form field configuration
- `validation_rules` (JSON) - Custom validation rules
- `help_text`, `sort_order`, `is_active`, `allows_zero_amount`

**Categories (2025 System)**:
- **Expenses**: Rechnung von Dienstleister, Direktbuchung Kostenkonto, Auslagenerstattung, Rückzahlung, Bankgebühren
- **Income**: Hausgeld-Zahlung, Sonderumlage, Gutschrift Dienstleister, Zinserträge, Sonstige Einnahme  
- **Neutral**: Umbuchung, Korrektur

### 5. **Kostenkonto** - Cost Accounts
```sql
-- Chart of accounts with cost type classification
-- Determines if costs are chargeable to tenants (umlagefähig)
```
**Purpose**: Chart of accounts for cost classification
**Key Fields**:
- `nummer` (account number), `bezeichnung` (description)
- `kategorisierungsTyp` (cost categorization type)
- `umlageschluessel_id` (distribution key reference)
- `is_active` (active status)

**Cost Types**:
- `UMLAGEFAEHIG_HEIZUNG` - Chargeable heating costs
- `UMLAGEFAEHIG_SONSTIGE` - Other chargeable costs
- `NICHT_UMLAGEFAEHIG` - Non-chargeable costs
- `RUECKLAGENZUFUEHRUNG` - Reserve contributions

### 6. **Dienstleister** - Service Providers
```sql
-- Service providers and contractors
-- Supports invoice management and parser configuration
```
**Purpose**: Management of contractors and service providers
**Key Fields**:
- `name`, `adresse`, `telefon`, `email`
- `vertragsbeginn`, `vertragsende` (contract period)
- `parser_config` (JSON) - PDF parser configuration
- Relationships: One-to-many with `Rechnung` and `Dokument`

### 7. **Rechnung** - Invoices
```sql
-- Invoice management with tax deduction support
-- Tracks labor costs for §35a EStG tax benefits
```
**Purpose**: Invoice tracking with tax deduction support
**Key Fields**:
- `rechnungsnummer`, `rechnungsdatum`, `faelligkeitsdatum`
- `betrag_netto`, `mehrwertsteuer`, `betrag_mit_steuern`
- `arbeits_fahrtkosten` - Labor/travel costs for §35a EStG tax deduction
- Foreign key to `dienstleister`

### 8. **Dokument** - Document Storage
```sql
-- Document management and categorization
-- Supports various document types for WEG administration
```
**Purpose**: Document storage and organization
**Key Fields**:
- `dateiname`, `dateipfad`, `dateityp`
- `kategorie`: `eigentuemer`, `umlaufbeschluss`, `jahresabschluss`
- Foreign keys to `weg` and `dienstleister`

### 9. **Hausgeldabrechnung** - Annual Statements
```sql
-- Annual property management statements
-- Generated reports for each ownership unit
```
**Purpose**: Annual property management cost statements
**Key Fields**:
- `jahr` (year), `erstellungsdatum` (creation date)
- `gesamtkosten`, `eigentuemeranteil` (total costs, owner share)
- `saldo` (balance), `dateiname` (generated file name)
- Foreign key to `weg_einheit`

### 10. **MonatsSaldo** - Monthly Balances
```sql
-- Monthly account balance tracking
-- Supports balance development reporting
```
**Purpose**: Monthly account balance tracking for financial reporting
**Key Fields**:
- `balance_month` (YYYY-MM format), `balance_amount`
- Foreign key to `weg`
- Unique constraint on `weg_id` + `balance_month`

### 11. **Umlageschluessel** - Distribution Keys
```sql
-- Cost distribution methods
-- Determines how costs are allocated across units
```
**Purpose**: Defines how costs are distributed among ownership units
**Key Fields**:
- `schluessel` (key code), `bezeichnung` (description)

**Distribution Methods**:
- `01*` - External heating costs (fixed amounts per unit)
- `02*` - External water/other costs (fixed amounts per unit)  
- `03*` - Equal distribution among units
- `04*` - Fixed amounts (Festumlage)
- `05*` - MEA-based distribution (ownership share percentages)
- `06*` - Special distribution (e.g., elevator pump access)

### 12. **HeizWasserkosten** - External Heating/Water Costs
```sql
-- External heating and water cost allocations
-- Managed by external billing companies
```
**Purpose**: External heating and water cost management
**Key Fields**:
- `heizkosten`, `wasser_kosten` (heating costs, water costs)
- `year` (billing year)
- Foreign keys to `weg` and `weg_einheit`
- Special handling for external billing integration

### 13. **WegEinheitVorauszahlung** - Advance Payments
```sql
-- Annual advance payment amounts per unit
-- Supports different payment amounts per unit per year
```
**Purpose**: Tracks annual advance payment amounts for each unit
**Key Fields**:
- `year`, `vorauszahlung_betrag` (advance payment amount)
- Foreign key to `weg_einheit`
- Unique constraint on `weg_einheit_id` + `year`

## Entity Relationships

### Primary Relationships
```
Weg (1) ←→ (N) WegEinheit
Weg (1) ←→ (N) MonatsSaldo
Weg (1) ←→ (N) Dokument

WegEinheit (1) ←→ (N) Zahlung
WegEinheit (1) ←→ (N) Hausgeldabrechnung
WegEinheit (1) ←→ (N) WegEinheitVorauszahlung
WegEinheit (1) ←→ (N) HeizWasserkosten

Dienstleister (1) ←→ (N) Rechnung
Dienstleister (1) ←→ (N) Dokument

Kostenkonto (1) ←→ (N) Zahlung
Kostenkonto (N) ←→ (1) Umlageschluessel

Zahlungskategorie (1) ←→ (N) Zahlung

Rechnung (1) ←→ (N) Zahlung
```

### Complex Relationships
- **Zahlung** is the central hub connecting: WegEinheit, Kostenkonto, Zahlungskategorie, Dienstleister, Rechnung
- **HeizWasserkosten** has dual relationships: WEG-level totals and unit-level allocations
- **JSON Configuration**: Zahlungskategorie and Dienstleister use JSON fields for flexible configuration

## Database Features

### Migrations
- **Doctrine Migrations**: All schema changes tracked in `migrations/` folder
- **Version Control**: Each migration timestamped and reversible
- **Major Migrations**: 2025 Zahlungskategorie system overhaul, HGA architecture improvements

### Data Integrity
- **Foreign Key Constraints**: Maintain referential integrity
- **Unique Constraints**: Prevent duplicate data (e.g., MonatsSaldo per WEG per month)
- **Nullable Fields**: Flexible data entry where business logic allows

### Performance Considerations
- **Indexes**: Automatic indexing on foreign keys and unique constraints
- **Query Optimization**: Repository classes with optimized queries
- **Data Volume**: Designed for medium-scale WEG operations (typically 2-20 units per WEG)

## Key Business Logic Implementations

### 1. Cost Distribution (Umlageschlüssel)
- **MEA Distribution**: Uses `miteigentumsanteile` from WegEinheit (e.g., 290/1000 = 29%)
- **Equal Distribution**: Dynamically counts active units per WEG
- **Special Cases**: Hebeanlage only affects units with elevator pump access

### 2. Year Assignment Flexibility
- **Cross-Year Transactions**: `abrechnungsjahrZuordnung` allows transactions to be assigned to different accounting years
- **Example**: December 2024 expense can be assigned to 2025 accounting period

### 3. Tax Deduction Tracking (§35a EStG)
- **Labor Cost Tracking**: `arbeits_fahrtkosten` field in Rechnung table
- **Automatic Calculation**: Proportional labor cost calculation for partial invoice payments
- **Legal Compliance**: Supports German tax law requirements for household services

### 4. Dynamic Form Configuration
- **JSON-Driven Forms**: Zahlungskategorie `field_config` determines form field visibility
- **Validation Rules**: Database-stored validation rules for flexible business logic
- **Auto-Population**: Automatic field setting based on category selection

## Data Fixtures and Testing

### Development Data
- **Comprehensive Fixtures**: All entities have fixture classes for development setup
- **Real-World Data**: Based on actual WEG scenarios with realistic amounts and relationships
- **Cross-Referenced**: Fixtures maintain proper entity relationships and constraints

### Backup Strategy
- **Regular Backups**: `backup/` folder contains timestamped database dumps
- **Migration Checkpoints**: Backups created before major migrations
- **Recovery Procedures**: Documented restoration processes

## Security Considerations

### Data Protection
- **No Sensitive Data**: No credit card or payment processing data stored
- **Personal Information**: Owner names and addresses for legitimate business purposes only
- **Access Control**: Controller-level security for data access

### Data Validation
- **Entity Validation**: Doctrine validation constraints
- **Business Logic**: Service-layer validation for complex business rules
- **Input Sanitization**: Symfony form validation and sanitization

## Performance Monitoring

### Query Optimization
- **Repository Patterns**: Custom repository methods for efficient data retrieval
- **Eager Loading**: Strategic use of Doctrine joins to reduce N+1 queries
- **Indexing Strategy**: Database indexes on frequently queried fields

### Scaling Considerations
- **Multi-WEG Support**: Architecture supports multiple WEGs in single instance
- **Data Archiving**: Consider archival strategy for historical data beyond legal requirements
- **Caching Strategy**: Symfony cache for calculated values and report generation

## Common Database Operations

### Financial Reporting Queries
```sql
-- Total costs by category for a WEG year
SELECT k.bezeichnung, SUM(ABS(z.betrag)) as total
FROM zahlung z 
JOIN kostenkonto k ON z.kostenkonto_id = k.id 
WHERE z.weg_einheit_id IN (SELECT id FROM weg_einheit WHERE weg_id = ?)
AND YEAR(z.datum) = ?
GROUP BY k.id;

-- Owner payment summary
SELECT we.nummer, we.eigentuemer, SUM(z.betrag) as total_payments
FROM weg_einheit we
JOIN zahlung z ON z.eigentuemer_id = we.id
WHERE we.weg_id = ? AND YEAR(z.datum) = ?
GROUP BY we.id;
```

### Data Consistency Checks
```sql
-- Verify MEA totals per WEG
SELECT weg_id, SUM(CAST(SUBSTRING_INDEX(miteigentumsanteile, '/', 1) AS DECIMAL) / 
                    CAST(SUBSTRING_INDEX(miteigentumsanteile, '/', -1) AS DECIMAL)) as total_mea
FROM weg_einheit 
GROUP BY weg_id;

-- Check for orphaned payments
SELECT COUNT(*) FROM zahlung z 
LEFT JOIN weg_einheit we ON z.weg_einheit_id = we.id 
WHERE we.id IS NULL;
```

## Future Database Enhancements

### Planned Improvements
- **Audit Logging**: Track changes to financial data with user attribution
- **Advanced Reporting**: Materialized views for complex financial reports
- **API Support**: Database optimization for REST API endpoints
- **Integration Tables**: Support for DATEV and external accounting system integration

### Scalability Preparations
- **Partitioning Strategy**: Consider table partitioning for large transaction volumes
- **Read Replicas**: Prepare for read-only reporting database replicas
- **Data Retention Policies**: Implement automated archival for historical data

This comprehensive database schema supports all aspects of German WEG property management while maintaining flexibility for future enhancements and regulatory changes.