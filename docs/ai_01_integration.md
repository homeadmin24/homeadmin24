# AI Integration - Overview & Configuration

**Status**: Production-Ready
**Last Updated**: 2026-02-16

---


## Feature Behavior
- Scope: Definiert die globale AI-Architektur fuer homeadmin24 (Provider, Routing, Konfiguration, Datenschutzgrenzen).
- Inputs/Outputs: Eingaben sind App-Kontext + User-Requests; Ausgaben sind strukturierte AI-Antworten je Feature-Modul.
- Invarianten: Sensible Finanz-/Eigentuemerdaten bevorzugt lokal (Ollama); Provider-Umschaltung erfolgt nur ueber explizite Config.
- Akzeptanz: Alle referenzierten AI-Module sind erreichbar, provider-seitig testbar und liefern reproduzierbare Antworten im Zielkontext.

## Operational Runbook
- Trigger: Bei Erstsetup, Provider-Wechsel, API-Key-Rotation, Latenz-/Ausfallproblemen.
- Schritte: `.env.local` setzen, Container neu starten, Provider-Tests ausfuehren, betroffene Feature-Endpunkte pruefen.
- Verifikation: Health-Checks und Beispielabfragen liefern erwartete Antwortstruktur ohne Fallback-Fehler.
- Recovery/Rollback: Fehlerhaften Provider deaktivieren (`AI_*_ENABLED=false`), lokal-only Modus nutzen, Konfiguration auf letzten stabilen Stand zuruecksetzen.

## Overview

homeadmin24 provides AI-powered features for property management:

| Feature | Documentation |
|---------|---------------|
| Payment Auto-Categorization | [ai_02_payment-auto-categorize.md](ai_02_payment-auto-categorize.md) |
| Natural Language Financial Queries | [ai_03_financial-queries.md](ai_03_financial-queries.md) |
| Invoice Data Extraction (PDF) | [ai_04_invoice-data-xtraction.md](ai_04_invoice-data-xtraction.md) |
| HGA Quality Checks | [ai_05_hga_quality-checks.md](ai_05_hga_quality-checks.md) |

### Architecture Strategy

**Privacy-First Hybrid Model**:
- **Ollama (Local LLM)** for sensitive owner/financial data (DSGVO-compliant)
- **Claude API** (optional) for non-sensitive analysis, higher accuracy
- Environment-configurable switching between providers

---

## Quick Start

### Setup (5 minutes)

```bash
# 1. Copy environment template
cp .env.local.example .env.local

# 2. Get Claude API key from https://console.anthropic.com/
#    - Create account / Sign in
#    - Go to API Keys -> Create Key
#    - Add credits at Plans & Billing (minimum $5)

# 3. Add to .env.local:
AI_CLAUDE_ENABLED=true
ANTHROPIC_API_KEY=sk-ant-...

# 4. Start services
docker compose down && docker compose up -d
```

### Quick Test

```bash
# HGA Quality Check with Claude (3-5s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=claude

# HGA Quality Check with Ollama (60-90s):
http://127.0.0.1:8000/dokument/102/quality-check-debug?full=1&provider=ollama

# Provider status check:
./tests/ai/test-ai-providers.sh
docker compose exec web php tests/ai/test-ollama-direct.php
```

---

## Provider Comparison

| Provider | Type | Speed | Cost | Privacy | Best For |
|----------|------|-------|------|---------|----------|
| **Ollama** | Local LLM | 60-90s | Free | 100% local | Regular use, privacy-critical |
| **Claude Haiku** | Anthropic API | 3-5s | ~0.002 EUR/check | Cloud (Anthropic) | Quick validation |

---

## Environment Configuration

### Configuration Files

| File | Purpose | Committed? | Secrets? |
|------|---------|------------|----------|
| `.env` | Safe defaults | Yes | No |
| `.env.local` | Local dev secrets | No | Yes |
| `.env.demo` | Demo config | Yes | No |
| `.env.prod` | Prod config | Yes | No |

### Security

**Safe to commit:** `.env`, `.env.demo`, `.env.prod`, `.env.local.example`

**NEVER commit:** `.env.local`, any file with `ANTHROPIC_API_KEY=sk-ant-...`

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

### Demo/Production

```bash
# .env.demo / .env.prod (committed, no secrets)
AI_ENABLED=true
AI_CLAUDE_ENABLED=true
# ANTHROPIC_API_KEY set via GitHub Secrets or server env
```

