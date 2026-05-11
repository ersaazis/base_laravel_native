<?php

namespace App\Http\Middleware;

use App\Services\MobileCredentialStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileUnlocked
{
    public function __construct(private readonly MobileCredentialStore $credentials) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->credentials->shouldRequireUnlock()) {
            $this->credentials->lock();

            return redirect()->route('settings.unlock');
        }

        return $next($request);
    }
}
