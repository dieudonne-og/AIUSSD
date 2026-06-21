# AI-Based Digital Inclusion Assessment System — Design

**Date:** 2026-06-21
**Case study:** Nyarugunga Sector, Kicukiro District, Rwanda (cells: Kamashashi, Nonko, Rwimbogo)
**Author context:** Final-year BBIT proposal, University of Kigali. Must be viva-defensible.

## 1. Purpose

Citizens answer a short bilingual (English + Kinyarwanda) digital-inclusion survey
over USSD (any GSM phone, no internet). Responses are stored in MySQL, scored into
a digital-inclusion category by a transparent rule-based scorer, and shown to local
government officials on a web dashboard with charts and aggregated indicators.

## 2. Tech stack (fixed)

- Backend: plain PHP 8.x, no framework.
- DB: MySQL 8 (PDO).
- USSD: HTTP POST endpoint, MTN Rwanda CON/END session model. No live gateway —
  a web-based simulator posts to the same endpoint and renders CON/END screens.
- Scorer: rule-based PHP behind a `ScoreService` interface (swap for ML later).
- Dashboard: server-rendered PHP + Chart.js (vendored locally).
- Runtime: `php -S localhost:8000 -t public` + local MySQL 8.

## 3. Folder structure

```
DIGITALINCLUSION/
  public/
    index.php          # dashboard entry + simple router
    ussd.php           # USSD gateway endpoint (POST, CON/END)
    simulator.php      # web phone simulator -> posts to ussd.php
    assets/            # chart.js (vendored), css
  src/
    Database.php       # PDO connection (config-driven)
    UssdSession.php    # session state (consent -> Q1..Q8), persisted by sessionId
    UssdFlow.php       # exact bilingual screens, one source of truth
    Score/
      ScoreService.php   # interface
      RuleBasedScorer.php
    Repository/
      ResponseRepository.php
      UserRepository.php
    Auth.php           # official login (session, hashed passwords)
  config/
    config.php         # DB creds, app settings
    scoring.php        # ALL weights + thresholds (commented, tunable)
  views/               # login, overview, table templates
  sql/
    schema.sql
    seed.php           # ~30 fake responses across 3 cells
  README.md
```

## 4. USSD flow

Exact bilingual screens per the brief (verbatim, not paraphrased). One CON screen
per question; final thank-you is END.

- **Consent gate**: 1 Yes -> Q1; 2 No -> END (store flagged consent=No row only).
- **Q1** cell (context), **Q2** device, **Q3** access, **Q4** affordability,
  **Q5** frequency, **Q6** main use (context), **Q7** skills, **Q8** service use.
- **Final**: compute score, save full response, show END thank-you.

Session model: gateway sends `sessionId`, `phoneNumber` (ignored/not stored —
anonymous), `text` (accumulated input). State machine maps step count to next
screen. Server replies `CON <text>` to continue or `END <text>` to finish.

## 5. Data model

`responses` (one row per survey attempt, no personal identifiers):
- `id`, `session_id`, `consented` TINYINT (1/0)
- `cell`, `q2`..`q8` (chosen option numbers; NULL when consent=No)
- `score` INT NULL, `category` VARCHAR NULL, `created_at` TIMESTAMP

Consent=No → row with `consented=0`, answers/score NULL (decided: flag column,
keeps refusal rate countable in one table).

`users` (dashboard logins): `id`, `username`, `password_hash`, `created_at`.

`schema.sql` creates both. `seed.php` inserts ~30 realistic completed responses
spread across the 3 cells + one default admin user.

## 6. Rule-based scorer (approved)

6 indicators, max 100. Q1 (location) and Q6 (usage type) are stored context, not
scored.

| Q  | Indicator       | Best          | Mid           | Worst |
|----|-----------------|---------------|---------------|-------|
| Q2 | Device          | smartphone 20 | basic 10      | none 0 |
| Q3 | Internet access | home 20       | sometimes 10  | no 0  |
| Q4 | Affordability   | easy 15       | difficult 7   | cannot 0 |
| Q5 | Frequency       | daily 15      | few/week 8    | rarely 0 |
| Q7 | Skills          | good 15       | basic 8       | none 0 |
| Q8 | Service use     | often 15      | rarely 8      | no 0  |

Max = 20+20+15+15+15+15 = 100.

Categories: 0–25 Excluded · 26–50 Low · 51–75 Moderate · 76–100 Included.

All weights/thresholds live in `config/scoring.php` with a comment block explaining
the computation for the viva. `RuleBasedScorer implements ScoreService` so a trained
model can replace it without touching USSD, repository, or dashboard code.

## 7. Dashboard

- Session login for officials (hashed passwords).
- Overview: total respondents, average inclusion score, category breakdown.
- Charts (Chart.js): category distribution, breakdown by cell, indicator-level bars
  (% home internet, % can't afford data, % no digital skills).
- Table of recent anonymized responses.

## 8. Build order (incremental, each stage runnable + testable)

1. **DB**: `config/`, `schema.sql`, `seed.php` → verify: tables exist, ~30 rows.
2. **USSD**: `ussd.php` + `UssdSession`/`UssdFlow` + `simulator.php` → verify: walk
   full survey in browser, completed row saved.
3. **Scorer**: `scoring.php` + `RuleBasedScorer` wired into END → verify: known
   answers produce expected score/category.
4. **Dashboard**: login + overview + charts + table → verify: charts render seed data.

## 9. Local run commands (to finalize in README)

- Create DB + load schema: `mysql -u root -p < sql/schema.sql`
- Seed: `php sql/seed.php`
- Serve: `php -S localhost:8000 -t public`
- Simulator: `http://localhost:8000/simulator.php`
- Dashboard: `http://localhost:8000/index.php`

## 10. Out of scope (YAGNI)

No real ML model, no live gateway integration, no SMS, no multi-sector config, no
user roles beyond a single official login.
