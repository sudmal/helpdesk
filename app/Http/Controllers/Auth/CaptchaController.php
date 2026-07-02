<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;

class CaptchaController
{
    public function generate(Request $request): \Illuminate\Http\JsonResponse
    {
        $a  = random_int(1, 9);
        $b  = random_int(1, 9);
        $op = random_int(0, 1) ? '+' : '-';
        if ($op === '-' && $a < $b) { [$a, $b] = [$b, $a]; }
        $answer = ($op === '+') ? ($a + $b) : ($a - $b);

        $request->session()->put('captcha_answer', $answer);

        $noise = '';
        for ($i = 0; $i < 5; $i++) {
            $x1 = random_int(5, 175); $y1 = random_int(5, 45);
            $x2 = random_int(5, 175); $y2 = random_int(5, 45);
            $noise .= "<line x1=\"{$x1}\" y1=\"{$y1}\" x2=\"{$x2}\" y2=\"{$y2}\" stroke=\"#1e3a5f\" stroke-width=\"1.5\"/>";
        }

        $expr = htmlspecialchars("{$a} {$op} {$b} = ?");
        $svg  = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"180\" height=\"54\">"
              . "<rect width=\"180\" height=\"54\" fill=\"#0f172a\" rx=\"8\"/>"
              . $noise
              . "<text x=\"90\" y=\"36\" text-anchor=\"middle\" font-size=\"23\" "
              . "font-family=\"Courier New, monospace\" font-weight=\"bold\" fill=\"#93c5fd\" letter-spacing=\"5\">"
              . $expr
              . "</text></svg>";

        return response()->json(['img' => 'data:image/svg+xml;base64,' . base64_encode($svg)]);
    }
}