<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\NumberUpdated;
use Illuminate\Http\JsonResponse;

class NumberUpdaterController extends Controller
{
    /**
     * Broadcast a number update to the public demo channel.
     */
    public function update(int $number): JsonResponse
    {
        NumberUpdated::dispatch($number);

        return response()->json([
            'number' => $number,
        ]);
    }
}
