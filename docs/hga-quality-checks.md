# HGA AI Quality Checks

**Status**: Core Feature Complete (Learning Loop: Partial)
**Last Updated**: 2026-02-16

---


## Feature Behavior
- Scope: Definiert AI-gestuetzte Pre-Flight-Qualitaetspruefung fuer HGA-Dokumente vor Versand.
- Inputs/Outputs: Input ist `Dokument.hgaData` + optionales Feedback; Output sind Findings, Scores und Handlungshinweise.
- Invarianten: Rule-based Checks laufen immer, AI-Checks ergaenzen nur; fehlende AI darf den Basis-Flow nicht blockieren.
- Akzeptanz: Kritische Auffaelligkeiten werden reproduzierbar erkannt, und Feedback kann in spaetere Analysen einfliessen.

## Operational Runbook
- Trigger: Vor HGA-Versand, bei Streitfaellen, bei auffaelligem Ergebnis oder Provider-Problemen.
- Schritte: Quality-Check mit beiden Providern (oder Debug-Endpoint) ausfuehren, Findings vergleichen, Feedback speichern.
- Verifikation: Findings sind fachlich nachvollziehbar, JSON-Struktur ist vollstaendig, und UI zeigt konsistente Ergebnisse.
- Recovery/Rollback: Bei Provider-Fehlern auf Rule-only/alternativen Provider wechseln und Versand bis Plausibilisierung blocken.

## Overview

AI-powered pre-flight quality checks for Hausgeldabrechnung (HGA) documents before sending to property owners.

### Goals

1. **Prevent errors** - Catch calculation mistakes, missing data, unusual patterns
2. **Build confidence** - Pre-flight validation before sending reports
3. **Save time** - Reduce manual review time
4. **Learn patterns** - Improve checks based on user feedback

### Key Features

- Dual AI provider support: Ollama (free, DSGVO-compliant) + Claude (premium, precise)
- Rule-based + AI checks combined
- User feedback collection with prompt injection
- Feedback repository queries ready for admin dashboard

---

## Architecture

```
User Interface (/dokument/{id})
[Download] [Mit Ollama pruefen] [Mit Claude pruefen]
         |
         v
DokumentController
POST /dokument/{id}/quality-check
POST /dokument/{id}/quality-feedback
GET  /dokument/{id}/quality-check-debug
         |
         v
HgaQualityCheckService
  - runQualityChecks()
  - checkDataCompleteness()        <100ms (rule-based)
  - checkCalculationPlausibility()
  - checkCompliance()
  - runAIAnalysis()                3-90s (provider-dependent)
         |
    +----+----+
    |         |
 Ollama    Claude
(60-90s)   (3-5s)
    |         |
    +----+----+
         |
         v
HgaQualityFeedback
  - User ratings (helpful/not helpful)
  - Issue reports (false_negative, false_positive, new_check)
  - Recent issues injected back into AI prompts
```

---

## Implementation Status

### Core Quality Checks

| Component | Status | File |
|-----------|--------|------|
| HgaQualityCheckService | Done | `src/Service/Hga/HgaQualityCheckService.php` |
| Rule-based: Data Completeness | Done | External costs, payment records, required fields |
| Rule-based: Calculation Plausibility | Done | MEA distribution (+-10%), heating range, 35a limit |
| Rule-based: Compliance | Done | 35a percentage (20%), distribution key consistency |
| AI analysis integration | Done | Dual-provider (Ollama + Claude), graceful fallback |
| AI prompt engineering | Done | Context-rich prompt with feedback injection |
| Debug endpoint | Done | `GET /dokument/{id}/quality-check-debug` |

### Data Layer

| Component | Status | File |
|-----------|--------|------|
| HGA data storage (Dokument.hgaData) | Done | JSON field populated during HGA generation |
| HgaQualityFeedback entity | Done | `src/Entity/HgaQualityFeedback.php` |
| HgaQualityFeedbackRepository | Done | `src/Repository/HgaQualityFeedbackRepository.php` |
| Database migration | Done | `migrations/Version20251229_AddHgaQualityChecks.php` |
| HGA data population (CLI) | Done | `HgaGenerateCommand` stores hgaData on Dokument |
| HGA data population (Web) | Done | `AbrechnungController` + `HgaController` store hgaData |

### AI Providers

| Component | Status | File |
|-----------|--------|------|
| OllamaService.analyzeHgaQuality() | Done | `src/Service/OllamaService.php` |
| ClaudeProvider.analyzeHgaQuality() | Done | `src/Service/AI/ClaudeProvider.php` |
| Provider availability checks | Done | Graceful fallback if provider unavailable |
| Cost tracking (Claude) | Done | ~0.013 EUR/check logged |

### Frontend

| Component | Status | File |
|-----------|--------|------|
| Stimulus controller | Done | `assets/controllers/hga_quality_check_controller.js` |
| Quality check buttons (Ollama + Claude) | Done | On HGA document pages |
| Results modal (color-coded severity) | Done | `templates/dokument/_modals/hga_quality_check.html.twig` |
| Feedback: helpful/not helpful rating | Done | Thumbs up/down in modal |
| Feedback: problem reporting form | Done | false_negative, false_positive, new_check |
| Toast notifications | Done | Success feedback after rating |

