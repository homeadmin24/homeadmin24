# PDF Parser Feature Roadmap

## Overview
Implement a flexible PDF parsing system that automatically creates Rechnung entities from uploaded invoice PDFs. Each Dienstleister will have its own parser configuration to handle their specific invoice formats.

## Phase 1: Database Schema & Infrastructure (Week 1)

### 1.1 Extend Dienstleister Entity
```sql
ALTER TABLE dienstleister ADD COLUMN parser_config JSON DEFAULT NULL;
ALTER TABLE dienstleister ADD COLUMN parser_class VARCHAR(255) DEFAULT NULL;
ALTER TABLE dienstleister ADD COLUMN ai_parsing_prompt TEXT DEFAULT NULL;
ALTER TABLE dienstleister ADD COLUMN parser_enabled TINYINT(1) DEFAULT 0;
```

**Parser Config JSON Structure:**
```json
{
  "parser_type": "regex|ai|hybrid",
  "field_mappings": {
    "rechnungsnummer": {
      "pattern": "Rechnungsnummer:\\s*([A-Z0-9]+)",
      "type": "regex"
    },
    "betrag_mit_steuern": {
      "pattern": "Gesamtbetrag:\\s*([0-9,]+)\\s*€",
      "type": "regex",
      "transform": "german_decimal"
    }
  },
  "date_formats": ["d.m.Y", "d/m/Y"],
  "decimal_separator": ",",
  "thousand_separator": "."
}
```

### 1.2 Create Parser Result Entity
```php
// src/Entity/ParserResult.php
class ParserResult {
    private ?int $id;
    private Dokument $dokument;
    private ?Rechnung $rechnung;
    private array $extractedData;
    private array $confidence;
    private string $status; // success, partial, failed
    private ?string $errorMessage;
    private \DateTime $createdAt;
}
```

## Phase 2: Parser Architecture (Week 2)

### 2.1 Base Parser Interface
```php
// src/Service/Parser/ParserInterface.php
interface ParserInterface {
    public function parse(string $pdfPath): ParserResult;
    public function validateResult(ParserResult $result): bool;
    public function getRequiredFields(): array;
}
```

### 2.2 Abstract Base Parser
```php
// src/Service/Parser/AbstractPdfParser.php
abstract class AbstractPdfParser implements ParserInterface {
    protected function extractText(string $pdfPath): string;
    protected function parseAmount(string $text): ?float;
    protected function parseDate(string $text): ?\DateTime;
    protected function applyFieldMapping(array $config, string $text): array;
}
```

### 2.3 Specific Parser Implementations
```php
// src/Service/Parser/MaassParser.php
class MaassParser extends AbstractPdfParser {
    // Specific parsing logic for Maaß Gebäudemanagement
}

// src/Service/Parser/GenericRegexParser.php
class GenericRegexParser extends AbstractPdfParser {
    // Uses parser_config from database
}
```

## Phase 3: Parser Factory & Service (Week 3)

### 3.1 Parser Factory
```php
// src/Service/Parser/ParserFactory.php
class ParserFactory {
    public function createParser(Dienstleister $dienstleister): ParserInterface {
        if ($dienstleister->getParserClass()) {
            return new $parserClass($dienstleister->getParserConfig());
        }
        return new GenericRegexParser($dienstleister->getParserConfig());
    }
}
```

### 3.2 Invoice Processing Service
```php
// src/Service/InvoiceProcessingService.php
class InvoiceProcessingService {
    public function processDocument(Dokument $dokument): ?Rechnung {
        // 1. Check if document is invoice + has Dienstleister
        // 2. Get parser from factory
        // 3. Parse PDF
        // 4. Create Rechnung entity
        // 5. Link to Dokument
        // 6. Save ParserResult for audit
    }
}
```

## Phase 4: Integration with Document Upload (Week 4)

### 4.1 Modify DokumentController
```php
// After successful upload:
if ($dokument->getKategorie() === 'rechnungen' && $dokument->getDienstleister()) {
    $this->invoiceProcessor->processDocument($dokument);
}
```

### 4.2 Add Event System
```php
// src/Event/DocumentUploadedEvent.php
class DocumentUploadedEvent {
    public function __construct(private Dokument $dokument) {}
}

// src/EventListener/InvoiceParserListener.php
class InvoiceParserListener {
    public function onDocumentUploaded(DocumentUploadedEvent $event) {
        // Async processing via Messenger
    }
}
```

## Phase 5: AI Integration (Week 5-6)

### 5.1 AI Parser Implementation
```php
// src/Service/Parser/AiParser.php
class AiParser extends AbstractPdfParser {
    private OpenAiClient $aiClient;
    
    public function parse(string $pdfPath): ParserResult {
        $text = $this->extractText($pdfPath);
        $prompt = $this->buildPrompt($text);
        $response = $this->aiClient->complete($prompt);
        return $this->parseAiResponse($response);
    }
}
```

