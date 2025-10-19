-- Update kostenkonto table to set taxDeductible flag for §35a EStG eligible accounts
-- Based on existing tax_deductible_accounts configuration

UPDATE kostenkonto SET tax_deductible = 1 
WHERE nummer IN ('040100', '040101', '041100', '042111', '045100', '047000');

-- Verify the update
SELECT nummer, bezeichnung, tax_deductible 
FROM kostenkonto 
WHERE nummer IN ('040100', '040101', '041100', '042111', '045100', '047000')
ORDER BY nummer;