### Controller Endpoints

| Route | Method | Status |
|-------|--------|--------|
| `/dokument/{id}/quality-check` | POST | Done |
| `/dokument/{id}/quality-feedback` | POST | Done |
| `/dokument/{id}/quality-check-debug` | GET | Done (dev utility) |

### Learning Loop

| Component | Status | Details |
|-----------|--------|---------|
| Feedback collection (UI -> DB) | Done | Modal UI persists to hga_quality_feedback table |
| Feedback injection into AI prompts | Done | 10 most recent issues added to prompt context |
| Repository: getRecentIssues() | Done | Used by HgaQualityCheckService for prompt context |
| Repository: getUnimplementedFeedback() | Done | Query exists, no UI consumer |
| Repository: getProviderStatistics() | Done | Query exists, no UI consumer |
| Repository: getFrequentIssues() | Done | Query exists, no UI consumer |
| Admin feedback dashboard | Not started | No controller, no template |
| Feedback lifecycle (mark implemented) | Not started | DB field exists, no UI to toggle |
| Provider accuracy comparison UI | Not started | Query exists, no display |
| Automated rule generation from feedback | Not started | Concept only |

### Testing

| Component | Status | Details |
|-----------|--------|---------|
| Manual test script | Done | `tests/ai/test-quality-check.php` |
| PHPUnit tests | Not started | No unit or integration tests |

---

## Check Categories

### 1. Data Completeness (Critical)

- External costs (heating/water) present
- Payment records exist (Ist > 0)
- Payment count plausibility (10-16 per unit per year)
- All required fields populated

### 2. Calculation Plausibility (High)

- Individual costs match MEA percentage (+-10% tolerance)
- Heating cost anomaly detection (>5.000 EUR suspicious)
- Tax deductions <= 1.200 EUR/year (35a limit)

### 3. Compliance (Medium)

- 35a EStG tax deduction correctly calculated (<= 20% of eligible costs)
- Distribution keys consistently applied

### 4. AI Pattern Detection

- Unusual cost patterns
- Year-over-year anomalies
- Context-specific validation
- Enhanced by user feedback context (up to 10 recent issues injected)

---

## Usage

### In the UI

1. Navigate to a Hausgeldabrechnung document
2. Click quality check button:
   - "Mit Ollama pruefen" - Free, local, DSGVO-compliant
   - "Mit Claude pruefen" - Premium, faster, more accurate
3. Wait for analysis (3-90s depending on provider)
4. Review results in modal dialog
5. Provide feedback (thumbs up/down) to improve future checks
6. Report problems via "Problem melden" form (false negative, false positive, new check idea)

### Debug Endpoint

```bash
# View prompt only (instant):
http://127.0.0.1:8000/dokument/102/quality-check-debug

# Full analysis with Ollama (60-90s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=ollama

# Full analysis with Claude (3-5s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=claude
```

### Understanding Results

| Status | Meaning |
|--------|---------|
| Pass (Green) | No issues found, ready to send |
| Warning (Yellow) | Minor issues, review recommended |
| Critical (Red) | Serious problems, do NOT send until fixed |

---

## Learning Loop

### Current State

The learning loop has two layers:

**Layer 1 - Automatic (implemented):** User feedback is collected via the UI, stored in `hga_quality_feedback`, and the 10 most recent issues are automatically injected into AI prompts for future checks. This means the AI sees what users flagged as wrong and can avoid repeating those mistakes.

**Layer 2 - Manual review (not yet implemented):** The repository has queries for admin review (`getUnimplementedFeedback()`, `getProviderStatistics()`, `getFrequentIssues()`), but there is no dashboard UI. Feedback is collected but never surfaced to administrators for action.

### Data Flow

```
User submits feedback (thumbs up/down, problem report)
         |
         v
hga_quality_feedback table
  - ai_provider, ai_result (full response stored)
  - user_feedback_type (false_negative | false_positive | new_check)
  - user_description (free text)
  - helpful_rating (true/false)
  - implemented (false by default)
         |
         v
HgaQualityCheckService.runQualityChecks()
  - Calls getRecentIssues(10) from repository
  - Injects recent false_negative and new_check feedback into AI prompt
  - AI sees: "Users previously reported these issues, watch for them"
         |
         v
AI produces better-informed analysis
```

### Feedback Types

| Type | Meaning | Used For |
|------|---------|----------|
| `false_negative` | Real error that the check missed | Injected into prompts so AI watches for it |
| `false_positive` | Warning that was incorrect | Tracked but not yet used to suppress |
| `new_check` | User suggests a new validation rule | Candidate for hardcoded rule |

### Repository Queries (Ready, No UI)

```php
// Used automatically by HgaQualityCheckService
$repo->getRecentIssues(10);

// Ready for admin dashboard
$repo->getUnimplementedFeedback();   // Issues not yet acted on
$repo->getProviderStatistics();       // { provider, total, helpful, not_helpful, accuracy }
$repo->getFrequentIssues(3);          // Recurring problems (3+ occurrences)
```

