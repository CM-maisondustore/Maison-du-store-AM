<?php

namespace App\Http\Controllers\Staff;

use App\Support\Help\HelpGuides;
use Illuminate\Http\Request;

class HelpController
{
    public function index(Request $request)
    {
        $user = $request->user();
        return view('staff.help', [
            'groups' => HelpGuides::grouped($user),
            'role' => HelpGuides::ROLES[HelpGuides::role($user)],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $user = $request->user();
        $guide = HelpGuides::find($user, $slug);
        abort_unless($guide, 404);
        return view('staff.help-guide', [
            'guide' => $guide,
            'html' => HelpGuides::html($guide),
            'next' => HelpGuides::next($user, $slug),
            'related' => array_values(array_filter(HelpGuides::grouped($user)[$guide['group']] ?? [], fn ($g) => $g['slug'] !== $slug)),
        ]);
    }
}
