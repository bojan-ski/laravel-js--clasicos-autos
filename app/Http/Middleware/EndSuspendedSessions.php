<?php

namespace App\Http\Middleware;

use App\Services\SuspensionService;
use App\Support\SuspensionText;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EndSuspendedSessions
{
    public function __construct(private SuspensionService $suspensions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $this->suspensions->liftIfExpired($user);

        if (! $user->isSuspended()) {
            return $next($request);
        }

        $notice = SuspensionText::notice($user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $notice], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('login')->withErrors(['email' => $notice]);
    }
}
