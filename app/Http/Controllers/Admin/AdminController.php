<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-taxonomy');

        $teams = Team::query()
            ->orderBy('sort_order')
            ->with(['specialties' => fn ($q) => $q->orderBy('sort_order')->withCount('subcategories')])
            ->get();

        $projects = Project::query()->with('country')->orderBy('id')->get();

        return view('admin.index', [
            'teams' => $teams,
            'projects' => $projects,
        ]);
    }
}
