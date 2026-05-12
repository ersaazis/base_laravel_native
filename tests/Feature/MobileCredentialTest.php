<?php

use App\Models\MobileCredential;
use App\Services\MobileApiClient;
use App\Services\MobileCredentialStore;
use App\Services\OpenApiSpec;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

test('login page renders for guests', function () {
    Http::preventStrayRequests();

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('<footer', false)
        ->assertDontSee('<native:bottom-nav', false)
        ->assertDontSee('bottom-nav-item', false)
        ->assertDontSee('data-biometric-overlay', false)
        ->assertDontSee('biometric-progress', false)
        ->assertDontSee('Secure access to your vault')
        ->assertDontSee('data-biometric-login', false)
        ->assertDontSee('Use Biometrics')
        ->assertSee('name="email"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('Buat akun baru')
        ->assertDontSee('two_factor_code')
        ->assertDontSee('recovery_code');
});

test('login page shows signup when site config enables registration', function () {
    app(MobileCredentialStore::class)->updateSiteConfig(site_config_payload(true));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Create new account');
});

test('signup routes follow disabled registration from site config', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
    ]);

    $this->get(route('signup'))->assertRedirect(route('login'));

    $this->post(route('signup.store'), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('login'));
});

test('sanctum token is encrypted in the local credential table', function () {
    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', [
        'id' => 1,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $stored = MobileCredential::query()->firstOrFail();

    expect($stored->plain_text_token)->toBe('1|sanctum-token');
    expect($stored->getRawOriginal('plain_text_token'))->not->toContain('sanctum-token');
});

test('openapi spec contains the sanctum token check endpoint', function () {
    expect(app(OpenApiSpec::class)->hasOperation('get', '/auth/check-token'))->toBeTrue();
});

test('openapi spec contains the two factor challenge endpoint', function () {
    expect(app(OpenApiSpec::class)->hasOperation('post', '/auth/two-factor-challenge'))->toBeTrue();
});

test('openapi spec contains the two factor recovery code endpoint', function () {
    expect(app(OpenApiSpec::class)->hasOperation('post', '/auth/two-factor-recovery-code'))->toBeTrue();
});

test('email password login stores a sanctum token', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/login') => Http::response([
            'message' => 'Authenticated.',
            'data' => [
                'two_factor' => false,
                'user' => user_payload(),
                'plain_text_token' => '1|sanctum-token',
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => 'jane@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $credential = MobileCredential::query()->firstOrFail();

    expect($credential->plain_text_token)->toBe('1|sanctum-token');
    expect($credential->user)->toMatchArray(user_payload());
});

test('mobile credentials are isolated per client cookie', function () {
    $webClient = (string) Str::uuid();
    $jumpClient = (string) Str::uuid();

    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/login') => Http::response([
            'message' => 'Authenticated.',
            'data' => [
                'two_factor' => false,
                'user' => user_payload(),
                'plain_text_token' => '1|web-token',
            ],
        ]),
        api_url('/site-config') => site_config_response(false),
        api_url('/auth/check-token') => Http::response([
            'message' => 'Success.',
            'data' => ['active' => true, 'user' => user_payload()],
        ]),
    ]);

    $this->withCookie('mobile_client_id', $webClient)
        ->post(route('login.store'), [
            'email' => 'web@example.com',
            'password' => 'password',
        ])
        ->assertRedirect(route('dashboard'));

    expect(MobileCredential::query()->where('client_id', $webClient)->exists())->toBeTrue();

    $this->withCookie('mobile_client_id', $jumpClient)
        ->post(route('startup.check'))
        ->assertRedirect(route('login'));

    expect(MobileCredential::query()
        ->where('client_id', $webClient)
        ->whereNotNull('plain_text_token')
        ->exists())->toBeTrue();
    expect(MobileCredential::query()
        ->where('client_id', $jumpClient)
        ->whereNotNull('plain_text_token')
        ->exists())->toBeFalse();
});

test('startup renders site config branding', function () {
    app(MobileCredentialStore::class)->updateSiteConfig(site_config_payload(false, [
        'site_name' => 'Golf Specialist',
        'logo_url' => 'https://example.com/logo.png',
    ]));

    $this->get(route('startup'))
        ->assertOk()
        ->assertSee('Golf Specialist')
        ->assertSee('https://example.com/logo.png');
});

test('startup loading screen does not render a header bar', function () {
    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('startup'))
        ->assertOk()
        ->assertSee('data-startup-screen', false)
        ->assertDontSee('<header', false)
        ->assertDontSee('border-b border-vault-border/40 bg-vault-bg', false);
});

test('startup check redirects guests to login', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
    ]);

    $this->post(route('startup.check'))->assertRedirect(route('login'));
});

test('startup check has get and javascript fallback for native jump webviews', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
    ]);

    $this->get(route('startup'))
        ->assertOk()
        ->assertSee('data-startup-check', false)
        ->assertSee('method="GET"', false)
        ->assertSee('data-startup-check-url', false)
        ->assertSee('<noscript>', false)
        ->assertSee('http-equiv="refresh"', false)
        ->assertSee('window.location.replace', false)
        ->assertDontSee('startupForm.submit()', false)
        ->assertDontSee('startupForm.requestSubmit()', false);

    $this->get(route('startup.check'))->assertRedirect(route('login'));
});

test('startup check accepts native jump auto submits without a csrf cookie', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
    ]);

    $this->app->instance('env', 'local');
    $this->withMiddleware();

    $this->post(route('startup.check'))->assertRedirect(route('login'));
});

test('startup check validates token before dashboard', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/auth/check-token') => Http::response([
            'message' => 'Success.',
            'data' => ['active' => true, 'user' => user_payload()],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('startup.check'))->assertRedirect(route('dashboard'));
});

test('startup check clears invalid tokens', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/auth/check-token') => Http::response(['message' => 'Unauthenticated.'], 401),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('startup.check'))->assertRedirect(route('login'));

    expect(app(MobileCredentialStore::class)->isAuthenticated())->toBeFalse();
});

