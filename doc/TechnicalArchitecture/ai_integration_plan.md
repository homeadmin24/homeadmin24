# AI Integration Implementation Plan for homeadmin24

**Document Version**: 1.0
**Date**: 2025-01-11
**Status**: Planning Phase

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [High-Value AI Features](#high-value-ai-features)
3. [Technology Evaluation](#technology-evaluation)
4. [Ollama Local LLM Deep Dive](#ollama-local-llm-deep-dive)
5. [Implementation Phases](#implementation-phases)
6. [Technical Architecture](#technical-architecture)
7. [Code Examples](#code-examples)
8. [Cost Analysis](#cost-analysis)
9. [Privacy & Compliance](#privacy--compliance)
10. [Next Steps](#next-steps)

---

## Executive Summary

### Vision
Enhance homeadmin24 WEG management system with AI capabilities to automate repetitive tasks, improve data quality, and provide intelligent insights while maintaining strict German data privacy compliance (DSGVO/GDPR).

### Approach
**Privacy-First Hybrid Model**:
- **Local LLM (Ollama)** for sensitive owner/financial data
- **Claude API** (optional) for non-sensitive analysis and demo system
- Configurable switching between providers

### Expected Impact
- **Time Savings**: 10-15 hours/month on administrative tasks
- **Data Quality**: 95%+ auto-categorization accuracy (up from ~70%)
- **Error Reduction**: Catch HGA calculation errors before sending
- **Cost**: ~$5-15/month (API) or free (local) vs. $0 (current manual work)

---

## High-Value AI Features

### 🎯 Priority Matrix

| Feature | Value | Complexity | Priority | Quick Win |
|---------|-------|------------|----------|-----------|
| **Smart Invoice Extraction** | ⭐⭐⭐ | Medium | **HIGH** | ✅ Yes |
| **Intelligent Categorization** | ⭐⭐⭐ | Low | **HIGH** | ✅ Yes |
| **HGA Anomaly Detection** | ⭐⭐⭐ | Medium | **HIGH** | No |
| **Bulk Document Analysis** | ⭐⭐ | Medium | **MEDIUM** | No |
| **Natural Language Queries** | ⭐⭐ | Medium | **MEDIUM** | No |
| **Document Classification** | ⭐⭐ | Low | **MEDIUM** | ✅ Yes |
| **Communication Drafts** | ⭐ | Low | **LOW** | ✅ Yes |
| **Contract Reminders** | ⭐ | Low | **LOW** | ✅ Yes |

---

## 1. Smart Invoice Data Extraction ⭐⭐⭐

### Current State
Manual entry of invoice data into Rechnung entity:
- Rechnungsnummer
- Rechnungsdatum
- Betrag netto, MwSt, Betrag mit Steuern
- Arbeits- und Fahrtkosten (§35a)

**Time**: 5-10 minutes per invoice × 50-100 invoices/year = 4-17 hours/year

### AI Enhancement
OCR + LLM extraction from PDF invoices with automatic field population.

### Example Prompt to AI
```
Extract from this invoice PDF:
- Rechnungsnummer (invoice number)
- Rechnungsdatum (date in YYYY-MM-DD format)
- Betrag netto (net amount as decimal)
- Mehrwertsteuer (VAT as decimal)
- Betrag mit Steuern (gross amount as decimal)
- Arbeits- und Fahrtkosten (labor/travel costs for §35a tax deduction)
- Suggest which Kostenkonto this should be assigned to based on:
  * Invoice description and line items
  * Dienstleister type
  * Historical patterns for similar invoices

Invoice text:
[PDF content extracted via OCR]

Available Kostenkonten:
040100 - Hausmeisterkosten
041400 - Heizungs-Reparaturen
042000 - Wasser
043000 - Allgemeinstrom
[...]

Return ONLY valid JSON matching this structure:
{
    "rechnungsnummer": "string",
    "rechnungsdatum": "YYYY-MM-DD",
    "betrag_netto": 123.45,
    "mehrwertsteuer": 23.45,
    "betrag_mit_steuern": 146.90,
    "arbeits_fahrtkosten": 50.00,
    "kostenkonto_vorschlag": "040100",
    "confidence": 0.95,
    "begruendung": "Kurze Erklärung warum dieses Kostenkonto"
}
```

### Implementation
- **Service**: `InvoiceExtractionService`
- **Controller**: Enhance `RechnungController` with "Analyze PDF" button
- **UI**: Show extracted data for review before saving
- **Validation**: Manual review required if confidence < 0.8

### Success Metrics
- Accuracy: >90% field extraction
- Time savings: 70% reduction in manual entry
- User satisfaction: Positive feedback on ease of use

---

## 2. Intelligent Payment Categorization ⭐⭐⭐

### Current State
Pattern matching in `ZahlungKategorisierungService`:
- Keyword-based matching (e.g., "hausgeld" → Hausgeld-Zahlung)
- Fixed priority order
- ~70% auto-categorization success rate

### AI Enhancement
Context-aware categorization with historical learning and fuzzy pattern recognition.

### Example Prompt to AI
```
Analyze this bank transaction and suggest the correct Kostenkonto:

Transaction Details:
- Partner: "Stadtwerke München"
- Purpose/Verwendungszweck: "Abschlag 10/2024 Vertragskonto 1234567"
- Amount: -842.50 EUR
- Date: 2024-10-15
- Booking Type: LASTSCHRIFT

Available Kostenkonto options with descriptions:
- 043000: Allgemeinstrom (Electricity for common areas)
- 043100: Gas (Gas/heating fuel)
- 042000: Wasser (Water supply)
- 042200: Abwasser (Sewage/wastewater)
- 043200: Müllentsorgung (Waste disposal)

Historical Context (last 3 similar transactions):
1. 2024-07-15: Stadtwerke München, "Abschlag 07/2024", -839.20 EUR → 043100 (Gas)
2. 2024-04-15: Stadtwerke München, "Abschlag 04/2024", -845.60 EUR → 043100 (Gas)
3. 2024-01-15: Stadtwerke München, "Abschlag 01/2024", -892.30 EUR → 043100 (Gas)

Additional Context:
- "Abschlag" means advance payment (Vorauszahlung)
- Stadtwerke München provides gas, electricity, and water services
- Pattern: Regular quarterly payments suggest utility contract

Based on the context (Stadtwerke, "Abschlag" pattern, historical assignments),
suggest the most likely Kostenkonto and provide confidence level.

Return ONLY valid JSON:
{
    "kostenkonto": "043100",
    "confidence": 0.95,
    "reasoning": "Quarterly advance payment to Stadtwerke München. Historical pattern shows consistent assignment to Gas (043100). Amount and frequency match previous gas payments.",
    "alternative": {
        "kostenkonto": "043000",
        "confidence": 0.15,
        "reasoning": "Could be electricity, but less likely given historical data"
    }
}
```

### Implementation
- **Service**: Enhance `ZahlungKategorisierungService`
- **Strategy**: Pattern matching first (fast), then AI fallback
- **Learning**: Store AI suggestions and corrections for future context
- **UI**: Show confidence level and reasoning to user

### Success Metrics
- Accuracy: Increase from 70% to 95%+
- Manual corrections: Track and use as training feedback
- Processing time: <3 seconds per transaction

---

## 3. Natural Language Financial Queries ⭐⭐

### Current State
Users must:
- Navigate to reports
- Filter manually
- Export data
- Calculate in Excel

### AI Enhancement
Ask questions in natural German, get instant answers with context.

### Example Prompts and Responses

**Query 1: Simple Cost Summary**
```
USER: "Wie viel haben wir 2024 für Heizung ausgegeben?"

SYSTEM CONTEXT PROVIDED:
- All Zahlung records for 2024 where kostenkonto.kategorisierungsTyp = UMLAGEFAEHIG_HEIZUNG
- Kostenkonto 006000 (Heizung/Gas) payment totals: 5,839.32 EUR
- Previous year (2023) comparison: 5,215.40 EUR
- Monthly breakdown available

AI RESPONSE:
"Im Jahr 2024 wurden insgesamt 5.839,32 € für Heizung ausgegeben
(Kostenkonto 006000 - Gas). Das entspricht einem Anstieg von 12%
gegenüber 2023 (5.215,40 €).

Monatliche Verteilung:
- Q1 2024: 2.156,80 € (37%)
- Q2 2024: 1.245,20 € (21%)
- Q3 2024: 892,40 € (15%)
- Q4 2024: 1.544,92 € (27%)

Der höchste Verbrauch war im Januar mit 892,50 €."
```

**Query 2: Owner Payment Status**
```
USER: "Hat Herr Müller alle Vorauszahlungen für 2024 bezahlt?"

SYSTEM CONTEXT PROVIDED:
- WegEinheit 0003, Eigentümer: "Max Müller"
- Expected: 12 × 240 EUR = 2,880 EUR
- Actual payments (Hausgeld-Zahlung): List of Zahlung records
- Payment dates and amounts

AI RESPONSE:
"Max Müller (Einheit 0003) hat 2024 alle Vorauszahlungen pünktlich bezahlt:
✅ 11 von 12 Monaten eingegangen (2.640,00 €)
⚠️ Dezember 2024 (240,00 €) noch ausstehend

Status: Offen 240,00 € (Fälligkeit: 01.12.2024)

Zahlungshistorie: Sehr zuverlässig, durchschnittlich 2 Tage vor Fälligkeit."
```

**Query 3: Cost Analysis**
```
USER: "Welche Kostenpositionen sind 2024 am stärksten gestiegen?"

SYSTEM CONTEXT PROVIDED:
- Year-over-year comparison for all Kostenkonto accounts
- Top 5 increases by percentage and absolute amount
- Contributing factors from Zahlung records

AI RESPONSE:
"Top 5 Kostensteigerungen 2024 vs. 2023:

1. 041400 Heizungs-Reparaturen: +2.450,80 € (+64%)
   Grund: Austausch Umwälzpumpe (1.980 €) + Wartung

2. 043100 Gas: +623,92 € (+12%)
   Grund: Preisanstieg + kälterer Winter

3. 050000 Verwaltervergütung: +245,50 € (+5%)
   Grund: Vertragliche Indexanpassung

4. 043200 Müllentsorgung: +124,30 € (+17%)
   Grund: Gebührenerhöhung Stadt München

5. 042000 Wasser: +89,40 € (+8%)
   Grund: Verbrauchssteigerung + Preisanpassung

Empfehlung: Bei HGA 2024 sollten Eigentümer auf die außergewöhnlichen
Heizungsreparaturen hingewiesen werden (einmalige Investition)."
```

### Implementation
- **Endpoint**: `/api/ai/query` (POST)
- **Service**: `AiQueryService`
- **Context Builder**: Fetch relevant data based on query analysis
- **UI**: Chat-style interface on dashboard
- **History**: Store queries and responses for conversation context

### Success Metrics
- Query success rate: >85% understood and answered correctly
- Response time: <5 seconds
- User adoption: Track usage frequency

---

## 4. Document Classification & Search ⭐⭐

### Current State
Manual document categorization when uploading to `Dokument` entity:
- eigentuemer (owner documents)
- umlaufbeschluss (resolutions)
- jahresabschluss (annual statements)
- rechnung (invoices)

### AI Enhancement
Automatic classification with metadata extraction and semantic search.

### Example Prompt to AI
```
Analyze this PDF document and provide classification metadata:

Document Text (first 500 words):
[PDF content]

Tasks:
1. Classify document type:
   - eigentuemer: Owner correspondence, personal documents
   - umlaufbeschluss: WEG resolutions, voting documents
   - jahresabschluss: Annual financial statements
   - rechnung: Invoices from service providers
   - vertrag: Contracts
   - protokoll: Meeting minutes
   - sonstiges: Other

2. Extract metadata:
   - Document date (if mentioned)
   - Related Dienstleister name (if invoice/contract)
   - Related Eigentümer name (if personal document)
   - Key subjects/topics (3-5 keywords)
   - Action items or deadlines (if any)

3. Suggest filename:
   - Format: YYYY-MM-DD_category_description.pdf
   - Example: 2024-10-15_rechnung_hausmeister_oktober.pdf

4. Identify important dates:
   - Contract start/end dates
   - Invoice due dates
   - Meeting dates
   - Deadlines

Return ONLY valid JSON:
{
    "kategorie": "rechnung",
    "confidence": 0.95,
    "datum": "2024-10-15",
    "dienstleister": "Hausmeister Müller GmbH",
    "eigentuemer": null,
    "keywords": ["hausmeister", "oktober", "reinigung"],
    "suggested_filename": "2024-10-15_rechnung_hausmeister_oktober.pdf",
    "wichtige_daten": [
        {"typ": "faelligkeit", "datum": "2024-11-15", "beschreibung": "Zahlungsziel"}
    ],
    "action_items": ["Zahlung veranlassen bis 15.11.2024"],
    "zusammenfassung": "Monatsrechnung Hausmeisterleistungen Oktober 2024"
}
```

### Implementation
- **Service**: `DocumentClassificationService`
- **Upload Flow**: Analyze on upload, suggest metadata
- **Search**: Add semantic search using embeddings
- **UI**: Auto-fill form fields with AI suggestions

### Success Metrics
- Classification accuracy: >90%
- Time savings: 80% reduction in manual categorization
- Search relevance: Improved document discovery

---

## 5. HGA Report Anomaly Detection ⭐⭐⭐

### Current State
HGA (Hausgeldabrechnung) generated automatically but:
- No quality checks before sending
- Errors discovered by owners → disputes
- No comparative analysis with previous years

### AI Enhancement
Intelligent pre-flight review to catch errors and anomalies.

### Example Prompt to AI
```
Review this HGA (Hausgeldabrechnung) for Unit 0003 before sending to owner.

Current Year HGA (2024):
{
    "einheit": "0003",
    "eigentuemer": "Max Mustermann",
    "mea": "290/1000" (29%),
    "gesamtkosten": 7134.75,
    "eigentuemeranteil": 2069.08,
    "vorauszahlungen": 2880.00,
    "guthaben": 810.92,

    "kostenpositionen": {
        "006000_Heizung": {"gesamt": 1693.17, "anteil": 490.82, "prozent": 24%},
        "013000_Versicherung": {"gesamt": 736.94, "anteil": 213.71, "prozent": 10%},
        "040100_Hausmeister": {"gesamt": 5790.54, "anteil": 1679.26, "prozent": 81%},
        "043000_Strom": {"gesamt": 504.13, "anteil": 146.20, "prozent": 7%},
        "050000_Verwaltung": {"gesamt": 1551.47, "anteil": 449.93, "prozent": 22%},
        [...]
    },

    "externe_kosten": {
        "heizkosten_extern": 450.00,
        "wasser_extern": 125.00
    }
}

Historical Context (Previous 3 years):
2023: Gesamtkosten 6.892.40 (Anteil: 1.998.80), Guthaben: 741.20
2022: Gesamtkosten 6.445.30 (Anteil: 1.869.14), Guthaben: 678.90
2021: Gesamtkosten 6.123.50 (Anteil: 1.775.82), Guthaben: 625.40

WEG Context:
- 4 Einheiten total
- MEA distribution: 0001=25%, 0002=23%, 0003=29%, 0004=23%
- Vorauszahlung/month for 0003: 240 EUR (unchanged since 2022)

Tasks:
1. Check for unusual cost increases (>20% vs last year):
   - Identify specific Kostenkonto with large increases
   - Explain possible reasons
   - Flag if unexpected

2. Verify calculations:
   - MEA percentage matches Eigentuemeranteil
   - Sum of all Anteile ≈ Gesamtkosten
   - Vorauszahlungen calculation correct

3. Check for missing cost categories:
   - Compare active Kostenkonto from 2023
   - Flag if expected categories missing

4. Validate external costs:
   - Heizkosten/Wasser match expected ranges
   - No unusual deviations

5. Overall plausibility:
   - Does refund amount make sense?
   - Is total cost trend reasonable?

Return ONLY valid JSON:
{
    "issues": [
        {
            "severity": "error|warning|info",
            "category": "calculation|missing_data|unusual_increase|formatting",
            "kostenkonto": "040100",
            "message": "Hausmeisterkosten um 64% gestiegen (3.540 € → 5.790 €)",
            "details": "Außergewöhnlicher Anstieg. Grund prüfen.",
            "suggestion": "In Anschreiben erklären: Austausch Umwälzpumpe (1.980 €) war einmalige Investition"
        }
    ],
    "overall_assessment": "warning",
    "summary": "HGA ist grundsätzlich korrekt, aber 2 Punkte benötigen Aufmerksamkeit vor Versand.",
    "send_recommendation": "review_required"
}
```

### Implementation
- **Service**: `HgaReviewService`
- **Workflow**: Auto-review before PDF generation
- **UI**: Show issues/warnings in admin interface
- **Approval**: Require explicit approval if warnings exist

### Success Metrics
- Error detection rate: Catch 95%+ of calculation errors
- Dispute reduction: Fewer owner complaints
- Confidence: Managers trust automated checks

---

## 6. Bulk Document Analysis ⭐⭐

### Current State
Monthly admin work:
- Receive 10-20 documents per month
- Manual processing one-by-one
- Tedious data entry

### AI Enhancement
Upload batch of PDFs, get analysis summary and bulk import suggestions.

### Example Prompt to AI
```
Process this batch of 15 documents for October 2024:

Documents (summaries of each):
1. File: stadtwerke_okt24.pdf
   First 200 chars: "Stadtwerke München, Rechnung Nr. RG-2024-10-1234..."

2. File: hausmeister_rechnung.pdf
   First 200 chars: "Hausmeister Service GmbH, Leistungen Oktober..."

[... 13 more files]

For EACH document, provide:
1. Document type (rechnung, vertrag, etc.)
2. Extracted key data (if invoice: number, date, amount, etc.)
3. Suggested Dienstleister linkage
4. Recommended Kostenkonto
5. Confidence level
6. Any missing information or issues

Then provide:
- Summary statistics (total costs by category)
- Consistency checks (duplicate invoices, unusual amounts)
- Suggested bulk import CSV for all invoices
- List of documents needing manual review (low confidence)

Return JSON array with one object per document plus summary.
```

### Implementation
- **Endpoint**: `/bulk-analyze`
- **Service**: `BulkDocumentAnalysisService`
- **Processing**: Queue-based for >10 documents
- **Output**: CSV for bulk import + review list

### Success Metrics
- Processing time: <1 minute for 15 documents
- Accuracy: >85% ready for auto-import
- Time savings: Process monthly docs in 10 minutes vs 2 hours

---

## 7. Eigentümer Communication Drafts ⭐

### Current State
Manual email writing for:
- Meeting invitations
- Payment reminders
- General announcements
- HGA cover letters

### AI Enhancement
Template-based communication generation with WEG-compliant language.

### Example Prompt to AI
```
Draft an email invitation for Eigentümerversammlung (owner meeting):

Context:
{
    "weg": "WEG Musterstraße 123",
    "datum": "2025-02-15",
    "uhrzeit": "18:00",
    "ort": "Gemeinschaftsraum, Musterstraße 123, EG",
    "einladungsfrist": "4 Wochen" (per WEG-Gesetz),

    "tagesordnung": [
        {"punkt": 1, "thema": "Begrüßung und Feststellung der Beschlussfähigkeit"},
        {"punkt": 2, "thema": "Genehmigung der Tagesordnung"},
        {"punkt": 3, "thema": "Genehmigung des Protokolls der letzten Versammlung"},
        {"punkt": 4, "thema": "Vorstellung und Genehmigung der Jahresabrechnung 2024"},
        {"punkt": 5, "thema": "Beschlussfassung: Austausch Aufzugssteuerung (Kostenschätzung: 8.500 €)"},
        {"punkt": 6, "thema": "Beschlussfassung: Neuer Verwaltungsvertrag ab 2026"},
        {"punkt": 7, "thema": "Verschiedenes"}
    ],

    "anlagen": [
        "Jahresabrechnung 2024 (HGA)",
        "Kostenvoranschlag Aufzugssteuerung",
        "Entwurf neuer Verwaltungsvertrag"
    ],

    "verwalter": {
        "name": "WEG Verwaltung",
        "ansprechpartner": "Herr Schmidt",
        "telefon": "089/12345678",
        "email": "schmidt@verwaltung.de"
    }
}

Requirements:
- Formal German business language
- WEG-Gesetz compliant (§24 WEG: proper notice period, agenda disclosure)
- Clear structure with paragraphs
- Professional but friendly tone
- Include all legal requirements
- RSVP request with deadline
- Contact information for questions

Create ONLY the email text, no additional explanations.
Use formal "Sie" form.
```

**Expected Output:**
```
Betreff: Einladung zur Eigentümerversammlung am 15.02.2025

Sehr geehrte Eigentümerinnen und Eigentümer,

hiermit laden wir Sie herzlich zur ordentlichen Eigentümerversammlung der
WEG Musterstraße 123 ein.

TERMIN UND ORT:
Datum: Samstag, 15. Februar 2025
Uhrzeit: 18:00 Uhr
Ort: Gemeinschaftsraum, Musterstraße 123, Erdgeschoss

TAGESORDNUNG:
1. Begrüßung und Feststellung der Beschlussfähigkeit
2. Genehmigung der Tagesordnung
3. Genehmigung des Protokolls der letzten Versammlung vom [Datum]
4. Vorstellung und Genehmigung der Jahresabrechnung 2024
5. Beschlussfassung: Austausch Aufzugssteuerung (Kostenschätzung: 8.500 €)
6. Beschlussfassung: Neuer Verwaltungsvertrag ab 2026
7. Verschiedenes

ANLAGEN:
Im Anhang dieser E-Mail bzw. separat zugehend finden Sie:
- Jahresabrechnung 2024 (HGA) für Ihre Einheit
- Kostenvoranschlag für den Austausch der Aufzugssteuerung
- Entwurf des neuen Verwaltungsvertrags ab 2026

Bitte nehmen Sie sich Zeit, die Unterlagen vor der Versammlung durchzusehen.
Bei Fragen zur Jahresabrechnung oder den Beschlussvorlagen stehe ich Ihnen
gerne zur Verfügung.

RÜCKMELDUNG:
Bitte teilen Sie uns bis zum 08.02.2025 mit, ob Sie persönlich teilnehmen
können oder sich vertreten lassen. Eine formlose Rückmeldung per E-Mail an
schmidt@verwaltung.de genügt.

Bei Verhinderung können Sie einen anderen Eigentümer oder eine Person Ihres
Vertrauens bevollmächtigen. Eine Vollmachtsvorlage senden wir Ihnen auf
Wunsch gerne zu.

Für Rückfragen stehe ich Ihnen unter 089/12345678 oder per E-Mail zur
Verfügung.

Wir freuen uns auf Ihr Erscheinen und eine konstruktive Versammlung.

Mit freundlichen Grüßen

Herr Schmidt
WEG Verwaltung
```

### Implementation
- **Service**: `CommunicationDraftService`
- **Templates**: Predefined types (meeting, reminder, announcement)
- **UI**: Form with context fields → Generate button
- **Edit**: Allow manual editing before sending

### Success Metrics
- Time savings: 80% reduction in email drafting
- Quality: Professional, consistent communication
- Legal compliance: WEG-Gesetz requirements met

---

## 8. Dienstleister Contract Reminders ⭐

### Current State
Manual tracking of contract end dates in spreadsheets or calendars.

### AI Enhancement
Smart reminders with contract renewal draft emails.

### Example Prompt to AI
```
Generate contract renewal reminder for Dienstleister:

Contract Details:
{
    "dienstleister": "Hausmeister Max Müller GmbH",
    "vertrag_art": "Hausmeistervertrag",
    "vertragsbeginn": "2023-04-01",
    "vertragsende": "2025-03-31",
    "kuendigungsfrist": "3 Monate zum Vertragsende",
    "letzter_kuendigungstermin": "2024-12-31",

    "leistungsumfang": [
        "Wöchentliche Treppenhausreinigung",
        "Grünflächenpflege",
        "Winterdienst",
        "Kleinreparaturen"
    ],

    "kosten_letztes_jahr": {
        "gesamt": 5790.54,
        "regular": 4566.16,
        "sonderleistungen": 1224.38
    },

    "bewertung": "Sehr zuverlässig, gute Qualität",

    "vertragshistorie": "Seit 2020, 2x verlängert"
}

Current Status:
- Contract ends in 3 months
- Last cancellation deadline: in 1 month
- Decision needed soon

Create email draft to Dienstleister:
1. Friendly greeting and relationship acknowledgment
2. Mention upcoming contract end
3. Express satisfaction with services (if applicable)
4. Request renewal quote for 2-year extension
5. Suggest reviewing scope based on usage patterns:
   - Note higher Sonderleistungen costs
   - Ask for optimization suggestions
6. Request §35a EStG compliant invoice format
7. Set deadline for response (3 weeks)

Tone: Professional but friendly, German business communication.
```

### Implementation
- **Service**: `ContractReminderService`
- **Schedule**: Cron job checks expiring contracts weekly
- **Email**: Auto-generate draft, admin reviews and sends
- **Tracking**: Log reminders and responses

### Success Metrics
- No missed contract renewals
- Proactive negotiation opportunities
- Better terms through timely engagement

---

## Technology Evaluation

### Option 1: Claude API (Anthropic)

**Pros:**
- ✅ Excellent quality and reasoning
- ✅ Fast inference (~1 second)
- ✅ Simple integration (REST API)
- ✅ Managed service (no maintenance)
- ✅ Supports 200k context window
- ✅ Strong German language support

**Cons:**
- ⚠️ Data sent to Anthropic (DSGVO concern)
- ⚠️ Requires internet connection
- 💰 Cost: ~$0.01-0.03 per request
- ⚠️ Requires Data Processing Agreement (DPA)

**Cost Estimate:**
- 100 invoices/month: $2-3
- 500 transactions/month: $5-15
- Queries: $2-5/month
- **Total: $10-25/month**

---

### Option 2: Ollama (Local LLM)

**Pros:**
- ✅ 100% on-premises (DSGVO compliant)
- ✅ No per-request costs
- ✅ No internet required
- ✅ Full data privacy
- ✅ Easy Docker integration
- ✅ Multiple models available

**Cons:**
- ⚠️ Slightly lower quality vs Claude
- ⚠️ Slower inference (2-5 seconds on CPU)
- ⚠️ Requires more hardware (8-16GB RAM)
- ⚠️ Model updates needed manually
- ⚠️ More complex setup

**Cost Estimate:**
- Hardware: $48/month (upgraded droplet 8GB RAM)
- Maintenance: Minimal
- **Total: $48/month OR free if using existing hardware**

---

### Recommendation: Hybrid Approach

**Best of Both Worlds:**

```php
// Configurable AI provider
$provider = $config->get('ai_provider'); // 'ollama' | 'claude' | 'hybrid'

if ($provider === 'hybrid') {
    // Use Ollama for sensitive owner data
    if ($containsOwnerData) {
        return $this->ollama->analyze($data);
    }

    // Use Claude for faster/better results on non-sensitive data
    return $this->claude->analyze($data);
}
```

**Benefits:**
- Privacy-first: Owner data stays local
- Performance: Fast responses where possible
- Cost-effective: Reduce API usage by 70%
- Flexibility: Easy switching per use case

---

## Ollama Local LLM Deep Dive

### What is Ollama?

**Ollama** is an open-source tool to run large language models (LLMs) locally on your own hardware without sending data to external services.

**Key Features:**
- 🏠 Run AI models on-premises
- 🔒 Complete data privacy (DSGVO/GDPR compliant)
- 💰 No per-request API costs
- 🐳 Easy Docker integration
- 🌍 Works offline
- 🔧 Simple REST API

**Website**: https://ollama.com

---

### Installation Options

#### Option 1: Direct Installation (Ubuntu)
```bash
# On production/demo droplet
curl -fsSL https://ollama.com/install.sh | sh

# Verify installation
ollama --version
```

#### Option 2: Docker (Recommended for homeadmin24)
```bash
# Run Ollama container
docker run -d \
  -v ollama:/root/.ollama \
  -p 11434:11434 \
  --name ollama \
  ollama/ollama
```

#### Option 3: Docker Compose (Best for homeadmin24)
```yaml
# Add to docker-compose.yaml
services:
  web:
    # existing homeadmin24 web service

  mysql:
    # existing database

  ollama:
    image: ollama/ollama:latest
    container_name: hausman-ollama
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama
    environment:
      - OLLAMA_HOST=0.0.0.0:11434
    restart: unless-stopped
    # Optional: GPU support for faster inference
    # deploy:
    #   resources:
    #     reservations:
    #       devices:
    #         - driver: nvidia
    #           count: 1
    #           capabilities: [gpu]

volumes:
  mysql_data:
  ollama_data:  # New volume for AI models
```

---

### Recommended Models for homeadmin24

#### Model Comparison

| Model | Size | RAM Needed | Speed (CPU) | German Quality | Best For |
|-------|------|------------|-------------|----------------|----------|
| **Llama 3.2 3B** | 2GB | 4GB | 🚀 Fast (40-60 tok/s) | ⭐⭐ Good | Quick categorization |
| **Llama 3.1 8B** | 4.7GB | 8GB | ⚡ Medium (30-50 tok/s) | ⭐⭐⭐ Excellent | General tasks (recommended) |
| **Mistral 7B** | 4.1GB | 8GB | ⚡ Medium (30-50 tok/s) | ⭐⭐⭐ Excellent | Structured extraction |
| **Qwen 2.5 7B** | 4.4GB | 8GB | ⚡ Medium (35-55 tok/s) | ⭐⭐⭐ Excellent | Multilingual + reasoning |
| **Gemma 2 9B** | 5.5GB | 16GB | 🐢 Slow (20-35 tok/s) | ⭐⭐ Good | Complex analysis |

**tok/s** = tokens per second (on typical 8-core CPU)

#### Recommended for homeadmin24: **Llama 3.1 8B**

**Why:**
- ✅ Excellent German language support
- ✅ Good reasoning capabilities
- ✅ Runs comfortably on 8GB RAM
- ✅ Fast enough for UI interactions (2-5 seconds per request)
- ✅ Good balance of quality vs. resources

**Alternative: Qwen 2.5 7B**
- Slightly better for multilingual content
- Strong at structured data extraction
- Similar resource requirements

---

### Model Installation

```bash
# Pull Llama 3.1 8B model
docker exec -it hausman-ollama ollama pull llama3.1:8b

# Or pull smaller model for testing
docker exec -it hausman-ollama ollama pull llama3.2:3b

# List installed models
docker exec -it hausman-ollama ollama list

# Test model
docker exec -it hausman-ollama ollama run llama3.1:8b "Erkläre kurz was eine WEG ist"
```

**Model Storage:**
- Models stored in Docker volume: `ollama_data`
- Size: 4-6GB per model
- Can have multiple models installed

---

### Performance Characteristics

#### Inference Speed

| Hardware | Llama 3.1 8B Speed | Time per Invoice |
|----------|-------------------|------------------|
| **CPU (8 cores)** | 30-50 tokens/sec | ~2-5 seconds |
| **Apple M1/M2** | 80-120 tokens/sec | ~1-2 seconds |
| **NVIDIA RTX 4060** | 150-250 tokens/sec | <1 second |

**For homeadmin24:**
- CPU-only is perfectly acceptable
- 2-5 seconds per invoice analysis is fine for UI
- No GPU needed for typical WEG workload

#### Resource Requirements

**Minimum (for testing):**
- 4GB RAM
- 2 CPU cores
- 5GB disk space

**Recommended (for production):**
- 8-16GB RAM (comfortable)
- 4-8 CPU cores
- 10GB disk space (SSD preferred)

**Droplet Sizing:**
| Droplet Type | RAM | vCPUs | Cost/Month | Suitable For |
|--------------|-----|-------|------------|--------------|
| Basic | 4GB | 2 | $24 | Testing only |
| Standard | 8GB | 4 | $48 | **Production (recommended)** |
| Performance | 16GB | 8 | $96 | High-volume or multiple models |

---

### API Integration

#### REST API Basics

Ollama provides a simple REST API on port 11434:

**Endpoints:**
- `POST /api/generate` - Generate completion
- `POST /api/chat` - Chat-style interaction
- `GET /api/tags` - List models
- `POST /api/embeddings` - Generate embeddings

#### Example: Simple Generation

```bash
curl http://localhost:11434/api/generate -d '{
  "model": "llama3.1:8b",
  "prompt": "Erkläre kurz was eine WEG ist.",
  "stream": false
}'
```

**Response:**
```json
{
  "model": "llama3.1:8b",
  "created_at": "2025-01-11T10:30:00Z",
  "response": "Eine WEG (Wohnungseigentümergemeinschaft) ist...",
  "done": true,
  "context": [...],
  "total_duration": 3500000000,
  "load_duration": 1200000000,
  "prompt_eval_count": 25,
  "eval_count": 150,
  "eval_duration": 2300000000
}
```

---

### PHP Service Implementation

See [Technical Architecture](#technical-architecture) section for complete `OllamaService` class.

**Key Methods:**
```php
// Invoice extraction
$result = $ollamaService->analyzeInvoice($pdfText);

// Payment categorization
$result = $ollamaService->suggestKostenkonto(
    $bezeichnung,
    $dienstleister,
    $betrag,
    $historicalData
);

// HGA review
$result = $ollamaService->reviewHga($hgaData, $previousYears);

// Communication draft
$text = $ollamaService->draftCommunication($type, $context);
```

---

### Docker Setup for homeadmin24

#### Step 1: Update docker-compose.yaml

```yaml
# Add ollama service (see installation section above)
```

#### Step 2: Update .env

```env
# .env
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b
AI_PROVIDER=ollama  # or 'claude' or 'hybrid'
```

#### Step 3: Configure Symfony Service

```yaml
# config/services.yaml
services:
    App\Service\OllamaService:
        arguments:
            $ollamaUrl: '%env(OLLAMA_URL)%'
            $model: '%env(OLLAMA_MODEL)%'
```

#### Step 4: Start Services

```bash
# Start all services including Ollama
docker-compose up -d

# Pull model
docker exec -it hausman-ollama ollama pull llama3.1:8b

# Verify
curl http://localhost:11434/api/tags
```

---

### Privacy & Compliance

#### DSGVO/GDPR Compliance

**Ollama Advantages:**
- ✅ 100% on-premises processing
- ✅ No data leaves your infrastructure
- ✅ No third-party data processors
- ✅ No Data Processing Agreement (DPA) needed
- ✅ Full control over data retention
- ✅ Easy to audit and document

**Claude API Challenges:**
- ⚠️ Data sent to Anthropic (US company)
- ⚠️ Requires DPA with Anthropic
- ⚠️ Data may cross borders (even with EU region)
- ⚠️ Subject to US CLOUD Act
- ⚠️ More complex compliance documentation

#### Comparison Matrix

| Aspect | Ollama (Local) | Claude API |
|--------|----------------|------------|
| **Data Location** | ✅ Your server only | ⚠️ Anthropic servers |
| **DSGVO Article 28** | ✅ Not applicable | ⚠️ DPA required |
| **Data Transfer** | ✅ None | ⚠️ EU→US transfer |
| **Audit Trail** | ✅ Full control | ⚠️ Limited |
| **Right to Deletion** | ✅ Immediate | ⚠️ Request needed |
| **Data Breach Risk** | ✅ Your controls | ⚠️ Third party |

---

### Cost Analysis: Ollama vs Claude API

#### Scenario: Typical WEG (4 units)

**Monthly Usage:**
- 50 invoices analyzed
- 300 payments categorized
- 5 HGA reviews
- 20 document classifications
- 10 communication drafts
- 50 natural language queries

**Total AI Requests:** ~435/month

#### Claude API Cost

| Task | Requests | Avg Tokens | Cost per Request | Monthly Cost |
|------|----------|------------|------------------|--------------|
| Invoice analysis | 50 | 2000 | $0.015 | $0.75 |
| Payment categorization | 300 | 800 | $0.006 | $1.80 |
| HGA review | 5 | 4000 | $0.030 | $0.15 |
| Document classification | 20 | 1500 | $0.011 | $0.22 |
| Communications | 10 | 1000 | $0.008 | $0.08 |
| Queries | 50 | 1200 | $0.009 | $0.45 |
| **TOTAL** | **435** | - | - | **~$3.50/month** |

**With growth (10 WEGs):** ~$35/month

#### Ollama Cost

**Infrastructure:**
- Upgraded droplet (8GB RAM): $48/month
- **OR** use existing hardware: $0/month

**Per-request cost:** $0 (free after hardware)

#### Break-Even Analysis

**If using dedicated droplet:**
- Droplet cost: $48/month
- Break-even at: ~14 WEGs (vs Claude API)

**If using existing hardware:**
- Ollama is always cheaper (free)

#### Hybrid Approach Cost

**Strategy:** Ollama for 80% of requests, Claude for complex 20%

| Provider | Requests | Cost |
|----------|----------|------|
| Ollama (local) | 350 | $0 |
| Claude API | 85 | $0.70 |
| Infrastructure | - | $48 |
| **TOTAL** | 435 | **$48.70/month** |

---

### Hybrid Architecture Design

#### Configuration System

```php
// config/packages/hausman.yaml
hausman:
    ai:
        provider: 'hybrid'  # 'ollama' | 'claude' | 'hybrid'

        ollama:
            url: '%env(OLLAMA_URL)%'
            model: 'llama3.1:8b'
            timeout: 60

        claude:
            api_key: '%env(CLAUDE_API_KEY)%'
            model: 'claude-3-5-sonnet-20241022'
            max_tokens: 4096

        hybrid:
            # Use Ollama for these tasks (privacy-sensitive)
            local_tasks:
                - 'invoice_analysis'
                - 'payment_categorization'
                - 'hga_review'

            # Use Claude for these tasks (better quality)
            cloud_tasks:
                - 'communication_drafts'
                - 'document_classification'
                - 'natural_language_queries'
```

#### Smart Router Service

```php
// src/Service/AiRouterService.php

class AiRouterService
{
    public function __construct(
        private OllamaService $ollama,
        private ClaudeService $claude,
        private ParameterBagInterface $params,
    ) {}

    public function route(string $task, callable $ollamaCall, callable $claudeCall): mixed
    {
        $provider = $this->params->get('hausman.ai.provider');

        if ($provider === 'ollama') {
            return $ollamaCall($this->ollama);
        }

        if ($provider === 'claude') {
            return $claudeCall($this->claude);
        }

        // Hybrid mode: check task routing
        $localTasks = $this->params->get('hausman.ai.hybrid.local_tasks');

        if (in_array($task, $localTasks, true)) {
            return $ollamaCall($this->ollama);
        }

        return $claudeCall($this->claude);
    }
}
```

#### Usage Example

```php
// In controller/service
$result = $this->aiRouter->route(
    'invoice_analysis',
    fn($ai) => $ai->analyzeInvoice($pdfText),  // Ollama version
    fn($ai) => $ai->analyzeInvoice($pdfText),  // Claude version
);
```

---

### Monitoring & Debugging

#### Health Check Endpoint

```bash
# Check if Ollama is running
curl http://localhost:11434/api/tags

# Expected response:
{
  "models": [
    {
      "name": "llama3.1:8b",
      "modified_at": "2025-01-11T10:00:00Z",
      "size": 4661234567
    }
  ]
}
```

#### Symfony Command for Testing

```php
// src/Command/TestOllamaCommand.php

#[AsCommand(name: 'app:test-ollama')]
class TestOllamaCommand extends Command
{
    public function __construct(
        private OllamaService $ollama,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing Ollama connection...');

        try {
            $result = $this->ollama->generate('Erkläre kurz was eine WEG ist.');
            $output->writeln('✅ Ollama is working!');
            $output->writeln('Response: ' . $result);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('❌ Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

```bash
# Run test
php bin/console app:test-ollama
```

#### Performance Monitoring

```php
// Add timing to service
class OllamaService
{
    private function generate(string $prompt): string
    {
        $startTime = microtime(true);

        // ... API call ...

        $duration = microtime(true) - $startTime;

        // Log slow requests
        if ($duration > 10) {
            $this->logger->warning('Slow Ollama request', [
                'duration' => $duration,
                'prompt_length' => strlen($prompt),
            ]);
        }

        return $response;
    }
}
```

---

### Troubleshooting

#### Common Issues

**Issue 1: "Connection refused"**
```bash
# Check if Ollama container is running
docker ps | grep ollama

# Check logs
docker logs hausman-ollama

# Restart container
docker-compose restart ollama
```

**Issue 2: "Model not found"**
```bash
# List installed models
docker exec -it hausman-ollama ollama list

# Pull missing model
docker exec -it hausman-ollama ollama pull llama3.1:8b
```

**Issue 3: "Slow inference (>10 seconds)"**
```bash
# Check CPU usage
docker stats hausman-ollama

# Consider:
# - Using smaller model (llama3.2:3b)
# - Upgrading droplet
# - Adding GPU support
```

**Issue 4: "Out of memory"**
```bash
# Check memory usage
docker stats

# Solution: Upgrade droplet or use smaller model
# llama3.2:3b only needs 4GB RAM
```

---

### Production Deployment Checklist

#### Before Going Live

- [ ] Ollama container running and healthy
- [ ] Model downloaded and verified
- [ ] Test command succeeds
- [ ] API integration works
- [ ] Error handling implemented
- [ ] Logging configured
- [ ] Performance acceptable (<5 sec)
- [ ] Fallback strategy defined
- [ ] Monitoring dashboard setup
- [ ] Documentation updated

#### Performance Tuning

```yaml
# docker-compose.yaml - optimize for production
services:
  ollama:
    # ... existing config ...
    deploy:
      resources:
        limits:
          memory: 12G  # Leave room for model + processing
        reservations:
          memory: 8G
    environment:
      - OLLAMA_NUM_PARALLEL=2  # Process 2 requests in parallel
      - OLLAMA_MAX_LOADED_MODELS=1  # Keep only one model in RAM
```

---

### Future Enhancements

#### Planned Improvements

1. **Model Fine-Tuning**
   - Train on WEG-specific vocabulary
   - Improve Kostenkonto suggestions
   - Better understanding of German property law

2. **Embeddings for Semantic Search**
   - Use Ollama embeddings API
   - Search documents by meaning, not just keywords
   - Related document suggestions

3. **Multi-Model Support**
   - Different models for different tasks
   - Smaller model for quick categorization
   - Larger model for complex analysis

4. **Caching Layer**
   - Cache similar prompts
   - Reduce redundant processing
   - Faster response for common queries

5. **Batch Processing**
   - Queue system for multiple invoices
   - Background processing
   - Progress tracking UI

---

## Implementation Phases

### Phase 1: Quick Wins (Weeks 1-2)

**Goal**: Get immediate value with minimal complexity

**Tasks:**
1. ✅ Set up Ollama Docker container
2. ✅ Create `OllamaService` base class
3. ✅ Implement enhanced payment categorization
4. ✅ Add document classification on upload
5. ✅ Test with production data
6. ✅ Deploy to demo system

**Success Criteria:**
- Ollama running stably
- 10%+ improvement in auto-categorization
- Positive user feedback

**Estimated Effort**: 20-30 hours

---

### Phase 2: High-Value Features (Weeks 3-6)

**Goal**: Major time savings on repetitive tasks

**Tasks:**
1. ✅ Implement invoice data extraction
   - PDF text extraction
   - Structured data parsing
   - UI integration
2. ✅ Build HGA anomaly detection
   - Historical comparison logic
   - Issue categorization
   - Admin review interface
3. ✅ Add AI provider configuration
   - Hybrid mode support
   - Claude API integration (optional)
4. ✅ Create admin AI settings page
5. ✅ Comprehensive testing

**Success Criteria:**
- 70% reduction in invoice entry time
- Catch 90%+ of HGA errors
- Configurable AI provider

**Estimated Effort**: 60-80 hours

---

### Phase 3: Advanced Features (Weeks 7-12)

**Goal**: Transform user experience with intelligent features

**Tasks:**
1. ✅ Natural language query system
   - Query parser
   - Context builder
   - Chat-style UI
2. ✅ Bulk document analysis
   - Upload multiple files
   - Batch processing queue
   - Summary reports
3. ✅ Communication drafts
   - Template system
   - Context-aware generation
4. ✅ Contract reminder automation
   - Scheduled jobs
   - Email integration

**Success Criteria:**
- 50+ queries per month
- Bulk processing used regularly
- High satisfaction with communications

**Estimated Effort**: 80-100 hours

---

### Phase 4: Optimization & Scale (Ongoing)

**Goal**: Refine and expand based on usage

**Tasks:**
- Fine-tune prompts based on feedback
- Add more document types
- Improve model selection
- Performance optimization
- User training and documentation

---

## Technical Architecture

### Service Layer

```
┌─────────────────────────────────────────────────┐
│              Application Layer                  │
│  (Controllers, Forms, Commands)                 │
└─────────────────┬───────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│           AI Abstraction Layer                  │
│                                                 │
│  ┌─────────────────┐     ┌──────────────────┐  │
│  │ AiRouterService │────►│ AiConfigService  │  │
│  └────────┬────────┘     └──────────────────┘  │
│           │                                     │
│           ├────────────┬────────────────────┐   │
│           ▼            ▼                    ▼   │
│  ┌─────────────┐ ┌──────────────┐ ┌───────────┐│
│  │OllamaService│ │ ClaudeService│ │HybridMode ││
│  └─────────────┘ └──────────────┘ └───────────┘│
└─────────────────────────────────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────────────┐
│           Feature Services                      │
│                                                 │
│  ┌────────────────────────────────────────┐    │
│  │ InvoiceExtractionService               │    │
│  │ PaymentCategorizationService           │    │
│  │ DocumentClassificationService          │    │
│  │ HgaReviewService                       │    │
│  │ QueryService                           │    │
│  │ CommunicationDraftService              │    │
│  │ BulkAnalysisService                    │    │
│  └────────────────────────────────────────┘    │
└─────────────────────────────────────────────────┘
```

---

### Service Implementation: OllamaService

```php
<?php
// src/Service/OllamaService.php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaService
{
    private const DEFAULT_TIMEOUT = 60;
    private const DEFAULT_TEMPERATURE = 0.1; // Low for factual extraction

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $ollamaUrl,
        private readonly string $model = 'llama3.1:8b',
    ) {}

    /**
     * Analyze invoice PDF and extract structured data
     */
    public function analyzeInvoice(string $invoiceText): array
    {
        $prompt = $this->buildInvoicePrompt($invoiceText);
        $response = $this->generate($prompt);
        return $this->extractJson($response);
    }

    /**
     * Suggest Kostenkonto for payment
     */
    public function suggestKostenkonto(
        string $bezeichnung,
        string $dienstleister,
        float $betrag,
        array $historicalData = []
    ): array {
        $prompt = $this->buildKostenkontoPrompt(
            $bezeichnung,
            $dienstleister,
            $betrag,
            $historicalData
        );

        $response = $this->generate($prompt);
        return $this->extractJson($response);
    }

    /**
     * Review HGA for anomalies
     */
    public function reviewHga(array $hgaData, array $previousYears = []): array
    {
        $prompt = $this->buildHgaReviewPrompt($hgaData, $previousYears);
        $response = $this->generate($prompt);
        return $this->extractJson($response);
    }

    /**
     * Generate communication draft
     */
    public function draftCommunication(string $type, array $context): string
    {
        $prompt = $this->buildCommunicationPrompt($type, $context);
        return $this->generate($prompt);
    }

    /**
     * Classify document
     */
    public function classifyDocument(string $documentText): array
    {
        $prompt = $this->buildDocumentClassificationPrompt($documentText);
        $response = $this->generate($prompt);
        return $this->extractJson($response);
    }

    /**
     * Answer natural language query
     */
    public function answerQuery(string $query, array $context): string
    {
        $prompt = $this->buildQueryPrompt($query, $context);
        return $this->generate($prompt);
    }

    /**
     * Core generation method
     */
    private function generate(
        string $prompt,
        array $options = [],
        int $timeout = self::DEFAULT_TIMEOUT
    ): string {
        $startTime = microtime(true);

        try {
            $response = $this->httpClient->request('POST', $this->ollamaUrl . '/api/generate', [
                'json' => [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => array_merge([
                        'temperature' => self::DEFAULT_TEMPERATURE,
                        'top_p' => 0.9,
                    ], $options),
                ],
                'timeout' => $timeout,
            ]);

            $data = $response->toArray();
            $result = $data['response'] ?? '';

            $duration = microtime(true) - $startTime;

            // Log slow requests
            if ($duration > 10) {
                $this->logger->warning('Slow Ollama request', [
                    'duration' => $duration,
                    'prompt_length' => strlen($prompt),
                    'model' => $this->model,
                ]);
            }

            $this->logger->info('Ollama request completed', [
                'duration' => $duration,
                'model' => $this->model,
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Ollama request failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);
            throw new \RuntimeException('Ollama request failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Extract JSON from LLM response
     */
    private function extractJson(string $response): array
    {
        // LLMs sometimes wrap JSON in markdown code blocks
        $response = preg_replace('/```json\s*|\s*```/', '', $response);
        $response = trim($response);

        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Fallback: try to find JSON in text
            if (preg_match('/\{.*\}/s', $response, $matches)) {
                try {
                    return json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e2) {
                    // Give up
                }
            }

            $this->logger->error('Could not extract JSON from Ollama response', [
                'response' => substr($response, 0, 500),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Could not extract JSON from LLM response: ' . $e->getMessage());
        }
    }

    // Prompt building methods

    private function buildInvoicePrompt(string $invoiceText): string
    {
        return <<<PROMPT
Du bist ein Assistent für deutsche WEG-Verwaltung.
Analysiere diese Rechnung und extrahiere folgende Informationen:

1. Rechnungsnummer
2. Rechnungsdatum (Format: YYYY-MM-DD)
3. Betrag netto (nur Zahl als Decimal)
4. Mehrwertsteuer (nur Zahl als Decimal)
5. Betrag mit Steuern (nur Zahl als Decimal)
6. Arbeits- und Fahrtkosten für §35a (nur Zahl als Decimal, oder 0 wenn nicht vorhanden)
7. Empfohlenes Kostenkonto basierend auf der Rechnungsbeschreibung

Rechnung:
{$invoiceText}

Verfügbare Kostenkonten:
040100 - Hausmeisterkosten
040101 - Hausmeister-Sonderleistungen
041100 - Schornsteinfeger
041400 - Heizungs-Reparaturen
042000 - Wasser
042200 - Abwasser
043000 - Allgemeinstrom
043100 - Gas
043200 - Müllentsorgung
044000 - Brandschutz
045100 - Instandhaltung
046000 - Versicherung: Gebäude
049000 - Nebenkosten Geldverkehr
050000 - Verwaltervergütung

WICHTIG: Antworte NUR mit gültigem JSON in diesem exakten Format:
{
    "rechnungsnummer": "string",
    "rechnungsdatum": "YYYY-MM-DD",
    "betrag_netto": 123.45,
    "mehrwertsteuer": 23.45,
    "betrag_mit_steuern": 146.90,
    "arbeits_fahrtkosten": 50.00,
    "kostenkonto_vorschlag": "040100",
    "confidence": 0.95,
    "begruendung": "Kurze Erklärung warum dieses Kostenkonto"
}
PROMPT;
    }

    private function buildKostenkontoPrompt(
        string $bezeichnung,
        string $dienstleister,
        float $betrag,
        array $historicalData
    ): string {
        $history = !empty($historicalData)
            ? "Historische Zahlungen:\n" . json_encode($historicalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : "";

        return <<<PROMPT
Du bist ein Experte für deutsche WEG-Buchhaltung.

Zahlung:
- Bezeichnung: {$bezeichnung}
- Dienstleister: {$dienstleister}
- Betrag: {$betrag} EUR

{$history}

Verfügbare Kostenkonten:
040100 - Hausmeisterkosten
041400 - Heizungs-Reparaturen
042000 - Wasser
042200 - Abwasser
043000 - Allgemeinstrom
043100 - Gas
043200 - Müllentsorgung
044000 - Brandschutz
045100 - Instandhaltung
046000 - Gebäudeversicherung
049000 - Nebenkosten Geldverkehr
050000 - Verwaltervergütung

Empfehle das passendste Kostenkonto und erkläre kurz warum.

Antworte NUR mit JSON:
{
    "kostenkonto": "040100",
    "confidence": 0.95,
    "reasoning": "Kurze Begründung"
}
PROMPT;
    }

    private function buildHgaReviewPrompt(array $hgaData, array $previousYears): string
    {
        $currentYear = json_encode($hgaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $previousYearsStr = json_encode($previousYears, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Du bist ein Prüfer für Hausgeldabrechnungen (HGA).

Aktuelle HGA:
{$currentYear}

Vorjahre zum Vergleich:
{$previousYearsStr}

Prüfe auf:
1. Ungewöhnliche Kostensteigerungen (>20% vs Vorjahr)
2. Fehlende typische Kostenpositionen
3. Rechenfehler bei Umlagen (MEA-Berechnung)
4. Unplausible Beträge

Antworte mit JSON:
{
    "issues": [
        {
            "severity": "error|warning|info",
            "category": "calculation|missing_data|unusual_increase|formatting",
            "kostenkonto": "040100",
            "message": "Beschreibung des Problems",
            "details": "Detaillierte Erklärung",
            "suggestion": "Vorschlag zur Behebung"
        }
    ],
    "overall_assessment": "ok|warning|error",
    "summary": "Kurze Zusammenfassung",
    "send_recommendation": "ok_to_send|review_required|do_not_send"
}
PROMPT;
    }

    private function buildCommunicationPrompt(string $type, array $context): string
    {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Erstelle einen {$type} für eine WEG-Verwaltung in Deutschland.

Kontext:
{$contextStr}

Anforderungen:
- Formeller aber freundlicher Ton
- Korrekte deutsche Geschäftssprache
- WEG-Gesetz konform
- Klare Struktur mit Absätzen
- Verwende formelle "Sie"-Anrede

Erstelle NUR den Text, keine zusätzlichen Erklärungen oder Metakommentare.
PROMPT;
    }

    private function buildDocumentClassificationPrompt(string $documentText): string
    {
        // Limit text length to avoid token limits
        $textPreview = substr($documentText, 0, 2000);

        return <<<PROMPT
Analysiere dieses Dokument und klassifiziere es:

Dokument (Auszug):
{$textPreview}

Aufgaben:
1. Dokumenttyp bestimmen:
   - eigentuemer: Eigentümer-Dokumente, persönliche Unterlagen
   - umlaufbeschluss: WEG-Beschlüsse, Abstimmungen
   - jahresabschluss: Jahresabrechnungen
   - rechnung: Rechnungen von Dienstleistern
   - vertrag: Verträge
   - protokoll: Versammlungsprotokolle
   - sonstiges: Sonstiges

2. Metadaten extrahieren:
   - Dokumentdatum
   - Bezogener Dienstleister (falls Rechnung/Vertrag)
   - Bezogener Eigentümer (falls persönliches Dokument)
   - Schlüsselwörter (3-5 wichtige Begriffe)

3. Dateinamen vorschlagen:
   Format: YYYY-MM-DD_kategorie_beschreibung.pdf

Antworte NUR mit JSON:
{
    "kategorie": "rechnung",
    "confidence": 0.95,
    "datum": "2024-10-15",
    "dienstleister": "Name oder null",
    "eigentuemer": "Name oder null",
    "keywords": ["keyword1", "keyword2"],
    "suggested_filename": "2024-10-15_rechnung_beschreibung.pdf",
    "zusammenfassung": "Kurze Zusammenfassung des Inhalts"
}
PROMPT;
    }

    private function buildQueryPrompt(string $query, array $context): string
    {
        $contextStr = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
Du bist ein Assistent für WEG-Verwaltung. Beantworte die folgende Frage basierend auf den bereitgestellten Daten.

Frage: {$query}

Verfügbare Daten:
{$contextStr}

Anforderungen:
- Beantworte präzise und sachlich
- Verwende Zahlen und Daten aus dem Kontext
- Strukturiere die Antwort klar (Absätze, Listen wenn sinnvoll)
- Erkläre Zusammenhänge wenn relevant
- Gib konkrete Empfehlungen wenn angebracht

Antworte auf Deutsch in klarer, verständlicher Sprache.
PROMPT;
    }
}
```

---

### Configuration

```yaml
# config/packages/hausman.yaml
hausman:
    ai:
        # Provider: 'ollama' | 'claude' | 'hybrid'
        provider: '%env(AI_PROVIDER)%'

        ollama:
            url: '%env(OLLAMA_URL)%'
            model: '%env(OLLAMA_MODEL)%'
            timeout: 60

        claude:
            api_key: '%env(CLAUDE_API_KEY)%'
            model: 'claude-3-5-sonnet-20241022'
            max_tokens: 4096

        hybrid:
            # Tasks routed to local Ollama (privacy-sensitive)
            local_tasks:
                - invoice_analysis
                - payment_categorization
                - hga_review
                - owner_queries

            # Tasks routed to Claude API (better quality, non-sensitive)
            cloud_tasks:
                - communication_drafts
                - document_classification
                - general_queries
```

```yaml
# config/services.yaml
services:
    App\Service\OllamaService:
        arguments:
            $ollamaUrl: '%env(OLLAMA_URL)%'
            $model: '%env(OLLAMA_MODEL)%'

    App\Service\ClaudeService:
        arguments:
            $apiKey: '%env(CLAUDE_API_KEY)%'

    App\Service\AiRouterService:
        arguments:
            $config: '@parameter_bag'
```

```env
# .env
AI_PROVIDER=ollama
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b
CLAUDE_API_KEY=  # Optional: sk-ant-...
```

---

## Cost Analysis

### Total Cost of Ownership (3 Years)

#### Option 1: Ollama Only

| Cost Item | Year 1 | Year 2 | Year 3 | Total |
|-----------|--------|--------|--------|-------|
| **Hardware** (upgraded droplet) | $576 | $576 | $576 | $1,728 |
| **Development** (40 hours @ $100/hr) | $4,000 | - | - | $4,000 |
| **Maintenance** (5 hours/year @ $100/hr) | $500 | $500 | $500 | $1,500 |
| **TOTAL** | $5,076 | $1,076 | $1,076 | **$7,228** |

**Per Month Average**: $201

---

#### Option 2: Claude API Only

| Cost Item | Year 1 | Year 2 | Year 3 | Total |
|-----------|--------|--------|--------|-------|
| **API Costs** (435 requests/month) | $120 | $180 | $240 | $540 |
| **Development** (20 hours @ $100/hr) | $2,000 | - | - | $2,000 |
| **Maintenance** (2 hours/year @ $100/hr) | $200 | $200 | $200 | $600 |
| **TOTAL** | $2,320 | $380 | $440 | **$3,140** |

**Per Month Average**: $87

**Note**: Assumes usage grows 50% per year

---

#### Option 3: Hybrid (Recommended)

| Cost Item | Year 1 | Year 2 | Year 3 | Total |
|-----------|--------|--------|--------|-------|
| **Hardware** (Ollama) | $576 | $576 | $576 | $1,728 |
| **API Costs** (20% requests) | $24 | $36 | $48 | $108 |
| **Development** (60 hours @ $100/hr) | $6,000 | - | - | $6,000 |
| **Maintenance** (8 hours/year @ $100/hr) | $800 | $800 | $800 | $2,400 |
| **TOTAL** | $7,400 | $1,412 | $1,424 | **$10,236** |

**Per Month Average**: $284

**BUT**: Best privacy compliance + flexibility

---

### ROI Analysis

#### Time Savings (Conservative Estimate)

| Task | Current Time | AI Time | Savings | Frequency | Annual Savings |
|------|--------------|---------|---------|-----------|----------------|
| Invoice entry | 5 min | 1 min | 4 min | 100/year | 400 min (6.7 hrs) |
| Payment categorization | 2 min | 10 sec | 1.5 min | 300/year | 450 min (7.5 hrs) |
| HGA review | 30 min | 10 min | 20 min | 4/year | 80 min (1.3 hrs) |
| Document classification | 2 min | 30 sec | 1.5 min | 200/year | 300 min (5 hrs) |
| Monthly processing | 120 min | 30 min | 90 min | 12/year | 1080 min (18 hrs) |
| **TOTAL** | - | - | - | - | **~39 hours/year** |

**Value of Time Saved:**
- At $50/hour: $1,950/year
- At $100/hour: $3,900/year

**Payback Period:**
- Ollama: 2.6 years (at $100/hr)
- Claude API: 0.6 years (at $100/hr)
- Hybrid: 1.9 years (at $100/hr)

**Plus Intangible Benefits:**
- Fewer errors → less owner disputes
- Better data quality → better decisions
- Improved user experience
- Competitive advantage

---

## Privacy & Compliance

### DSGVO/GDPR Requirements

#### Data Processing Principles (Article 5)

| Principle | Ollama | Claude API |
|-----------|--------|------------|
| **Lawfulness** | ✅ Internal processing | ⚠️ Need legal basis for transfer |
| **Purpose Limitation** | ✅ WEG management only | ✅ Same |
| **Data Minimization** | ✅ Full control | ⚠️ Sent to third party |
| **Accuracy** | ✅ Our responsibility | ✅ Our responsibility |
| **Storage Limitation** | ✅ Our control | ⚠️ Anthropic retention policy |
| **Integrity & Confidentiality** | ✅ Our security measures | ⚠️ Anthropic security + ours |

#### Data Subject Rights

| Right | Implementation |
|-------|----------------|
| **Right to Access** (Art. 15) | Provide AI-processed data on request |
| **Right to Rectification** (Art. 16) | Allow corrections to AI-extracted data |
| **Right to Erasure** (Art. 17) | Delete AI-analyzed documents |
| **Right to Object** (Art. 21) | Opt-out of AI processing (manual fallback) |
| **Right to Data Portability** (Art. 20) | Export original + AI-extracted data |

#### Technical Measures

**Ollama (On-Premises):**
- ✅ Data never leaves infrastructure
- ✅ No third-party processors
- ✅ Full audit trail
- ✅ Encryption at rest (disk level)
- ✅ Access controls (Symfony security)

**Claude API (Cloud):**
- ⚠️ Data sent to Anthropic
- ⚠️ Requires DPA with Anthropic
- ⚠️ Data may transit EU borders
- ✅ Encrypted in transit (HTTPS)
- ⚠️ Retention policy: 30 days (Anthropic)

#### Recommended Approach

**Privacy-First Configuration:**
```yaml
# config/packages/hausman.yaml
hausman:
    ai:
        provider: 'hybrid'

        # Data classification rules
        data_sensitivity:
            high:  # Use Ollama only
                - owner_personal_data
                - financial_details
                - payment_history
            medium:  # Prefer Ollama, fallback Claude
                - invoices
                - contracts
            low:  # Can use Claude
                - generic_queries
                - communication_templates
```

---

### Documentation Template for DSGVO Compliance

```markdown
# AI-Verarbeitung in homeadmin24 - Datenschutz-Dokumentation

## 1. Zweck der Verarbeitung
- Automatische Kategorisierung von Zahlungen
- Extraktion von Rechnungsdaten aus PDFs
- Qualitätssicherung von Hausgeldabrechnungen
- Erstellung von Kommunikationsentwürfen

## 2. Rechtsgrundlage
- Art. 6 Abs. 1 lit. b DSGVO (Vertragserfüllung - WEG-Verwaltung)
- Art. 6 Abs. 1 lit. f DSGVO (Berechtigtes Interesse - Verwaltungseffizienz)

## 3. Verarbeitete Daten
- Rechnungsdaten (Beträge, Daten, Dienstleister)
- Zahlungsinformationen (Buchungstexte, Beträge)
- Kostenzuordnungen
- KEINE direkten Eigentümer-Personendaten bei Cloud-Verarbeitung

## 4. Technische Maßnahmen
- **Lokale Verarbeitung**: Ollama auf eigenem Server
- **Verschlüsselung**: TLS für Datenübertragung
- **Zugriffskontrolle**: Symfony Security (Rollen)
- **Logging**: Audit-Trail für AI-Verarbeitungen
- **Opt-Out**: Manuelle Verarbeitung auf Anfrage möglich

## 5. Auftragsverarbeiter
- **Bei Ollama-Nutzung**: Keine (rein interne Verarbeitung)
- **Bei Claude-Nutzung**: Anthropic PBC (USA) - DPA erforderlich

## 6. Datenweitergabe
- Keine Weitergabe an Dritte außer technische Dienstleister (siehe 5)

## 7. Speicherdauer
- AI-Verarbeitungsergebnisse: Identisch mit Ursprungsdokument
- Logs: 90 Tage

## 8. Betroffenenrechte
- Auskunft über AI-verarbeitete Daten
- Berichtigung extrahierter Daten
- Löschung von Dokumenten und Analysen
- Widerspruch gegen automatische Verarbeitung
```

---

## Next Steps

### Immediate Actions (This Week)

1. **Review and Decision**
   - [ ] Review this implementation plan
   - [ ] Decide on AI provider strategy (Ollama / Claude / Hybrid)
   - [ ] Get stakeholder buy-in

2. **Technical Preparation**
   - [ ] Update docker-compose.yaml with Ollama service
   - [ ] Test Ollama installation on development environment
   - [ ] Pull and test Llama 3.1 8B model
   - [ ] Verify hardware requirements

3. **Privacy Assessment**
   - [ ] Review DSGVO compliance requirements
   - [ ] Decide which data can use Cloud AI
   - [ ] Prepare data protection documentation

### Phase 1 Kickoff (Next 2 Weeks)

1. **Development Setup**
   - [ ] Create `OllamaService` base class
   - [ ] Implement test command
   - [ ] Add configuration system
   - [ ] Set up logging and monitoring

2. **First Feature: Enhanced Categorization**
   - [ ] Integrate AI into `ZahlungKategorisierungService`
   - [ ] Test with historical data
   - [ ] Measure accuracy improvement
   - [ ] Deploy to demo system

3. **Documentation**
   - [ ] Update technical documentation
   - [ ] Create user guide for AI features
   - [ ] Document privacy measures

### Ongoing

- **Monthly Reviews**: Check AI accuracy, performance, costs
- **User Feedback**: Collect and incorporate suggestions
- **Prompt Tuning**: Refine prompts based on results
- **Feature Expansion**: Add new AI capabilities based on priorities

---

## Success Metrics

### Key Performance Indicators (KPIs)

| Metric | Current | Target (6 months) | Measurement |
|--------|---------|-------------------|-------------|
| **Auto-categorization accuracy** | 70% | 95%+ | % correctly categorized payments |
| **Invoice entry time** | 5 min | 1 min | Average time per invoice |
| **HGA error rate** | Unknown | <5% | Errors caught before sending |
| **User satisfaction** | - | 4.5/5 | Survey rating |
| **AI feature usage** | 0% | 80%+ | % of eligible tasks using AI |
| **Time savings** | 0 hrs | 40 hrs/year | Tracked time reductions |

### Qualitative Goals

- ✅ Users feel confident in AI suggestions
- ✅ Reduced manual data entry burden
- ✅ Fewer owner disputes due to errors
- ✅ Competitive advantage in WEG market
- ✅ Full DSGVO compliance maintained

---

## Conclusion

This implementation plan provides a comprehensive roadmap for integrating AI capabilities into homeadmin24 while maintaining strict privacy compliance. The hybrid approach leveraging Ollama for sensitive data and optionally Claude API for non-sensitive tasks offers the best balance of privacy, performance, and cost.

**Recommended Path Forward:**
1. Start with Ollama (Phase 1) - privacy-first
2. Implement quick wins (categorization, classification)
3. Add high-value features (invoice extraction, HGA review)
4. Optionally add Claude API for non-sensitive tasks
5. Continuously refine based on feedback

**Expected Outcome:**
- 40+ hours/year time savings
- 95%+ auto-categorization accuracy
- Improved data quality and user satisfaction
- Full DSGVO compliance
- Competitive advantage in WEG management market

---

**Document Version**: 1.0
**Last Updated**: 2025-01-11
**Next Review**: 2025-02-11
**Owner**: homeadmin24 Development Team

---

## Appendix: Additional Resources

### Links
- [Ollama Official Documentation](https://github.com/ollama/ollama/tree/main/docs)
- [Claude API Documentation](https://docs.anthropic.com/)
- [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html)
- [German WEG Law Resources](https://www.gesetze-im-internet.de/woeigg/)

### Internal Documentation
- [Database Schema](DATABASE_SCHEMA.md)
- [CSV Import System](../CoreSystem/csv_import_system.md)
- [Zahlungskategorie System](../CoreSystem/zahlungskategorie-system.md)
- [Authentication System](../CoreSystem/auth_system_concept.md)

### Example Prompts Library
See inline examples throughout this document for production-ready prompts.
