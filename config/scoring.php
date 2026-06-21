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
