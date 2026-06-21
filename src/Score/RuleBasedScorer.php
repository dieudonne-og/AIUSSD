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
