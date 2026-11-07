<?php

namespace App\Http\Controllers;

use App\Events\NameUpdated;

class NameUpdaterController extends Controller
{
    public function update(string $name)
    {
        NameUpdated::dispatch($name);

        return response()->json([
            'name' => $name,
        ]);
    }
}
