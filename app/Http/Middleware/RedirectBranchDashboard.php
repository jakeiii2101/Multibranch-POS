<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RedirectBranchDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->user()?->role, [User::ROLE_MANAGER, User::ROLE_SUPERVISOR], true)) {
            return redirect()->route('branch-monitor');
        }

        if ($request->user()?->isCashier() && DB::table('original_branch_inventory')->exists()) {
            return redirect()->route('pos');
        }

        return $next($request);
    }
}
