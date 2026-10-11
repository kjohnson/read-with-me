<?php

namespace App\Http\Controllers;

use App\Support\Story;
use Illuminate\View\View;

class StoryController extends Controller
{
    /**
     * Show the stories available to read.
     */
    public function index(): View
    {
        return view('stories.index', ['stories' => Story::all()]);
    }
}
