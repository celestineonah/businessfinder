# BusinessFinder Nigeria — National LGA Dataset

Source:
INEC CVR Live Locator

Source base:
https://cvr.inecnigeria.org/locator/

Acquisition date:
2026-09-09

Coverage:
- 36 states
- Federal Capital Territory
- 37 jurisdictions total
- 774 LGAs / FCT Area Councils

Administrative breakdown:
- 768 Local Government Areas
- 6 FCT Area Councils

Canonical import CSV:
nigeria-lgas.csv

Audit/provenance CSV:
nigeria-lgas-audit.csv

Generator:
scripts/data/build-national-lgas.php

Important normalization:
- INEC FCT value "MUNICIPAL" is preserved in the audit dataset.
- Canonical BusinessFinder value is "Abuja Municipal Area Council".
- Multiple INEC CVR centres representing one administrative LGA are collapsed.
- Osun IFE EAST had two INEC CVR centres but remains one LGA.

Production import:
- 774 rows validated
- 774 rows inserted
- 0 failures
- 37 jurisdictions represented

Production source SHA-256:
d7a121584ebfcb473eb6db963341003a209ab1930b1c738845b9b7878f3ae0f8
