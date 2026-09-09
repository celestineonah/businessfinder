# GRID3 LGA Reconciliation

Purpose:
Map GRID3 Nigeria settlement LGA names to BusinessFinder's canonical
INEC-derived 774-LGA geography.

Result:
- GRID3 distinct LGA pairs: 774
- Exact canonical matches: 707
- Deterministic punctuation/spacing matches: 19
- Explicit reviewed aliases: 48
- Resolved canonical LGAs: 774
- Unresolved: 0
- Fuzzy production matches: 0

State-code normalization:
- GRID3 BR -> BusinessFinder BO (Borno)
- GRID3 KB -> BusinessFinder KE (Kebbi)

Alias file:
grid3-lga-aliases.csv

Validator:
scripts/data/validate-grid3-lga-reconciliation.php

The alias table exists only where exact or deterministic normalization
is insufficient. Production ingestion must not use fuzzy LGA matching.