**Secrets Management:**
1. Add GitHub Secret: `ANTHROPIC_API_KEY_DEMO` or `ANTHROPIC_API_KEY_PROD`
2. In deployment workflow:
   ```yaml
   env:
     ANTHROPIC_API_KEY: ${{ secrets.ANTHROPIC_API_KEY_DEMO }}
   ```

### Future: Ollama-Only Production

```bash
# .env.demo / .env.prod
AI_ENABLED=true
AI_CLAUDE_ENABLED=false
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b
```

Migration steps:
1. Add `ollama` service to production docker-compose
2. Allocate GPU/CPU resources for Ollama
3. Update `.env.demo`/`.env.prod` to disable Claude
4. Deploy and test
5. Remove `ANTHROPIC_API_KEY` from GitHub Secrets

---

## Ollama Learning & Fine-tuning

### Two-Level Learning Approach

**Level 1: Prompt Engineering** (Immediate, No Model Changes)
- Inject good Claude examples directly into prompts
- Works instantly, no training required

**Level 2: Model Fine-tuning** (Permanent, Model Changes)
- Train a custom Ollama model with collected examples
- Reduces prompt size, faster responses

### Level 1: Few-Shot Learning

Users rate responses with thumbs up/down. Good examples are stored in `ai_query_response` table and injected into future prompts:

```php
$goodExamples = $this->aiQueryResponseRepository->getGoodClaudeExamples(5);

$prompt = <<<PROMPT
Du bist ein Experte fuer WEG-Finanzen.

LERNE VON DIESEN HOCHWERTIGEN BEISPIEL-ANTWORTEN:
{$examples}

---AKTUELLE FRAGE---
{$query}
PROMPT;
```

### Level 2: Model Fine-tuning

```bash
# 1. Export training data
docker compose exec web php bin/console app:export-training-data \
  --output=/tmp/ollama-training.jsonl \
  --min-rating=good --limit=50

# 2. Create custom model
cat > /tmp/Modelfile-weg-finance <<EOF
FROM llama3.1:8b
SYSTEM "Du bist ein Experte fuer deutsche WEG. Du verstehst Kostenkonto-Nummern, Hausgeldabrechnungen, und 35a EStG."
PARAMETER temperature 0.3
PARAMETER top_p 0.9
EOF

docker cp /tmp/ollama-training.jsonl hausman-ollama:/tmp/
docker cp /tmp/Modelfile-weg-finance hausman-ollama:/tmp/
docker exec -it hausman-ollama ollama create weg-finance -f /tmp/Modelfile-weg-finance

# 3. Use custom model
# Set OLLAMA_MODEL=weg-finance in docker-compose
```

### Recommended Workflow

| Phase | Duration | Action |
|-------|----------|--------|
| 1. Collect Data | Weeks 1-4 | Dual-provider mode, users rate responses, collect 20-50 good examples |
| 2. First Fine-tune | Week 5 | Export examples, create custom model, A/B test |
| 3. Iterate | Ongoing | Continue collecting, retrain monthly, disable Claude when Ollama reaches 80% quality |

---

## Privacy & DSGVO Compliance

### Data Processed by AI

**What AI sees:**
- Payment descriptions (Verwendungszweck)
- Service provider names (Dienstleister)
- Amounts and dates
- Payment categories (Kostenkonto)

**What AI does NOT see:**
- Owner personal data (names, addresses)
- Bank account numbers
- Sensitive personal information

### Legal Basis

- **Art. 6 (1) lit. b GDPR**: Processing necessary for contract performance (WEG management)
- **Art. 6 (1) lit. f GDPR**: Legitimate interest in efficient administration

### Technical Measures (Privacy by Design)

1. **Local Processing**: Ollama runs on-premises, no external data transfer
2. **Data Minimization**: Only relevant payment metadata sent to AI
3. **Anonymization**: Owner names removed from AI context
4. **Retention**: AI processing logs deleted after 90 days
5. **Opt-Out**: Manual categorization always available

### Ollama vs Claude Privacy Comparison

| Aspect | Ollama (Local) | Claude API |
|--------|----------------|------------|
| Data Location | Your server only | Anthropic servers |
| DSGVO Article 28 | Not applicable | DPA required |
| Data Transfer | None | EU to US transfer |
| Audit Trail | Full control | Limited |
| Right to Deletion | Immediate | Request needed |

---

## Related Documentation

- [Payment Auto-Categorization](ai_02_payment-auto-categorize.md)
- [Financial Queries](ai_03_financial-queries.md)
- [Invoice Data Extraction](ai_04_invoice-data-xtraction.md)
- [HGA Quality Checks](ai_05_hga_quality-checks.md)
- [Core System](core_system.md)
- [Local Setup](setup_local.md)
