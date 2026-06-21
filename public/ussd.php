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
