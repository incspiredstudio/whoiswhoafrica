# Data breakdown: how the staging records are grouped

Generated 2026-11-11 00:52 from `acquisition/data/staging.sqlite` by `acquisition/pipeline/breakdown.py`. Run it again after each run to refresh this file: `python -I acquisition/pipeline/breakdown.py`.

**Unreviewed staging data.** Every grouping below is assigned by script from Wikidata. No person has checked it. This file holds counts only, with no names.

## 1. Totals

| Type | Records |
|---|---:|
| Organisations | 9,257 |
| Entrepreneurs | 1,551 |
| Notable people (living) | 1,682 |
| Notable people (deceased) | 999 |
| **Total** | **13,489** |

| Run | Organisations | Entrepreneurs | Notable people (living) | Notable people (deceased) | Total |
|---|---:|---:|---:|---:|---:|
| `deceased-2026-11-09` | 0 | 0 | 0 | 999 | 999 |
| `expansion-2026-11-09` | 8,358 | 1,154 | 0 | 0 | 9,512 |
| `first-1000-2026-11-09` | 592 | 397 | 0 | 0 | 989 |
| `leaders-2026-11-10` | 307 | 0 | 1,682 | 0 | 1,989 |

## 2. Region

| Region | Organisations | Entrepreneurs | Notable people (living) | Notable people (deceased) | Total | Countries |
|---|---:|---:|---:|---:|---:|---:|
| West Africa | 2,639 | 609 | 586 | 225 | 4,059 | 16 |
| North Africa | 2,569 | 200 | 347 | 282 | 3,398 | 6 |
| East Africa | 1,684 | 311 | 306 | 153 | 2,454 | 14 |
| Southern Africa | 1,686 | 256 | 260 | 218 | 2,420 | 10 |
| Central Africa | 670 | 175 | 183 | 83 | 1,111 | 8 |
| Historical African states | 0 | 0 | 0 | 38 | 38 | 20 |
| Pan-African bodies | 9 | 0 | 0 | 0 | 9 | 1 |

Regions follow the African Union's five regions, simplified: Mauritania sits in West Africa, Sudan in North Africa. "Historical African states" holds deceased people whose only African citizenship is a former state, where the birthplace does not map to a present-day country.

## 3. Country

