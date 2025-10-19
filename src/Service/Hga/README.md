# HGA Service Architecture

## Overview

The `App\Service\Hga` namespace provides a clean, testable, and maintainable architecture for Hausgeldabrechnung (HGA) calculations and report generation. This replaces the messy `App\Service\Hausgeldabrechnung` namespace with a well-structured service layer.

## Architecture Principles

1. **Interface-Driven Design**: All major components implement interfaces for better testability and flexibility
2. **Separation of Concerns**: Each service has a single, well-defined responsibility
3. **No Hardcoded Values**: All configuration is externalized
4. **Full Test Coverage**: Every service has comprehensive unit tests
5. **Clean Dependencies**: Services depend on abstractions, not concrete implementations

## Directory Structure

```
src/Service/Hga/
├── Calculation/
│   ├── CostCalculationService.php       # Handles cost categorization and calculation
│   ├── DistributionService.php          # Implements distribution key logic
│   ├── ExternalCostService.php          # Manages external costs (heating/water)
│   ├── PaymentCalculationService.php    # Calculates payments and balances
│   └── TaxCalculationService.php        # Handles §35a EStG tax calculations
├── Configuration/
│   └── HgaConfiguration.php             # Configuration implementation
├── Generator/
│   ├── AbstractReportGenerator.php      # Base class for report generators
│   └── TxtReportGenerator.php           # TXT format generator
├── CalculationInterface.php             # Core calculation contract
├── ConfigurationInterface.php           # Configuration contract
├── HgaService.php                       # Main orchestration service
├── HgaServiceInterface.php              # Main service contract
├── ReportGeneratorInterface.php         # Report generation contract
└── README.md                            # This file
```

## Core Components

### 1. HgaService (Main Orchestrator)

The main entry point that orchestrates all calculations:

```php
$reportData = $hgaService->generateReportData($einheit, 2024);
```

**Responsibilities:**
- Validates inputs
- Orchestrates all calculations
- Handles errors gracefully
- Provides complete report data

### 2. Calculation Services

#### DistributionService
Handles all distribution key calculations (01* through 06*):
- MEA-based distribution (05*)
- Equal distribution (03*)
- Hebeanlage special distribution (06*)
- External cost placeholders (01*, 02*, 04*)

#### CostCalculationService
Categorizes and calculates costs:
- Umlagefähige costs (chargeable to tenants)
- Nicht umlagefähige costs (owner-only costs)
- Rücklagenzuführung (reserve contributions)

#### PaymentCalculationService
Manages payment calculations:
- Advance payments (Soll)
- Actual payments (Ist)
- Payment balances and differences
- Payment history details

#### TaxCalculationService
Calculates tax-deductible amounts per §35a EStG:
- Identifies tax-deductible accounts
- Calculates labor cost percentages from invoices
- Distributes tax benefits by ownership share

#### ExternalCostService
Manages externally calculated costs:
- Heating costs from external billing
- Water costs from external billing
- Validation of external cost data

### 3. Configuration

#### HgaConfiguration
Provides centralized configuration access:
- Monthly payment amounts from database
- Tax-deductible account lists
- Report section headers and texts
- Wirtschaftsplan (budget) data

### 4. Report Generators

#### TxtReportGenerator
Generates plain text reports:
- Properly formatted sections
- Aligned columns
- German number formatting
- Complete HGA report structure

## Usage Example

```php
use App\Service\Hga\HgaServiceInterface;

class HausgeldabrechnungController extends AbstractController
{
    public function __construct(
        private HgaServiceInterface $hgaService
    ) {}
    
    #[Route('/hga/{einheitId}/{year}', name: 'hga_generate')]
    public function generate(int $einheitId, int $year): Response
    {
        $einheit = $this->wegEinheitRepository->find($einheitId);
        
        try {
            // Generate complete report data
            $reportData = $this->hgaService->generateReportData($einheit, $year);
            
            // Generate formatted report
            $txtReport = $this->txtGenerator->generateReport($einheit, $year);
            
            return new Response($txtReport, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8'
            ]);
        } catch (\InvalidArgumentException $e) {
            // Handle validation errors
            return new Response($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            // Handle calculation errors
            return new Response($e->getMessage(), 500);
        }
    }
}
```

## Service Registration (services.yaml)

```yaml
services:
    # Interfaces
    App\Service\Hga\CalculationInterface:
        alias: App\Service\Hga\Calculation\DistributionService
        
    App\Service\Hga\ConfigurationInterface:
        alias: App\Service\Hga\Configuration\HgaConfiguration
        
    App\Service\Hga\HgaServiceInterface:
        alias: App\Service\Hga\HgaService
        
    App\Service\Hga\ReportGeneratorInterface:
        alias: App\Service\Hga\Generator\TxtReportGenerator
        
    # Concrete services
    App\Service\Hga\:
        resource: '../src/Service/Hga/*'
        exclude: '../src/Service/Hga/{README.md}'
```

## Testing

All services have comprehensive test coverage:

```bash
# Run all HGA tests
php bin/phpunit tests/Service/Hga/

# Run specific test suites
php bin/phpunit tests/Service/Hga/Calculation/
php bin/phpunit tests/Service/Hga/HgaServiceTest.php
```

### Test Structure

Each service has a corresponding test class:
- `DistributionServiceTest`: Tests all distribution calculations
- `CostCalculationServiceTest`: Tests cost categorization and grouping
- `PaymentCalculationServiceTest`: Tests payment calculations
- `TaxCalculationServiceTest`: Tests tax deduction logic
- `HgaServiceTest`: Tests main orchestration

### Key Testing Principles

1. **Mock Dependencies**: All external dependencies are mocked
2. **Test Edge Cases**: Include tests for error conditions
3. **Data Providers**: Use for testing multiple scenarios
4. **Assertions**: Test both success and failure paths

## Migration from Old Structure

To migrate from the old `Hausgeldabrechnung` namespace:

1. Update service imports:
   ```php
   // Old
   use App\Service\Hausgeldabrechnung\CalculationService;
   
   // New
   use App\Service\Hga\HgaServiceInterface;
   ```

2. Update service injection:
   ```php
   // Old
   public function __construct(
       CalculationService $calculationService,
       Generator $generator
   ) {}
   
   // New
   public function __construct(
       HgaServiceInterface $hgaService,
       ReportGeneratorInterface $reportGenerator
   ) {}
   ```

3. Update method calls:
   ```php
   // Old
   $costs = $this->calculationService->calculateTotalCosts($zahlungen, $mea, $einheit, $weg);
   
   // New
   $reportData = $this->hgaService->generateReportData($einheit, $year);
   $costs = $reportData['costs'];
   ```

## Benefits of New Architecture

1. **Testability**: Each component can be tested in isolation
2. **Maintainability**: Clear separation of concerns
3. **Extensibility**: Easy to add new report formats or calculation types
4. **Type Safety**: Interfaces provide clear contracts
5. **Error Handling**: Consistent error handling throughout
6. **Performance**: Efficient caching and data access
7. **Documentation**: Self-documenting through interfaces and types

## Future Enhancements

1. **Additional Report Formats**:
   - `PdfReportGenerator`
   - `ExcelReportGenerator`
   - `JsonReportGenerator`

2. **Advanced Calculations**:
   - Multi-year comparisons
   - Predictive budgeting
   - Automated anomaly detection

3. **API Support**:
   - REST API endpoints
   - GraphQL schema
   - Webhook notifications

4. **Audit Trail**:
   - Calculation history
   - Change tracking
   - Version comparison