# Digital Inclusion Assessment System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a bilingual USSD digital-inclusion survey for Nyarugunga Sector with MySQL storage, a transparent rule-based scorer, a web USSD simulator, and an officials' dashboard.

**Architecture:** Plain PHP 8.3, no framework. USSD endpoint is stateless (parses the gateway's accumulated `text` string into the current step, MTN CON/END model). Scoring sits behind a `ScoreService` interface so a future ML model is a drop-in. Dashboard is server-rendered PHP + Chart.js. PDO/MySQL throughout.

**Tech Stack:** PHP 8.3 (CLI built-in server), MySQL 8 (PDO/pdo_mysql), Chart.js (vendored), HTML/CSS.

## Global Constraints

- PHP 8.x, no framework (ask before adding any dependency or framework).
- MySQL 8 via PDO (`pdo_mysql`). User installs: `sudo apt install mysql-server php8.3-mysql`.
- USSD copy is **verbatim bilingual** (English first, Kinyarwanda in brackets) exactly as the brief — never paraphrase.
- Responses are **anonymous**: never store phone number / msisdn.
- All scorer weights + thresholds live ONLY in `config/scoring.php`, with a comment block explaining the computation (viva defense).
- Scorer: rule-based now, behind `ScoreService` interface — swappable for ML without touching USSD/repo/dashboard.
- Scoring (approved): Q2 device 20/10/0 · Q3 access 20/10/0 · Q4 afford 15/7/0 · Q5 freq 15/8/0 · Q7 skills 15/8/0 · Q8 service 15/8/0 → max 100. Categories: 0–25 Excluded · 26–50 Low · 51–75 Moderate · 76–100 Included. Q1 (cell) + Q6 (use) stored, not scored.
- Consent=No → one `responses` row with `consented=0`, answers/score NULL.
- Cells: Kamashashi, Nonko, Rwimbogo.
- Run: `php -S localhost:8000 -t public`.

---

## File Structure

- `config/config.php` — DB credentials + app settings
- `config/scoring.php` — weights, thresholds, category labels (single source)
- `src/Database.php` — PDO factory
- `src/Score/ScoreService.php` — interface
- `src/Score/RuleBasedScorer.php` — rule-based implementation
- `src/UssdFlow.php` — exact bilingual screens (one source of truth)
- `src/UssdSession.php` — stateless step machine over `text`
- `src/Repository/ResponseRepository.php` — writes + dashboard aggregates
- `src/Repository/UserRepository.php` — official accounts
- `src/Auth.php` — session login
- `sql/schema.sql` — tables
- `sql/seed.php` — ~30 fake responses + admin user
- `public/ussd.php` — gateway endpoint
- `public/simulator.php` — web phone simulator
- `public/index.php` — dashboard router (login/overview/logout)
- `views/login.php`, `views/dashboard.php` — templates
- `public/assets/chart.js`, `public/assets/app.css`
- `tests/run.php` — CLI assertion harness (scorer + flow)
- `README.md`

---

## Task 1: Config + Database connection + schema

**Files:**
- Create: `config/config.php`, `config/scoring.php`, `src/Database.php`, `sql/schema.sql`

**Interfaces:**
- Produces: `Database::connect(): PDO`; `config.php` returns `['db'=>['host','name','user','pass'], 'admin'=>['username','password']]`; `scoring.php` returns `['weights'=>..., 'categories'=>...]`.

- [ ] **Step 1: Write `config/config.php`**

```php
<?php
// Central app + DB config. Copy of values the whole app reads.
return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'digital_inclusion',
        'user' => 'di_app',          // dedicated app user (root uses socket auth)
        'pass' => 'di_pass_2026',    // created during environment setup
        'charset' => 'utf8mb4',
    ],
    // Default dashboard official created by seed.php
    'admin' => [
        'username' => 'official',
        'password' => 'changeme123',
    ],
];
```

- [ ] **Step 2: Write `config/scoring.php`** (single source of weights — see comment block)

```php
<?php
/*
 * RULE-BASED DIGITAL INCLUSION SCORE
 * ----------------------------------
 * Six indicators (Q2,Q3,Q4,Q5,Q7,Q8) each award points for the chosen option.
 * Q1 (cell) and Q6 (main use) are context only and are NOT scored.
 *
 *   Q2 Device         smartphone 20 | basic 10 | none 0
 *   Q3 Internet access home 20      | sometimes 10 | no 0
 *   Q4 Affordability  easy 15       | difficult 7  | cannot 0
 *   Q5 Frequency      daily 15      | few/week 8   | rarely 0
 *   Q7 Skills         good 15       | basic 8      | none 0
 *   Q8 Service use     often 15     | rarely 8     | no 0
 *
 * Max possible = 20+20+15+15+15+15 = 100. Score = sum of awarded points.
 * Category by score: 0-25 Excluded, 26-50 Low, 51-75 Moderate, 76-100 Included.
 * To re-tune the model, edit ONLY this file.
 */
return [
    'weights' => [
        'q2' => [1 => 20, 2 => 10, 3 => 0],
        'q3' => [1 => 20, 2 => 10, 3 => 0],
        'q4' => [1 => 15, 2 => 7,  3 => 0],
        'q5' => [1 => 15, 2 => 8,  3 => 0],
        'q7' => [1 => 15, 2 => 8,  3 => 0],
        'q8' => [1 => 15, 2 => 8,  3 => 0],
    ],
    // Ordered ascending by 'max'; first bucket whose max >= score wins.
    'categories' => [
        ['max' => 25,  'label' => 'Excluded'],
        ['max' => 50,  'label' => 'Low'],
        ['max' => 75,  'label' => 'Moderate'],
        ['max' => 100, 'label' => 'Included'],
    ],
];
```

- [ ] **Step 3: Write `src/Database.php`**

```php
<?php
// PDO factory. One place that knows how to open a MySQL connection.
class Database
{
    public static function connect(): PDO
    {
        $cfg = require __DIR__ . '/../config/config.php';
        $db  = $cfg['db'];
        $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
        return new PDO($dsn, $db['user'], $db['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
}
```

- [ ] **Step 4: Write `sql/schema.sql`**

```sql
-- Digital Inclusion Assessment System schema (MySQL 8).
-- The `digital_inclusion` database and the `di_app` user are provisioned
-- during environment setup (see README). This script creates the tables;
-- `di_app` holds privileges only on this database, so no CREATE DATABASE here.

-- One row per survey attempt. Anonymous: no phone number stored.
CREATE TABLE IF NOT EXISTS responses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    session_id  VARCHAR(64) NOT NULL,
    consented   TINYINT(1) NOT NULL DEFAULT 1,   -- 0 = declined consent
    cell        VARCHAR(20) NULL,                -- Q1: Kamashashi/Nonko/Rwimbogo
    q2 TINYINT NULL, q3 TINYINT NULL, q4 TINYINT NULL,
    q5 TINYINT NULL, q6 TINYINT NULL, q7 TINYINT NULL, q8 TINYINT NULL,
    score       INT NULL,                        -- 0..100, NULL if declined
    category    VARCHAR(20) NULL,                -- Excluded/Low/Moderate/Included
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dashboard officials.
CREATE TABLE IF NOT EXISTS users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 5: Create DB and verify**

Run: `mysql -h127.0.0.1 -udi_app -pdi_pass_2026 digital_inclusion < sql/schema.sql && mysql -h127.0.0.1 -udi_app -pdi_pass_2026 digital_inclusion -e "SHOW TABLES;"`
Expected: lists `responses` and `users`.

- [ ] **Step 6: Verify PDO connects**

Run: `php -r 'require "src/Database.php"; Database::connect(); echo "OK\n";'`
Expected: `OK`

- [ ] **Step 7: Commit**

```bash
git add config src/Database.php sql/schema.sql
git commit -m "feat: config, PDO connection, MySQL schema"
```

---

## Task 2: Rule-based scorer (TDD)

**Files:**
- Create: `src/Score/ScoreService.php`, `src/Score/RuleBasedScorer.php`, `tests/run.php`

**Interfaces:**
- Consumes: `config/scoring.php`.
- Produces: `interface ScoreService { public function score(array $answers): array; }` where `$answers` has keys `q2..q8` (int option) and return is `['score'=>int, 'category'=>string]`.

- [ ] **Step 1: Write the failing test in `tests/run.php`**

```php
<?php
// Minimal CLI assertion harness. Run: php tests/run.php
require __DIR__ . '/../src/Score/ScoreService.php';
require __DIR__ . '/../src/Score/RuleBasedScorer.php';

$pass = 0; $fail = 0;
function check($name, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; echo "PASS $name\n"; }
    else { $fail++; echo "FAIL $name: got " . var_export($got, true) . " want " . var_export($want, true) . "\n"; }
}