test('native double back to close plugin is registered and enabled from dashboard shell', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $script = file_get_contents(resource_path('js/app.js'));
    $provider = file_get_contents(app_path('Providers/NativeServiceProvider.php'));
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($layout)
        ->toContain("'doubleBackToClose' => __('mobile.common.double_back_to_close')")
        ->toContain("'doubleBackToClose' => \$showsMobileLayout")
        ->not->toContain('mobile-biometrics')
        ->not->toContain('biometricsPluginInstalled');

    expect($script)
        ->toContain('function syncDoubleBackToClose')
        ->toContain('let doubleBackToCloseEnabled = false')
        ->toContain("routePath(url) === '/home'")
        ->toContain('DoubleBackToClose.Enable')
        ->toContain('DoubleBackToClose.Disable')
        ->toContain('mobileMessages.doubleBackToClose')
        ->not->toContain('Biometric');

    expect($provider)
        ->toContain('DoubleBackToCloseServiceProvider::class')
        ->toContain('DialogServiceProvider::class');

    expect(array_key_exists('codingwithrk/double-back-to-close', $composer['require']))->toBeTrue();
    expect(array_key_exists('nativephp/mobile-dialog', $composer['require']))->toBeTrue();
});

test('mobile buttons show processing state to prevent duplicate clicks', function () {
    $script = file_get_contents(resource_path('js/app.js'));
    $styles = file_get_contents(resource_path('css/app.css'));
    $feedback = file_get_contents(resource_path('views/layouts/partials/feedback.blade.php'));

    expect($script)
        ->toContain('function setButtonProcessing')
        ->toContain('const processingTimeouts = new WeakMap()')
        ->toContain('function resetProcessingStates')
        ->toContain('scheduleProcessingReset(button, () => setButtonProcessing(button, false))')
        ->toContain('scheduleProcessingReset(form, () => setFormProcessing(form, false), 15000)')
        ->toContain("button.dataset.processing = 'true'")
        ->toContain("button.setAttribute('aria-busy', 'true')")
        ->toContain('setFormProcessing(form, true, event.submitter)')
        ->toContain('[data-copy-value]')
        ->toContain("button.matches('[data-copy-value], [data-password-toggle]')")
        ->toContain("window.addEventListener('pageshow'")
        ->not->toContain("document.addEventListener('pointerdown'")
        ->not->toContain('data-biometric-cancel');

    expect($styles)
        ->toContain("button[data-processing='true']")
        ->toContain('pointer-events: none')
        ->toContain('opacity: 0.42')
        ->not->toContain("button[data-processing='true']::after")
        ->not->toContain('button-processing-spin');
});

test('password toggle swaps icon and pressed state', function () {
    $field = file_get_contents(resource_path('views/mobile/partials/field.blade.php'));
    $icons = file_get_contents(resource_path('views/mobile/partials/icon.blade.php'));
    $script = file_get_contents(resource_path('js/app.js'));

    expect($field)
        ->toContain('aria-pressed="false"')
        ->toContain('data-password-icon-show')
        ->toContain('data-password-icon-hide')
        ->toContain("'name' => 'eye'")
        ->toContain("'name' => 'eye-off'");

    expect($icons)->toContain("@case('eye')");

    expect($script)
        ->toContain("const revealing = input.type === 'password'")
        ->toContain("button.setAttribute('aria-pressed', revealing ? 'true' : 'false')")
        ->toContain("button.querySelector('[data-password-icon-show]')")
        ->toContain("button.querySelector('[data-password-icon-hide]')")
        ->toContain('showIcon.hidden = revealing')
        ->toContain('hideIcon.hidden = !revealing');
});

test('mobile color palette is monochrome', function () {
    $styles = file_get_contents(resource_path('css/app.css'));
    $tabs = file_get_contents(resource_path('views/mobile/profile/partials/tabs.blade.php'));

    expect($styles)
        ->toContain('--color-vault-bg: #050505')
        ->toContain('--color-vault-primary: #ffffff')
        ->not->toContain('56, 189, 248')
        ->not->toContain('125, 211, 252')
        ->not->toContain('250, 204, 21')
        ->not->toContain('#38bdf8')
        ->not->toContain('#facc15')
        ->not->toContain('#7dd3fc');

    expect($tabs)
        ->toContain('rgba(255,255,255,0.14)')
        ->not->toContain('rgba(56,189,248,0.22)');
});

test('mobile typography uses helvetica font stack', function () {
    $styles = file_get_contents(resource_path('css/app.css'));
    $welcome = file_get_contents(resource_path('views/welcome.blade.php'));

    expect($styles)
        ->toContain("--font-sans: 'Helvetica Neue', Helvetica, Arial")
        ->toContain('font-family: var(--font-sans)')
        ->not->toContain('Instrument Sans');

    expect($welcome)
        ->toContain("'Helvetica Neue', Helvetica, Arial")
        ->not->toContain('Instrument Sans');
});

