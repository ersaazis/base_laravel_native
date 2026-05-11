<?php

namespace App\Http\Middleware;

use App\Services\MobileApiClient;
use App\Services\MobileCredentialStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAuthenticated
{
    public function __construct(
        private readonly MobileCredentialStore $credentials,
        private readonly MobileApiClient $api,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->credentials->isAuthenticated()) {
            return redirect()->route('login');
        }

        if ($this->credentials->needsTokenCheck() && ! $this->api->checkToken()) {
            return redirect()->route('login')->withErrors([
                'api' => __('mobile.errors.session_expired'),
            ]);
        }

        return $next($request);
    }
}