$s = new RuleBasedScorer();

// Best answers -> 100 / Included
$best = ['q2'=>1,'q3'=>1,'q4'=>1,'q5'=>1,'q7'=>1,'q8'=>1];
check('best score', $s->score($best)['score'], 100);
check('best category', $s->score($best)['category'], 'Included');

// Worst answers -> 0 / Excluded
$worst = ['q2'=>3,'q3'=>3,'q4'=>3,'q5'=>3,'q7'=>3,'q8'=>3];
check('worst score', $s->score($worst)['score'], 0);
check('worst category', $s->score($worst)['category'], 'Excluded');

// Mixed: q2=1(20) q3=2(10) q4=2(7) q5=2(8) q7=2(8) q8=3(0) = 53 -> Moderate
$mix = ['q2'=>1,'q3'=>2,'q4'=>2,'q5'=>2,'q7'=>2,'q8'=>3];
check('mixed score', $s->score($mix)['score'], 53);
check('mixed category', $s->score($mix)['category'], 'Moderate');

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/run.php`
Expected: FATAL — `ScoreService.php` / `RuleBasedScorer.php` not found.

- [ ] **Step 3: Write `src/Score/ScoreService.php`**

```php
<?php
// Scoring contract. Swap RuleBasedScorer for an ML-backed class later
// without changing any caller.
interface ScoreService
{
    /** @param array $answers keys q2..q8 (chosen option int)
     *  @return array ['score'=>int 0..100, 'category'=>string] */
    public function score(array $answers): array;
}
```

- [ ] **Step 4: Write `src/Score/RuleBasedScorer.php`**

```php
<?php
require_once __DIR__ . '/ScoreService.php';

// Transparent weighted-sum scorer. All numbers come from config/scoring.php.
class RuleBasedScorer implements ScoreService
{
    private array $weights;
    private array $categories;

    public function __construct(?array $config = null)
    {
        $config ??= require __DIR__ . '/../../config/scoring.php';
        $this->weights    = $config['weights'];
        $this->categories = $config['categories'];
    }