test('mobile shell uses svelte spa navigation', function () {
    $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $script = file_get_contents(resource_path('js/app.js'));
    $spa = file_get_contents(resource_path('js/mobile-spa.svelte'));
    $package = json_decode(file_get_contents(base_path('package.json')), true);
    $viteConfig = file_get_contents(base_path('vite.config.js'));

    expect($layout)
        ->toContain('data-mobile-spa-root')
        ->toContain('data-mobile-runtime-config')
        ->toContain('data-mobile-spa-controller');

    expect($script)
        ->toContain("import { mount } from 'svelte'")
        ->toContain("import MobileSpa from './mobile-spa.svelte'")
        ->toContain('window.mobileApp')
        ->toContain('initializeMobilePage')
        ->toContain('options.animate !== false')
        ->toContain('initializeMobileToasts');

    expect($spa)
        ->toContain("document.addEventListener('click', onClick)")
        ->toContain("document.addEventListener('submit', onSubmit)")
        ->toContain('currentRoot.replaceWith(nextRoot)')
        ->toContain("Accept: 'text/html, application/xhtml+xml'")
        ->toContain("cache: 'no-store'")
        ->toContain("'Cache-Control': 'no-cache'")
        ->toContain("Pragma: 'no-cache'")
        ->toContain('let navigationVersion = 0')
        ->toContain('function nextNavigationVersion')
        ->toContain('function isCurrentNavigation')
        ->toContain('navigationId: result.navigationId')
        ->toContain('function syncDocumentMetadata')
        ->toContain('document.documentElement.lang = nextDocument.documentElement.lang')
        ->toContain('document.body.className = nextDocument.body.className')
        ->toContain('function showRouteSkeleton')
        ->toContain('function updateBottomNavigation')
        ->toContain('document.querySelector(bottomNavSelector)')
        ->toContain("item.classList.toggle('is-active', active)")
        ->toContain('content.replaceChildren(routeSkeleton(url))')
        ->toContain('function samePage')
        ->toContain('beforeScrollTop')
        ->toContain('animate: false')
        ->toContain('window.mobileApp?.setFormProcessing?.(form, false)')
        ->toContain('event.stopImmediatePropagation()')
        ->toContain("if (error instanceof DOMException && error.name === 'AbortError')")
        ->toContain('abortController === controller')
        ->toContain("historyMode: 'replace'")
        ->not->toContain('mobileSpaNavigating');

    expect(array_key_exists('svelte', $package['devDependencies']))->toBeTrue();
    expect(array_key_exists('@sveltejs/vite-plugin-svelte', $package['devDependencies']))->toBeTrue();
    expect($viteConfig)->toContain('svelte()');
});

test('mobile runtime avoids jump hot reload and expensive webview effects', function () {
    $viteConfig = file_get_contents(base_path('vite.config.js'));
    $nativeConfig = file_get_contents(config_path('nativephp.php'));
    $styles = file_get_contents(resource_path('css/app.css'));
    $mobileLayout = file_get_contents(resource_path('views/layouts/mobile.blade.php'));
    $appLayout = file_get_contents(resource_path('views/layouts/app.blade.php'));
    $plainLayout = file_get_contents(resource_path('views/layouts/plain.blade.php'));
    $script = file_get_contents(resource_path('js/app.js'));
    $spa = file_get_contents(resource_path('js/mobile-spa.svelte'));

    expect($viteConfig)
        ->toContain('refresh: false')
        ->toContain('hmr: false')
        ->not->toContain('refresh: true')
        ->not->toContain('hmr: {');

    expect($nativeConfig)
        ->toContain("'runtime' => [")
        ->toContain("'mode' => env('NATIVEPHP_RUNTIME_MODE', 'persistent')")
        ->toContain("'reset_instances' => env('NATIVEPHP_RUNTIME_RESET_INSTANCES', true)")
        ->toContain("'gc_between_dispatches' => env('NATIVEPHP_RUNTIME_GC_BETWEEN_DISPATCHES', false)");

    expect($styles)
        ->not->toContain('backdrop-filter')
        ->not->toContain('scale:')
        ->toContain('transform: scale(0.985)')
        ->toContain('.mobile-bottom-nav-item.is-active')
        ->toContain('.mobile-route-skeleton')
        ->toContain('.mobile-skeleton-block')
        ->toContain('.mobile-performance-mode .vault-pattern::before')
        ->toContain('.mobile-performance-mode .vault-card')
        ->toContain('box-shadow: none');

    expect($appLayout)
        ->toContain('mobile-performance-mode')
        ->toContain("'performanceMode' => true")
        ->toContain("'spaTimeout' => 10000");

    expect($script)
        ->toContain('function isPerformanceMode')
        ->toContain('if (reduceMotion || isPerformanceMode())')
        ->toContain('function stopNotificationRefresh')
        ->toContain('notificationRefreshInFlight')
        ->toContain('}, 2500)')
        ->toContain('Math.max(30000')
        ->toContain('startupForm.isConnected')
        ->toContain('window.location.replace(startupForm.dataset.startupCheckUrl || startupForm.action)')
        ->toContain('syncDoubleBackToClose')
        ->not->toContain('startupForm.requestSubmit()')
        ->not->toContain('startupForm.submit()')
        ->not->toContain('autoBiometricForm');

    expect($spa)
        ->toContain('window.mobileSecurity?.spaTimeout || 10000')
        ->toContain('controller.abort()');

    expect($mobileLayout)
        ->toContain('data-notification-refresh="60000"')
        ->toContain('data-mobile-home-logo')
        ->toContain('data-mobile-nav-item="home"')
        ->toContain('data-mobile-nav-item="profile"')
        ->toContain("request()->routeIs('profile.*', 'settings.*', 'security.*')")
        ->not->toContain('shadow-[0_0_18px')
        ->not->toContain('backdrop-blur');

    expect($plainLayout)
        ->not->toContain('backdrop-blur');
});

test('two factor setup actions preserve the mobile spa scroll position', function () {
    $view = file_get_contents(resource_path('views/mobile/security/index.blade.php'));
    $spa = file_get_contents(resource_path('js/mobile-spa.svelte'));

    expect($view)
        ->toContain("route('security.two-factor.enable')")
        ->toContain("route('security.two-factor.cancel')")
        ->toContain("route('security.two-factor.recovery-codes')");

    expect(substr_count($view, 'data-spa-preserve-scroll'))->toBeGreaterThanOrEqual(3);

    expect($spa)
        ->toContain('function shouldPreserveFormScroll')
        ->toContain("form.hasAttribute('data-spa-preserve-scroll')")
        ->toContain('shouldPreserveFormScroll(form, method, beforeUrl, finalUrl)');
});

