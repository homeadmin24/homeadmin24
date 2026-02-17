# AI Invoice Data Extraction

**Status**: Production-Ready
**Last Updated**: 2026-02-16

---


## Feature Behavior
- Scope: Beschreibt die Extraktion strukturierter Rechnungsdaten aus PDFs in `Rechnung`-Entities.
- Inputs/Outputs: Input ist `Dokument` (PDF + Metadaten); Output ist verknuepfte Rechnung mit normalisierten Feldern.
- Invarianten: Parser-Prioritaet (explicit class > hardcoded > regex config > LLM fallback) bleibt stabil und nachvollziehbar.
- Akzeptanz: Relevante Kernfelder werden korrekt extrahiert, gespeichert und mit dem Ursprungsdokument verknuepft.

## Operational Runbook
- Trigger: Bei Parse-Fehlern, schlechter Extraktionsqualitaet oder neuem Lieferantenlayout.
- Schritte: PDF-Textqualitaet pruefen, Parserauswahl nachvollziehen, Regex/Parser-Config anpassen, optional DocIntel-Path testen.
- Verifikation: Persistierte Rechnung enthaelt erwartete Betrags-/Datumsfelder und erscheint korrekt im UI.
- Recovery/Rollback: Auf robusteren Parser (z. B. GenericRegex) wechseln oder LLM-Fallback aktivieren/deaktivieren.

## Overview

Automatic extraction of structured data from invoice PDFs into `Rechnung` entities. Supports regex-based parsing for stable vendor templates and LLM-based extraction for complex/variable formats.

### Entry Points

- Manual trigger from Dokument UI: "Als Rechnung parsen"
- Controller action: `POST /dokument/{id}/parse`

---

## Processing Flow

```
User triggers parse on Dokument (category: rechnungen)
    |
    v
InvoiceProcessingService validates eligibility
    |
    v
ParserFactory selects parser
    |
    +-- 1. parser_class field (highest priority)
    |       Custom class or 'LlmParser'
    |
    +-- 2. Hardcoded parsers (middle priority)
    |       Name-based detection (e.g. 'Maass' -> MaassParser)
    |
    +-- 3. parser_config JSON (regex-based)
    |       GenericRegexParser with field mappings
    |
    +-- 4. LlmParser fallback (if AI available)
            Automatic when no config exists
    |
    v
Parser extracts data from PDF text
    |
    v
Rechnung entity created and persisted, linked to Dokument
```

---

## Core Classes

| Class | Purpose |
|-------|---------|
| `src/Service/InvoiceProcessingService.php` | Orchestrates parsing flow |
| `src/Service/Parser/ParserFactory.php` | Selects appropriate parser |
| `src/Service/Parser/ParserInterface.php` | Parser contract |
| `src/Service/Parser/AbstractPdfParser.php` | Base class with PDF utilities |
| `src/Service/Parser/GenericRegexParser.php` | Regex-based extraction |
| `src/Service/Parser/MaassParser.php` | Custom parser for Maass invoices |
| `src/Service/Parser/LlmParser.php` | LLM-based extraction (Ollama/Claude) |
| `src/Service/AI/DocIntelProvider.php` | OCR + LayoutLM service client |
| `src/Service/AI/DocIntelInterface.php` | Vision provider contract |

---

## Extracted Fields

| Field | Description | Format |
|-------|-------------|--------|
| `rechnungsnummer` | Invoice number | String ("RE-2024-001") |
| `betrag_mit_steuern` | Gross amount | German decimal ("1.234,56") |
| `gesamt_mwst` | VAT amount | German decimal |
| `arbeits_fahrtkosten` | Labor costs for 35a EStG | German decimal |
| `datum_leistung` | Service/invoice date | DD.MM.YYYY |
| `faelligkeitsdatum` | Due date | DD.MM.YYYY |
| `information` | Notes (includes LLM extraction metadata) | Text |

---

## Regex Parser Configuration

### Dienstleister Configuration Fields

| Field | Type | Description |
|-------|------|-------------|
| `parser_config` | JSON | Regex patterns for field extraction |
| `parser_class` | string | Custom parser class name |
| `ai_parsing_prompt` | text | Custom prompt override for LLM |
| `parser_enabled` | boolean | Enable/disable parsing |

### Example parser_config

