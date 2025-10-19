# **CalculationService Improvement Tasks** 📋

## **🔥 Critical Architecture Issues (High Priority)**

### **1. Eliminate Dual Calculation Paths** ✅ **COMPLETED**
- **Problem**: Tax calculation uses `calculateHebeanlageAnteil()`, main calculation uses `DistributionCalculationService`
- **Solution**: Unified all calculations to use single DistributionCalculationService
- **Changes**: Removed `calculateHebeanlageAnteil()` method, updated tax calculation to use DistributionCalculationService
- **Result**: Single source of truth for all Hebeanlage calculations using database-driven approach

### **2. Remove All Hardcoded Distribution Logic**
- **Problem**: MEA percentages, unit counts, distribution ratios scattered in code
- **Locations**:
  - `CalculationService.php:522-525` (Umlageschlüssel display)
  - ~~`CalculationService.php:961-967` (Hebeanlage calculation)~~ ✅ **COMPLETED** 
  - `DistributionCalculationService.php:217-224` (heating factors)
  - Various MEA hardcoded values (0.19, 0.25, 0.29, 0.27)
- **Task**: Move to database configuration tables
- **Effort**: 3-4 days

### **3. Fix Method Responsibility Violations**
- **Problem**: `getCostsByKategorisierungsTyp()` does too much (grouping + calculation + distribution)
- **Task**: Split into separate responsibilities:
  - `PaymentGrouper` - groups payments by kostenkonto  
  - `CostCalculator` - calculates totals
  - `DistributionCalculator` - applies distribution keys
- **Effort**: 2-3 days

## **🚧 Data Consistency Issues (High Priority)**

### **4. Create WEG Configuration Management**
- **Problem**: WEG-specific settings scattered (unit counts, MEA values, distribution rules)
- **Task**: Create `WegConfiguration` entity with:
  - Unit count per WEG
  - MEA values per unit
  - Custom distribution rules
  - Monthly payment amounts
- **Database**: New `weg_configuration` table
- **Effort**: 2-3 days

### **5. Standardize Distribution Key Storage**
- **Problem**: Distribution logic exists in multiple places
- **Task**: Create `distribution_rule` table:
  ```sql
  CREATE TABLE distribution_rule (
    id INT PRIMARY KEY,
    weg_id INT,
    kostenkonto_id INT, 
    distribution_key VARCHAR(10),
    custom_config JSON,
    is_active BOOLEAN
  );
  ```
- **Effort**: 1-2 days

### **6. Fix External Cost Integration**
- **Problem**: Heating/Water costs (01*, 02*) use simplified hardcoded factors
- **Task**: Create proper external cost import system
- **Files**: `DistributionCalculationService.php:217-245`
- **Effort**: 3-5 days (depends on external data format)

## **⚡ Performance & Maintainability (Medium Priority)**

### **7. Extract Business Logic from Controllers**
- **Problem**: HGA generation mixed with HTTP handling
- **Task**: Create dedicated `HgaApplicationService` 
- **Files**: Various HGA controller methods
- **Effort**: 1-2 days

### **8. Implement Proper Value Objects**
- **Problem**: Primitive obsession (floats for money, strings for distribution keys)
- **Task**: Create:
  - `Money` value object (amount + currency)
  - `DistributionKey` enum/value object  
  - `MEA` value object (with validation)
  - `PaymentPeriod` value object
- **Effort**: 2-3 days

### **9. Add Comprehensive Validation**
- **Problem**: No validation for business rules (MEA sum = 1.0, payment amounts, etc.)
- **Task**: Add domain validators:
  - `MEAValidator` - ensures WEG MEA totals = 1.0
  - `PaymentValidator` - validates payment amounts/dates
  - `DistributionValidator` - checks distribution rule consistency
- **Effort**: 1-2 days

## **🧪 Testing & Quality (Medium Priority)**

### **10. Create Calculation Test Suite**
- **Problem**: No unit tests for complex business logic
- **Task**: Create test coverage for:
  - Each distribution key calculation
  - Tax calculation logic  
  - Rücklagenzuführung handling
  - Edge cases (zero amounts, missing data)
- **Reference Data**: Use 2020-2024 HGA outputs as golden master tests
- **Effort**: 3-4 days

### **11. Add Calculation Auditing**
- **Problem**: Hard to debug why calculations change
- **Task**: Add calculation step logging:
  ```php
  class CalculationAudit {
      public function logStep(string $step, array $input, array $output): void;
      public function getCalculationTrace(WegEinheit $unit, int $year): array;
  }
  ```
- **Effort**: 1-2 days

### **12. Implement Calculation Versioning**
- **Problem**: Can't compare different calculation versions
- **Task**: Tag calculations with version/algorithm identifier
- **Use Case**: A/B testing old vs new logic
- **Effort**: 1 day

## **🔧 Configuration & Flexibility (Low Priority)**

### **13. Make Tax Rules Configurable**
- **Problem**: §35a EStG rules hardcoded for specific years
- **Task**: Create `tax_rule` configuration system
- **Future-Proof**: Handle tax law changes without code deployment
- **Effort**: 2-3 days