test('flash feedback renders as fixed mobile toast without changing content layout', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);
    $script = file_get_contents(resource_path('js/app.js'));
    $styles = file_get_contents(resource_path('css/app.css'));
    $feedback = file_get_contents(resource_path('views/layouts/partials/feedback.blade.php'));

    $this->withSession(['status' => 'Password updated.'])
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Password updated.')
        ->assertSee('mobile-toast-region', false)
        ->assertSee('data-mobile-toast', false)
        ->assertSee('mobile-toast-close', false)
        ->assertSee('Dismiss notification')
        ->assertDontSee('mb-4 rounded-2xl border border-vault-success', false);

    expect($script)
        ->toContain('function dismissMobileToast')
        ->toContain("toast.addEventListener('click'")
        ->toContain("event.key === 'Enter' || event.key === ' '")
        ->toContain('dismissMobileToast(toast)');

    expect($styles)
        ->toContain('.mobile-toast-close')
        ->toContain('width: 2.75rem')
        ->toContain('cursor: pointer')
        ->toContain('.mobile-toast:focus-visible');

    expect($feedback)
        ->toContain('\Native\Mobile\Facades\Dialog::toast($toastMessage)')
        ->toContain('! app()->runningUnitTests()');
});

test('email password login redirects to two factor challenge when required', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/login') => Http::response([
            'message' => 'Two factor authentication required.',
            'data' => [
                'two_factor' => true,
                'challenge_token' => 'temporary-challenge-token',
            ],
        ], 202),
    ]);

    $this->post(route('login.store'), [
        'email' => 'jane@example.com',
        'password' => 'password',
    ])
        ->assertRedirect(route('two-factor.challenge'))
        ->assertSessionHas('mobile_two_factor_challenge_token', 'temporary-challenge-token');

    expect(MobileCredential::query()->count())->toBe(0);
});

test('two factor challenge page accepts an authenticator or recovery code', function () {
    $this->withSession([
        'mobile_two_factor_challenge_token' => 'temporary-challenge-token',
    ])
        ->get(route('two-factor.challenge'))
        ->assertOk()
        ->assertSee('name="code"', false)
        ->assertSee('name="recovery_code"', false)
        ->assertSee('Use recovery code');
});

test('two factor challenge stores a sanctum token and clears the challenge token', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/two-factor-challenge') => Http::response([
            'message' => 'Authenticated.',
            'data' => [
                'two_factor' => true,
                'user' => user_payload(),
                'plain_text_token' => '1|sanctum-token',
            ],
        ]),
    ]);

    $this->withSession([
        'mobile_two_factor_challenge_token' => 'temporary-challenge-token',
    ])
        ->post(route('two-factor.challenge.store'), [
            'code' => '123456',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('mobile_two_factor_challenge_token');

    expect(MobileCredential::query()->firstOrFail()->plain_text_token)->toBe('1|sanctum-token');
});

test('two factor challenge accepts a recovery code when authenticator code is unavailable', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/two-factor-recovery-code') => Http::response([
            'message' => 'Authenticated.',
            'data' => [
                'two_factor' => true,
                'user' => user_payload(),
                'plain_text_token' => '1|sanctum-token',
            ],
        ]),
    ]);

    $this->withSession([
        'mobile_two_factor_challenge_token' => 'temporary-challenge-token',
    ])
        ->post(route('two-factor.challenge.store'), [
            'recovery_code' => 'recovery-code',
        ])
        ->assertRedirect(route('dashboard'))
        ->assertSessionMissing('mobile_two_factor_challenge_token');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains((string) $request->url(), '/auth/two-factor-recovery-code')
        && $request['challenge_token'] === 'temporary-challenge-token'
        && $request['recovery_code'] === 'recovery-code'
        && ! isset($request['code']));

    expect(MobileCredential::query()->firstOrFail()->plain_text_token)->toBe('1|sanctum-token');
});

test('two factor challenge requires either an authenticator or recovery code', function () {
    Http::preventStrayRequests();
    Http::fake();

    $this->withSession([
        'mobile_two_factor_challenge_token' => 'temporary-challenge-token',
    ])
        ->post(route('two-factor.challenge.store'))
        ->assertSessionHasErrors(['code', 'recovery_code']);

    Http::assertNothingSent();
});

test('two factor challenge requires a local challenge token', function () {
    $this->post(route('two-factor.challenge.store'), [
        'code' => '123456',
    ])->assertRedirect(route('login'));
});

test('current mobile screens render without debug pre blocks', function (string $routeName) {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/profile') => Http::response([
            'message' => 'Success.',
            'data' => ['user' => user_payload()],
        ]),
        api_url('/profile/access') => Http::response([
            'message' => 'Success.',
            'data' => [
                'role' => ['id' => 1, 'name' => 'Administrator', 'key' => 'administrator'],
                'permissions' => ['users' => ['view-active', 'create']],
            ],
        ]),
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => true],
        ]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => ['setup_key' => 'ABCD EFGH'],
        ]),
        api_url('/profile/security/two-factor/recovery-codes') => Http::response([
            'message' => 'Success.',
            'data' => ['recovery_codes' => ['code-one', 'code-two']],
        ]),
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [
                [
                    'id' => 'notification-1',
                    'title' => 'Welcome',
                    'message' => 'Akun siap dipakai.',
                    'read' => false,
                    'created_at_human' => 'now',
                ],
            ],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route($routeName))
        ->assertOk()
        ->assertDontSee('<pre', false);
})->with([
    'dashboard',
    'profile.edit',
    'security.index',
    'notifications.index',
    'settings.index',
]);

test('dashboard stores access map and only displays role', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/profile') => Http::response([
            'message' => 'Success.',
            'data' => ['user' => user_payload()],
        ]),
        api_url('/profile/access') => Http::response([
            'message' => 'Success.',
            'data' => [
                'role' => ['id' => 1, 'name' => 'Administrator', 'key' => 'administrator'],
                'permissions' => ['users' => ['view-active', 'create']],
            ],
        ]),
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Administrator')
        ->assertSee('Account overview')
        ->assertDontSee('Secured identity')
        ->assertDontSee('Active access groups')
        ->assertDontSee('Security score')
        ->assertDontSee('Recent Activity')
        ->assertDontSee('Quick Actions')
        ->assertDontSee('Permission')
        ->assertDontSee('Access map')
        ->assertDontSee('view-active');

    $access = app(MobileCredentialStore::class)->access();

    expect($access['role']['name'] ?? null)->toBe('Administrator');
    expect($access['permissions']['users'] ?? null)->toBe(['view-active', 'create']);
});

