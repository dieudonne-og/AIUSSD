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