### **14. Support Multiple Output Formats**
- **Problem**: TXT/PDF generators have duplicated logic
- **Task**: Create template-based output system
- **Formats**: TXT, PDF, Excel, JSON API
- **Effort**: 3-4 days

### **15. Add Multi-WEG Support**
- **Problem**: System assumes single WEG context  
- **Task**: Make all calculations WEG-aware
- **Database**: Ensure all entities properly reference WEG
- **Effort**: 2-3 days

## **📊 Monitoring & Debugging (Low Priority)**

### **16. Add Performance Monitoring**
- **Problem**: HGA generation can be slow with large datasets
- **Task**: Add timing/profiling for calculation steps
- **Tools**: Symfony Profiler integration
- **Effort**: 1 day

### **17. Create Calculation Diff Tool**
- **Problem**: Hard to see what changed between HGA versions
- **Task**: Build diff comparison tool for HGA outputs
- **Use Case**: Validate after algorithm changes
- **Effort**: 2 days

### **18. Add Data Quality Checks**
- **Problem**: Bad data can cause incorrect calculations
- **Task**: Pre-calculation validation:
  - Missing payments
  - Duplicate entries  
  - Invalid amounts
  - Inconsistent dates
- **Effort**: 1-2 days

---

## **📈 Estimated Total Effort**

| Priority | Tasks | Days | Weeks |
|----------|-------|------|-------|
| **High** | 6 tasks | 14-20 days | **3-4 weeks** |
| **Medium** | 6 tasks | 10-14 days | **2-3 weeks** |
| **Low** | 6 tasks | 12-16 days | **2.5-3 weeks** |
| **TOTAL** | **18 tasks** | **36-50 days** | **7.5-10 weeks** |

## **🎯 Recommended Implementation Order**

### **Phase 1: Foundation (Week 1-2)**
1. Eliminate dual calculation paths (#1)
2. Create WEG configuration management (#4)  
3. Fix method responsibilities (#3)

### **Phase 2: Data Consistency (Week 3-4)**
4. Remove hardcoded values (#2)
5. Standardize distribution keys (#5)
6. Add comprehensive validation (#9)

### **Phase 3: Quality & Testing (Week 5-6)**  
7. Create calculation test suite (#10)
8. Implement value objects (#8)
9. Add calculation auditing (#11)

### **Phase 4: Polish & Features (Week 7-8)**
10. Extract business logic (#7)
11. Support multiple output formats (#14)
12. Add performance monitoring (#16)

---

## **🤔 Rewrite vs Improvement Decision**

### **Arguments for Complete Rewrite:**
- **Clean Architecture**: Start fresh with SOLID principles, DDD
- **Modern Patterns**: Event-driven, CQRS, proper abstractions
- **Testable Design**: Unit tests for every business rule
- **Configuration-Driven**: All business rules in database/config
- **Future-Proof**: Easier to add new WEGs, distribution types
- **Developer Sanity**: No more fighting legacy architecture

### **Arguments for Incremental Improvement:**
- **Lower Risk**: Existing calculations are proven to work
- **Faster Delivery**: Immediate improvements possible  
- **Known Edge Cases**: Years of production testing
- **Gradual Migration**: Can improve one piece at a time

### **Recommendation:**
Given the **complexity of German WEG domain** and **5 years of reference data**, a **complete rewrite** is recommended with:
- Current system as reference implementation
- Comprehensive test suite using 2020-2024 HGA outputs
- A/B testing during transition
- Fallback to old system if issues arise

**Investment**: 2-3 months for clean foundation
**Payoff**: Maintainable, reliable, extensible system

---

## **🏗️ Suggested Rewrite Architecture**

```php
// Clean domain model
class HgaCalculation {
    public function __construct(
        private readonly Weg $weg,
        private readonly int $year,
        private readonly PaymentRepository $payments,
        private readonly DistributionEngine $distributionEngine,
        private readonly TaxCalculator $taxCalculator,
    ) {}
}

// Configuration-driven distributions
class DistributionEngine {
    public function calculateShare(
        Money $totalCosts,
        DistributionKey $key,
        WegEinheit $unit,
    ): Money {
        return $this->strategies[$key->value]->calculate($totalCosts, $unit);
    }
}

// Separate tax calculation
class TaxCalculator {
    public function calculateDeductibleAmount(
        Collection $payments,
        WegEinheit $unit,
    ): TaxDeduction {}
}
```

### **Implementation Plan for Rewrite:**
1. **Phase 1**: Build new calculation engine (1 month)
2. **Phase 2**: Migrate output generators to use new engine (2 weeks)  
3. **Phase 3**: Comprehensive testing against 2020-2024 data (2 weeks)
4. **Phase 4**: Switch over with fallback to old system (1 week)

**This incremental approach** for improvements gives you:
- ✅ **Immediate wins** (eliminate regressions) 
- ✅ **Solid foundation** (proper architecture)
- ✅ **Future flexibility** (easy to extend)
- ✅ **Risk mitigation** (can stop at any phase)

**The rewrite approach** gives you:
- ✅ **Clean slate** (no legacy baggage)
- ✅ **Modern architecture** (maintainable long-term)
- ✅ **Comprehensive testing** (validated against known data)
- ✅ **Developer productivity** (joy instead of frustration)