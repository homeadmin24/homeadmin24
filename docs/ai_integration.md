# AI Integration Documentation

**Document Version**: 2.1
**Date**: 2026-01-15
**Status**: Production-Ready

---

## Quick Start

### Setup (5 minutes)

```bash
# 1. Copy environment template
cp .env.local.example .env.local

# 2. Get Claude API key from https://console.anthropic.com/
#    - Create account / Sign in
#    - Go to API Keys → Create Key
#    - Add credits at Plans & Billing (minimum $5)

# 3. Add to .env.local:
AI_CLAUDE_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...

# 4. Start services
docker compose down && docker compose up -d
```

### Quick Test

**HGA Quality Check:**
```bash
# With Claude (3-5s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=claude

# With Ollama (60-90s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=ollama
```

**Test Scripts:**
```bash
./tests/ai/test-ai-providers.sh           # Check provider status
docker compose exec web php tests/ai/test-ollama-direct.php
```

### Provider Comparison

| Provider | Speed | Cost | Privacy | Best For |
|----------|-------|------|---------|----------|
| **Ollama** | 60-90s | Free | 100% local | Regular use, privacy-critical |
| **Claude Haiku** | 3-5s | ~€0.002/check | Cloud (Anthropic) | Quick validation, very affordable |

### Security ⚠️

**Safe to commit:**
- `.env` (no secrets, only safe defaults)
- `.env.demo`, `.env.prod` (configuration, no secrets)
- `.env.local.example` (template)

**NEVER commit:**
- `.env.local` (contains your API key)
- Any file with `ANTHROPIC_API_KEY=sk-ant-...`

---

## Table of Contents

