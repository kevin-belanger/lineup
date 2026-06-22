<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class StatisticController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.statistics');
    }
}
