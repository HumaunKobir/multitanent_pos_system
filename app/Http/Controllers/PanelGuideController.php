<?php

namespace App\Http\Controllers;

use App\Support\PanelGuide;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PanelGuideController extends Controller
{
    public function __invoke(Request $request, PanelGuide $panelGuide): Response
    {
        $user = $request->user();
        $guide = $panelGuide->build($user);

        if ($guide['sections'] === []) {
            abort(403);
        }

        return Inertia::render('admin/panel-guide', [
            'panelGuide' => $guide,
        ]);
    }
}
