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
