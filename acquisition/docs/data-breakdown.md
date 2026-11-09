# Data breakdown: how the staging records are grouped

Generated 2026-11-09 23:18 from `acquisition/data/staging.sqlite` by `acquisition/pipeline/breakdown.py`. Run it again after each run to refresh this file: `python -I acquisition/pipeline/breakdown.py`.

**Unreviewed staging data.** Every grouping below is assigned by script from Wikidata. No person has checked it. This file holds counts only, with no names.

## 1. Totals

| Type | Records |
|---|---:|
| Organisations | 8,950 |
| Entrepreneurs | 1,551 |
| Notable people (deceased) | 999 |
| **Total** | **11,500** |

| Run | Organisations | Entrepreneurs | Notable people (deceased) | Total |
|---|---:|---:|---:|---:|
| `deceased-2026-11-09` | 0 | 0 | 999 | 999 |
| `expansion-2026-11-09` | 8,358 | 1,154 | 0 | 9,512 |
| `first-1000-2026-11-09` | 592 | 397 | 0 | 989 |

## 2. Region

| Region | Organisations | Entrepreneurs | Notable people (deceased) | Total | Countries |
|---|---:|---:|---:|---:|---:|
| West Africa | 2,545 | 609 | 225 | 3,379 | 16 |
| North Africa | 2,511 | 200 | 282 | 2,993 | 6 |
| Southern Africa | 1,620 | 256 | 218 | 2,094 | 10 |
| East Africa | 1,613 | 311 | 153 | 2,077 | 14 |
| Central Africa | 652 | 175 | 83 | 910 | 8 |
| Historical African states | 0 | 0 | 38 | 38 | 20 |
| Pan-African bodies | 9 | 0 | 0 | 9 | 1 |

Regions follow the African Union's five regions, simplified: Mauritania sits in West Africa, Sudan in North Africa. "Historical African states" holds deceased people whose only African citizenship is a former state, where the birthplace does not map to a present-day country.

## 3. Country

