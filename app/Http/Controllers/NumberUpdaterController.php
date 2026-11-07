<?php

namespace App\Http\Controllers;

use App\Events\NumberUpdated;

class NumberUpdaterController extends Controller
{
    public function update(int $number) {
        NumberUpdated::dispatch($number);

        return response()->json([
            'number' => $number
        ]);
    }
}
