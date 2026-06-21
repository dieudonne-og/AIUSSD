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

echo "\n$pass passed, $fail failed\n";
exit($fail === 0 ? 0 : 1);
