# AI Environment Configuration Strategy

**Document Version**: 1.0
**Date**: 2025-12-01
**Status**: Implementation Ready

---

## Overview

This document describes how to configure AI features per environment:
- **Local Development**: Use Ollama (full AI features)
- **Demo/Production**: Disable AI or use pattern-matching only

This allows developers to test and develop AI features locally without requiring AI infrastructure on production servers.

---

## Architecture

### Environment-Based Configuration

```
┌─────────────────────────────────────────────────────┐
│              Application Environments                │
└─────────────────────────────────────────────────────┘
           │                │                │
           ▼                ▼                ▼
    ┌───────────┐    ┌───────────┐    ┌───────────┐
    │   LOCAL   │    │   DEMO    │    │   PROD    │
    └─────┬─────┘    └─────┬─────┘    └─────┬─────┘
          │                │                │
          ▼                ▼                ▼
    ┌───────────┐    ┌───────────┐    ┌───────────┐
    │  Ollama   │    │  Disabled │    │  Disabled │
    │  (Local)  │    │  (Pattern │    │  (Pattern │
    │           │    │   Only)   │    │   Only)   │
    └───────────┘    └───────────┘    └───────────┘
```

### Service Layer Design

```php
// Service automatically adapts based on configuration
ZahlungKategorisierungService
    │
    ├─ Pattern Matcher (always available)
    │
    └─ AI Categorizer (optional, only if enabled)
         │
         └─ OllamaService (only in dev environment)
```

---

## Configuration Files

### 1. Environment Variables

#### `.env` (Local Development)
```env
###> AI Configuration ###
AI_ENABLED=true
AI_PROVIDER=ollama
OLLAMA_URL=http://ollama:11434
OLLAMA_MODEL=llama3.1:8b
###< AI Configuration ###
```

#### `.env.droplet.example` (Production Template)
```env
###> AI Configuration ###
AI_ENABLED=false
# AI_PROVIDER=ollama
# OLLAMA_URL=http://ollama:11434
# OLLAMA_MODEL=llama3.1:8b
###< AI Configuration ###
```

#### `.env.demo` (Demo Environment)
```env
###> AI Configuration ###
AI_ENABLED=false
# AI_PROVIDER=ollama
###< AI Configuration ###
```

### 2. Symfony Configuration

#### `config/packages/hausman.yaml`

```yaml
hausman:
    ai:
        # Enable/disable AI features per environment
        enabled: '%env(bool:AI_ENABLED)%'
        provider: '%env(default::AI_PROVIDER)%'  # 'ollama' or 'none'

        ollama:
            url: '%env(default::OLLAMA_URL)%'
            model: '%env(default::OLLAMA_MODEL)%'
            timeout: 60

        # Fallback behavior when AI is disabled
        fallback:
            # Still use pattern matching
            use_pattern_matching: true
            # Log when AI would have been used (for future planning)
            log_ai_opportunities: true
```

#### `config/services.yaml`

```yaml
services:
    # Ollama service (only instantiated if AI is enabled)
    App\Service\OllamaService:
        arguments:
            $ollamaUrl: '%env(default::OLLAMA_URL)%'
            $model: '%env(default:llama3.1\:8b:OLLAMA_MODEL)%'
            $enabled: '%env(bool:AI_ENABLED)%'

    # Main categorization service
    App\Service\ZahlungKategorisierungService:
        arguments:
            $aiEnabled: '%env(bool:AI_ENABLED)%'
```

---

## Implementation

### Enhanced ZahlungKategorisierungService

