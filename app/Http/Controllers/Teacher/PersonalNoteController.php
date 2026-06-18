<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PersonalNoteController extends Controller
{
    public function __invoke(): View
    {
        return view('teacher.personal-notes');
    }
}
