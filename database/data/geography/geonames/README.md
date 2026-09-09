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
