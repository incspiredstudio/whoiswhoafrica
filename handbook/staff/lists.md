# Countries, industries and organisation types

The directory's lists are kept on WordPress's own list screens under **Directory**: **Places**, **Industries** and **Organisation types**. Editors and Administrators can add and rename entries; Reviewers can only use them. The names you give are what visitors see in breadcrumbs and filters.

## Add a country or a city

1. Open **Directory > Places**.
2. For a **country**:
   - **Name**: the country's name, for example Nigeria.
   - **Parent**: leave it at "None".
   - **Kind**: Country.
   - **Country code**: its two-letter code, for example `NG` for Nigeria or `GH` for Ghana. Each code can be used once, and it must match the codes the import uses.
3. For a **city**:
   - **Name**: the city, for example Lagos.
   - **Parent**: its country.
   - **Kind**: City. Leave **Country code** empty.
4. Press **Add New Place**.

Pan-African bodies use the place **Pan-African** (kind Pan-African body, code `PAN`). Do not add another.

If something is missing, the screen says what, for example "A city needs its country as Parent."

## Add a sector or an industry

Industries sit under sectors. Top-level entries are sectors; entries under a sector are industries.

1. Open **Directory > Industries**.
2. To add an **industry**: give its **Name**, and choose its sector as **Parent**.
3. To add a **sector**: give its **Name** and leave **Parent** at "None". Then add its industries under it.
4. **Also known as**: other names for the entry, separated by commas, for example `Petroleum, Oil and gas`. The import uses them to match the values it receives.
5. Press **Add New Industry**.

An industry cannot sit under another industry, and a sector that already has industries cannot be moved under another sector.

**Unclassified** is a fixed entry: it holds the profiles that still need an industry. It cannot be renamed, moved or deleted, and it is never shown in public navigation.

## Add an organisation type

1. Open **Directory > Organisation types**.
2. Give its **Name**, for example "State-owned enterprise", and any **Also known as** names.
3. Press **Add New Organisation type**.

## Rename an entry or change its address

Open the entry, change its **Name** or **Slug**, and press **Update**. When the slug changes, the old one is kept: directory addresses that use it, such as `/directory/?industry=old-slug`, keep working and send visitors to the new address. Each change is written to the audit log.

## The columns

| List | Columns |
|---|---|
| Places | Kind, Country code, Country (parent), Profiles |
| Industries | Sector (parent), Also known as, Profiles. A sector shows "(a sector)". |
| Organisation types | Also known as, Profiles |