1. [Quick Start](#quick-start) ⭐
2. [Overview](#overview)
3. [Environment Configuration](#environment-configuration)
4. [HGA Quality Checks](#hga-quality-checks)
5. [Payment Categorization](#payment-categorization)
6. [Natural Language Queries](#natural-language-queries)
7. [Ollama Learning & Fine-tuning](#ollama-learning--fine-tuning)
8. [Invoice Data Extraction (LLM)](#invoice-data-extraction-llm)
9. [Privacy & Compliance](#privacy--compliance)

---

## Overview

### AI Features

The homeadmin24 system provides AI-powered features for:

1. **Intelligent Payment Categorization** - Auto-categorize payments with 95%+ accuracy
2. **Natural Language Financial Queries** - Ask questions in German, get instant answers
3. **Invoice Data Extraction** - Automatically extract structured data from PDFs
4. **HGA Quality Checks** - Pre-flight review to catch errors before sending

### Invoice Data Extraction (LLM)

LLM-based invoice extraction is now implemented using the same Ollama/Claude dual-provider pattern.

See full documentation: **[AI Invoice Data Extraction](ai_doc_extraction.md)**

### Architecture Strategy

**Privacy-First Hybrid Model**:
- **Local LLM (Ollama)** for sensitive owner/financial data (DSGVO compliant)
- **Claude API** (optional) for non-sensitive analysis
- Environment-configurable switching between providers

---

## Environment Configuration

### Supported AI Providers

| Provider | Type | Speed | Cost | Privacy |
|----------|------|-------|------|---------|
| **Ollama** | Local LLM | 60-90s | Free | 100% local |
| **Claude** | Anthropic API (Haiku) | 3-5s | ~€0.002/check | Cloud |

### Configuration Files

| File | Purpose | Committed? | Secrets? |
|------|---------|------------|----------|
| `.env` | Safe defaults | ✅ Yes | ❌ No |
| `.env.local` | Local dev secrets | ❌ No | ✅ Yes |
| `.env.demo` | Demo config | ✅ Yes | ❌ No |
| `.env.prod` | Prod config | ✅ Yes | ❌ No |

### Local Development (Both Providers)

```bash
# .env.local (git-ignored, contains your API key)
AI_ENABLED=true
AI_CLAUDE_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-api03-your-key-here
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b
```

**Setup:**
```bash
# 1. Copy example
cp .env.local.example .env.local

# 2. Add your API key to .env.local

# 3. Start services
docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d

# 4. Pull Ollama model (first time)
docker exec -it hausman-ollama ollama pull llama3.1:8b
```

### Demo/Production (Current Setup)

**Configuration:** Claude only for fast, reliable AI features

```bash
# .env.demo / .env.prod (committed, no secrets)
AI_ENABLED=true
AI_CLAUDE_ENABLED=true
# ANTHROPIC_API_KEY set via GitHub Secrets
```

**Secrets Management:**
1. Add GitHub Secret: `ANTHROPIC_API_KEY_DEMO` or `ANTHROPIC_API_KEY_PROD`
2. In deployment workflow:
   ```yaml
   env:
     ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY_DEMO }}
   ```
3. Or set as environment variable on server

### Demo/Production (Future Migration)

**Goal:** Switch to Ollama (no API costs, full privacy)

```bash
# .env.demo / .env.prod
AI_ENABLED=true
AI_CLAUDE_ENABLED=false
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b
```

**Migration Steps:**
1. Add `ollama` service to production docker-compose
2. Allocate GPU/CPU resources for Ollama
3. Update `.env.demo`/`.env.prod` to disable Claude
4. Deploy and test
5. Remove `ANTHROPIC_API_KEY` from GitHub Secrets

### Setup Instructions

**1. Start services with AI:**
```bash
# Start all services including Ollama
docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d

# Pull AI model (first time only, ~5GB download)
docker exec -it hausman-ollama ollama pull llama3.1:8b

# Verify model is available
docker exec hausman-ollama ollama list
```

**2. Test AI:**
```bash
docker compose exec web php bin/console app:test-ai
```

---

## HGA Quality Checks

### Overview

AI-powered pre-flight quality checks for Hausgeldabrechnung (HGA) documents before sending to owners.

**Benefits:**
- Catch errors before distribution
- Ensure data completeness
- Verify calculation accuracy
- Save time on manual review

### How It Works

```
┌──────────────────────────────┐
│ 1. Rule-Based Checks         │  <100ms
│ (Always run)                 │
│ - Missing required fields    │
│ - Negative total costs       │
│ - Payment mismatches         │
└──────────┬───────────────────┘
           │
    ┌──────┴──────┐
    │ Run AI?     │
    └──────┬──────┘
           │
    ┌──────┴──────────┐
    │                 │
  Ollama           Claude
(60-90s)          (5-10s)
    │                 │
    ▼                 ▼
┌──────────────────────────────┐
│ AI Analysis Result           │
│ - Overall assessment         │
│ - Confidence score           │
│ - Issues found               │
│ - Recommendations            │
└──────────────────────────────┘
```

### Using Quality Checks

**In the UI:**
1. Go to HGA document detail page
2. Click quality check button:
   - "Qualitätsprüfung mit Ollama" (slow, free, private)
   - "Qualitätsprüfung mit Claude" (fast, paid, cloud)
3. Wait for analysis (5-90 seconds depending on provider)
4. Review results in modal dialog
5. Provide feedback (👍/👎) to improve future checks

**Debug Endpoint:**
```bash
# View prompt only (instant):
http://127.0.0.1:8000/dokument/102/quality-check-debug

# Full analysis with Ollama (60-90s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=ollama

# Full analysis with Claude (5-10s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=claude
```

### AI Analysis Response

```json
{
  "overall_assessment": "warning",
  "confidence": 0.85,
  "issues_found": [
    {
      "category": "calculation",
      "severity": "high",
      "issue": "Suspicious heating cost distribution",
      "details": "Heating costs (€1,875) seem high for MEA 290/1000",
      "recommendation": "Verify heating cost allocation formula"
    }
  ],
  "summary": "Document is mostly complete but heating costs appear unusually high for this unit size. Recommend double-checking the allocation."
}
```

### Provider Comparison for HGA Checks

| Feature | Ollama | Claude |
|---------|--------|--------|
| **Speed** | 60-90s | 5-10s |
| **Cost** | Free | ~€0.01 |
| **Quality** | Good | Excellent |
| **Privacy** | 100% local | Sent to Anthropic |
| **Best for** | Regular checks | Quick validation |

### Implementation

See `src/Service/Hga/HgaQualityCheckService.php` for complete implementation.

**Key Components:**
- `runQualityChecks()` - Main entry point
- `runRuleBasedChecks()` - Fast validation rules
- `runAIAnalysis()` - Provider-agnostic AI call
- `buildAIPrompt()` - Context-rich prompt generation

**User Feedback:**
- Stored in `hga_quality_feedback` table
- Used to improve future AI prompts
- Tracks false positives/negatives

**Further Reading:**
- See [HGA Quality Checks Documentation](hga-quality-checks.md) for complete implementation details
- Test scripts available in `tests/ai/`

---

## Payment Categorization

### Current Limitations

The existing `ZahlungKategorisierungService` uses keyword-based pattern matching:
- Fixed priority order
- Limited context awareness
- No learning from corrections
- **~70% auto-categorization success rate**
- Manual intervention required for 30% of payments

### AI Enhancement Strategy

**Hybrid Approach** - Best of both worlds:

```
┌──────────────────────────────┐
│   1. Quick Pattern Match     │  <50ms
│   (Existing logic)           │
└──────────┬───────────────────┘
           │
    ┌──────┴──────┐
    │ Confidence? │
    └──────┬──────┘
           │
    ┌──────┴──────────┐
    │                 │
HIGH (>95%)      LOW (<95%)
    │                 │
    ▼                 ▼
┌─────────┐   ┌──────────────────┐
│ ✓ Accept│   │ 2. AI Analysis   │  2-5s
│ Pattern │   │ (Ollama LLM)     │
└─────────┘   └──────────────────┘
```

### AI Context Enrichment

**What AI sees that pattern matching doesn't:**

1. **Historical Patterns**
   ```json
   {
     "previous_payments": [
       {
         "date": "2024-07-15",
         "partner": "Stadtwerke München",
         "purpose": "Abschlag 07/2024",
         "amount": -839.20,
         "assigned_to": "043100 - Gas"
       }
     ]
   }
   ```

2. **Semantic Understanding**
   - "Abschlag" = advance payment (suggests recurring utility)
   - Amount similarity indicates same service type
   - Payment frequency patterns

3. **Fuzzy Matching**
   - "Stadtwerke München" ≈ "SWM" ≈ "Stadtwerke Muenchen GmbH"

### Example Prompt

```
Analysiere diese Bankbuchung und ordne sie der passendsten Kostenkonto-Kategorie zu:

BUCHUNGSDETAILS:
- Bezeichnung/Verwendungszweck: "Abschlag 10/2024 Vertragskonto 1234567"
- Buchungspartner: "Stadtwerke München"
- Betrag: -842.50 EUR
- Datum: 2024-10-15

HISTORISCHE ZAHLUNGEN (ähnliche vergangene Buchungen):
- 2024-07-15: Stadtwerke München, "Abschlag 07/2024" (-839.20 EUR) → 043100 (Gas)
- 2024-04-15: Stadtwerke München, "Abschlag 04/2024" (-845.60 EUR) → 043100 (Gas)

VERFÜGBARE KOSTENKONTEN:
043000 - Allgemeinstrom
043100 - Gas
042000 - Wasser
042200 - Abwasser

⚠️ WICHTIG: Gelernte Muster haben Priorität über generische Regeln!

Antworte NUR mit gültigem JSON:
{
    "kostenkonto": "043100",
    "confidence": 0.95,
    "reasoning": "Quartalsmäßiger Abschlag an Stadtwerke München. Historische Zuordnung zu Gas (043100). Betrag und Frequenz konsistent mit früheren Gas-Zahlungen."
}
```

### Implementation

See `src/Service/OllamaService.php` for complete implementation.

**Key Methods:**
```php
// Suggest Kostenkonto for payment categorization
public function suggestKostenkonto(
    string $bezeichnung,
    string $partner,
    float $betrag,
    array $historicalData = [],
    array $learningExamples = [],
    array $availableKategorien = [],
): array
```

### Success Metrics

- **Accuracy**: Increase from 70% to 95%+
- **Time Savings**: 7.5 hours/year (300 payments × 1.5 min saved)
- **Manual Review Rate**: Reduce from 30% to <10%

---

## Natural Language Queries

### Overview

Ask questions about financial data in natural German and receive intelligent answers powered by AI.

**Benefits:**
- Instant answers without navigating complex UIs
- Natural language - no technical knowledge required
- Context-aware - understands WEG-specific terminology
- Intelligent analysis - automatic year-over-year comparisons

### Example Queries

**1. Cost Summary**
```
USER: "Wie viel haben wir 2024 für Heizung ausgegeben?"

AI RESPONSE:
Im Jahr 2024 wurden insgesamt 5.839,32 € für Heizung ausgegeben
(Kostenkonto 006000 - Gas). Das entspricht einem Anstieg von 12%
gegenüber 2023 (5.215,40 €).

Monatliche Verteilung:
- Q1 2024: 2.156,80 € (37%)
- Q2 2024: 1.245,20 € (21%)
- Q3 2024: 892,40 € (15%)
- Q4 2024: 1.544,92 € (27%)

Der höchste Verbrauch war im Januar mit 892,50 €.
```

**2. Owner Payment Status**
```
USER: "Hat Herr Müller alle Vorauszahlungen für 2024 bezahlt?"

AI RESPONSE:
Max Müller (Einheit 0003) hat 2024 alle Vorauszahlungen pünktlich bezahlt:
✅ 11 von 12 Monaten eingegangen (2.640,00 €)
⚠️ Dezember 2024 (240,00 €) noch ausstehend

Status: Offen 240,00 € (Fälligkeit: 01.12.2024)

Zahlungshistorie: Sehr zuverlässig, durchschnittlich 2 Tage vor Fälligkeit.
```

**3. Cost Analysis**
```
USER: "Welche Kostenpositionen sind 2024 am stärksten gestiegen?"

AI RESPONSE:
Top 5 Kostensteigerungen 2024 vs. 2023:

1. 041400 Heizungs-Reparaturen: +2.450,80 € (+64%)
   Grund: Austausch Umwälzpumpe (1.980 €) + Wartung

2. 043100 Gas: +623,92 € (+12%)
   Grund: Preisanstieg + kälterer Winter

3. 050000 Verwaltervergütung: +245,50 € (+5%)
   Grund: Vertragliche Indexanpassung
```

### API Endpoint

```php
POST /api/ai/query

Request:
{
  "query": "Wie viel haben wir 2024 für Heizung ausgegeben?"
}

Response:
{
  "success": true,
  "query": "Wie viel haben wir 2024 für Heizung ausgegeben?",
  "answer": "Im Jahr 2024 wurden insgesamt 5.839,32 € für Heizung...",
  "context_size": 5
}
```

### Query Type Detection

The system automatically detects what type of question is being asked:

1. **Cost Queries** - Keywords: kosten, ausgaben, ausgegeben, betrag
2. **Owner Payment Queries** - Keywords: eigentümer, vorauszahlung, hausgeld
3. **Cost Increase Queries** - Keywords: gestiegen, steigerung, vergleich

### Performance

| Query Type | Context Size | AI Processing | Total Response Time |
|------------|--------------|---------------|---------------------|
| Simple cost query | Small (< 50 payments) | 2-3 seconds | ~3 seconds |
| Owner payment status | Medium (~ 100 payments) | 3-4 seconds | ~4 seconds |
| Year comparison | Large (~ 500 payments) | 4-6 seconds | ~6 seconds |

**First Request**: May take 60-90 seconds (model loading)
**Subsequent Requests**: 2-6 seconds

---

## Ollama Learning & Fine-tuning

### Two-Level Learning Approach

**Level 1: Prompt Engineering** (Immediate, No Model Changes)
- Inject good Claude examples directly into prompts
- Works instantly, no training required
- Examples sent with every query (increases context size)

**Level 2: Model Fine-tuning** (Permanent, Model Changes)
- Train a custom Ollama model with collected examples
- Creates persistent improvements in model weights
- Reduces prompt size, faster responses

### Level 1: Few-Shot Learning

**Step 1: Collect Good Examples**

Users rate responses with 👍/👎. Good examples are stored in `ai_query_response` table.

**Step 2: Inject into Prompts**

```php
// Fetch top-rated Claude examples
$goodExamples = $this->aiQueryResponseRepository->getGoodClaudeExamples(5);

$prompt = <<<PROMPT
Du bist ein Experte für WEG-Finanzen.

LERNE VON DIESEN HOCHWERTIGEN BEISPIEL-ANTWORTEN:

BEISPIEL 1:
Frage: Wie viel haben wir 2024 für Gas ausgegeben?
Gute Antwort: Im Jahr 2024 wurden 5.839,32 € für Gas ausgegeben...

---AKTUELLE FRAGE---
{$query}
PROMPT;
```

### Level 2: Model Fine-tuning

**Step 1: Export Training Data**
```bash
docker compose exec web php bin/console app:export-training-data \
  --output=/tmp/ollama-training.jsonl \
  --min-rating=good \
  --limit=50
```

**Step 2: Create Custom Model**
```bash
# Create Modelfile
cat > /tmp/Modelfile-weg-finance <<EOF
FROM llama3.1:8b

SYSTEM """
Du bist ein Experte für deutsche Wohnungseigentümergemeinschaften (WEG).
Du verstehst Kostenkonto-Nummern, Hausgeldabrechnungen, und §35a EStG.
"""

PARAMETER temperature 0.3
PARAMETER top_p 0.9
EOF

# Copy to container and create model
docker cp /tmp/ollama-training.jsonl hausman-ollama:/tmp/
docker cp /tmp/Modelfile-weg-finance hausman-ollama:/tmp/
docker exec -it hausman-ollama ollama create weg-finance -f /tmp/Modelfile-weg-finance
```

**Step 3: Use Custom Model**
```yaml
# docker-compose.dev.yml
environment:
  - OLLAMA_MODEL=weg-finance  # Use custom fine-tuned model
```

### Recommended Workflow

**Phase 1: Collect Data** (Weeks 1-4)
1. Use dual-provider mode (Ollama + Claude)
2. Users rate responses with 👍/👎
3. Collect 20-50 good Claude examples
4. Use Level 1 (prompt engineering) for immediate gains

**Phase 2: First Fine-tune** (Week 5)
1. Export top 20 examples
2. Create custom `weg-finance` model
3. A/B test: Base model vs fine-tuned model
4. Measure accuracy improvement

**Phase 3: Iterative Improvement** (Ongoing)
1. Continue collecting ratings
2. Retrain model monthly with new examples
3. Track accuracy metrics over time
4. When Ollama reaches 80% of Claude quality → disable Claude

### Success Metrics

| Metric | Target | How to Measure |
|--------|--------|----------------|
| **Ollama accuracy** | >80% of Claude | User ratings (good/bad ratio) |
| **Response time** | <3s | Average response_time from DB |
| **User preference** | >60% prefer Ollama | Ratings comparison |
| **Cost savings** | €0.01 → €0.00 | Claude usage reduction |

---

## Privacy & Compliance

### DSGVO/GDPR Considerations

#### Data Processed by AI

**What AI sees:**
- ✅ Payment descriptions (Verwendungszweck)
- ✅ Service provider names (Dienstleister)
- ✅ Amounts and dates
- ✅ Payment categories (Kostenkonto)

**What AI does NOT see:**
- ❌ Owner personal data (names, addresses)
- ❌ Bank account numbers
- ❌ Sensitive personal information

#### Legal Basis

- **Art. 6 (1) lit. b GDPR**: Processing necessary for contract performance (WEG management)
- **Art. 6 (1) lit. f GDPR**: Legitimate interest in efficient administration

#### Technical Measures

**Privacy by Design:**
1. **Local Processing**: Ollama runs on-premises, no external data transfer
2. **Data Minimization**: Only relevant payment metadata sent to AI
3. **Anonymization**: Remove owner names from AI context
4. **Retention**: AI processing logs deleted after 90 days
5. **Opt-Out**: Manual categorization always available

### Ollama vs Claude API Comparison

| Aspect | Ollama (Local) | Claude API |
|--------|----------------|------------|
| **Data Location** | ✅ Your server only | ⚠️ Anthropic servers |
| **DSGVO Article 28** | ✅ Not applicable | ⚠️ DPA required |
| **Data Transfer** | ✅ None | ⚠️ EU→US transfer |
| **Audit Trail** | ✅ Full control | ⚠️ Limited |
| **Right to Deletion** | ✅ Immediate | ⚠️ Request needed |

### Privacy Policy Addition

```markdown
### Automatische Zahlungskategorisierung

Zur effizienten Verwaltung Ihrer WEG nutzen wir ein KI-gestütztes System
zur automatischen Kategorisierung von Zahlungen.

**Verarbeitete Daten:**
- Buchungstexte (Verwendungszweck)
- Dienstleister-Namen
- Beträge und Buchungsdaten

**Verarbeitung:**
- Lokal auf unserem Server (keine Cloud-Dienste)
- Keine Weitergabe an Dritte
- Keine Speicherung personenbezogener Eigentümerdaten

**Ihre Rechte:**
- Auskunft über AI-verarbeitete Daten
- Widerspruch gegen automatische Kategorisierung
- Manuelle Korrektur jederzeit möglich
```

---

## Related Documentation

- [AI Invoice Data Extraction](ai_doc_extraction.md) - LLM-based PDF parsing
- [HGA Quality Checks](hga-quality-checks.md) - AI quality validation
- [Core System Documentation](core_system.md) - Payment categorization, CSV import, auth system
- [Local Setup Guide](setup_local.md) - Docker development environment
- [Production Deployment](setup_production.md) - Deployment options

---

**Document Status**: Production-Ready (Local Development Only)
**Next Steps**: Test with real users, collect feedback, refine prompts
