<?php

namespace App\Http\Controllers\Quality;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class QmhReportController extends Controller
{
    public function index(): View
    {
        return view('quality.reports.index');
    }
}