    public function score(array $answers): array
    {
        $score = 0;
        foreach ($this->weights as $q => $points) {
            $choice = $answers[$q] ?? null;
            $score += $points[$choice] ?? 0;   // unknown option = 0 points
        }
        return ['score' => $score, 'category' => $this->categorize($score)];
    }

    private function categorize(int $score): string
    {
        foreach ($this->categories as $bucket) {
            if ($score <= $bucket['max']) {
                return $bucket['label'];
            }
        }
        return end($this->categories)['label'];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php tests/run.php`
Expected: `6 passed, 0 failed`

- [ ] **Step 6: Commit**

```bash
git add src/Score tests/run.php
git commit -m "feat: rule-based scorer behind ScoreService interface (tested)"
```

---

## Task 3: Repositories + seed data

**Files:**
- Create: `src/Repository/ResponseRepository.php`, `src/Repository/UserRepository.php`, `sql/seed.php`

**Interfaces:**
- Consumes: `Database::connect()`, `RuleBasedScorer`, `config.php` admin block.
- Produces:
  - `ResponseRepository::__construct(PDO)`; `saveCompleted(string $sid, string $cell, array $answers, int $score, string $category): void` where `$answers` keys are `q2..q8`; `saveDeclined(string $sid): void`.
  - Dashboard aggregates: `total(): int`, `averageScore(): float`, `categoryBreakdown(): array` (`label=>count`), `byCell(): array` (`cell=>['count'=>int,'avg'=>float]`), `indicatorStats(): array` (keys `home_internet_pct`, `cannot_afford_pct`, `no_skills_pct`), `recent(int $limit): array`.
  - `UserRepository::__construct(PDO)`; `findByUsername(string): ?array`; `create(string $username, string $passwordHash): void`.

- [ ] **Step 1: Write `src/Repository/UserRepository.php`**

```php
<?php
class UserRepository
{
    public function __construct(private PDO $db) {}

    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        return $stmt->fetch() ?: null;
    }

    public function create(string $username, string $passwordHash): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (username, password_hash) VALUES (?, ?)'
        );
        $stmt->execute([$username, $passwordHash]);
    }
}
```

- [ ] **Step 2: Write `src/Repository/ResponseRepository.php`**

```php
<?php
class ResponseRepository
{
    public function __construct(private PDO $db) {}