### 5.2 Prompt Templates
```php
// Store in database or config
$promptTemplate = "
Extract the following information from this German invoice:
- Invoice number (Rechnungsnummer)
- Total amount including tax (Gesamtbetrag)
- Tax amount (Mehrwertsteuer)
- Service date (Leistungsdatum)
- Due date (Fälligkeitsdatum)

Invoice text:
{invoice_text}

Return as JSON with these exact keys: rechnungsnummer, betrag_mit_steuern, gesamt_mw_st, datum_leistung, faelligkeitsdatum
";
```

## Phase 6: Admin UI & Configuration (Week 7)

### 6.1 Dienstleister Configuration UI
- Add parser configuration form to Dienstleister edit
- Field mapping builder
- Test parser with sample PDFs
- View parsing history/results

### 6.2 Parser Dashboard
```twig
{# templates/parser/dashboard.html.twig #}
- Success rate by Dienstleister
- Recent parsing results
- Failed parsings requiring manual intervention
- Confidence scores
```

## Phase 7: Testing & Refinement (Week 8)

### 7.1 Unit Tests
```php
// tests/Service/Parser/MaassParserTest.php
class MaassParserTest extends TestCase {
    public function testParseValidInvoice() {
        // Test with sample PDFs
    }
}
```

### 7.2 Integration Tests
- Test full upload → parse → create flow
- Test error handling
- Test AI fallback scenarios

## Implementation Priority

### MVP (Phase 1-3):
1. **Database changes** - Add parser configuration fields
2. **Basic regex parser** - For Maaß Gebäudemanagement
3. **Manual trigger** - Button in document view to parse

### Enhanced (Phase 4-5):
4. **Automatic parsing** - On upload
5. **AI integration** - For complex invoices
6. **Confidence scoring** - Flag low-confidence results

### Advanced (Phase 6-7):
7. **Admin UI** - Configure parsers via web interface
8. **Batch processing** - Parse historical documents
9. **Learning system** - Improve parsers based on corrections

## Technical Considerations

### PDF Libraries
- **Symfony/Panther**: For complex PDFs with forms
- **Smalot/PdfParser**: Simple text extraction
- **pdftotext**: System command for reliability

### AI Services
- **OpenAI GPT-4**: Best accuracy for German invoices
- **Claude API**: Alternative with good multilingual support
- **Local LLM**: For privacy-sensitive data

### Performance
- Use Symfony Messenger for async processing
- Cache parsed results
- Implement retry mechanism for failures

### Security
- Validate all extracted data
- Sanitize before database insertion
- Audit trail for all parsing activities

## Sample Implementation for Maaß

### Step 1: Migration
```php
// migrations/VersionXXX.php
$this->addSql('ALTER TABLE dienstleister ADD parser_config JSON DEFAULT NULL');
$this->addSql('ALTER TABLE dienstleister ADD parser_enabled TINYINT(1) DEFAULT 0');
$this->addSql('ALTER TABLE dienstleister ADD ai_parsing_prompt TEXT DEFAULT NULL');
```

### Step 2: Update Entity
```php
// src/Entity/Dienstleister.php
#[ORM\Column(type: Types::JSON, nullable: true)]
private ?array $parserConfig = null;

#[ORM\Column(type: Types::BOOLEAN)]
private bool $parserEnabled = false;

#[ORM\Column(type: Types::TEXT, nullable: true)]
private ?string $aiParsingPrompt = null;
```

### Step 3: Configure Maaß Parser
```sql
UPDATE dienstleister 
SET parser_config = '{
    "parser_type": "regex",
    "field_mappings": {
        "rechnungsnummer": {
            "pattern": "Rechnungsnr\\.:\\s*([A-Z0-9]+)",
            "type": "regex"
        },
        "betrag_mit_steuern": {
            "pattern": "Gesamtbetrag\\s+([0-9.,]+)\\s*EUR",
            "type": "regex",
            "transform": "german_decimal"
        },
        "datum_leistung": {
            "pattern": "Leistungszeitraum:\\s*([0-9]{2}\\.[0-9]{2}\\.[0-9]{4})",
            "type": "regex",
            "transform": "date"
        }
    }
}',
parser_enabled = 1
WHERE id = 4;
```

## Next Steps

1. **Approve roadmap** and prioritize phases
2. **Create feature branch**: `feature/pdf-parser`
3. **Implement Phase 1**: Database schema
4. **Build MVP parser** for Maaß
5. **Test with real invoices**
6. **Iterate based on results**

This roadmap provides a structured approach to implementing PDF parsing with flexibility for different invoice formats and future AI integration.