```json
{
  "parser_type": "regex",
  "field_mappings": {
    "rechnungsnummer": {
      "pattern": "/Rechnung(?:s-?Nr\\.?)?\\s*:?\\s*([A-Z0-9\\-]+)/i"
    },
    "betrag_mit_steuern": {
      "pattern": "/Gesamt(?:betrag)?\\s*:?\\s*([0-9.,]+)\\s*(?:EUR)/i",
      "transform": "german_decimal"
    },
    "datum_leistung": {
      "pattern": "/(?:Rechnungs)?[Dd]atum\\s*:?\\s*(\\d{1,2}\\.\\d{1,2}\\.\\d{2,4})/",
      "transform": "date"
    }
  }
}
```

### Best Practices

- Only one parsing method per Dienstleister
- If `parser_class` is set, `parser_config` should be NULL
- Use regex for vendors with stable templates
- Use LLM for complex/variable invoice formats

---

## LLM Extraction Layer

### Provider Selection

| Provider | Use Case | Cost |
|----------|----------|------|
| `ollama` | Default, local processing, DSGVO-compliant | Free |
| `claude` | Complex invoices, higher accuracy | ~0.01 EUR/invoice |

### Usage

```php
// Auto-selection with LLM fallback
$parser = $parserFactory->createParser($dienstleister);
$rechnung = $parser->parse($dokument);

// Explicit provider selection
$parser = $parserFactory->createParser($dienstleister, 'claude');

// Direct LLM parser creation
$llmParser = $parserFactory->createLlmParser('ollama');
if ($llmParser->isProviderAvailable()) {
    $rechnung = $llmParser->parse($dokument);
}
```

### Force LLM for a specific vendor

```sql
UPDATE dienstleister
SET parser_class = 'LlmParser', parser_config = NULL
WHERE id = 123;
```

### Confidence Scoring

Each extraction includes a confidence score (0.0-1.0):

- **>= 0.7**: High confidence, auto-accepted
- **< 0.7**: Low confidence, marked as `ausstehend` for review

Extraction metadata stored in `Rechnung.information`:
```
[LLM-Extraktion via ollama, Konfidenz: 85%] Rechnungsnummer und Betrag klar erkennbar.
```

### Prompt Structure

```
Du bist ein Experte fuer die Extraktion von Rechnungsdaten...

PDF-TEXT:
{extracted text}

AUFGABE:
Extrahiere folgende Felder...

Antworte NUR mit gueltigem JSON:
{
    "rechnungsnummer": "...",
    "betrag_mit_steuern": "...",
    "confidence": 0.85,
    "reasoning": "..."
}
```

---

## OCR + LayoutLM (DocIntel)

For scanned PDFs and complex layouts.

| Aspect | Detail |
|--------|--------|
| Service | DocIntel (separate repo `../doc-intel`) |
| PHP Client | `DocIntelProvider` |
| Input | Rendered page images (base64) |
| Output | Structured invoice fields + confidence |
| Endpoint | `POST /api/invoice-extract` |
| Debug | `GET /dokument/{id}/docintel-debug` |

Flow: PDF pages rendered to images (`PdfRenderService` / `pdftoppm`) -> OCR + layout detection (PaddleOCR) -> Optional LayoutLM -> Normalized fields returned to PHP client.

DocIntel is wired into `LlmParser` as a fallback when required fields are missing.

---

## Implementation Status

| Phase | Status |
|-------|--------|
| Parser Foundation (AbstractPdfParser, GenericRegexParser, MaassParser) | Done |
| Parser Factory (priority-based selection) | Done |
| LLM Integration (Ollama/Claude) | Done |
| Confidence Scoring | Done |
| OCR + LayoutLM Service (DocIntel) | Done |
| Auto-parsing on upload (Messenger queue) | Planned |
| Admin UI for parser configuration | Planned |
| Learning Loop (user corrections improve extractions) | Planned |

---

## Troubleshooting

### LLM Parser Not Available

Check: `AI_OLLAMA_ENABLED=true` in `.env`, Ollama container running, model pulled (`docker compose exec ollama ollama pull llama3.1:8b`)

### Low Confidence Extractions

1. Check PDF text quality: `pdftotext -layout invoice.pdf -`
2. Add regex `parser_config` for stable vendors
3. Use Claude provider for complex formats

### Parsing Errors

```bash
docker compose logs web | grep LlmParser
```

---

## Related Documentation

- [AI Integration Overview](al-overview.md) - Configuration, privacy
- [HGA Quality Checks](hga-quality-checks.md) - Same Ollama/Claude pattern
