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
