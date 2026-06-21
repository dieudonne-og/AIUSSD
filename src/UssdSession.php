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