test('authenticated layout renders home and profile bottom navigation items', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/profile') => Http::response([
            'message' => 'Success.',
            'data' => ['user' => user_payload()],
        ]),
        api_url('/profile/access') => Http::response([
            'message' => 'Success.',
            'data' => ['role' => ['name' => 'Administrator'], 'permissions' => []],
        ]),
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-notification-status-url', false)
        ->assertSee('data-notification-trigger', false)
        ->assertSee('data-notification-badge', false)
        ->assertSee('aria-label="Notifications"', false)
        ->assertDontSee('Secure access to your vault')
        ->assertSee('Home')
        ->assertSee('Profile')
        ->assertSee('data-mobile-bottom-nav', false)
        ->assertDontSee('label="Vault"', false)
        ->assertDontSee('label="Activity"', false)
        ->assertDontSee('label="Security"', false)
        ->assertDontSee('<native:bottom-nav-item', false)
        ->assertDontSee('<footer', false)
        ->assertDontSee('Settings</');

    $mobileLayout = file_get_contents(resource_path('views/layouts/mobile.blade.php'));

    expect($mobileLayout)->toContain('data-mobile-bottom-nav')
        ->toContain("'name' => 'home'")
        ->toContain('data-mobile-home-logo')
        ->not->toContain('id="vault"')
        ->not->toContain('id="activity"')
        ->not->toContain('id="security"')
        ->not->toContain('<native:bottom-nav-item');
});

test('security pages keep profile bottom navigation active and logo links home', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => false],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('data-mobile-home-logo', false)
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertSee('class="mobile-bottom-nav-item is-active" data-mobile-nav-item="profile"', false)
        ->assertSee('aria-current="page"', false);
});

test('logout clears credentials and renders auth layout without menu items', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/logout') => Http::response(['message' => 'Signed out.']),
    ]);

    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());

    $this->followingRedirects()
        ->post(route('logout'))
        ->assertOk()
        ->assertSee('name="email"', false)
        ->assertDontSee('bottom-nav-item', false);

    expect($credentials->isAuthenticated())->toBeFalse();
});

test('mobile forms use post routes without method spoofing fallbacks', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);

    $this->post(route('profile.language.update'), ['locale' => 'id'])
        ->assertRedirect(route('profile.edit'));

    $this->get('/profile/language')
        ->assertRedirect(route('profile.edit'));

    $this->get('/security/password')
        ->assertRedirect(route('security.index'));

    expect($credentials->activeLocale())->toBe('id');
});

test('profile and settings do not expose biometrics controls', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('Enable biometrics')
        ->assertDontSee('Disable biometrics')
        ->assertDontSee('data-biometric-form', false)
        ->assertDontSee('data-biometric-verified', false);

    $this->get(route('settings.index'))
        ->assertOk()
        ->assertDontSee('Enable biometrics')
        ->assertDontSee('Disable biometrics')
        ->assertDontSee('data-biometric-form', false)
        ->assertDontSee('data-biometric-verified', false);
});

test('profile and security render as tabs under the account header', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => false],
        ]),
    ]);

    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('Profile')
        ->assertSee('Security')
        ->assertSee('Edit profile')
        ->assertDontSee('Update password');

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('Profile')
        ->assertSee('Security')
        ->assertSee('Update password');
});

test('security setup hides confirm disable and backup sections until needed', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => false],
        ]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => ['setup_key' => 'ABCD EFGH'],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('security.index'))
        ->assertOk()
        ->assertDontSee('Security status')
        ->assertDontSee('Two-factor authentication')
        ->assertDontSee('Confirm 2FA')
        ->assertDontSee('Password to disable')
        ->assertDontSee('Backup access');

    $this->withSession(['two_factor_setup_requested' => true])
        ->get(route('security.index'))
        ->assertOk()
        ->assertSee('ABCD EFGH')
        ->assertSee('Cancel')
        ->assertSee('Confirm 2FA')
        ->assertDontSee('Authenticator URL')
        ->assertDontSee('Password to disable');
});

test('enabling two factor redirects to setup mode and renders qr code data', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::sequence()
            ->push(['message' => 'Enabled.', 'data' => ['enabled' => false]])
            ->push(['message' => 'Success.', 'data' => ['enabled' => false]]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => [
                'svg' => '<svg viewBox="0 0 10 10"></svg>',
                'setup_key' => 'ABCD EFGH',
            ],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.two-factor.enable'))
        ->assertRedirect(route('security.index', ['setup' => 1]).'#two-factor-setup');

    $this->get(route('security.index', ['setup' => 1]))
        ->assertOk()
        ->assertSee('<svg', false)
        ->assertSee('two-factor-qr', false)
        ->assertSee('ABCD EFGH')
        ->assertSee('Copy setup key')
        ->assertSee('data-copy-value="ABCD EFGH"', false)
        ->assertSee('Cancel')
        ->assertSee('Confirm 2FA')
        ->assertDontSee('Authenticator URL')
        ->assertDontSee('Password to disable');
});

test('two factor setup renders nested qr payloads from the enable response', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::sequence()
            ->push([
                'message' => 'Enabled.',
                'data' => [
                    'qr_code' => [
                        'svg' => '<svg viewBox="0 0 20 20"></svg>',
                        'setup_key' => 'NESTED SETUP KEY',
                    ],
                ],
            ])
            ->push(['message' => 'Success.', 'data' => ['enabled' => false]]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.two-factor.enable'))
        ->assertRedirect(route('security.index', ['setup' => 1]).'#two-factor-setup');

    $this->get(route('security.index', ['setup' => 1]))
        ->assertOk()
        ->assertSee('<svg', false)
        ->assertSee('NESTED SETUP KEY')
        ->assertSee('data-copy-value="NESTED SETUP KEY"', false)
        ->assertSee('Confirm 2FA');
});

