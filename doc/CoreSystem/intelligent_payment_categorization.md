# Intelligent Payment Categorization

**Document Version**: 1.0
**Date**: 2025-12-01
**Status**: Implementation Phase
**Priority**: HIGH ⭐⭐⭐

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current State Analysis](#current-state-analysis)
3. [AI Enhancement Strategy](#ai-enhancement-strategy)
4. [Technical Architecture](#technical-architecture)
5. [Implementation Plan](#implementation-plan)
6. [Testing Strategy](#testing-strategy)
7. [Success Metrics](#success-metrics)
8. [Privacy & Compliance](#privacy--compliance)

---

## Executive Summary

### Objective

Enhance the existing payment categorization system from ~70% accuracy to **95%+ accuracy** using AI-powered context-aware analysis with historical learning and fuzzy pattern recognition.

### Current Limitations

The existing `ZahlungKategorisierungService` uses keyword-based pattern matching with:
- Fixed priority order
- Limited context awareness
- No learning from corrections
- ~70% auto-categorization success rate
- Manual intervention required for 30% of payments

### Solution

Implement a **hybrid AI categorization system** that:
1. **Pattern matching first** (fast, <50ms) for high-confidence cases
2. **AI fallback** (2-5 seconds) for ambiguous or new patterns
3. **Historical learning** from user corrections
4. **Confidence scoring** to guide manual review
5. **Privacy-first** using local Ollama LLM

### Expected Impact

- **Accuracy**: Increase from 70% to 95%+
- **Time Savings**: 7.5 hours/year (300 payments × 1.5 min saved)
- **Data Quality**: Better insights from improved categorization
- **User Experience**: Less manual work, more confidence

---

## Current State Analysis

### ZahlungKategorisierungService Overview

**Location**: `src/Service/ZahlungKategorisierungService.php`

**Current Strategy**:
```php
public function kategorisieren(Zahlung $zahlung): ?Zahlungskategorie
{
    // 1. Automatic pattern matching (keyword-based)
    $kategorie = $this->findMatchingKategorie($zahlung);

    // 2. If no match, mark for manual review
    if (!$kategorie) {
        $zahlung->setKategorisierungStatus('manual_review');
    }

    return $kategorie;
}
```

### Pattern Matching Examples

**Current Pattern Detection**:
```php
// Hausgeld-Zahlung pattern
if (stripos($verwendungszweck, 'hausgeld') !== false) {
    return $kategorien['hausgeld_zahlung'];
}

// Stadtwerke → Gas pattern
if (stripos($partner, 'stadtwerke') !== false) {
    return $kategorien['gas'];  // Fixed assignment
}

// Schornsteinfeger pattern
if (stripos($partner, 'schornstein') !== false) {
    return $kategorien['schornsteinfeger'];
}
```

### Limitations

| Issue | Impact | Example |
|-------|--------|---------|
| **Keyword ambiguity** | Wrong category | "Stadtwerke" could be Gas, Strom, or Wasser |
| **New patterns** | Manual work | New service provider not in keyword list |
| **Context ignorance** | Errors | No consideration of amount, frequency, history |
| **No learning** | Repeated errors | Same pattern fails repeatedly |
| **Fixed priority** | Sub-optimal | First match wins, not best match |

### Success Rate Analysis

**Based on historical data**:
- **70%** automatically categorized (pattern match)
- **30%** require manual review
  - 15% completely ambiguous
  - 10% new patterns
  - 5% edge cases

**Time Cost**:
- Manual categorization: ~2 minutes per payment
- 300 payments/year × 30% = 90 payments
- **Total**: ~3 hours/year manual work

---

## AI Enhancement Strategy

### Hybrid Approach

```
┌─────────────────────────────────────────────────┐
│           Payment Categorization Flow           │
└─────────────────────────────────────────────────┘
                       │
                       ▼
        ┌──────────────────────────┐
        │   1. Quick Pattern Match  │  <50ms
        │   (Existing logic)        │
        └──────────┬─────────────────┘
                   │
            ┌──────┴──────┐
            │ Confidence? │
            └──────┬──────┘
                   │
        ┌──────────┴──────────┐
        │                     │
    HIGH (>95%)          LOW (<95%)
        │                     │
        ▼                     ▼
┌───────────────┐   ┌─────────────────────┐
│  ✓ Accept     │   │  2. AI Analysis      │  2-5s
│  Pattern Match│   │  (Ollama LLM)        │
└───────────────┘   └──────────┬──────────┘
                               │
                        ┌──────┴──────┐
                        │ AI Confidence│
                        └──────┬──────┘
                               │
                    ┌──────────┴──────────┐
                    │                     │
                HIGH (>85%)          LOW (<85%)
                    │                     │
                    ▼                     ▼
            ┌───────────────┐   ┌──────────────────┐
            │  ✓ Use AI     │   │ Manual Review    │
            │  Suggestion   │   │ with AI hint     │
            └───────────────┘   └──────────────────┘
```

### AI Context Enrichment

**What AI sees that pattern matching doesn't**:

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
   - "Vertragskonto" = contract account (suggests ongoing service)
   - Amount similarity indicates same service type

3. **Fuzzy Matching**
   - "Stadtwerke München" ≈ "SWM" ≈ "Stadtwerke Muenchen GmbH"
   - "Schornsteinfeger" ≈ "Kaminkehrer" ≈ "Bezirksschornsteinfeger"

4. **Multi-Factor Analysis**
   - Amount range typical for category
   - Payment frequency (monthly, quarterly, annual)
   - Booking type (LASTSCHRIFT, ÜBERWEISUNG)
   - Date patterns

---

## Technical Architecture

### Component Diagram

```
┌─────────────────────────────────────────────────────────┐
│              ZahlungKategorisierungService              │
│                                                         │
│  ┌─────────────────┐         ┌─────────────────────┐   │
│  │ Pattern Matcher │         │  AI Categorizer     │   │
│  │ (Fast, 70%)     │         │  (Slow, 95%+)       │   │
│  └────────┬────────┘         └──────────┬──────────┘   │
│           │                              │              │
│           ▼                              ▼              │
│  ┌─────────────────────────────────────────────────┐   │
│  │         Confidence Evaluator                    │   │
│  └─────────────────────┬───────────────────────────┘   │
│                        │                                │
│                        ▼                                │
│  ┌─────────────────────────────────────────────────┐   │
│  │         Historical Learning Store               │   │
│  │  (Track corrections for future improvements)    │   │
│  └─────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │      OllamaService            │
         │  (Local LLM Integration)      │
         └───────────────┬───────────────┘
                         │
                         ▼
         ┌───────────────────────────────┐
         │     Ollama Docker Container   │
         │  Model: llama3.1:8b           │
         └───────────────────────────────┘
```

### Database Schema Extensions

**New Entity: `KategorisierungCorrection`**

Track user corrections to improve AI over time:

```sql
CREATE TABLE kategorisierung_correction (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zahlung_id INT NOT NULL,
    suggested_kategorie_id INT,
    actual_kategorie_id INT NOT NULL,
    correction_type ENUM('pattern_failed', 'ai_failed', 'user_override'),
    confidence_score DECIMAL(3,2),
    created_at DATETIME NOT NULL,
    created_by_id INT NOT NULL,

    FOREIGN KEY (zahlung_id) REFERENCES zahlung(id),
    FOREIGN KEY (suggested_kategorie_id) REFERENCES zahlungskategorie(id),
    FOREIGN KEY (actual_kategorie_id) REFERENCES zahlungskategorie(id),
    FOREIGN KEY (created_by_id) REFERENCES user(id)
);
```

**New Field: `Zahlung.ai_confidence`**

```sql
ALTER TABLE zahlung
ADD COLUMN ai_confidence DECIMAL(3,2) DEFAULT NULL,
ADD COLUMN ai_reasoning TEXT DEFAULT NULL;
```

### Service Layer Enhancement

**Enhanced ZahlungKategorisierungService**:

```php
<?php
// src/Service/ZahlungKategorisierungService.php

namespace App\Service;

class ZahlungKategorisierungService
{
    public function __construct(
        private readonly OllamaService $ollama,
        private readonly KategorisierungHistoryService $history,
        private readonly EntityManagerInterface $em,
    ) {}

    public function kategorisieren(Zahlung $zahlung): ?Zahlungskategorie
    {
        // STEP 1: Try fast pattern matching
        $patternMatch = $this->tryPatternMatch($zahlung);

        if ($patternMatch && $patternMatch['confidence'] > 0.95) {
            $zahlung->setAiConfidence($patternMatch['confidence']);
            return $patternMatch['kategorie'];
        }

        // STEP 2: Use AI for ambiguous cases
        try {
            $aiResult = $this->categorizeWithAI($zahlung);

            if ($aiResult['confidence'] > 0.85) {
                $zahlung->setAiConfidence($aiResult['confidence']);
                $zahlung->setAiReasoning($aiResult['reasoning']);
                return $aiResult['kategorie'];
            }

            // STEP 3: Low confidence → manual review with AI hint
            $zahlung->setKategorisierungStatus('manual_review');
            $zahlung->setAiConfidence($aiResult['confidence']);
            $zahlung->setAiReasoning($aiResult['reasoning']);

            return $aiResult['kategorie']; // Suggestion for user

        } catch (\Exception $e) {
            // Fallback to pattern match or manual review
            return $patternMatch['kategorie'] ?? null;
        }
    }

    private function categorizeWithAI(Zahlung $zahlung): array
    {
        // Get historical context
        $historicalData = $this->history->getRelevantHistory($zahlung);

        // Get available categories
        $kategorien = $this->getAvailableKategorien();

        // Call AI
        $result = $this->ollama->suggestKostenkonto(
            bezeichnung: $zahlung->getBezeichnung(),
            partner: $zahlung->getBuchungspartner(),
            betrag: $zahlung->getBetrag(),
            buchungstyp: $zahlung->getBuchungstyp(),
            historicalData: $historicalData,
            availableKategorien: $kategorien,
        );

        // Find matching Zahlungskategorie entity
        $kategorie = $this->findKategorieByKostenkonto($result['kostenkonto']);

        return [
            'kategorie' => $kategorie,
            'confidence' => $result['confidence'],
            'reasoning' => $result['reasoning'],
        ];
    }

    public function recordCorrection(
        Zahlung $zahlung,
        ?Zahlungskategorie $suggested,
        Zahlungskategorie $actual,
        User $user,
    ): void {
        $correction = new KategorisierungCorrection();
        $correction->setZahlung($zahlung);
        $correction->setSuggestedKategorie($suggested);
        $correction->setActualKategorie($actual);
        $correction->setConfidenceScore($zahlung->getAiConfidence());
        $correction->setCorrectionType($this->determineCorrectionType($zahlung, $suggested));
        $correction->setCreatedBy($user);

        $this->em->persist($correction);
        $this->em->flush();

        // Future: Use corrections to fine-tune prompts or retrain models
    }
}
```

---

## AI Prompt Design

### Prompt Template

```php
private function buildCategorizationPrompt(
    string $bezeichnung,
    string $partner,
    float $betrag,
    string $buchungstyp,
    array $historicalData,
    array $kategorien,
): string {
    $historicalStr = $this->formatHistoricalData($historicalData);
    $kategorienStr = $this->formatKategorien($kategorien);

    return <<<PROMPT
Du bist ein Experte für deutsche WEG-Buchhaltung und Zahlungskategorisierung.

Analysiere diese Bankbuchung und ordne sie der passendsten Zahlungskategorie zu:

BUCHUNGSDETAILS:
- Bezeichnung/Verwendungszweck: "{$bezeichnung}"
- Buchungspartner: "{$partner}"
- Betrag: {$betrag} EUR
- Buchungstyp: {$buchungstyp}

{$historicalStr}

VERFÜGBARE KATEGORIEN:
{$kategorienStr}

KONTEXT & REGELN:
1. "Abschlag" oder "Vorauszahlung" deutet auf wiederkehrende Betriebskosten hin
2. Ähnliche Buchungspartner + ähnlicher Betrag + ähnlicher Rhythmus → gleiche Kategorie wie historisch
3. Stadtwerke können Gas, Strom oder Wasser sein → Prüfe Historik und Betragshöhe
4. Versicherungen haben meist jährliche Zahlungen
5. Hausmeister-Leistungen sind meist monatlich oder pro Einsatz

AUFGABE:
Analysiere alle Faktoren (Text, Betrag, Historik, Muster) und empfehle die beste Kategorie.

Antworte NUR mit gültigem JSON in diesem Format:
{
    "kostenkonto": "043100",
    "zahlungskategorie_name": "Gas/Heizung",
    "confidence": 0.95,
    "reasoning": "Quartalsmäßiger Abschlag an Stadtwerke München. Historische Zuordnung zu Gas. Betrag und Frequenz konsistent mit früheren Gas-Zahlungen."
}
PROMPT;
}
```

### Example AI Input/Output

**Input Scenario: Ambiguous Payment**
```json
{
  "bezeichnung": "Abschlag 10/2024 Vertragskonto 1234567",
  "partner": "Stadtwerke München",
  "betrag": -842.50,
  "buchungstyp": "LASTSCHRIFT",
  "historical": [
    {
      "date": "2024-07-15",
      "partner": "Stadtwerke München",
      "purpose": "Abschlag 07/2024",
      "amount": -839.20,
      "kategorie": "043100 - Gas"
    },
    {
      "date": "2024-04-15",
      "partner": "Stadtwerke München",
      "purpose": "Abschlag 04/2024",
      "amount": -845.60,
      "kategorie": "043100 - Gas"
    }
  ]
}
```

**AI Output**:
```json
{
  "kostenkonto": "043100",
  "zahlungskategorie_name": "Gas/Heizung",
  "confidence": 0.95,
  "reasoning": "Quartalsmäßiger Abschlag an Stadtwerke München. Historische Zuordnung zu Gas (Kostenkonto 043100). Betrag (~840 EUR) und Frequenz (alle 3 Monate) konsistent mit früheren Gas-Zahlungen. Keine Hinweise auf Strom oder Wasser."
}
```

**Result**: ✅ Automatically categorized with 95% confidence

---

## Implementation Plan

### Phase 1: Foundation (Week 1)

#### Tasks

1. **Set up Ollama Docker container**
   - [ ] Add Ollama service to `docker-compose.yaml`
   - [ ] Pull `llama3.1:8b` model
   - [ ] Test basic connectivity

2. **Create base OllamaService**
   - [ ] `src/Service/OllamaService.php`
   - [ ] Implement `generate()` method
   - [ ] Add error handling and logging
   - [ ] Create test command

3. **Database migrations**
   - [ ] Create `KategorisierungCorrection` entity
   - [ ] Add `ai_confidence` and `ai_reasoning` fields to `Zahlung`
   - [ ] Generate and run migrations

4. **Configuration**
   - [ ] Add AI settings to `config/packages/hausman.yaml`
   - [ ] Environment variables in `.env`

**Deliverables**:
- ✅ Ollama running and responding
- ✅ Basic service integration
- ✅ Database ready for AI data

---

### Phase 2: AI Integration (Week 2)

#### Tasks

1. **Enhance OllamaService**
   - [ ] Implement `suggestKostenkonto()` method
   - [ ] Add prompt templates
   - [ ] JSON response parsing
   - [ ] Confidence score handling

2. **Create KategorisierungHistoryService**
   - [ ] Fetch relevant historical payments
   - [ ] Format data for AI context
   - [ ] Cache frequently accessed patterns

3. **Update ZahlungKategorisierungService**
   - [ ] Add AI categorization fallback
   - [ ] Implement confidence threshold logic
   - [ ] Preserve existing pattern matching

4. **Testing with real data**
   - [ ] Export 100 historical payments
   - [ ] Test AI categorization accuracy
   - [ ] Compare against actual categories
   - [ ] Measure performance

**Deliverables**:
- ✅ Working AI categorization
- ✅ Accuracy >85% on test set
- ✅ Response time <5 seconds

---

### Phase 3: UI & Feedback Loop (Week 3)

#### Tasks

1. **Update Zahlung admin interface**
   - [ ] Show AI confidence score
   - [ ] Display reasoning in UI
   - [ ] Visual indicator for AI-categorized payments
   - [ ] "Accept AI suggestion" button

2. **Manual review workflow**
   - [ ] Filter payments by confidence threshold
   - [ ] Bulk review interface
   - [ ] Quick correction actions

3. **Correction tracking**
   - [ ] Record user corrections
   - [ ] Show correction history
   - [ ] Analytics dashboard

4. **Learning feedback**
   - [ ] Track correction patterns
   - [ ] Identify weak spots
   - [ ] Prepare for future fine-tuning

**Deliverables**:
- ✅ User-friendly review interface
- ✅ Correction tracking active
- ✅ Feedback loop established

---

### Phase 4: Optimization & Monitoring (Week 4)

#### Tasks

1. **Performance optimization**
   - [ ] Cache AI results for identical payments
   - [ ] Batch processing for imports
   - [ ] Async processing for large datasets

2. **Prompt refinement**
   - [ ] Analyze failure cases
   - [ ] Improve prompt based on corrections
   - [ ] A/B test different prompts

3. **Monitoring dashboard**
   - [ ] Accuracy tracking over time
   - [ ] Confidence distribution
   - [ ] Processing time metrics
   - [ ] Cost tracking (if using API)

4. **Documentation**
   - [ ] User guide for AI features
   - [ ] Admin guide for corrections
   - [ ] Privacy documentation update

**Deliverables**:
- ✅ Optimized performance
- ✅ Monitoring in place
- ✅ Complete documentation

---

## Testing Strategy

### Test Scenarios

#### 1. High-Confidence Pattern Match (Should NOT use AI)

```php
public function testHighConfidencePatternMatch(): void
{
    $zahlung = new Zahlung();
    $zahlung->setBezeichnung('Hausgeld Wohnung 3 November 2024');
    $zahlung->setBuchungspartner('Max Mustermann');
    $zahlung->setBetrag(240.00);

    $kategorie = $this->service->kategorisieren($zahlung);

    $this->assertEquals('Hausgeld-Zahlung', $kategorie->getName());
    $this->assertGreaterThan(0.95, $zahlung->getAiConfidence());
    $this->assertNull($zahlung->getAiReasoning()); // Pattern match, no AI used
}
```

#### 2. Ambiguous Case (Should use AI)

```php
public function testAmbiguousCaseUsesAI(): void
{
    $zahlung = new Zahlung();
    $zahlung->setBezeichnung('Abschlag 10/2024');
    $zahlung->setBuchungspartner('Stadtwerke München');
    $zahlung->setBetrag(-842.50);

    $kategorie = $this->service->kategorisieren($zahlung);

    $this->assertEquals('Gas', $kategorie->getKostenkonto()->getNummer());
    $this->assertNotNull($zahlung->getAiReasoning()); // AI was used
    $this->assertGreaterThan(0.85, $zahlung->getAiConfidence());
}
```

#### 3. Historical Learning

```php
public function testHistoricalLearning(): void
{
    // Create historical pattern
    $historical = $this->createHistoricalPayments([
        ['partner' => 'SWM', 'amount' => -850, 'kategorie' => 'Gas'],
        ['partner' => 'SWM', 'amount' => -855, 'kategorie' => 'Gas'],
        ['partner' => 'SWM', 'amount' => -840, 'kategorie' => 'Gas'],
    ]);

    // New payment with similar pattern
    $zahlung = new Zahlung();
    $zahlung->setBezeichnung('Energiekosten 12/2024');
    $zahlung->setBuchungspartner('Stadtwerke München GmbH'); // Different format
    $zahlung->setBetrag(-845.00); // Similar amount

    $kategorie = $this->service->kategorisieren($zahlung);

    $this->assertEquals('Gas', $kategorie->getKostenkonto()->getNummer());
    $this->assertStringContainsString('historisch', $zahlung->getAiReasoning());
}
```

#### 4. Low Confidence → Manual Review

```php
public function testLowConfidenceRequiresManualReview(): void
{
    $zahlung = new Zahlung();
    $zahlung->setBezeichnung('Zahlung für Dienstleistung');
    $zahlung->setBuchungspartner('Unbekannte Firma GmbH');
    $zahlung->setBetrag(-1234.56);

    $kategorie = $this->service->kategorisieren($zahlung);

    $this->assertEquals('manual_review', $zahlung->getKategorisierungStatus());
    $this->assertLessThan(0.85, $zahlung->getAiConfidence());
    $this->assertNotNull($kategorie); // Still provides suggestion
}
```

### Performance Benchmarks

| Metric | Target | Measurement |
|--------|--------|-------------|
| Pattern match time | <50ms | 95th percentile |
| AI categorization time | <5s | Average |
| Accuracy (with AI) | >95% | Correct on first try |
| Manual review rate | <10% | % requiring human input |

---

## Success Metrics

### Quantitative Metrics

| Metric | Current | Target (3 months) | How to Measure |
|--------|---------|-------------------|----------------|
| **Auto-categorization accuracy** | 70% | 95%+ | % correctly categorized (no correction needed) |
| **Manual review rate** | 30% | <10% | % payments requiring human review |
| **Time per payment** | 2 min | 30 sec | Average time from import to categorization |
| **Correction rate** | Unknown | <5% | % of AI suggestions corrected by users |
| **AI usage rate** | 0% | 25% | % of payments using AI (vs pattern match) |

### Qualitative Metrics

- ✅ Users trust AI suggestions
- ✅ Reduced frustration with manual categorization
- ✅ Improved data quality for reports
- ✅ Faster monthly closing process

### Monitoring Dashboard

**Track over time**:
1. Accuracy trend (weekly)
2. Confidence distribution histogram
3. Most common correction patterns
4. Average processing time
5. Pattern match vs AI usage ratio

---

## Privacy & Compliance

### DSGVO/GDPR Considerations

#### Data Processed by AI

**What AI sees**:
- ✅ Payment descriptions (Verwendungszweck)
- ✅ Service provider names (Dienstleister)
- ✅ Amounts and dates
- ✅ Payment categories (Kostenkonto)

**What AI does NOT see**:
- ❌ Owner personal data (names, addresses)
- ❌ Bank account numbers (only last 4 digits if needed)
- ❌ Sensitive personal information

#### Legal Basis

- **Art. 6 (1) lit. b GDPR**: Processing necessary for contract performance (WEG management)
- **Art. 6 (1) lit. f GDPR**: Legitimate interest in efficient administration

#### Technical Measures

**Privacy by Design**:
1. **Local Processing**: Ollama runs on-premises, no external data transfer
2. **Data Minimization**: Only relevant payment metadata sent to AI
3. **Anonymization**: Remove owner names from AI context
4. **Retention**: AI processing logs deleted after 90 days
5. **Opt-Out**: Manual categorization always available

#### Documentation

**Add to privacy policy**:
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

## Future Enhancements

### Phase 2 Features (3-6 months)

1. **Multi-Language Support**
   - English payment descriptions
   - Multi-lingual service providers

2. **Advanced Pattern Learning**
   - Automatically detect new patterns from corrections
   - Suggest new Zahlungskategorie rules

3. **Batch Import Intelligence**
   - Analyze entire CSV before importing
   - Detect anomalies and duplicates
   - Suggest bulk category assignments

4. **Predictive Categorization**
   - Predict future payments based on history
   - Alert on missing expected payments

5. **Integration with Invoice Analysis**
   - Link categorized payments to invoices
   - Cross-validate amounts

---

## References

### Related Documentation

- [AI Integration Plan](../TechnicalArchitecture/ai_integration_plan.md)
- [Zahlungskategorie System](zahlungskategorie-system.md)
- [CSV Import System](csv_import_system.md)
- [Database Schema](../TechnicalArchitecture/DATABASE_SCHEMA.md)

### External Resources

- [Ollama Documentation](https://github.com/ollama/ollama/tree/main/docs)
- [Llama 3.1 Model Card](https://huggingface.co/meta-llama/Llama-3.1-8B)
- [GDPR Guidelines on Automated Decision Making](https://gdpr-info.eu/art-22-gdpr/)

---

**Document Version**: 1.0
**Last Updated**: 2025-12-01
**Next Review**: 2026-01-01
**Owner**: homeadmin24 Development Team