```php
<?php
// src/Service/ZahlungKategorisierungService.php

namespace App\Service;

use App\Entity\Zahlung;
use Psr\Log\LoggerInterface;

class ZahlungKategorisierungService
{
    public function __construct(
        private readonly ZahlungskategorieRepository $zahlungskategorieRepository,
        private readonly KostenkontoRepository $kostenkontoRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ?OllamaService $ollama,  // Nullable - may not be available
        private readonly LoggerInterface $logger,
        private readonly bool $aiEnabled = false,  // Environment-based flag
    ) {
    }

    public function kategorisieren(Zahlung $zahlung): bool
    {
        // Skip if already fully categorized
        if (null !== $zahlung->getHauptkategorie() && null !== $zahlung->getKostenkonto()) {
            return false;
        }

        $bezeichnung = mb_strtolower($zahlung->getBezeichnung() ?? '');
        $dienstleister = $zahlung->getDienstleister();
        $dienstleisterName = $dienstleister ? mb_strtolower($dienstleister->getBezeichnung()) : '';
        $dienstleisterArt = $dienstleister ? mb_strtolower($dienstleister->getArtDienstleister() ?? '') : '';

        // STEP 1: Try pattern matching (always available)
        $kategorie = $this->findKategorie($bezeichnung, $dienstleisterName, $dienstleisterArt);
        $kostenkonto = $this->findKostenkonto($bezeichnung, $dienstleisterName, $dienstleisterArt);

        $patternMatchSuccess = (null !== $kategorie && null !== $kostenkonto);

        // STEP 2: Try AI if enabled and pattern matching didn't find both
        if ($this->aiEnabled && !$patternMatchSuccess && $this->ollama) {
            try {
                $aiResult = $this->tryAiCategorization($zahlung, $kategorie, $kostenkonto);

                if ($aiResult['kategorie']) {
                    $kategorie = $aiResult['kategorie'];
                }
                if ($aiResult['kostenkonto']) {
                    $kostenkonto = $aiResult['kostenkonto'];
                }

                // Store AI metadata
                if (isset($aiResult['confidence'])) {
                    $zahlung->setAiConfidence($aiResult['confidence']);
                    $zahlung->setAiReasoning($aiResult['reasoning'] ?? null);
                }

            } catch (\Exception $e) {
                // AI failed, continue with pattern match results
                $this->logger->warning('AI categorization failed', [
                    'zahlung_id' => $zahlung->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        } elseif (!$this->aiEnabled && !$patternMatchSuccess) {
            // Log that AI could have helped here (for future planning)
            $this->logger->info('AI categorization opportunity (but AI disabled)', [
                'zahlung_id' => $zahlung->getId(),
                'bezeichnung' => $zahlung->getBezeichnung(),
                'partner' => $zahlung->getBuchungspartner(),
            ]);
        }

        // Set categorie if found
        if ($kategorie && !$zahlung->getHauptkategorie()) {
            $zahlung->setHauptkategorie($kategorie);
        }

        // Set kostenkonto if found and valid
        if ($kostenkonto) {
            if ($kostenkonto->isActive() && (!$kategorie || $this->isKostenkontoAllowed($kategorie, $kostenkonto))) {
                $zahlung->setKostenkonto($kostenkonto);
            }
        }

        // Auto-assign eigentuemer for Hausgeld payments
        if ($kategorie && 'Hausgeld-Zahlung' === $kategorie->getName() && $dienstleister && !$zahlung->getEigentuemer()) {
            $eigentuemer = $this->findEigentuemer($dienstleister->getBezeichnung());
            if ($eigentuemer) {
                $zahlung->setEigentuemer($eigentuemer);
            }
        }

        // Only count as "categorized" if BOTH kategorie and kostenkonto are set
        return null !== $zahlung->getHauptkategorie() && null !== $zahlung->getKostenkonto();
    }

    private function tryAiCategorization(
        Zahlung $zahlung,
        ?Zahlungskategorie $patternKategorie,
        ?Kostenkonto $patternKostenkonto,
    ): array {
        // Get historical context
        $historicalData = $this->getHistoricalPayments($zahlung);

        // Get available categories
        $kategorien = $this->zahlungskategorieRepository->findAll();

        // Call AI
        $result = $this->ollama->suggestKostenkonto(
            bezeichnung: $zahlung->getBezeichnung(),
            partner: $zahlung->getBuchungspartner() ?? '',
            betrag: (float) $zahlung->getBetrag(),
            buchungstyp: '', // TODO: Add to Zahlung entity if needed
            historicalData: $historicalData,
            availableKategorien: $this->formatKategorienForAI($kategorien),
        );

        // Find matching entities
        $aiKostenkonto = $this->findKostenkontoByNummer($result['kostenkonto'] ?? '');
        $aiKategorie = $this->findKategorieByKostenkonto($aiKostenkonto);

        return [
            'kategorie' => $aiKategorie ?? $patternKategorie,
            'kostenkonto' => $aiKostenkonto ?? $patternKostenkonto,
            'confidence' => $result['confidence'] ?? 0.0,
            'reasoning' => $result['reasoning'] ?? null,
        ];
    }

    private function getHistoricalPayments(Zahlung $zahlung): array
    {
        // Get similar payments from history
        $partner = $zahlung->getBuchungspartner();
        if (!$partner) {
            return [];
        }

        $repository = $this->entityManager->getRepository(Zahlung::class);
        $qb = $repository->createQueryBuilder('z');

        $similar = $qb
            ->where('z.buchungspartner LIKE :partner')
            ->andWhere('z.hauptkategorie IS NOT NULL')
            ->andWhere('z.kostenkonto IS NOT NULL')
            ->andWhere('z.id != :current_id')
            ->setParameter('partner', '%' . $partner . '%')
            ->setParameter('current_id', $zahlung->getId() ?: 0)
            ->orderBy('z.datum', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        return array_map(function (Zahlung $z) {
            return [
                'date' => $z->getDatum()->format('Y-m-d'),
                'partner' => $z->getBuchungspartner(),
                'purpose' => $z->getBezeichnung(),
                'amount' => (float) $z->getBetrag(),
                'kategorie' => $z->getKostenkonto()?->getNummer() . ' - ' . $z->getKostenkonto()?->getBezeichnung(),
            ];
        }, $similar);
    }

    // ... existing methods (findKategorie, findKostenkonto, etc.) ...
}
```