test('two factor setup derives a copyable setup key from an authenticator url', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::sequence()
            ->push(['message' => 'Enabled.', 'data' => ['enabled' => false]])
            ->push(['message' => 'Success.', 'data' => ['enabled' => false]]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => [
                'svg' => '<svg viewBox="0 0 10 10"></svg>',
                'otpauth_url' => 'otpauth://totp/Test:user@example.com?secret=URLSECRET123&issuer=Test',
            ],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.two-factor.enable'))
        ->assertRedirect(route('security.index', ['setup' => 1]).'#two-factor-setup');

    $this->get(route('security.index', ['setup' => 1]))
        ->assertOk()
        ->assertSee('URLSECRET123')
        ->assertDontSee('Authenticator URL')
        ->assertDontSee('otpauth://totp/Test:user@example.com?secret=URLSECRET123&amp;issuer=Test', false)
        ->assertSee('data-copy-source', false)
        ->assertSee('data-copy-value="URLSECRET123"', false);
});

test('cancelling two factor setup clears setup session data', function () {
    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->withSession([
        'two_factor_setup_requested' => true,
        'two_factor_setup_data' => ['setup_key' => 'ABCD EFGH'],
    ])
        ->post(route('security.two-factor.cancel'))
        ->assertRedirect(route('security.index'))
        ->assertSessionMissing('two_factor_setup_requested')
        ->assertSessionMissing('two_factor_setup_data');
});

test('two factor setup renders copyable secret key payloads', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::sequence()
            ->push([
                'message' => 'Enabled.',
                'data' => [
                    'two_factor' => [
                        'secret_key' => 'SECRETKEY123',
                    ],
                ],
            ])
            ->push(['message' => 'Success.', 'data' => ['enabled' => false]]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.two-factor.enable'))
        ->assertRedirect(route('security.index', ['setup' => 1]).'#two-factor-setup');

    $this->get(route('security.index', ['setup' => 1]))
        ->assertOk()
        ->assertSee('SECRETKEY123')
        ->assertSee('Copy setup key')
        ->assertSee('data-copy-value="SECRETKEY123"', false);
});

test('two factor setup renders copyable camel case secret payloads', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::sequence()
            ->push([
                'message' => 'Enabled.',
                'data' => [
                    'twoFactor' => [
                        'secretKey' => 'CAMELSECRET123',
                    ],
                ],
            ])
            ->push(['message' => 'Success.', 'data' => ['enabled' => false]]),
        api_url('/profile/security/two-factor/qr-code') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.two-factor.enable'))
        ->assertRedirect(route('security.index', ['setup' => 1]).'#two-factor-setup');

    $this->get(route('security.index', ['setup' => 1]))
        ->assertOk()
        ->assertSee('CAMELSECRET123')
        ->assertSee('Copy setup key')
        ->assertSee('data-copy-value="CAMELSECRET123"', false);
});

test('confirming two factor returns to enabled security view with recovery codes', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor/confirm') => Http::response([
            'message' => 'Confirmed.',
            'data' => [],
        ]),
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => false],
        ]),
        api_url('/profile/security/two-factor/recovery-codes') => Http::response([
            'message' => 'Success.',
            'data' => ['recovery_codes' => ['recovery-one', 'recovery-two']],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->withSession([
        'two_factor_setup_requested' => true,
        'two_factor_setup_data' => ['setup_key' => 'ABCD EFGH'],
    ])->post(route('security.two-factor.confirm'), [
        'code' => '123456',
    ])->assertRedirect(route('security.index'));

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Backup access')
        ->assertSee('recovery-one')
        ->assertDontSee('Security status')
        ->assertDontSee('Confirm 2FA')
        ->assertDontSee('ABCD EFGH');
});

test('security action endpoints redirect back to security on accidental get requests', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);

    $this->get('/security/two-factor')
        ->assertRedirect(route('security.index'));

    $this->get('/security/two-factor/recovery-codes')
        ->assertRedirect(route('security.index').'#recovery-codes');
});

test('regenerating recovery codes always returns to the security screen', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor/recovery-codes') => Http::response([
            'message' => 'Regenerated.',
            'data' => ['recovery_codes' => ['new-code']],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->from(route('security.two-factor.recovery-codes'))
        ->post(route('security.two-factor.recovery-codes'))
        ->assertRedirect(route('security.index').'#recovery-codes')
        ->assertSessionHas('status', 'Recovery codes regenerated.');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && str_contains((string) $request->url(), '/profile/security/two-factor/recovery-codes'));
});

test('password update accepts post form submissions', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/password') => Http::response(['message' => 'Updated.']),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect();

    Http::assertSent(fn ($request): bool => $request->method() === 'PUT'
        && str_contains((string) $request->url(), '/profile/security/password')
        && $request['current_password'] === 'old-password'
        && $request['password'] === 'new-password'
        && $request['password_confirmation'] === 'new-password');
});

test('password update mismatch stays local and does not call the api', function () {
    Http::preventStrayRequests();
    Http::fake();

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->post(route('security.password.update'), [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors('password');

    Http::assertNothingSent();
});

test('security shows disable and backup only when two factor is enabled', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => true],
        ]),
        api_url('/profile/security/two-factor/recovery-codes') => Http::response([
            'message' => 'Success.',
            'data' => ['recovery_codes' => ['code-one']],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Password to disable')
        ->assertSee('Backup access')
        ->assertSee('code-one');
});

