<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobileApiClient;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly MobileApiClient $api) {}

    public function index(): View
    {
        return view('mobile.settings.index', [
            'baseUrl' => $this->api->baseUrl(),
        ]);
    }
}
