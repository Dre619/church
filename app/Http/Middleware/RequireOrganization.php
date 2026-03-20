<?php

namespace App\Http\Middleware;

use App\Models\OrganizationUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if(!$request->user())
            {
                return $next($request);
            }
        if(auth()->check() && auth()->user()->role == 'admin'){
            return $next($request);
        }

        $belongsToAny = OrganizationUser::where('user_id', $request->user()->id)->exists();

        if (! $belongsToAny) {
            return redirect()
                ->route('create.organization')
                ->with('warning', 'To proceed, create an organization!');
        }

        return $next($request);
    }
}