test('notification list renders level labels without forced page reload marker', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [
                ['id' => 'info-1', 'title' => 'Info', 'message' => 'Info message', 'level' => 0, 'read' => false],
                ['id' => 'success-1', 'title' => 'Success', 'message' => 'Success message', 'level' => 1, 'read' => false],
                ['id' => 'warning-1', 'title' => 'Warning', 'message' => 'Warning message', 'level' => 2, 'read' => false],
                ['id' => 'error-1', 'title' => 'Error', 'message' => 'Error message', 'level' => 3, 'read' => false],
            ],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('data-notifications-list', false)
        ->assertDontSee('data-notifications-refresh', false)
        ->assertSee('Notifications')
        ->assertDontSee('Activity')
        ->assertSee('Info')
        ->assertSee('Success')
        ->assertSee('Warning')
        ->assertSee('Error');
});

test('notification status exposes unread count for the header icon badge', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [
                'notifications' => [
                    ['id' => 'read-1', 'title' => 'Read', 'read' => true],
                    ['id' => 'unread-1', 'title' => 'Unread', 'read' => false],
                    ['id' => 'unread-2', 'title' => 'Unread', 'read' => false],
                ],
            ],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->getJson(route('notifications.status'))
        ->assertOk()
        ->assertJson(['unread_count' => 2]);
});

test('notification polling preserves password update toast flash', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/password') => Http::response(['message' => 'Updated.']),
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [
                'notifications' => [],
                'unread_count' => 0,
            ],
        ]),
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => false],
        ]),
    ]);

    app(MobileCredentialStore::class)->storeToken('1|sanctum-token', user_payload());

    $this->from(route('security.index'))
        ->post(route('security.password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect(route('security.index'))
        ->assertSessionHas('status', 'Password updated.');

    $this->getJson(route('notifications.status'))
        ->assertOk()
        ->assertJson(['unread_count' => 0]);

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Password updated.')
        ->assertSee('data-mobile-toast', false);
});

test('selected locale changes profile and security visible text', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile/security/two-factor') => Http::response([
            'message' => 'Success.',
            'data' => ['enabled' => false],
        ]),
    ]);

    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->updateLocale('id');
    $credentials->storeToken('1|sanctum-token', user_payload());

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Edit profil')
        ->assertSee('Simpan profil')
        ->assertSee('Keluar')
        ->assertDontSee('Edit profile');

    $this->get(route('security.index'))
        ->assertOk()
        ->assertSee('Ubah kata sandi')
        ->assertSee('Pengaturan authenticator')
        ->assertDontSee('Autentikasi dua faktor')
        ->assertDontSee('Update password');
});

test('selected locale changes dashboard visible text in chinese', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile') => Http::response([
            'message' => 'Success.',
            'data' => ['user' => user_payload()],
        ]),
        api_url('/profile/access') => Http::response([
            'message' => 'Success.',
            'data' => ['role' => ['name' => 'Administrator'], 'permissions' => []],
        ]),
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->updateLocale('zh');
    $credentials->storeToken('1|sanctum-token', user_payload());

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('账户概览')
        ->assertSee('已验证')
        ->assertDontSee('Account overview')
        ->assertDontSee('Verified');
});

test('mobile locale files avoid english fallback strings on visible labels', function () {
    $englishStrings = mobile_translation_strings(require base_path('lang/en/mobile.php'));
    $allowedIdenticalTranslations = [
        'auth.login.submit',
        'auth.signup.name',
        'home.score',
        'profile.name',
    ];

    foreach (['zh', 'es', 'id', 'de', 'fr'] as $locale) {
        $localizedStrings = mobile_translation_strings(require base_path("lang/{$locale}/mobile.php"));

        expect(array_diff_key($englishStrings, $localizedStrings))->toBeEmpty();
        expect(array_diff_key($localizedStrings, $englishStrings))->toBeEmpty();

        $fallbackKeys = [];

        foreach ($englishStrings as $key => $englishValue) {
            if (in_array($key, $allowedIdenticalTranslations, true)) {
                continue;
            }

            if (($localizedStrings[$key] ?? null) === $englishValue) {
                $fallbackKeys[] = $key;
            }
        }

        if ($fallbackKeys !== []) {
            $this->fail("English fallback strings found for {$locale}: ".implode(', ', $fallbackKeys));
        }
    }
});

test('startup refreshes and caches dynamic languages from site config', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false, [
            'languages' => [
                'current' => 'es',
                'default' => 'es',
                'enabled' => [
                    ['locale' => 'es', 'name' => 'Spanish', 'native_name' => 'Español'],
                    ['locale' => 'fr', 'name' => 'French', 'native_name' => 'Français'],
                ],
            ],
        ]),
    ]);

    $this->post(route('startup.check'))->assertRedirect(route('login'));

    $credentials = app(MobileCredentialStore::class);

    expect($credentials->enabledLocaleCodes())->toBe(['es', 'fr']);
    expect($credentials->activeLocale())->toBe('es');

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/site-config')
        && str_contains((string) $request->url(), 'lang=en'));
});

test('guest auth language selector follows cached site config', function () {
    app(MobileCredentialStore::class)->updateSiteConfig(site_config_payload(false, [
        'languages' => [
            'current' => 'en',
            'default' => 'en',
            'enabled' => [
                ['locale' => 'en', 'name' => 'English', 'native_name' => 'English'],
                ['locale' => 'id', 'name' => 'Indonesian', 'native_name' => 'Indonesia'],
            ],
        ],
    ]));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('name="locale"', false)
        ->assertSee('name="redirect_to"', false)
        ->assertSee('value="'.route('login').'"', false)
        ->assertSee('Indonesia')
        ->assertDontSee('Français');
});

test('guest login submit button uses generic login text in indonesian', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->updateLocale('id');

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('>Login<', false)
        ->assertDontSee('Masuk ke Brankas')
        ->assertDontSee('Login ke Vault');
});

test('guest language update redirects to the requested auth screen', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));

    $this->post(route('language.update'), [
        'locale' => 'id',
        'redirect_to' => route('password.forgot'),
    ])->assertRedirect(route('password.forgot'));

    expect($credentials->activeLocale())->toBe('id');
});

