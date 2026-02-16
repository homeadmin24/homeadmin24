# AI Payment Auto-Categorization

**Status**: Production-Ready
**Last Updated**: 2026-02-16

---


## Feature Behavior
- Scope: Beschreibt die automatische Zuordnung von Zahlungen zu Kostenkonten inkl. AI-Fallback fuer unklare Faelle.
- Inputs/Outputs: Eingaben sind Zahlungsdaten + historische Muster; Ausgabe ist eine kategorisierte Zahlung mit Confidence/Begruendung.
- Invarianten: Duplikaterkennung und bestehende deterministic Rules duerfen durch AI nicht unterlaufen werden.
- Akzeptanz: Auto-Kategorisierungsquote steigt, manuelle Korrekturen sinken, und Korrekturen fliessen in spaetere Vorschlaege ein.

## Operational Runbook
- Trigger: Bei Fehlklassifizierungen, schlechter Confidence, geaenderten Buchungsmustern oder Provider-Ausfall.
- Schritte: Beispiele reproduzieren, Pattern-Matches gegenpruefen, AI-Provider testen, Korrektur-Flow ausfuehren.
- Verifikation: Korrigierte Buchungen werden persistiert und bei aehnlichen neuen Zahlungen priorisiert vorgeschlagen.
- Recovery/Rollback: AI temporär deaktivieren und rein regelbasiert kategorisieren, bis Prompt/Config angepasst sind.

## Overview

AI-enhanced payment categorization assigns incoming bank transactions to the correct Kostenkonto automatically. The system uses a hybrid approach combining fast regex pattern matching with LLM-based analysis for ambiguous cases.

### Key Metrics

| Metric | Before AI | With AI |
|--------|-----------|---------|
| Auto-categorization rate | ~70% | 95%+ |
| Manual review rate | ~30% | <10% |
| Time savings | - | ~7.5 hours/year (300 payments x 1.5 min) |

---

## Architecture

```
 1. Quick Pattern Match          <50ms
 (Existing regex logic)
           |
     Confidence?
           |
     +-----+-------+
     |             |
 HIGH (>95%)   LOW (<95%)
     |             |
     v             v
  Accept        2. AI Analysis     2-5s
  Pattern       (Ollama LLM)
```

### Core Service

`src/Service/ZahlungKategorisierungService.php`
- Pattern-based auto-categorization (regex matching)
- AI-enhanced categorization via Ollama/Claude
- Learning from user corrections
- 3-level duplicate detection (SEPA ID -> Amount+Date+Partner -> Fuzzy matching)

### AI Service

`src/Service/OllamaService.php`

```php
public function suggestKostenkonto(
    string $bezeichnung,
    string $partner,
    float $betrag,
    array $historicalData = [],
    array $learningExamples = [],
    array $availableKategorien = [],
): array
```

---

## AI Context Enrichment

The AI sees context that pattern matching cannot use:

### 1. Historical Patterns

```json
{
  "previous_payments": [
    {
      "date": "2024-07-15",
      "partner": "Stadtwerke Muenchen",
      "purpose": "Abschlag 07/2024",
      "amount": -839.20,
      "assigned_to": "043100 - Gas"
    }
  ]
}
```

### 2. Semantic Understanding

- "Abschlag" = advance payment (suggests recurring utility)
- Amount similarity indicates same service type
- Payment frequency patterns

### 3. Fuzzy Matching

- "Stadtwerke Muenchen" = "SWM" = "Stadtwerke Muenchen GmbH"

---

## Example Prompt

```
Analysiere diese Bankbuchung und ordne sie der passendsten Kostenkonto-Kategorie zu:

BUCHUNGSDETAILS:
- Bezeichnung/Verwendungszweck: "Abschlag 10/2024 Vertragskonto 1234567"
- Buchungspartner: "Stadtwerke Muenchen"
- Betrag: -842.50 EUR
- Datum: 2024-10-15

HISTORISCHE ZAHLUNGEN (aehnliche vergangene Buchungen):
- 2024-07-15: Stadtwerke Muenchen, "Abschlag 07/2024" (-839.20 EUR) -> 043100 (Gas)
- 2024-04-15: Stadtwerke Muenchen, "Abschlag 04/2024" (-845.60 EUR) -> 043100 (Gas)

VERFUEGBARE KOSTENKONTEN:
043000 - Allgemeinstrom
043100 - Gas
042000 - Wasser
042200 - Abwasser

WICHTIG: Gelernte Muster haben Prioritaet ueber generische Regeln!

Antworte NUR mit gueltigem JSON:
{
    "kostenkonto": "043100",
    "confidence": 0.95,
    "reasoning": "Quartalsmaessiger Abschlag an Stadtwerke Muenchen. Historische Zuordnung zu Gas (043100)."
}
```

---

## Provider Comparison

| Feature | Ollama | Claude |
|---------|--------|--------|
| Speed | 2-5s | 1-2s |
| Cost | Free | ~0.001 EUR/categorization |
| Quality | Good (improves with learning) | Excellent |
| Privacy | 100% local | Sent to Anthropic |

---

## Related Documentation

- [AI Integration Overview](ai_01_integration.md) - Configuration, privacy, learning
- [Core System](core_system.md) - CSV import, payment workflow
