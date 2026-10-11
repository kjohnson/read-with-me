<?php

namespace App\Http\Controllers;

use App\Support\Story;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the landing page, or send signed-in users to the story list.
     */
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('stories.index');
        }

        return view('welcome', ['stories' => Story::all()]);
    }
}