test('guest language update avoids redirecting to startup check post endpoint', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));

    $this->from(route('startup.check'))
        ->post(route('language.update'), [
            'locale' => 'id',
            'redirect_to' => route('startup.check'),
        ])->assertRedirect(route('login'));

    expect($credentials->activeLocale())->toBe('id');
});

test('guest language validation redirects to login instead of startup check', function () {
    app(MobileCredentialStore::class)->updateSiteConfig(site_config_payload(false, [
        'languages' => [
            'current' => 'en',
            'default' => 'en',
            'enabled' => [
                ['locale' => 'en', 'name' => 'English', 'native_name' => 'English'],
                ['locale' => 'id', 'name' => 'Indonesian', 'native_name' => 'Indonesia'],
            ],
        ],
    ]));

    $this->from(route('startup.check'))
        ->post(route('language.update'), ['locale' => 'fr'])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('locale');
});

test('guest language update changes following auth api lang parameter', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));

    $this->patch(route('language.update'), ['locale' => 'id'])->assertRedirect();

    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/login') => Http::response([
            'message' => 'Authenticated.',
            'data' => [
                'two_factor' => false,
                'user' => user_payload(),
                'plain_text_token' => '1|sanctum-token',
            ],
        ]),
    ]);

    $this->post(route('login.store'), [
        'email' => 'jane@example.com',
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/auth/login')
        && str_contains((string) $request->url(), 'lang=id'));
});

test('profile language update persists authenticated locale', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateLocale('id');

    $this->patch(route('profile.language.update'), ['locale' => 'zh'])
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', '语言已更新。');

    expect($credentials->activeLocale())->toBe('zh');
});

test('authenticated api requests include the active lang parameter', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->updateLocale('fr');
    $credentials->storeToken('1|sanctum-token', user_payload());

    Http::preventStrayRequests();
    Http::fake([
        api_url('/profile') => Http::response([
            'message' => 'Success.',
            'data' => ['user' => user_payload()],
        ]),
        api_url('/profile/access') => Http::response([
            'message' => 'Success.',
            'data' => ['role' => ['name' => 'Administrator'], 'permissions' => []],
        ]),
        api_url('/profile/notifications*') => Http::response([
            'message' => 'Success.',
            'data' => [],
        ]),
    ]);

    $this->get(route('dashboard'))->assertOk();

    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/profile')
        && str_contains((string) $request->url(), 'lang=fr'));
    Http::assertSent(fn ($request): bool => str_contains((string) $request->url(), '/profile/access')
        && str_contains((string) $request->url(), 'lang=fr'));
});

test('token checks fail open on transient connection timeouts', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());

    Http::preventStrayRequests();
    Http::fake([
        api_url('/auth/check-token') => fn () => throw new ConnectionException('Connection timeout.'),
    ]);

    expect(app(MobileApiClient::class)->checkToken())->toBeTrue();
    expect($credentials->isAuthenticated())->toBeTrue();
});

test('mobile credential store reuses the loaded credential during a request', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false));
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);

    $store = new MobileCredentialStore;

    DB::flushQueryLog();
    DB::enableQueryLog();

    $store->token();
    $store->user();
    $store->access();
    $store->activeLocale();
    $store->siteConfigFetchedAt();

    $credentialSelects = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_starts_with(strtolower((string) $query['query']), 'select')
            && str_contains((string) $query['query'], 'mobile_credentials'))
        ->count();

    DB::disableQueryLog();

    expect($credentialSelects)->toBe(1);
});

test('profile rejects languages not enabled by site config', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->updateSiteConfig(site_config_payload(false, [
        'languages' => [
            'current' => 'en',
            'default' => 'en',
            'enabled' => [
                ['locale' => 'en', 'name' => 'English', 'native_name' => 'English'],
            ],
        ],
    ]));
    $credentials->storeToken('1|sanctum-token', user_payload());

    $this->patch(route('profile.language.update'), ['locale' => 'fr'])
        ->assertSessionHasErrors('locale');
});

/**
 * @return array{id: int, name: string, email: string}
 */
function user_payload(): array
{
    return [
        'id' => 1,
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ];
}

function api_url(string $path): string
{
    $url = app(MobileApiClient::class)->baseUrl().$path;

    return str_contains($url, '*') ? $url : $url.'?*';
}

/**
 * @param  array<string, mixed>  $overrides
 */
function site_config_response(bool $registrationEnabled, array $overrides = []): mixed
{
    return Http::response([
        'message' => 'Success.',
        'data' => [
            'config' => site_config_payload($registrationEnabled, $overrides),
        ],
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function site_config_payload(bool $registrationEnabled, array $overrides = []): array
{
    return array_replace([
        'site_name' => 'Golf Web Specialist',
        'logo_url' => null,
        'favicon_url' => null,
        'apple_touch_icon_url' => null,
        'registration_enabled' => $registrationEnabled,
        'languages' => [
            'current' => 'en',
            'default' => 'en',
            'enabled' => [
                ['locale' => 'en', 'name' => 'English', 'native_name' => 'English'],
                ['locale' => 'zh', 'name' => 'Chinese', 'native_name' => '中文'],
                ['locale' => 'es', 'name' => 'Spanish', 'native_name' => 'Español'],
                ['locale' => 'id', 'name' => 'Indonesian', 'native_name' => 'Indonesia'],
                ['locale' => 'de', 'name' => 'German', 'native_name' => 'Deutsch'],
                ['locale' => 'fr', 'name' => 'French', 'native_name' => 'Français'],
            ],
        ],
    ], $overrides);
}

/**
 * @return array<string, string>
 */
function mobile_translation_strings(array $translations, string $prefix = ''): array
{
    $strings = [];

    foreach ($translations as $key => $value) {
        $translationKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

        if (is_array($value)) {
            $strings = array_merge($strings, mobile_translation_strings($value, $translationKey));

            continue;
        }

        $strings[$translationKey] = (string) $value;
    }

    ksort($strings);

    return $strings;
}
