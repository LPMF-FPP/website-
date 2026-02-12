<?php

namespace App\Http\Controllers;

use App\Services\ChangelogService;

class ChangelogController extends Controller
{
    public function __construct(
        private readonly ChangelogService $service
    ) {}

    public function index()
    {
        $changelogs = $this->service->getChangelogs();

        return view('changelogs.index', compact('changelogs'));
    }
}
