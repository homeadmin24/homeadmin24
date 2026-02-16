# AI Financial Queries

**Status**: Production-Ready
**Last Updated**: 2026-02-16

---


## Feature Behavior
- Scope: Definiert Natural-Language-Abfragen auf WEG-Finanzdaten inkl. provider-basierter Antwortgenerierung.
- Inputs/Outputs: Input ist Freitextfrage + WEG-Kontext; Output ist eine nachvollziehbare, zahlenbasierte Antwort.
- Invarianten: Antworten muessen auf aktuellem Datenkontext basieren und duerfen keine nicht vorhandenen Daten erfinden.
- Akzeptanz: Beispielfragen liefern konsistente Zahlen, und Nutzer koennen Antworten bewerten (Feedback-Loop).

## Operational Runbook
- Trigger: Bei leeren/inkorrekten Antworten, Endpoint-Fehlern oder Provider-Switch.
- Schritte: Query-Endpunkte testen (`ollama`, `claude`, `compare`), Request/Response-Payloads vergleichen, Kontextdaten pruefen.
- Verifikation: Antworten enthalten konkrete, plausibele Werte und passen zum angefragten Zeitraum/Objekt.
- Recovery/Rollback: Auf stabilen Provider wechseln und Compare-Mode als Diagnose nutzen, bis Prompt/Context-Build korrigiert ist.

## Overview

Natural language financial queries allow users to ask questions about WEG financial data in German and receive instant AI-powered answers. Accessible via the "AI-Anfragen" tab under `/weg`.

### Benefits

- Instant answers without navigating complex UIs
- Natural language - no technical knowledge required
- Context-aware - understands WEG-specific terminology
- Intelligent analysis - automatic year-over-year comparisons

---

## Usage Examples

### Cost Summary

```
USER: "Wie viel haben wir 2024 fuer Heizung ausgegeben?"

AI: Im Jahr 2024 wurden insgesamt 5.839,32 EUR fuer Heizung ausgegeben
(Kostenkonto 006000 - Gas). Das entspricht einem Anstieg von 12%
gegenueber 2023 (5.215,40 EUR).

Monatliche Verteilung:
- Q1 2024: 2.156,80 EUR (37%)
- Q2 2024: 1.245,20 EUR (21%)
- Q3 2024: 892,40 EUR (15%)
- Q4 2024: 1.544,92 EUR (27%)
```

### Owner Payment Status

```
USER: "Hat Herr Mueller alle Vorauszahlungen fuer 2024 bezahlt?"

AI: Max Mueller (Einheit 0003) hat 2024 alle Vorauszahlungen puenktlich bezahlt:
- 11 von 12 Monaten eingegangen (2.640,00 EUR)
- Dezember 2024 (240,00 EUR) noch ausstehend
```

### Cost Analysis

```
USER: "Welche Kostenpositionen sind 2024 am staerksten gestiegen?"

AI: Top 3 Kostensteigerungen 2024 vs. 2023:
1. 041400 Heizungs-Reparaturen: +2.450,80 EUR (+64%)
2. 043100 Gas: +623,92 EUR (+12%)
3. 050000 Verwalterverguetung: +245,50 EUR (+5%)
```

---

## Architecture

### API Endpoints

```
POST /api/ai/query/ollama    - Query via Ollama (local, free)
POST /api/ai/query/claude    - Query via Claude (cloud, paid)
POST /api/ai/query/compare   - Query both, compare side-by-side
GET  /api/ai/query/examples  - Get example queries by category
POST /api/ai/response/{id}/rate - Rate a response (good/bad)
```

### Request/Response

```json
// Request
POST /api/ai/query/ollama
{ "query": "Wie viel haben wir 2024 fuer Heizung ausgegeben?" }

// Response
{
  "success": true,
  "query": "Wie viel haben wir 2024 fuer Heizung ausgegeben?",
  "answer": "Im Jahr 2024 wurden insgesamt 5.839,32 EUR ...",
  "provider": "ollama",
  "response_time": 3.42,
  "response_id": 123,
  "cost": 0.0
}
```

### Query Type Detection

The system automatically detects question types:

| Type | Keywords | Context Size |
|------|----------|-------------|
| Cost queries | kosten, ausgaben, ausgegeben, betrag | Small (<50 payments) |
| Owner payment queries | eigentuemer, vorauszahlung, hausgeld | Medium (~100 payments) |
| Cost increase queries | gestiegen, steigerung, vergleich | Large (~500 payments) |

### Performance

| Query Type | AI Processing | Total Response Time |
|------------|---------------|---------------------|
| Simple cost query | 2-3s | ~3s |
| Owner payment status | 3-4s | ~4s |
| Year comparison | 4-6s | ~6s |

First request may take 60-90s (Ollama model loading). Subsequent requests: 2-6s.

---

## UI

The query interface is in the "AI-Anfragen" tab under `/weg`:

- Text input for natural language questions
- Three buttons: Ollama (DSGVO), Claude, Compare
- Compare mode shows both answers side-by-side with rating buttons
- Example queries loaded from API, clickable to auto-fill
- User feedback (thumbs up/down) stored for Ollama learning

---

## User Feedback Loop

1. User rates responses with thumbs up/down
2. Good Claude examples are stored in `ai_query_response` table
3. Top-rated examples are injected into Ollama prompts (few-shot learning)
4. Over time, Ollama quality approaches Claude quality

See [AI Integration - Learning](ai_01_integration.md#ollama-learning--fine-tuning) for details on the training pipeline.

---

## Related Documentation

- [AI Integration Overview](ai_01_integration.md) - Configuration, learning, privacy
- [Payment Auto-Categorization](ai_02_payment-auto-categorize.md)