| Country | Region | Organisations | Entrepreneurs | Notable people (living) | Notable people (deceased) | Total |
|---|---|---:|---:|---:|---:|---:|
| Nigeria | West Africa | 993 | 340 | 164 | 55 | 1,552 |
| South Africa | Southern Africa | 950 | 179 | 123 | 146 | 1,398 |
| Egypt | North Africa | 955 | 72 | 95 | 142 | 1,264 |
| Morocco | North Africa | 465 | 54 | 97 | 43 | 659 |
| Ghana | West Africa | 395 | 113 | 106 | 27 | 641 |
| Tunisia | North Africa | 478 | 45 | 51 | 25 | 599 |
| Uganda | East Africa | 413 | 112 | 46 | 15 | 586 |
| Algeria | North Africa | 392 | 21 | 66 | 44 | 523 |
| Kenya | East Africa | 298 | 99 | 53 | 25 | 475 |
| Cameroon | Central Africa | 167 | 120 | 50 | 16 | 353 |
| Democratic Republic of the Congo | Central Africa | 232 | 29 | 34 | 28 | 323 |
| Tanzania | East Africa | 176 | 38 | 23 | 15 | 252 |
| Ivory Coast | West Africa | 160 | 27 | 48 | 15 | 250 |
| Senegal | West Africa | 142 | 22 | 45 | 16 | 225 |
| Ethiopia | East Africa | 151 | 16 | 32 | 22 | 221 |
| Guinea | West Africa | 143 | 24 | 36 | 8 | 211 |
| Benin | West Africa | 151 | 17 | 20 | 12 | 200 |
| Sudan | North Africa | 155 | 6 | 19 | 15 | 195 |
| Rwanda | East Africa | 121 | 24 | 32 | 7 | 184 |
| Angola | Southern Africa | 140 | 13 | 13 | 13 | 179 |
| Somalia | East Africa | 129 | 8 | 25 | 15 | 177 |
| Burkina Faso | West Africa | 115 | 12 | 39 | 8 | 174 |
| Zimbabwe | Southern Africa | 126 | 13 | 21 | 14 | 174 |
| Namibia | Southern Africa | 130 | 6 | 22 | 5 | 163 |
| Libya | North Africa | 124 | 2 | 19 | 13 | 158 |
| Togo | West Africa | 111 | 19 | 14 | 7 | 151 |
| Niger | West Africa | 113 | 4 | 22 | 8 | 147 |
| Madagascar | East Africa | 104 | 5 | 19 | 15 | 143 |
| Zambia | Southern Africa | 99 | 10 | 13 | 9 | 131 |
| Mali | West Africa | 73 | 13 | 25 | 16 | 127 |
| Botswana | Southern Africa | 86 | 13 | 14 | 6 | 119 |
| Burundi | East Africa | 79 | 3 | 25 | 9 | 116 |
| Gabon | Central Africa | 72 | 7 | 17 | 8 | 104 |
| Mozambique | Southern Africa | 65 | 6 | 19 | 8 | 98 |
| Central African Republic | Central Africa | 49 | 0 | 33 | 6 | 88 |
| Malawi | Southern Africa | 50 | 16 | 16 | 5 | 87 |
| Chad | Central Africa | 51 | 7 | 16 | 8 | 82 |
| South Sudan | East Africa | 63 | 1 | 6 | 5 | 75 |
| Liberia | West Africa | 38 | 3 | 11 | 20 | 72 |
| Mauritius | East Africa | 55 | 2 | 10 | 5 | 72 |
| Republic of the Congo | Central Africa | 45 | 12 | 8 | 7 | 72 |
| The Gambia | West Africa | 49 | 7 | 9 | 5 | 70 |
| Mauritania | West Africa | 46 | 4 | 13 | 6 | 69 |
| Guinea-Bissau | West Africa | 36 | 1 | 12 | 11 | 60 |
| Sierra Leone | West Africa | 41 | 2 | 10 | 6 | 59 |
| Cape Verde | West Africa | 33 | 1 | 12 | 5 | 51 |
| Equatorial Guinea | Central Africa | 32 | 0 | 11 | 5 | 48 |
| Seychelles | East Africa | 28 | 0 | 11 | 5 | 44 |
| Eritrea | East Africa | 30 | 1 | 7 | 5 | 43 |
| São Tomé and Príncipe | Central Africa | 22 | 0 | 14 | 5 | 41 |
| Eswatini | Southern Africa | 25 | 0 | 10 | 5 | 40 |
| Djibouti | East Africa | 23 | 1 | 10 | 5 | 39 |
| Lesotho | Southern Africa | 15 | 0 | 9 | 7 | 31 |
| Comoros | East Africa | 14 | 1 | 7 | 5 | 27 |
| Pan-African | Pan-African bodies | 9 | 0 | 0 | 0 | 9 |
| Kingdom of Egypt (historical) | Historical African states | 0 | 0 | 0 | 5 | 5 |
| Sultanate of Zanzibar (historical) | Historical African states | 0 | 0 | 0 | 5 | 5 |
| Beylik of Tunis (historical) | Historical African states | 0 | 0 | 0 | 4 | 4 |
| Dahomey (historical) | Historical African states | 0 | 0 | 0 | 4 | 4 |
| Kingdom of Kongo (historical) | Historical African states | 0 | 0 | 0 | 3 | 3 |
| Merina Kingdom (historical) | Historical African states | 0 | 0 | 0 | 2 | 2 |
| Zulu Kingdom (historical) | Historical African states | 0 | 0 | 0 | 2 | 2 |
| Ashanti Empire (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Benin Empire (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Ethiopian Empire (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Federation of Rhodesia and Nyasaland (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Gaza Empire (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Hafsid dynasty (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Kanem-Bornu Empire (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Khedivate of Egypt (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Mamluk Sultanate of Egypt (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Rhodesia (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| South African Republic (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Sultanate of Darfur (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |
| Yeke Kingdom (historical) | Historical African states | 0 | 0 | 0 | 1 | 1 |

| Records per country | Countries | Records |
|---|---:|---:|
| 500 or more | 8 | 7,222 |
| 100 to 499 | 25 | 4,952 |
| 50 to 99 | 13 | 955 |
| Under 50 | 29 | 360 |

## 4. Organisations by category

The category comes from the organisation's Wikidata type. Rules are checked in this order and the first match wins: NGO or association, Media, Education, Research, Healthcare, Government, then Business if the type is a kind of business, else Other.

| Category | Records | Website reached | With logo | With photo |
|---|---:|---:|---:|---:|
| Business | 3,002 | 1,111 | 1,154 | 851 |
| Education | 2,159 | 878 | 843 | 596 |
| Government | 2,015 | 619 | 587 | 256 |
| NGO or association | 1,177 | 358 | 356 | 93 |
| Healthcare | 279 | 77 | 60 | 107 |
| Media | 275 | 109 | 117 | 40 |
| Other | 189 | 48 | 44 | 30 |
| Research | 161 | 63 | 58 | 39 |

**Most common Wikidata types in each category:**

- **Business:** Business 944, Airline 333, Hotel 309, Company 220, Enterprise 187, Bank 117, Joint-stock company 67, State-owned enterprise 47
- **Education:** University 795, Academic institution 411, Higher education institution 135, Public university 134, Educational institution 110, Private university 93, College 73, Polytechnic 45
- **Government:** Government agency 616, Ministry 379, Central bank 41, National archives 40, Moroccan public institution 39, Supreme court 37, Ministry in the government of Guinea 34, Justice ministry 34
- **NGO or association:** Labor union 295, Nonprofit organization 224, Non-governmental organization 155, Association 103, National Red Cross and Red Crescent society 51, National trade union center 47, Voluntary association 41, Foundation 31
- **Healthcare:** Hospital 210, Teaching hospital 22, Medical organization 20, Tertiary referral hospital 3, Medical facility 3, General hospital 3, Private hospital 2, Pharmaceutical company 2
- **Media:** Publishing house 113, News agency 30, Business 15, University 13, Public university 11, Radio station 10, Media company 9, Book publisher 8
- **Other:** Stock exchange 17, Laboratory 12, Human rights organization 11, Anti-corruption agency 11, Organization 10, Umbrella organization 8, Aviation authority 8, Employers' organization 7
- **Research:** Research institute 94, Research center 24, Think tank 17, Organization 4, Government agency 4, Facility 4, Institute for medical research 3, Public research institution 2

## 5. Entrepreneurs by title

The title is the founder or owner link if Wikidata has one, else a current position, else the business occupation.

| Title | Records |
|---|---:|
| Businessperson | 582 |
| Entrepreneur | 415 |
| Chief executive officer | 99 |
| Business executive | 92 |
| Banker | 68 |
| Founder | 57 |
| Social entrepreneur | 43 |
| Owner | 23 |
| Film producer | 11 |
| President | 10 |
| Other titles (77) | 151 |

## 5a. Brief audience groups (run `leaders-2026-11-10`)

Groups from the brief, section 4. Living people are grouped by the organisation they lead, if Wikidata names them as its chief executive, chair, rector or head. Otherwise they are grouped by field, as in section 6. Sector organisations are grouped by their Wikidata type.

| Type | Group | Records | With image | Meet a plan criterion |
|---|---|---:|---:|---:|
| Notable people (living) | Creative or sports figure | 549 | 539 | 0 |
| Notable people (living) | Public-sector leader | 547 | 450 | 0 |
| Notable people (living) | Academic or professional | 437 | 405 | 0 |
| Notable people (living) | Business leader | 124 | 50 | 0 |
| Notable people (living) | NGO or community leader | 25 | 15 | 0 |
| Organisations | Healthcare | 260 | 137 | 260 |
| Organisations | Chamber of commerce | 26 | 10 | 26 |
| Organisations | Finance and investment | 21 | 13 | 21 |

**Living people by field:** Politics and government 525, Science, medicine and academia 373, Sport 180, Film, theatre and broadcasting 147, Literature and journalism 118, Business 107, Music 100, Law 82, Visual arts and architecture 19, Other 18, Activism and liberation 13.

## 6. Notable people (deceased) by field

The field is the one that matches most of the person's Wikidata occupations and positions held. A tie goes to the earlier field in this order: royalty, politics, activism, military, religion, science, literature, music, film, visual arts, sport, business, law. If nothing matches, the Wikidata description decides. So a singer and activist with three music occupations counts under Music, and an archbishop with several church positions under Religion.

| Field | Records | With photo |
|---|---:|---:|
| Politics and government | 461 | 405 |
| Sport | 116 | 67 |
| Literature and journalism | 96 | 77 |
| Royalty and traditional rulers | 88 | 75 |
| Science, medicine and academia | 55 | 43 |
| Film, theatre and broadcasting | 43 | 35 |
| Religion | 41 | 30 |
| Music | 40 | 38 |
| Other | 18 | 12 |
| Activism and liberation | 16 | 11 |
| Visual arts and architecture | 13 | 9 |
| Military | 6 | 5 |
| Business | 4 | 3 |
| Law | 2 | 2 |

| Era | Records |
|---|---:|
| Died 2000 or later | 575 |
| Died 1950 to 1999 | 276 |
| Died 1900 to 1949 | 70 |
| Died 1500 to 1899 | 78 |

## 7. Plan criterion

| Type | Criterion | Records |
|---|---|---:|
| Notable people (deceased) | Historical figure: outside the plan criteria | 999 |
| Entrepreneurs | Lead: E1 or E2 not yet shown | 1,550 |
| Entrepreneurs | E1: founder of a listed company | 1 |
| Organisations | O5: has a Wikipedia article (via Wikidata) | 9,210 |
| Organisations | O1: listed on a stock exchange | 47 |
| Notable people (living) | Lead: E1 or E2 not yet shown | 604 |
| Notable people (living) | I1: public office (shown on Wikidata, to confirm) | 547 |
| Notable people (living) | I6: has awards (to check against the named-award list) | 449 |
| Notable people (living) | I2: leads an organisation (met if it is listed) | 58 |
| Notable people (living) | I3: university head or academic body (to confirm) | 24 |

## 8. Organisation website check

| Result | Organisations |
|---|---:|
| No website listed | 3,290 |
| Reached | 3,263 |
| Unreachable | 1,096 |
| Site returned an error | 719 |
| Domain not found or invalid | 652 |
| Not fetched: blocked by robots.txt | 237 |

## 9. Images

| Type | With photo | With logo | Logos without a licence | No image |
|---|---:|---:|---:|---:|
| Notable people (deceased) | 812 | 0 | 0 | 187 |
| Entrepreneurs | 516 | 0 | 0 | 1,035 |
| Organisations | 2,012 | 3,219 | 2,428 | 4,866 |
| Notable people (living) | 1,459 | 0 | 0 | 223 |

Photos and logos from Wikimedia Commons carry their licence and credit. Logos from organisations' own sites have no licence and need permission or removal before any public use.

## 10. Flags

| Flag | Records |
|---|---:|
| `website_no_website` | 3,290 |
| `living_person_review_required` | 1,682 |
| `criterion_not_confirmed_from_primary_source` | 1,682 |
| `does_not_yet_meet_E1_E2` | 1,550 |
| `website_unreachable` | 1,096 |
| `outside_mvp_scope_deceased` | 999 |
| `website_http_error` | 719 |
| `website_bad_url` | 652 |
| `current_role_not_confirmed` | 412 |
| `possible_duplicate` | 311 |
| `website_robots_blocked` | 237 |
| `founder_role_not_confirmed_on_company_site` | 50 |
| `site_description_dropped_by_scan` | 20 |

## 11. Candidates excluded, by reason

| Type | Reason | Candidates |
|---|---|---:|
| Notable people (deceased) | `historical_state_not_african` | 1,523 |
| Notable people (deceased) | `not_african` | 273 |
| Notable people (deceased) | `died_before_1500` | 122 |
| Notable people (deceased) | `notable_for_crime` | 51 |
| Notable people (deceased) | `died_as_minor` | 48 |
| Notable people (deceased) | `not_deceased` | 31 |
| Notable people (deceased) | `no_english_name` | 4 |
| Notable people (deceased) | `sensitive_term_in_final_text` | 1 |
| Entrepreneurs | `non_business_primary_role` | 2,115 |
| Entrepreneurs | `politician` | 971 |
| Entrepreneurs | `no_business_evidence` | 554 |
| Entrepreneurs | `not_citizen_of_listed_country` | 266 |
| Entrepreneurs | `not_entrepreneur` | 165 |
| Entrepreneurs | `no_english_name` | 105 |
| Entrepreneurs | `not_african` | 85 |
| Entrepreneurs | `sensitive_term` | 72 |
| Entrepreneurs | `sensitive_term_in_final_text` | 3 |
| Entrepreneurs | `deceased` | 3 |
| Organisations | `excluded_type` | 6,950 |
| Organisations | `sensitive_term` | 1,221 |
| Organisations | `dissolved` | 836 |
| Organisations | `no_english_name` | 494 |
| Organisations | `sensitive_term_in_final_text` | 4 |
| Organisations | `manual_exclusion` | 3 |
| Notable people (living) | `no_clear_field` | 2,237 |
| Notable people (living) | `not_african` | 909 |
| Notable people (living) | `sensitive_term` | 811 |
| Notable people (living) | `activism_political_opinion` | 462 |
| Notable people (living) | `already_in_directory` | 426 |
| Notable people (living) | `military_role` | 103 |
| Notable people (living) | `traditional_ruler` | 102 |
| Notable people (living) | `not_african_citizen` | 66 |
| Notable people (living) | `no_english_name` | 38 |
| Notable people (living) | `political_affiliation_in_text` | 30 |
| Notable people (living) | `religious_role` | 23 |
| Notable people (living) | `malformed_name` | 15 |
| Notable people (living) | `sensitive_term_in_final_text` | 11 |
| Notable people (living) | `deceased_or_historical` | 11 |
| Notable people (living) | `manual_exclusion` | 1 |
| sector_org | `already_in_directory` | 90 |
| sector_org | `excluded_type` | 31 |
| sector_org | `not_a_chamber` | 30 |
| sector_org | `sensitive_term` | 22 |
| sector_org | `no_english_name` | 15 |
| sector_org | `dissolved` | 4 |
