# AI Invoice Data Extraction

**Document Version**: 2.1
**Date**: 2026-01-16
**Status**: Reference

---

## Scope

This document describes the invoice data extraction feature (PDF -> Rechnung), including the LLM-based extraction layer using Ollama and Claude.

---

## Current Implementation

### Entry Points

- Manual trigger from the Dokument UI: "Als Rechnung parsen"
- Controller action: `POST /dokument/{id}/parse`

### Processing Flow

1. User triggers parse on a Dokument in category `rechnungen`
2. `InvoiceProcessingService` validates eligibility
3. `ParserFactory` selects a parser (regex, custom, or LLM)
4. Parser extracts data from PDF text
5. A `Rechnung` entity is created and persisted
6. `Dokument` is linked to `Rechnung`

### Core Classes

| Class | Purpose |
|-------|---------|
| `src/Service/InvoiceProcessingService.php` | Orchestrates parsing flow |
| `src/Service/Parser/ParserFactory.php` | Selects appropriate parser |
| `src/Service/Parser/ParserInterface.php` | Parser contract |
| `src/Service/Parser/AbstractPdfParser.php` | Base class with PDF utilities |
| `src/Service/Parser/GenericRegexParser.php` | Regex-based extraction |
| `src/Service/Parser/MaassParser.php` | Custom parser for Maaß invoices |
| `src/Service/Parser/LlmParser.php` | **LLM-based extraction (Ollama/Claude)** |
| `src/Service/AI/DocIntelProvider.php` | OCR + LayoutLM service client (DocIntel) |
| `src/Service/AI/DocIntelInterface.php` | Vision provider contract |

### Supported Fields in Rechnung

| Field | Description |
|-------|-------------|
| `rechnungsnummer` | Invoice number |
| `betrag_mit_steuern` | Gross amount (German format: "1.234,56") |
| `gesamt_mwst` | VAT amount |
| `arbeits_fahrtkosten` | Labor costs for §35a EStG |
| `datum_leistung` | Service/invoice date |
| `faelligkeitsdatum` | Due date |
| `information` | Notes (includes LLM extraction metadata) |

### Dienstleister Configuration Fields

| Field | Type | Description |
|-------|------|-------------|
| `parser_config` | JSON | Regex patterns for field extraction |
| `parser_class` | string | Custom parser class name |
| `ai_parsing_prompt` | text | Custom prompt override for LLM |
| `parser_enabled` | boolean | Enable/disable parsing |

---

## Parser Architecture

### Parser Selection Priority

```
ParserFactory.createParser(Dienstleister, provider='ollama')
│
├── 1. parser_class field (highest priority)
│   └── Custom class or 'LlmParser'
│
├── 2. Hardcoded parsers (middle priority)
│   └── Name-based detection (e.g., 'Maaß' → MaassParser)
│
├── 3. parser_config JSON (regex-based)
│   └── GenericRegexParser with field mappings
│
└── 4. LlmParser fallback (if AI available)
    └── Automatic when no config exists
```

### Example parser_config (Regex)

```json
{
  "parser_type": "regex",
  "field_mappings": {
    "rechnungsnummer": {
      "pattern": "/Rechnung(?:s-?Nr\\.?)?\\s*:?\\s*([A-Z0-9\\-]+)/i"
    },
    "betrag_mit_steuern": {
      "pattern": "/Gesamt(?:betrag)?\\s*:?\\s*([0-9.,]+)\\s*(?:EUR|€)/i",
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

### Overview

The LLM extraction layer uses the same Ollama/Claude dual-provider pattern as HGA Quality Checks. It provides AI-powered invoice data extraction when regex-based parsing is not configured or fails.

### Architecture

```
LlmParser
├── OllamaService.extractInvoiceData()   # Local, DSGVO-compliant
└── ClaudeProvider.extractInvoiceData()  # Higher accuracy, API costs
```

### Provider Selection

| Provider | Use Case | Cost |
|----------|----------|------|
| `ollama` | Default, local processing, DSGVO-compliant | Free |
| `claude` | Complex invoices, higher accuracy needed | ~€0.01/invoice |

### Usage Examples

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

// Check LLM availability
if ($parserFactory->isLlmAvailable('claude')) {
    // Claude is configured and available
}
```

### Database Configuration

To force LLM parsing for a specific Dienstleister:

```sql
UPDATE dienstleister
SET parser_class = 'LlmParser', parser_config = NULL
WHERE id = 123;
```

### Extracted Fields

The LLM extracts these fields from invoice PDFs:

| Field | Format | Example |
|-------|--------|---------|
| `rechnungsnummer` | String | "RE-2024-001" |
| `betrag_mit_steuern` | German decimal | "1.234,56" |
| `gesamt_mwst` | German decimal | "234,56" |
| `datum_leistung` | DD.MM.YYYY | "15.03.2024" |
| `faelligkeitsdatum` | DD.MM.YYYY | "30.03.2024" |
| `arbeits_fahrtkosten` | German decimal | "500,00" |

