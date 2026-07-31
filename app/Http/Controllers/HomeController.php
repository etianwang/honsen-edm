<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    public function index(): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $project = $user->canSeeAllProjects()
            ? Project::query()->orderBy('id')->first()
            : $user->projects()->orderBy('projects.id')->first();

        if (! $project) {
            abort(403, '你的账号暂未被分配任何项目，请联系管理员');
        }

        return redirect()->route('project.show', $project);
    }
}
