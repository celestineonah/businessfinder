# BusinessFinder Nigeria — GeoNames City Selection

Purpose:
Build a source-backed initial Nigerian city/search-locality layer for
BusinessFinder Nigeria.

Primary selection source:
GeoNames Nigeria country dump (NG.zip / NG.txt)

Selection rule:
- PPLC and PPLA records always qualify as candidates.
- PPLA2 and PPL records qualify when population is at least 15,000.
- Other populated-place feature codes are excluded from this V1 candidate set.

Candidate result:
- 269 candidates
- 37 jurisdictions represented
- 37 national/state administrative capitals
- 67 initially suggested major locations

Administrative resolution:
- 262 candidates resolve deterministically using GeoNames admin2 code
  -> GRID3 LGA code
  -> BusinessFinder canonical LGA.
- 0 unknown admin2 codes
- 0 state mismatches among those 262
- 0 canonical LGA failures

Seven GeoNames candidates lacked admin2:
- Amaigbo
- Takum
- Oke Ila
- Lagos
- Ode
- Oke Mesi
- Bonny

Reviewed exception decisions:
- Amaigbo: reject because source state and coordinate conflict.
- Takum: correct to Taraba / Takum.
- Oke Ila: correct to Osun / Ifedayo.
- Lagos: retain for metro/hierarchy review; do not reduce to one LGA.
- Ode: correct to Ekiti / Ekiti East.
- Oke Mesi: correct to Ekiti / Ekiti West.
- Bonny: accept as Rivers / Bonny.

GRID3 point-in-polygon validation was used for the seven exception
coordinates.

Important:
GeoNames population and feature classification are selection signals,
not BusinessFinder verification of legal city status.

No fuzzy administrative matching is permitted in production.

## Final Hierarchy Boundary Reconciliation

The 67 records requiring hierarchy review were independently checked
against GRID3 Nigeria LGA polygon boundaries using their GeoNames
coordinates.

Initial result:
- Review rows: 67
- Boundary matches: 64
- Boundary mismatches: 3
- No polygon: 0
- Multiple polygons: 0
- Canonicalization errors: 0

Reviewed corrections:
- Baro: Niger / Lapai -> Niger / Agaie
- Degema Hulk: Rivers / Abua-Odual -> Rivers / Degema
- Obonoma: Rivers / Degema -> Rivers / Akuku Toru

Final result after applying reviewed corrections:
- Review rows: 67
- Boundary matches: 67
- Boundary mismatches: 0
- No polygon: 0
- Multiple polygons: 0
- Canonicalization errors: 0

Final hierarchy boundary audit SHA-256:
9a54930853b81a52f1d1b991c5c041700c9d93c8b9eeb9ef505c2c37c77a4a61

Administrative geography reconciliation for the V1 city candidate layer
is therefore complete.

Remaining work is hierarchy classification only:
city, area, metro component, or reject.

No fuzzy administrative matching is permitted in production.
