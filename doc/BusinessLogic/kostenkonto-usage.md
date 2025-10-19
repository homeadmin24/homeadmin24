# Current Kostenkonto Usage Analysis (2025)

## Active Kostenkonto Accounts in Database

### Maintenance & Services (Wartung & Dienstleistungen):
- **040100** - Hausmeisterkosten: 19 payments, -5,790.54 €
- **040101** - Hausmeister-Sonderleistungen: 7 payments, -1,224.38 €
- **041100** - Schornsteinfeger: 1 payment, -133.30 €
- **041400** - Heizungs-Reparaturen: 12 payments, -6,247.05 €
- **041800** - Servicekosten-Heizkostenabrechnung: 3 payments, -1,268.11 €
- **042111** - Wasseranalytik: 2 payments, -850.85 €
- **044000** - Brandschutz: 1 payment, -121.38 €
- **047000** - Hebeanlage: 2 payments, -1,195.95 €

### Utilities (Versorgung):
- **043000** - Allgemeinstrom: 11 payments, -504.13 €
- **043100** - Gas: 12 payments, -5,839.32 €
- **043200** - Müllentsorgung: 5 payments, -844.74 €
- **043400** - Abwasser/Kanalgebühren: 6 payments, -748.20 €

### Insurance (Versicherungen):
- **046000** - Versicherung: Gebäude: 3 payments, -2,413.24 €
- **046200** - Versicherung: Haftpflicht: 2 payments, -128.52 €

### Administration (Verwaltung):
- **050000** - Verwaltervergütung: 22 payments, -5,345.64 €
- **052000** - Beiratsvergütung: 2 payments, -300.00 €
- **048100** - Mahngebühren WEG: 1 payment, -13.00 €
- **049000** - Nebenkosten Geldverkehr WEG-Konto: 26 payments, -145.89 €

### Accounting & Reserves (Buchhaltung & Rücklagen):
- **053100** - Vorjahresabschlüsse: 17 payments, -55.23 €
- **054000** - Rücklagenzuführung: 1 payment, +800.00 €

## Summary Statistics:
- **Total Active Accounts**: 20 different kostenkonto accounts
- **Total Payments**: 147 individual payments
- **Total Expenses**: ~31,600 € (negative amounts)
- **Total Income**: ~800 € (positive amounts)
- **Net Cash Flow**: ~-30,800 €

## Account Usage Patterns:
- **Most Active**: 049000 (Nebenkosten Geldverkehr) - 26 payments
- **Highest Volume**: 041400 (Heizungs-Reparaturen) - 6,247.05 €
- **Smallest**: 048100 (Mahngebühren WEG) - 13.00 €
- **Only Positive**: 054000 (Rücklagenzuführung) - 800.00 €

## HGA Report Integration:
- **Umlagefähige Kosten**: 040100, 040101, 041100, 041800, 042111, 043000, 043200, 046000, 046200
- **Nicht Umlagefähige Kosten**: 041400, 044000, 047000, 048100, 049000, 050000, 052000, 053100
- **Rücklagenzuführung**: 054000
- **Excluded from HGA**: 043100 (Gas), 043400 (Abwasser) - handled externally

## Recent Changes:
- **ENTKALKUNG WARMWASSERBOILER**: Payment ID 10 moved from 2024 to 2023 via abrechnungsjahrZuordnung
- **User Interface**: Added "Jahr" column to zahlung table for better visibility
- **Form Enhancement**: Added abrechnungsjahrZuordnung field to edit forms

## Data Quality Notes:
- All accounts properly categorized for HGA reporting
- Clear separation between chargeable (umlagefähig) and non-chargeable costs
- Proper handling of reserve contributions as cost reductions
- External heating/water costs handled separately from internal utility costs