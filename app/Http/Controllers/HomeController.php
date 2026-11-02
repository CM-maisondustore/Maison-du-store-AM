<?php
namespace App\Http\Controllers;
use App\Models\SiteContent;
class HomeController
{
    public function __invoke()
    {
        $contents = SiteContent::with('media')->where('published', true)->orderBy('position')->orderBy('id')->get();
        return view('home', ['hero' => $contents->firstWhere('slot', 'hero'), 'contactIllustration' => $contents->firstWhere('slot', 'contact'), 'portfolio' => $contents->where('kind', 'portfolio')]);
    }
}
