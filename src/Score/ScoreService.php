<?php
// Scoring contract. Swap RuleBasedScorer for an ML-backed class later
// without changing any caller.
interface ScoreService
{
    /** @param array $answers keys q2..q8 (chosen option int)
     *  @return array ['score'=>int 0..100, 'category'=>string] */
    public function score(array $answers): array;
}