### OllamaService with Disabled State

```php
<?php
// src/Service/OllamaService.php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OllamaService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $ollamaUrl,
        private readonly string $model = 'llama3.1:8b',
        private readonly bool $enabled = false,  // Environment flag
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function suggestKostenkonto(
        string $bezeichnung,
        string $partner,
        float $betrag,
        string $buchungstyp,
        array $historicalData,
        array $availableKategorien,
    ): array {
        if (!$this->enabled) {
            throw new \RuntimeException('OllamaService is disabled in this environment');
        }

        // Check if Ollama is reachable
        if (!$this->isOllamaAvailable()) {
            throw new \RuntimeException('Ollama service is not available at ' . $this->ollamaUrl);
        }

        // Build prompt and call AI
        $prompt = $this->buildCategorizationPrompt(
            $bezeichnung,
            $partner,
            $betrag,
            $buchungstyp,
            $historicalData,
            $availableKategorien
        );

        $response = $this->generate($prompt);
        return $this->extractJson($response);
    }

    private function isOllamaAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->ollamaUrl . '/api/tags', [
                'timeout' => 2,
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    // ... rest of implementation ...
}
```

---

## Docker Compose Configuration

### `docker-compose.yaml` (Base)

```yaml
# Base configuration (no Ollama)
services:
  web:
    build: .
    ports:
      - "8000:80"
    environment:
      - APP_ENV=${APP_ENV:-prod}
      - AI_ENABLED=${AI_ENABLED:-false}
    depends_on:
      - mysql

  mysql:
    image: mysql:9
    environment:
      MYSQL_ROOT_PASSWORD: rootpassword
      MYSQL_DATABASE: homeadmin24
    ports:
      - "3307:3306"
    volumes:
      - mysql_data:/var/lib/mysql

volumes:
  mysql_data:
```

### `docker-compose.dev.yml` (Local Development Override)

```yaml
# Development override - adds Ollama
services:
  web:
    volumes:
      - .:/var/www/html  # Mount host directory for live code changes
    environment:
      - APP_ENV=dev
      - AI_ENABLED=true
      - AI_PROVIDER=ollama
      - OLLAMA_URL=http://ollama:11434
      - OLLAMA_MODEL=llama3.1:8b

  # Ollama service (ONLY in development)
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

volumes:
  ollama_data:  # Additional volume for AI models
```

---

## Usage Examples

### Local Development (with AI)

```bash
# Start all services including Ollama
docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d

# Wait for services to start
sleep 10

# Pull AI model (first time only)
docker exec -it hausman-ollama ollama pull llama3.1:8b

# Verify AI is available
docker exec -it hausman-ollama ollama list

# Run application - AI features are enabled
docker compose exec web php bin/console app:test-ai

# Import payments with AI categorization
docker compose exec web php bin/console app:import-csv /path/to/file.csv
```

### Demo/Production (without AI)

```bash
# Start only web and mysql (no Ollama)
docker compose up -d

# AI features are disabled
# Pattern matching still works
# No AI overhead on production server

# Import payments with pattern matching only
docker compose exec web php bin/console app:import-csv /path/to/file.csv
```