    public function saveCompleted(string $sid, string $cell, array $a, int $score, string $category): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO responses
                (session_id, consented, cell, q2,q3,q4,q5,q6,q7,q8, score, category)
             VALUES (?,1,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $sid, $cell,
            $a['q2'], $a['q3'], $a['q4'], $a['q5'], $a['q6'], $a['q7'], $a['q8'],
            $score, $category,
        ]);
    }

    public function saveDeclined(string $sid): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO responses (session_id, consented) VALUES (?, 0)'
        );
        $stmt->execute([$sid]);
    }

    public function total(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM responses WHERE consented = 1'
        )->fetchColumn();
    }

    public function averageScore(): float
    {
        $v = $this->db->query(
            'SELECT AVG(score) FROM responses WHERE consented = 1'
        )->fetchColumn();
        return round((float) $v, 1);
    }

    public function categoryBreakdown(): array
    {
        $rows = $this->db->query(
            'SELECT category, COUNT(*) c FROM responses
             WHERE consented = 1 GROUP BY category'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) { $out[$r['category']] = (int) $r['c']; }
        return $out;
    }

    public function byCell(): array
    {
        $rows = $this->db->query(
            'SELECT cell, COUNT(*) c, AVG(score) a FROM responses
             WHERE consented = 1 GROUP BY cell'
        )->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[$r['cell']] = ['count' => (int) $r['c'], 'avg' => round((float) $r['a'], 1)];
        }
        return $out;
    }

    public function indicatorStats(): array
    {
        $total = $this->total() ?: 1; // avoid divide-by-zero
        $pct = fn(string $where) => round(
            100 * (int) $this->db->query(
                "SELECT COUNT(*) FROM responses WHERE consented = 1 AND $where"
            )->fetchColumn() / $total, 1
        );
        return [
            'home_internet_pct' => $pct('q3 = 1'),  // Yes, at home
            'cannot_afford_pct' => $pct('q4 = 3'),  // cannot afford
            'no_skills_pct'     => $pct('q7 = 3'),  // no digital skills
        ];
    }

    public function recent(int $limit): array
    {
        $stmt = $this->db->prepare(
            'SELECT cell, score, category, created_at FROM responses
             WHERE consented = 1 ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
```

- [ ] **Step 3: Write `sql/seed.php`** (uses the scorer so seeded scores are real)

```php
<?php
// Seed ~30 realistic fake responses across the 3 cells + one admin user.
// Run AFTER schema.sql is loaded: php sql/seed.php
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Score/RuleBasedScorer.php';
require __DIR__ . '/../src/Repository/ResponseRepository.php';
require __DIR__ . '/../src/Repository/UserRepository.php';

$db    = Database::connect();
$cfg   = require __DIR__ . '/../config/config.php';
$resp  = new ResponseRepository($db);
$users = new UserRepository($db);
$scorer = new RuleBasedScorer();

// Fresh start so re-running seed is idempotent.
$db->exec('DELETE FROM responses');
$db->exec('DELETE FROM users');

// Admin official.
$users->create($cfg['admin']['username'], password_hash($cfg['admin']['password'], PASSWORD_DEFAULT));

$cells = ['Kamashashi', 'Nonko', 'Rwimbogo'];
mt_srand(42); // deterministic seed data

for ($i = 0; $i < 30; $i++) {
    $cell = $cells[$i % 3];
    $a = [
        'q2' => mt_rand(1, 3),
        'q3' => mt_rand(1, 3),
        'q4' => mt_rand(1, 3),
        'q5' => mt_rand(1, 3),
        'q6' => mt_rand(1, 4),
        'q7' => mt_rand(1, 3),
        'q8' => mt_rand(1, 3),
    ];
    $r = $scorer->score($a);
    $resp->saveCompleted("seed-$i", $cell, $a, $r['score'], $r['category']);
}

echo "Seeded " . $resp->total() . " responses across " . count($cells) . " cells.\n";
```

- [ ] **Step 4: Run seed and verify**

Run: `php sql/seed.php`
Expected: `Seeded 30 responses across 3 cells.`

- [ ] **Step 5: Verify spread + admin**

Run: `mysql -h127.0.0.1 -udi_app -pdi_pass_2026 digital_inclusion -e "SELECT cell, COUNT(*) FROM responses GROUP BY cell; SELECT username FROM users;"`
Expected: 3 cells each with rows, `official` user present.

- [ ] **Step 6: Commit**

```bash
git add src/Repository sql/seed.php
git commit -m "feat: repositories + seed data (30 responses, admin user)"
```

---

## Task 4: USSD flow + stateless session machine (TDD)

**Files:**
- Create: `src/UssdFlow.php`, `src/UssdSession.php`
- Modify: `tests/run.php` (append flow tests)

**Interfaces:**
- Consumes: `RuleBasedScorer`, `ResponseRepository`.
- Produces:
  - `UssdFlow::screens(): array` — ordered list; index 0 = consent, 1 = Q1 cell, 2 = Q2 ... 8 = Q8. Each: `['key'=>string,'prompt'=>string,'options'=>[1=>label,...]]`. `key` for Q1 is `cell`, for others `q2..q8`, consent is `consent`.
  - `UssdSession::__construct(ResponseRepository, ScoreService)`; `handle(string $sessionId, string $text): string` returns `"CON ..."` or `"END ..."`. `$text` is the gateway's `*`-joined inputs (empty on first hit).

- [ ] **Step 1: Append failing flow tests to `tests/run.php`** (before the final summary `echo`)

```php
// --- USSD flow tests ---
require __DIR__ . '/../src/UssdFlow.php';
require __DIR__ . '/../src/UssdSession.php';

// Fake repo so flow tests need no database.
class FakeRepo {
    public array $completed = []; public array $declined = [];
    public function saveCompleted($s,$c,$a,$sc,$cat){ $this->completed[]=compact('s','c','a','sc','cat'); }
    public function saveDeclined($s){ $this->declined[]=$s; }
}

$repo = new FakeRepo();
$sess = new UssdSession($repo, new RuleBasedScorer());

// First hit -> consent screen (CON, mentions Welcome)
check('consent is CON', str_starts_with($sess->handle('s1',''), 'CON '), true);
check('consent text', str_contains($sess->handle('s1',''), 'Digital Inclusion Survey'), true);

// Decline -> END + declined saved
$out = $sess->handle('s2','2');
check('decline ends', str_starts_with($out,'END '), true);
check('decline saved', $repo->declined === ['s2'], true);

// Consent yes -> Q1 cell screen
check('after yes -> Q1', str_contains($sess->handle('s3','1'), 'cell'), true);

// Full best-path walk: consent=1, cell=1, q2..q8 all best (=1) -> END + saved 100
$full = '1*1*1*1*1*1*1*1*1'; // consent,cell,q2,q3,q4,q5,q6,q7,q8
$out = $sess->handle('s4', $full);
check('full walk ends', str_starts_with($out,'END '), true);
check('full walk thank-you', str_contains($out,'Thank you'), true);
check('full walk saved score', $repo->completed[0]['sc'], 100);
check('full walk saved cell', $repo->completed[0]['c'], 'Kamashashi');
```

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/run.php`
Expected: FATAL — `UssdFlow.php` not found.

- [ ] **Step 3: Write `src/UssdFlow.php`** (exact bilingual copy — do not paraphrase)

```php
<?php
// Single source of truth for the USSD survey screens, in order.
// Index 0 = consent gate; 1 = Q1 (cell); 2..8 = Q2..Q8.
// Copy is verbatim bilingual per the approved instrument.
class UssdFlow
{
    public static function screens(): array
    {
        return [
            [
                'key' => 'consent',
                'prompt' => "Welcome to the Digital Inclusion Survey for Nyarugunga Sector. Your answers are anonymous and will help local government improve digital services. Do you agree to continue? (Murakaza neza ku ibarura ry'ikoranabuhanga rya Nyarugunga. Ibisubizo byanyu ni ibanga kandi bizafasha ubuyobozi. Mwemeye gukomeza?)",
                'options' => [1 => 'Yes (Yego)', 2 => 'No (Oya)'],
            ],
            [
                'key' => 'cell',
                'prompt' => "In which cell do you live? (Utuye mu kagari kahe?)",
                'options' => [1 => 'Kamashashi', 2 => 'Nonko', 3 => 'Rwimbogo'],
            ],
            [
                'key' => 'q2',
                'prompt' => "What type of mobile phone do you own? (Ufite telefoni y'ubwoko ki?)",
                'options' => [1 => 'Smartphone (smartphone)', 2 => 'Basic/feature phone (Telefoni isanzwe)', 3 => 'I do not own a phone (Nta telefoni mfite)'],
            ],
            [
                'key' => 'q3',
                'prompt' => "Do you have access to the internet? (Ufite uburyo bwo kwinjira kuri interineti?)",
                'options' => [1 => 'Yes, at home (Yego, mu rugo)', 2 => 'Yes, but only sometimes (Yego, rimwe na rimwe)', 3 => 'No (Oya)'],
            ],
            [
                'key' => 'q4',
                'prompt' => "How easy is it for you to afford internet data? (Ubona ari byoroshye kwishyura interineti?)",
                'options' => [1 => 'Easy (Byoroshye)', 2 => 'Difficult (Bigoye)', 3 => 'I cannot afford it (Sinabishobora)'],
            ],
            [
                'key' => 'q5',
                'prompt' => "How often do you use the internet? (Ukoresha interineti kangahe?)",
                'options' => [1 => 'Every day (Buri munsi)', 2 => 'A few times a week (Inshuro nke mu cyumweru)', 3 => 'Rarely or never (Gake cyangwa nta na rimwe)'],
            ],
            [
                'key' => 'q6',
                'prompt' => "What do you mainly use the internet for? (Ukoresha interineti cyane cyane mu ki?)",
                'options' => [1 => 'Calls and messaging (Guhamagara no kohereza ubutumwa)', 2 => 'Social media (Imbuga nkoranyambaga)', 3 => 'Government/financial services (Serivisi za Leta / imari)', 4 => 'I do not use the internet (Sinkoresha interineti)'],
            ],
            [
                'key' => 'q7',
                'prompt' => "How would you rate your digital skills? (Wagena ute ubumenyi bwawe mu ikoranabuhanga?)",
                'options' => [1 => 'Good (Bwiza)', 2 => 'Basic (Bw\'ibanze)', 3 => 'None (Nta bumenyi mfite)'],
            ],
            [
                'key' => 'q8',
                'prompt' => "Have you ever used a mobile money or online government service? (Wigeze ukoresha serivisi ya mobile money cyangwa iya Leta kuri interineti?)",
                'options' => [1 => 'Yes, often (Yego, kenshi)', 2 => 'Yes, but rarely (Yego, ariko gake)', 3 => 'No (Oya)'],
            ],
        ];
    }

    // Render a screen as the menu text (prompt + numbered options).
    public static function render(array $screen): string
    {
        $lines = [$screen['prompt']];
        foreach ($screen['options'] as $n => $label) {
            $lines[] = "$n. $label";
        }
        return implode("\n", $lines);
    }
}
```

- [ ] **Step 4: Write `src/UssdSession.php`**

```php
<?php
require_once __DIR__ . '/UssdFlow.php';

// Stateless USSD state machine (MTN CON/END). The gateway sends the full
// accumulated input string in `text` (options joined by '*'); the number of
// inputs tells us which screen to show next.
class UssdSession
{
    private const CELLS = [1 => 'Kamashashi', 2 => 'Nonko', 3 => 'Rwimbogo'];

    public function __construct(private $responses, private $scorer) {}

    public function handle(string $sessionId, string $text): string
    {
        $inputs  = $text === '' ? [] : explode('*', $text);
        $screens = UssdFlow::screens();
        $step    = count($inputs); // 0 = show consent, 1 = show Q1, ...

        // Consent gate: if the user just answered consent with "No", end.
        if ($step >= 1 && $inputs[0] === '2') {
            $this->responses->saveDeclined($sessionId);
            return "END Thank you. You chose not to continue. (Murakoze. Mwahisemo kudakomeza.)";
        }

        // Still inside the questionnaire -> show the next screen.
        if ($step < count($screens)) {
            return "CON " . UssdFlow::render($screens[$step]);
        }

        // All answers collected -> score, save, end.
        // inputs: [0]=consent,[1]=cell,[2]=q2,[3]=q3,[4]=q4,[5]=q5,[6]=q6,[7]=q7,[8]=q8
        $answers = [
            'q2' => (int) $inputs[2], 'q3' => (int) $inputs[3],
            'q4' => (int) $inputs[4], 'q5' => (int) $inputs[5],
            'q6' => (int) $inputs[6], 'q7' => (int) $inputs[7],
            'q8' => (int) $inputs[8],
        ];
        $cell   = self::CELLS[(int) $inputs[1]] ?? 'Unknown';
        $result = $this->scorer->score($answers);
        $this->responses->saveCompleted($sessionId, $cell, $answers, $result['score'], $result['category']);

        return "END Thank you for completing the survey. (Murakoze kuzuza ibarura.)";
    }
}
```

- [ ] **Step 5: Run tests to verify pass**

Run: `php tests/run.php`
Expected: all PASS, `0 failed`.

- [ ] **Step 6: Commit**

```bash
git add src/UssdFlow.php src/UssdSession.php tests/run.php
git commit -m "feat: USSD bilingual flow + stateless session machine (tested)"
```

---

## Task 5: USSD endpoint + web simulator

**Files:**
- Create: `public/ussd.php`, `public/simulator.php`

**Interfaces:**
- Consumes: `Database`, `ResponseRepository`, `RuleBasedScorer`, `UssdSession`.
- Produces: HTTP endpoint accepting POST `sessionId`, `text` (and ignored `phoneNumber`), returning plain text `CON`/`END`. Simulator posts to it.

- [ ] **Step 1: Write `public/ussd.php`**

```php
<?php
// MTN-compatible USSD gateway endpoint. Accepts POST (sessionId, text),
// returns plain-text CON/END. phoneNumber is intentionally ignored (anonymous).
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Score/RuleBasedScorer.php';
require __DIR__ . '/../src/Repository/ResponseRepository.php';
require __DIR__ . '/../src/UssdSession.php';

header('Content-Type: text/plain; charset=utf-8');

$sessionId = $_POST['sessionId'] ?? 'local-' . substr(md5((string) microtime(true)), 0, 8);
$text      = $_POST['text'] ?? '';

$repo    = new ResponseRepository(Database::connect());
$session = new UssdSession($repo, new RuleBasedScorer());

echo $session->handle($sessionId, $text);
```

- [ ] **Step 2: Write `public/simulator.php`** (renders CON/END like a phone)

```php
<?php
// Browser-based USSD simulator. Posts the accumulated input string to ussd.php
// and shows the CON/END screen like a basic phone would.
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>USSD Simulator</title>
<style>
 body{font-family:system-ui;background:#222;display:flex;justify-content:center;padding:30px}
 .phone{width:300px;background:#000;border-radius:24px;padding:18px;color:#cfc}
 .screen{background:#04210a;min-height:260px;padding:14px;border-radius:8px;white-space:pre-wrap;font-size:14px}
 .end{color:#fc8}
 input,button{font-size:16px;padding:8px;margin-top:8px;width:100%;box-sizing:border-box}
 button{background:#1b5;color:#fff;border:0;border-radius:6px;cursor:pointer}
 .reset{background:#555}
</style></head><body>
<div class="phone">
  <div class="screen" id="screen">Press Start to dial *123#</div>
  <input id="reply" placeholder="Enter choice number" autocomplete="off">
  <button onclick="send()">Send</button>
  <button class="reset" onclick="reset()">Start / Reset</button>
</div>
<script>
let text = '', sessionId = '';
async function call() {
  const body = new URLSearchParams({sessionId, text});
  const r = await fetch('ussd.php', {method:'POST', body});
  const out = await r.text();
  const screen = document.getElementById('screen');
  screen.textContent = out.replace(/^(CON|END) /, '');
  screen.className = 'screen' + (out.startsWith('END') ? ' end' : '');
  if (out.startsWith('END')) text = ''; // session over
}
function reset() {
  text = ''; sessionId = 'sim-' + Date.now();
  document.getElementById('reply').value = '';
  call();
}
function send() {
  const v = document.getElementById('reply').value.trim();
  if (v === '') return;
  text = text === '' ? v : text + '*' + v;
  document.getElementById('reply').value = '';
  call();
}
reset();
</script>
</body></html>
```

- [ ] **Step 3: Start server**

Run: `php -S localhost:8000 -t public` (leave running in another terminal)

- [ ] **Step 4: Verify endpoint via curl — consent screen**

Run: `curl -s -X POST localhost:8000/ussd.php -d 'sessionId=t1&text='`
Expected: starts with `CON Welcome to the Digital Inclusion Survey`.

- [ ] **Step 5: Verify full walk saves a row**

Run: `curl -s -X POST localhost:8000/ussd.php -d 'sessionId=t2&text=1*2*1*1*1*1*1*1*1'`
Expected: `END Thank you for completing the survey. (Murakoze kuzuza ibarura.)`
Then: `mysql -h127.0.0.1 -udi_app -pdi_pass_2026 digital_inclusion -e "SELECT cell,score,category FROM responses WHERE session_id='t2';"`
Expected: one row, cell `Nonko`, score 100, `Included`.

- [ ] **Step 6: Verify simulator in browser**

Open `http://localhost:8000/simulator.php`, click Start, walk the survey. Expected: each screen renders bilingual; final shows END thank-you in amber.

- [ ] **Step 7: Commit**

```bash
git add public/ussd.php public/simulator.php
git commit -m "feat: USSD endpoint + web phone simulator"
```

---

## Task 6: Auth + login

**Files:**
- Create: `src/Auth.php`, `views/login.php`, `public/assets/app.css`

**Interfaces:**
- Consumes: `UserRepository`.
- Produces: `Auth::__construct(UserRepository)`; `attempt(string $u, string $p): bool` (sets `$_SESSION['uid']`); `check(): bool`; `logout(): void`. Login view posts `username`,`password` to `index.php?page=login`.

- [ ] **Step 1: Write `src/Auth.php`**

```php
<?php
// Session-based login for officials. Passwords are stored hashed.
class Auth
{
    public function __construct(private UserRepository $users) {}

    public function attempt(string $username, string $password): bool
    {
        $u = $this->users->findByUsername($username);
        if ($u && password_verify($password, $u['password_hash'])) {
            $_SESSION['uid'] = $u['id'];
            return true;
        }
        return false;
    }

    public function check(): bool { return !empty($_SESSION['uid']); }

    public function logout(): void { $_SESSION = []; session_destroy(); }
}
```

- [ ] **Step 2: Write `public/assets/app.css`**

```css
body{font-family:system-ui;margin:0;background:#f4f6f8;color:#1d2733}
header{background:#0b3d2e;color:#fff;padding:14px 22px;display:flex;justify-content:space-between;align-items:center}
header a{color:#cde;}
.wrap{max-width:1000px;margin:24px auto;padding:0 16px}
.cards{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.card{background:#fff;border-radius:10px;padding:18px 22px;box-shadow:0 1px 4px #0001;flex:1;min-width:160px}
.card b{font-size:28px;display:block}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}
.panel{background:#fff;border-radius:10px;padding:18px;box-shadow:0 1px 4px #0001}
table{width:100%;border-collapse:collapse}th,td{text-align:left;padding:8px;border-bottom:1px solid #eee}
.login{max-width:320px;margin:80px auto;background:#fff;padding:28px;border-radius:10px;box-shadow:0 1px 6px #0002}
.login input{width:100%;padding:10px;margin:8px 0;box-sizing:border-box}
.login button{width:100%;padding:10px;background:#0b3d2e;color:#fff;border:0;border-radius:6px;cursor:pointer}
.err{color:#b00;font-size:14px}
```

- [ ] **Step 3: Write `views/login.php`**

```php
<?php /* @var string|null $error */ ?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Official Login</title><link rel="stylesheet" href="assets/app.css"></head><body>
<form class="login" method="post" action="index.php?page=login">
  <h2>Digital Inclusion Dashboard</h2>
  <?php if (!empty($error)): ?><p class="err"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <input name="username" placeholder="Username" required>
  <input name="password" type="password" placeholder="Password" required>
  <button>Sign in</button>
</form></body></html>
```

- [ ] **Step 4: Smoke-test hashing**

Run: `php -r 'echo password_verify("changeme123", password_hash("changeme123", PASSWORD_DEFAULT)) ? "OK\n":"BAD\n";'`
Expected: `OK`

- [ ] **Step 5: Commit**

```bash
git add src/Auth.php views/login.php public/assets/app.css
git commit -m "feat: official auth + login view + base css"
```

---

## Task 7: Dashboard (router, overview, charts, table)

**Files:**
- Create: `public/index.php`, `views/dashboard.php`, `public/assets/chart.js`

**Interfaces:**
- Consumes: `Auth`, `UserRepository`, `ResponseRepository`, all aggregate methods from Task 3.
- Produces: routed dashboard at `index.php` (`?page=login|logout`, default overview). Charts via vendored Chart.js.

- [ ] **Step 1: Vendor Chart.js**

Run: `curl -sL https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js -o public/assets/chart.js && wc -c public/assets/chart.js`
Expected: a file > 100000 bytes. (If offline, the dashboard `<script src>` can point to the CDN URL instead — note in README.)

- [ ] **Step 2: Write `public/index.php`** (router + data load)

```php
<?php
session_start();
require __DIR__ . '/../src/Database.php';
require __DIR__ . '/../src/Repository/UserRepository.php';
require __DIR__ . '/../src/Repository/ResponseRepository.php';
require __DIR__ . '/../src/Auth.php';

$db    = Database::connect();
$auth  = new Auth(new UserRepository($db));
$page  = $_GET['page'] ?? 'overview';

if ($page === 'logout') { $auth->logout(); header('Location: index.php?page=login'); exit; }

if ($page === 'login') {
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($auth->attempt($_POST['username'] ?? '', $_POST['password'] ?? '')) {
            header('Location: index.php'); exit;
        }
        $error = 'Invalid username or password.';
    }
    require __DIR__ . '/../views/login.php';
    exit;
}

// Everything below requires login.
if (!$auth->check()) { header('Location: index.php?page=login'); exit; }

$repo = new ResponseRepository($db);
$total      = $repo->total();
$avg        = $repo->averageScore();
$categories = $repo->categoryBreakdown();
$cells      = $repo->byCell();
$indicators = $repo->indicatorStats();
$recent     = $repo->recent(15);

require __DIR__ . '/../views/dashboard.php';
```

- [ ] **Step 3: Write `views/dashboard.php`**

```php
<?php
/* @var int $total @var float $avg @var array $categories @var array $cells
   @var array $indicators @var array $recent */
$catOrder = ['Excluded','Low','Moderate','Included'];
$catCounts = array_map(fn($c) => $categories[$c] ?? 0, $catOrder);
$cellNames = array_keys($cells);
$cellCounts = array_map(fn($c) => $c['count'], $cells);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Digital Inclusion Dashboard</title>
<link rel="stylesheet" href="assets/app.css"><script src="assets/chart.js"></script></head><body>
<header>
  <strong>Digital Inclusion — Nyarugunga Sector</strong>
  <a href="index.php?page=logout">Logout</a>
</header>
<div class="wrap">
  <div class="cards">
    <div class="card"><span>Respondents</span><b><?= $total ?></b></div>
    <div class="card"><span>Avg inclusion score</span><b><?= $avg ?></b></div>
    <div class="card"><span>% home internet</span><b><?= $indicators['home_internet_pct'] ?>%</b></div>
    <div class="card"><span>% cannot afford data</span><b><?= $indicators['cannot_afford_pct'] ?>%</b></div>
    <div class="card"><span>% no digital skills</span><b><?= $indicators['no_skills_pct'] ?>%</b></div>
  </div>
  <div class="grid">
    <div class="panel"><h3>Category distribution</h3><canvas id="catChart"></canvas></div>
    <div class="panel"><h3>Respondents by cell</h3><canvas id="cellChart"></canvas></div>
    <div class="panel"><h3>Key indicators (%)</h3><canvas id="indChart"></canvas></div>
    <div class="panel">
      <h3>Recent responses</h3>
      <table><tr><th>Cell</th><th>Score</th><th>Category</th><th>When</th></tr>
        <?php foreach ($recent as $r): ?>
        <tr><td><?= htmlspecialchars($r['cell']) ?></td><td><?= $r['score'] ?></td>
            <td><?= htmlspecialchars($r['category']) ?></td><td><?= $r['created_at'] ?></td></tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>
<script>
new Chart(catChart, {type:'doughnut', data:{labels:<?= json_encode($catOrder) ?>,
  datasets:[{data:<?= json_encode($catCounts) ?>,
  backgroundColor:['#b00','#e67','#fb4','#1b5']}]}});
new Chart(cellChart, {type:'bar', data:{labels:<?= json_encode($cellNames) ?>,
  datasets:[{label:'Respondents', data:<?= json_encode($cellCounts) ?>, backgroundColor:'#0b3d2e'}]},
  options:{plugins:{legend:{display:false}}}});
new Chart(indChart, {type:'bar', data:{labels:['Home internet','Cannot afford','No skills'],
  datasets:[{label:'%', data:[<?= $indicators['home_internet_pct'] ?>,<?= $indicators['cannot_afford_pct'] ?>,<?= $indicators['no_skills_pct'] ?>],
  backgroundColor:['#1b5','#b00','#e67']}]},
  options:{scales:{y:{max:100}},plugins:{legend:{display:false}}}});
</script>
</body></html>
```

- [ ] **Step 4: Verify login redirect**

With server running, open `http://localhost:8000/index.php`. Expected: redirects to login form.

- [ ] **Step 5: Verify dashboard renders**

Log in with `official` / `changeme123`. Expected: 5 stat cards populated, 3 charts render from seed data, recent table lists rows.

- [ ] **Step 6: Commit**

```bash
git add public/index.php views/dashboard.php public/assets/chart.js
git commit -m "feat: officials dashboard with Chart.js + aggregates"
```

---

## Task 8: README + end-to-end run-through

**Files:**
- Create: `README.md`

**Interfaces:** none (documentation).

- [ ] **Step 1: Write `README.md`**

````markdown
# Digital Inclusion Assessment System — Nyarugunga Sector

Bilingual USSD digital-inclusion survey → MySQL → rule-based scorer → officials' dashboard.

## Requirements
- PHP 8.x with `pdo_mysql`
- MySQL 8

Install (Ubuntu): `sudo apt install mysql-server php8.3-mysql && sudo systemctl start mysql && sudo phpenmod pdo_mysql`

## Setup
1. Provision the database + app user (run once, as MySQL admin):
   ```sql
   CREATE DATABASE digital_inclusion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'di_app'@'127.0.0.1' IDENTIFIED BY 'di_pass_2026';
   CREATE USER 'di_app'@'localhost' IDENTIFIED BY 'di_pass_2026';
   GRANT ALL PRIVILEGES ON digital_inclusion.* TO 'di_app'@'127.0.0.1';
   GRANT ALL PRIVILEGES ON digital_inclusion.* TO 'di_app'@'localhost';
   FLUSH PRIVILEGES;
   ```
   (Default MySQL `root` uses socket auth; this dedicated user lets PHP connect over TCP. Change the password in both places + `config/config.php` if you like.)
2. Create tables: `mysql -h127.0.0.1 -udi_app -pdi_pass_2026 digital_inclusion < sql/schema.sql`
3. Seed data: `php sql/seed.php`
4. Start server: `php -S localhost:8000 -t public`

## Use
- USSD simulator: http://localhost:8000/simulator.php
- Dashboard: http://localhost:8000/index.php  (login: `official` / `changeme123`)

## Tests
`php tests/run.php`

## Tuning the scorer
All weights/thresholds live in `config/scoring.php` (see comment block).

## Replacing the scorer with ML
Implement `ScoreService` (`src/Score/ScoreService.php`) in a new class and swap it
in `public/ussd.php` and `sql/seed.php`. No other code changes.
````

- [ ] **Step 2: Full clean run-through**

Run, in order:
```bash
mysql -u root -p < sql/schema.sql
php sql/seed.php
php tests/run.php
php -S localhost:8000 -t public
```
Expected: schema loads, `Seeded 30 responses...`, `0 failed`, server starts. Then walk simulator → END, refresh dashboard → new row in recent table.

- [ ] **Step 3: Commit**

```bash
git add README.md
git commit -m "docs: README with setup, run, and ML-swap instructions"
```

---

## Self-Review

- **Spec coverage:** config+DB (T1) · scorer+interface (T2) · schema/seed/repos (T1,T3) · USSD flow+endpoint+simulator (T4,T5) · auth+dashboard+charts+table (T6,T7) · README+run commands (T8). Consent=No flag row (T4 saveDeclined + T1 schema). Anonymous (no msisdn stored, T5). All spec sections mapped.
- **Placeholder scan:** none — full code in every code step.
- **Type consistency:** `score(array):array` with `score`/`category` keys used identically in T2, T3 seed, T4 session. `$answers` keys `q2..q8` consistent across scorer/repo/session. Aggregate method names in T3 match T7 consumption (`total/averageScore/categoryBreakdown/byCell/indicatorStats/recent`). `Auth::attempt/check/logout` match T7 usage.
- **Note:** TDD applied to pure logic (scorer T2, flow T4); DB/HTTP/UI verified via curl + browser since no PHP test framework is installed (none requested).
