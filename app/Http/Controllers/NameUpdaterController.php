<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\NameUpdated;
use Illuminate\Http\JsonResponse;

class NameUpdaterController extends Controller
{
    /**
     * Broadcast a name update to the public demo channel.
     */
    public function update(string $name): JsonResponse
    {
        NameUpdated::dispatch($name);

        return response()->json([
            'name' => $name,
        ]);
    }
}