### What's Missing for Full Learning Loop

1. **Admin Feedback Dashboard** - Route + template to view collected feedback, mark as implemented, compare provider accuracy
2. **Feedback Lifecycle** - UI to toggle `implemented` flag when an issue is addressed (DB field exists)
3. **Provider A/B Comparison** - Display `getProviderStatistics()` results (Ollama vs Claude accuracy)
4. **Frequent Issues Report** - Surface `getFrequentIssues()` to identify patterns worthy of new hardcoded rules
5. **False Positive Suppression** - Use `false_positive` feedback to reduce noise in future checks

---

## Backend Implementation

### Core Service

`src/Service/Hga/HgaQualityCheckService.php` (622 lines)

```php
public function runQualityChecks(
    Dokument $dokument,
    string $provider = 'ollama',
    bool $includeUserFeedback = true
): array
```

Returns:
```php
[
    'status' => 'pass'|'warning'|'critical',
    'provider' => string,
    'processing_time' => float,
    'checks' => [
        [
            'category' => string,
            'severity' => 'critical'|'high'|'medium'|'low',
            'status' => 'pass'|'fail'|'warning',
            'message' => string,
            'details' => array,
        ],
    ],
    'ai_analysis' => [
        'overall_assessment' => string,
        'confidence' => float,
        'issues_found' => array,
        'summary' => string,
    ]|null,
    'user_feedback_injected' => int,
]
```

### AI Response Format

```json
{
    "overall_assessment": "warning",
    "confidence": 0.85,
    "issues_found": [
        {
            "category": "calculation",
            "severity": "high",
            "issue": "Suspicious heating cost distribution",
            "details": "Heating costs (1.875 EUR) seem high for MEA 290/1000",
            "recommendation": "Verify heating cost allocation formula"
        }
    ],
    "summary": "Document mostly complete but heating costs appear unusually high."
}
```

### HGA Data Structure

Stored in `Dokument.hgaData` JSON field, populated during HGA generation:

```php
[
    'year' => int,
    'einheit' => [ 'nummer', 'beschreibung', 'eigentuemer', 'mea', 'address' ],
    'external_costs' => [ 'heating' => [ 'unit_share', 'total' ], 'water' => [...] ],
    'costs' => [ 'total', 'umlagefaehig', 'nicht_umlagefaehig', 'ruecklagen' ],
    'payments' => [ 'soll', 'ist', 'differenz', 'status', 'count' ],
    'tax_deductible' => [ 'total', 'tax_reduction' ],
    'weg_totals' => [ 'gesamtkosten' ],
]
```

### Database Schema (hga_quality_feedback)

```sql
CREATE TABLE hga_quality_feedback (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dokument_id INT NOT NULL,       -- FK dokument, CASCADE
    einheit_id INT NOT NULL,        -- FK weg_einheit, CASCADE
    year INT NOT NULL,
    ai_provider VARCHAR(20) NOT NULL,
    ai_result JSON NULL,
    user_feedback_type VARCHAR(50) NOT NULL,
    user_description LONGTEXT NULL,
    helpful_rating TINYINT NULL,    -- true/false/null
    implemented TINYINT DEFAULT 0,
    created_at DATETIME NOT NULL,
    INDEX (ai_provider),
    INDEX (created_at),
    INDEX (implemented)
);
```

---

## Provider Comparison

| Feature | Ollama | Claude |
|---------|--------|--------|
| Speed | 60-90s | 3-5s |
| Cost | Free | ~0.013 EUR/check |
| Model | llama3.1:8b (configurable) | claude-3-haiku-20240307 |
| Quality | Good | Excellent |
| Privacy | 100% local | Sent to Anthropic |
| Timeout | 120s | Default |

---

## Testing

### Manual

```bash
# 1. Generate HGA document
docker compose exec web php bin/console app:hga-generate 3 2025 --unit=0003

# 2. Verify hga_data populated (check dokument.hga_data is not NULL)

# 3. Run quality check via UI or debug endpoint

# 4. Test feedback: submit rating, report issue, verify DB entry

# 5. CLI test script
docker compose exec web php tests/ai/test-quality-check.php [dokument_id]
```

### Automated Tests

Not yet implemented. Recommended test cases:
- `testDataCompletenessCheckFailsWhenNoExternalCosts`
- `testCalculationPlausibilityCheckFailsWhenTaxDeductionTooHigh`
- `testAIAnalysisInjectsUserFeedback`
- `testGracefulFallbackWhenAIUnavailable`

---

## Troubleshooting

**"Document has no HGA data"**: Regenerate document using latest CLI or web generator (both populate `hga_data` automatically).

**Ollama not responding**: Check container (`docker compose ps`), test connection (`docker compose exec web php bin/console app:test-ai`).

**Frontend assets not loading**: Rebuild (`npm run dev`), hard refresh browser.

---

## Related Documentation

- [AI Integration Overview](al-overview.md) - Configuration, learning, privacy
- [Invoice Data Extraction](invoice-extraction.md) - Same Ollama/Claude pattern