| Country | Region | Organisations | Entrepreneurs | Notable people (deceased) | Total |
|---|---|---:|---:|---:|---:|
| Nigeria | West Africa | 945 | 340 | 55 | 1,340 |
| South Africa | Southern Africa | 907 | 179 | 146 | 1,232 |
| Egypt | North Africa | 940 | 72 | 142 | 1,154 |
| Morocco | North Africa | 455 | 54 | 43 | 552 |
| Tunisia | North Africa | 470 | 45 | 25 | 540 |
| Ghana | West Africa | 380 | 113 | 27 | 520 |
| Uganda | East Africa | 381 | 112 | 15 | 508 |
| Algeria | North Africa | 373 | 21 | 44 | 438 |
| Kenya | East Africa | 287 | 99 | 25 | 411 |
| Cameroon | Central Africa | 163 | 120 | 16 | 299 |
| Democratic Republic of the Congo | Central Africa | 230 | 29 | 28 | 287 |
| Tanzania | East Africa | 170 | 38 | 15 | 223 |
| Ivory Coast | West Africa | 158 | 27 | 15 | 200 |
| Ethiopia | East Africa | 149 | 16 | 22 | 187 |
| Senegal | West Africa | 140 | 22 | 16 | 178 |
| Benin | West Africa | 148 | 17 | 12 | 177 |
| Sudan | North Africa | 152 | 6 | 15 | 173 |
| Guinea | West Africa | 140 | 24 | 8 | 172 |
| Angola | Southern Africa | 137 | 13 | 13 | 163 |
| Rwanda | East Africa | 117 | 24 | 7 | 148 |
| Somalia | East Africa | 125 | 8 | 15 | 148 |
| Zimbabwe | Southern Africa | 120 | 13 | 14 | 147 |
| Namibia | Southern Africa | 126 | 6 | 5 | 137 |
| Libya | North Africa | 121 | 2 | 13 | 136 |
| Togo | West Africa | 108 | 19 | 7 | 134 |
| Burkina Faso | West Africa | 113 | 12 | 8 | 133 |
| Niger | West Africa | 111 | 4 | 8 | 123 |
| Madagascar | East Africa | 102 | 5 | 15 | 122 |
| Zambia | Southern Africa | 97 | 10 | 9 | 116 |
| Botswana | Southern Africa | 84 | 13 | 6 | 103 |
| Mali | West Africa | 71 | 13 | 16 | 100 |
| Burundi | East Africa | 77 | 3 | 9 | 89 |
| Gabon | Central Africa | 70 | 7 | 8 | 85 |
| Mozambique | Southern Africa | 62 | 6 | 8 | 76 |
| Malawi | Southern Africa | 48 | 16 | 5 | 69 |
| South Sudan | East Africa | 61 | 1 | 5 | 67 |
| Chad | Central Africa | 47 | 7 | 8 | 62 |
| Republic of the Congo | Central Africa | 43 | 12 | 7 | 62 |
| Mauritius | East Africa | 53 | 2 | 5 | 60 |
| The Gambia | West Africa | 48 | 7 | 5 | 60 |
| Liberia | West Africa | 36 | 3 | 20 | 59 |
| Central African Republic | Central Africa | 48 | 0 | 6 | 54 |
| Mauritania | West Africa | 44 | 4 | 6 | 54 |
| Guinea-Bissau | West Africa | 34 | 1 | 11 | 46 |
| Sierra Leone | West Africa | 37 | 2 | 6 | 45 |
| Cape Verde | West Africa | 32 | 1 | 5 | 38 |
| Eritrea | East Africa | 30 | 1 | 5 | 36 |
| Equatorial Guinea | Central Africa | 30 | 0 | 5 | 35 |
| Seychelles | East Africa | 26 | 0 | 5 | 31 |
| Eswatini | Southern Africa | 24 | 0 | 5 | 29 |
| Djibouti | East Africa | 22 | 1 | 5 | 28 |
| São Tomé and Príncipe | Central Africa | 21 | 0 | 5 | 26 |
| Lesotho | Southern Africa | 15 | 0 | 7 | 22 |
| Comoros | East Africa | 13 | 1 | 5 | 19 |
| Pan-African | Pan-African bodies | 9 | 0 | 0 | 9 |
| Kingdom of Egypt (historical) | Historical African states | 0 | 0 | 5 | 5 |
| Sultanate of Zanzibar (historical) | Historical African states | 0 | 0 | 5 | 5 |
| Beylik of Tunis (historical) | Historical African states | 0 | 0 | 4 | 4 |
| Dahomey (historical) | Historical African states | 0 | 0 | 4 | 4 |
| Kingdom of Kongo (historical) | Historical African states | 0 | 0 | 3 | 3 |
| Merina Kingdom (historical) | Historical African states | 0 | 0 | 2 | 2 |
| Zulu Kingdom (historical) | Historical African states | 0 | 0 | 2 | 2 |
| Ashanti Empire (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Benin Empire (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Ethiopian Empire (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Federation of Rhodesia and Nyasaland (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Gaza Empire (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Hafsid dynasty (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Kanem-Bornu Empire (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Khedivate of Egypt (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Mamluk Sultanate of Egypt (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Rhodesia (historical) | Historical African states | 0 | 0 | 1 | 1 |
| South African Republic (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Sultanate of Darfur (historical) | Historical African states | 0 | 0 | 1 | 1 |
| Yeke Kingdom (historical) | Historical African states | 0 | 0 | 1 | 1 |

| Records per country | Countries | Records |
|---|---:|---:|
| 500 or more | 7 | 5,846 |
| 100 to 499 | 24 | 4,455 |
| 50 to 99 | 12 | 797 |
| Under 50 | 32 | 402 |

## 4. Organisations by category

The category comes from the organisation's Wikidata type. Rules are checked in this order and the first match wins: NGO or association, Media, Education, Research, Government, then Business if the type is a kind of business, else Other.

| Category | Records | Website reached | With logo | With photo |
|---|---:|---:|---:|---:|
| Business | 3,028 | 1,114 | 1,157 | 860 |
| Education | 2,151 | 873 | 840 | 592 |
| Government | 1,986 | 611 | 579 | 255 |
| NGO or association | 1,173 | 356 | 354 | 93 |
| Media | 275 | 109 | 117 | 40 |
| Other | 177 | 39 | 33 | 27 |
| Research | 160 | 62 | 57 | 38 |

**Most common Wikidata types in each category:**

- **Business:** Business 945, Airline 333, Hotel 309, Company 221, Enterprise 187, Bank 117, Joint-stock company 67, State-owned enterprise 47
- **Education:** University 795, Academic institution 411, Higher education institution 135, Public university 134, Educational institution 109, Private university 93, College 73, Polytechnic 45
- **Government:** Government agency 614, Ministry 379, Central bank 41, National archives 40, Supreme court 37, Ministry in the government of Guinea 34, Justice ministry 34, Statistical service 33
- **NGO or association:** Labor union 295, Nonprofit organization 221, Non-governmental organization 155, Association 103, National Red Cross and Red Crescent society 51, National trade union center 47, Voluntary association 41, Foundation 31
- **Media:** Publishing house 113, News agency 30, Business 15, University 13, Public university 11, Radio station 10, Media company 9, Book publisher 8
- **Other:** Laboratory 12, Human rights organization 11, Anti-corruption agency 11, Umbrella organization 8, Aviation authority 8, Employers' organization 7, Organization 6, Department of the South African government 6
- **Research:** Research institute 93, Research center 24, Think tank 17, Organization 4, Government agency 4, Facility 4, Institute for medical research 3, Public research institution 2

## 5. Entrepreneurs by title

The title is the founder or owner link if Wikidata has one, else a current position, else the business occupation.

| Title | Records |
|---|---:|
| Businessperson | 582 |
| Entrepreneur | 415 |
| Chief executive officer | 99 |
| Business executive | 92 |
| Banker | 68 |
| Founder | 59 |
| Social entrepreneur | 43 |
| Owner | 23 |
| Film producer | 11 |
| President | 10 |
| Other titles (75) | 149 |

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
| Other | 17 | 12 |
| Activism and liberation | 16 | 11 |
| Visual arts and architecture | 13 | 9 |
| Military | 7 | 5 |
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
| Organisations | O5: has a Wikipedia article (via Wikidata) | 8,904 |
| Organisations | O1: listed on a stock exchange | 46 |

## 8. Organisation website check

| Result | Organisations |
|---|---:|
| No website listed | 3,247 |
| Reached | 3,164 |
| Unreachable | 1,057 |
| Site returned an error | 682 |
| Domain not found or invalid | 579 |
| Not fetched: blocked by robots.txt | 221 |

## 9. Images

| Type | With photo | With logo | Logos without a licence | No image |
|---|---:|---:|---:|---:|
| Notable people (deceased) | 812 | 0 | 0 | 187 |
| Entrepreneurs | 516 | 0 | 0 | 1,035 |
| Organisations | 1,905 | 3,137 | 2,355 | 4,719 |

Photos and logos from Wikimedia Commons carry their licence and credit. Logos from organisations' own sites have no licence and need permission or removal before any public use.

## 10. Flags

| Flag | Records |
|---|---:|
| `website_no_website` | 3,247 |
| `does_not_yet_meet_E1_E2` | 1,550 |
| `website_unreachable` | 1,057 |
| `outside_mvp_scope_deceased` | 999 |
| `website_http_error` | 682 |
| `website_bad_url` | 579 |
| `possible_duplicate` | 311 |
| `website_robots_blocked` | 221 |
| `founder_role_not_confirmed_on_company_site` | 53 |
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
| Entrepreneurs | `non_business_primary_role` | 2,194 |
| Entrepreneurs | `politician` | 1,003 |
| Entrepreneurs | `no_business_evidence` | 668 |
| Entrepreneurs | `not_citizen_of_listed_country` | 273 |
| Entrepreneurs | `not_african` | 172 |
| Entrepreneurs | `not_entrepreneur` | 171 |
| Entrepreneurs | `sensitive_term` | 110 |
| Entrepreneurs | `no_english_name` | 106 |
| Entrepreneurs | `deceased` | 4 |
| Entrepreneurs | `sensitive_term_in_final_text` | 3 |
| Entrepreneurs | `manual_exclusion` | 1 |
| Organisations | `excluded_type` | 6,953 |
| Organisations | `sensitive_term` | 1,223 |
| Organisations | `dissolved` | 838 |
| Organisations | `no_english_name` | 494 |
| Organisations | `sensitive_term_in_final_text` | 4 |
| Organisations | `manual_exclusion` | 3 |