---

## Benefits of This Approach

### ✅ Advantages

1. **No Production Overhead**
   - No AI processing on demo/prod
   - No additional RAM requirements (8GB not needed)
   - No AI model storage (4-6GB per model)
   - Faster startup times

2. **Developer-Friendly**
   - Full AI features available locally
   - Test and develop AI enhancements
   - Easy to toggle on/off per developer

3. **Gradual Rollout**
   - Develop and test AI locally first
   - Enable on production only when ready
   - Easy to roll back if needed

4. **Cost-Effective**
   - No need to upgrade production droplet
   - Keep existing $6-12/month hosting
   - AI development doesn't affect production

5. **Privacy Compliance**
   - No AI in production = no AI privacy concerns (yet)
   - Simplifies GDPR documentation
   - Can add later when ready

### ⚠️ Trade-offs

1. **Categorization Accuracy**
   - Production stays at ~70% pattern matching accuracy
   - Demo doesn't showcase AI features
   - Manual review still needed for 30% of payments

2. **Feature Parity**
   - Developers have features users don't (yet)
   - Need to communicate limitations to users
   - Documentation must clarify what's available where

---

## Future Production Rollout

When ready to enable AI on production:

### Option 1: Enable Ollama on Production

```bash
# Update production .env
AI_ENABLED=true
AI_PROVIDER=ollama
OLLAMA_URL=http://ollama:11434

# Upgrade droplet to 8GB RAM ($48/month)
# Add Ollama to production docker-compose

# Deploy
./deploy-production.sh
```

### Option 2: Use Cloud API (Alternative)

```bash
# Use Claude API instead of Ollama
AI_ENABLED=true
AI_PROVIDER=claude
CLAUDE_API_KEY=sk-ant-...

# No infrastructure changes needed
# Pay per API call (~$3-10/month)
```

---

## Testing

### Test AI Availability

```php
<?php
// src/Command/TestAiCommand.php

namespace App\Command;

use App\Service\OllamaService;
use Symfony\Component\Console\Command\Command;

#[AsCommand(name: 'app:test-ai')]
class TestAiCommand extends Command
{
    public function __construct(
        private readonly OllamaService $ollama,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->ollama->isEnabled()) {
            $output->writeln('❌ AI is disabled in this environment');
            $output->writeln('💡 To enable: Set AI_ENABLED=true in .env');
            return Command::FAILURE;
        }

        $output->writeln('✅ AI is enabled');

        try {
            // Test connection
            $result = $this->ollama->suggestKostenkonto(
                bezeichnung: 'Test Abschlag Gas',
                partner: 'Stadtwerke München',
                betrag: -850.00,
                buchungstyp: 'LASTSCHRIFT',
                historicalData: [],
                availableKategorien: [],
            );

            $output->writeln('✅ Ollama is working!');
            $output->writeln('Suggested: ' . $result['kostenkonto']);
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $output->writeln('❌ Ollama error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
```

---

## Documentation Updates

### README.md Addition

```markdown
## AI Features (Local Development Only)

AI-powered payment categorization is available in local development:

**Enable AI locally:**
```bash
# 1. Start with dev override
docker compose -f docker-compose.yaml -f docker-compose.dev.yml up -d

# 2. Pull AI model (first time)
docker exec -it hausman-ollama ollama pull llama3.1:8b

# 3. Test AI
docker compose exec web php bin/console app:test-ai
```

**Note**: AI features are currently disabled on demo and production environments.
Pattern matching (70% accuracy) is used instead.
```

---

## Summary

**Configuration Strategy:**

| Environment | AI Enabled | Ollama Container | Accuracy | Cost |
|-------------|------------|------------------|----------|------|
| **Local Dev** | ✅ Yes | ✅ Running | 95%+ | Free |
| **Demo** | ❌ No | ❌ Not included | 70% | $0 |
| **Production** | ❌ No | ❌ Not included | 70% | $0 |

**To Enable AI**: Set `AI_ENABLED=true` in environment `.env` file and include Ollama service in docker-compose.

This approach gives you the best of both worlds:
- Full AI development and testing locally
- No production overhead until you're ready
- Easy to enable on production when desired

---

**Document Version**: 1.0
**Last Updated**: 2025-12-01
**Owner**: homeadmin24 Development Team
