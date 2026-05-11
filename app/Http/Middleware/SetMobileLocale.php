<?php

namespace App\Http\Middleware;

use App\Services\MobileCredentialStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetMobileLocale
{
    public function __construct(private readonly MobileCredentialStore $credentials) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->credentials->activeLocale());

        return $next($request);
    }
}
