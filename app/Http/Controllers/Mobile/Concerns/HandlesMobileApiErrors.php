<?php

namespace App\Http\Controllers\Mobile\Concerns;

use App\Services\MobileApiException;
use Illuminate\Http\RedirectResponse;

trait HandlesMobileApiErrors
{
    public function backWithApiError(MobileApiException $exception): RedirectResponse
    {
        return back()
            ->withInput(request()->except([
                'current_password',
                'two_factor_password',
                'password',
                'password_confirmation',
            ]))
            ->withErrors($exception->errors ?: ['api' => [$exception->getMessage()]]);
    }
}