### Confidence Scoring

Each extraction includes a confidence score (0.0-1.0):

- **≥ 0.7**: High confidence, auto-accepted
- **< 0.7**: Low confidence, marked as `ausstehend` for review

The extraction metadata is stored in `Rechnung.information`:

```
[LLM-Extraktion via ollama, Konfidenz: 85%] Rechnungsnummer und Betrag klar erkennbar, Datum aus Kopfzeile extrahiert.
```

### Prompt Engineering

The extraction prompt instructs the LLM to:

1. Extract fields in German format (decimal: comma, thousands: dot)
2. Identify §35a labor costs when present
3. Return structured JSON with confidence score
4. Handle "Abschlag" and "Vorauszahlung" invoices

Example prompt structure:

```
Du bist ein Experte für die Extraktion von Rechnungsdaten...

PDF-TEXT:
{extracted text}

AUFGABE:
Extrahiere folgende Felder...

Antworte NUR mit gültigem JSON:
{
    "rechnungsnummer": "...",
    "betrag_mit_steuern": "...",
    ...
    "confidence": 0.85,
    "reasoning": "..."
}
```

---

## OCR + LayoutLM (DocIntel)

### Goal

Handle scanned PDFs and complex layouts by combining OCR, layout detection, and vision models.

### Proposed Service Boundary

- **Service name:** DocIntel Service (separate repo)
- **Repo path (local):** `../doc-intel`
- **PHP client:** `DocIntelProvider`
- **Input:** rendered page images (base64)
- **Output:** structured invoice fields + confidence + reasoning

### Flow

1. Render PDF pages to images (`PdfRenderService`)
2. OCR + layout detection (PaddleOCR)
3. Optional LayoutLM extraction for structured fields
4. Return normalized invoice fields to PHP client

### Integration Notes

- DocIntel is now wired into `LlmParser` as a fallback when required fields are missing.
- PDF pages are rendered to images via `PdfRenderService` (requires `pdftoppm`).
- The service should expose `POST /api/invoice-extract`.

### Debug Endpoint

- `GET /dokument/{id}/docintel-debug` returns raw DocIntel extraction JSON.

---

## Implementation Status

| Phase | Status | Description |
|-------|--------|-------------|
| Phase 1: Parser Foundation | ✅ Done | AbstractPdfParser, GenericRegexParser, MaassParser |
| Phase 2: Parser Factory | ✅ Done | Priority-based parser selection |
| Phase 3: LLM Integration | ✅ Done | LlmParser with Ollama/Claude support |
| Phase 4: Confidence Scoring | ✅ Done | Low-confidence marked as pending |
| Phase 5: OCR + LayoutLM Service | ✅ Done | DocIntel service + OCR wired, LayoutLM hooks present |
| Phase 6: Auto-parsing on upload | ⏳ Planned | Messenger queue for async processing |
| Phase 7: Admin UI | ⏳ Planned | Web interface for parser configuration |
| Phase 8: Learning Loop | ⏳ Planned | User corrections improve future extractions |

---

## Configuration

### Environment Variables

```bash
# Ollama (local LLM)
AI_OLLAMA_ENABLED=true
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b

# Claude (Anthropic API)
AI_CLAUDE_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...

# DocIntel (OCR + LayoutLM)
DOCINTEL_ENABLED=false
DOCINTEL_URL=http://doc-intel:8000
```

---

## Next Steps

1. **LayoutLM**: Add a fine-tuned LayoutLM model and map labels to invoice fields.
2. **Field post-processing**: Normalize EUR amounts, dates, and VAT rates; parse labor/material costs from OCR text.
3. **DocIntel warmup**: Preload OCR and model weights on container start to reduce first-request latency.
4. **Vendor hints**: Support Dienstleister-specific hints/patterns to improve LLM and OCR extraction.
5. **Async pipeline**: Move DocIntel calls to Symfony Messenger to avoid request timeouts.

### Service Configuration

The ParserFactory is auto-wired with dependencies:

```yaml
# config/services.yaml
App\Service\Parser\ParserFactory:
    arguments:
        $projectDir: '%kernel.project_dir%'
```

---

## Troubleshooting

### LLM Parser Not Available

```php
if (!$parserFactory->isLlmAvailable()) {
    // Check:
    // 1. AI_OLLAMA_ENABLED=true in .env
    // 2. Ollama container is running
    // 3. Model is pulled: docker compose exec ollama ollama pull llama3.1:8b
}
```

### Low Confidence Extractions

If extractions frequently have low confidence:

1. Check PDF text quality: `pdftotext -layout invoice.pdf -`
2. Consider adding regex `parser_config` for stable vendors
3. Use Claude provider for complex formats

### Parsing Errors

Check logs for detailed error messages:

```bash
docker compose logs web | grep LlmParser
```

---

## Related Documentation

- [AI Integration](ai_integration.md) - Payment categorization, query answering
- [HGA Quality Checks](hga-quality-checks.md) - Same Ollama/Claude pattern

---

**Last Updated**: 2026-01-16
