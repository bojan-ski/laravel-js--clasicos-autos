<?php

namespace App\Http\Middleware;

use App\Support\Seo\Indexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddRobotsTag
{
    public function __construct(private Indexing $indexing) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $directive = $this->indexing->robotsDirective($request, $response->getStatusCode());

        if ($directive !== null) {
            $response->headers->set('X-Robots-Tag', $directive);
        }

        return $response;
    }
}
