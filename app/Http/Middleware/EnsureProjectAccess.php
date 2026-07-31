<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProjectAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $project = $request->route('project');

        if ($project instanceof Project && ! $user->canSeeAllProjects()) {
            $hasAccess = $user->projects()->whereKey($project->id)->exists();

            if (! $hasAccess) {
                abort(403, '你没有权限查看该项目');
            }
        }

        return $next($request);
    }
}
