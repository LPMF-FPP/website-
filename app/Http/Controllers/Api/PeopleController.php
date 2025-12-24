<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Person;

class PeopleController extends Controller
{
    public function __invoke(Person $person)
    {
        $this->authorize('view', $person);

        return response()->json([
            'id' => $person->id,
            'name' => $person->name,
            'role' => $person->role,
            'created_at' => optional($person->created_at)->toISOString(),
        ]);
    }
}
