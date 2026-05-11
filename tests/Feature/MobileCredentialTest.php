<?php

use App\Models\MobileCredential;
use App\Services\MobileApiClient;
use App\Services\MobileCredentialStore;
use App\Services\OpenApiSpec;
use Illuminate\Support\Facades\Http;

test('login page renders for guests', function () {
    Http::preventStrayRequests();

    $this->get(route('login'))
        ->assertOk()
        ->assertDontSee('<footer', false)
        ->assertDontSee('<native:bottom-nav', false)
        ->assertDontSee('bottom-nav-item', false)
        ->assertSee('data-biometric-overlay', false)
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

test('startup check redirects guests to login', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
    ]);

    $this->post(route('startup.check'))->assertRedirect(route('login'));
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

test('startup check redirects locked biometric sessions to unlock', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/auth/check-token') => Http::response([
            'message' => 'Success.',
            'data' => ['active' => true, 'user' => user_payload()],
        ]),
    ]);

    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->enableBiometrics();
    $credentials->lock();

    $this->post(route('startup.check'))->assertRedirect(route('settings.unlock'));
});

test('startup check requires unlock when biometrics are enabled on app launch', function () {
    Http::preventStrayRequests();
    Http::fake([
        api_url('/site-config') => site_config_response(false),
        api_url('/auth/check-token') => Http::response([
            'message' => 'Success.',
            'data' => ['active' => true, 'user' => user_payload()],
        ]),
    ]);

    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->enableBiometrics();

    $this->post(route('startup.check'))->assertRedirect(route('settings.unlock'));

    expect($credentials->credential()?->locked)->toBeTrue();
});

test('unlock screen starts biometric confirmation automatically', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->enableBiometrics();
    $credentials->lock();

    $this->get(route('settings.unlock'))
        ->assertOk()
        ->assertSee('data-biometric-form', false)
        ->assertSee('data-biometric-auto-submit', false)
        ->assertSee('data-biometric-verified', false)
        ->assertDontSee('biometric-progress', false);
});

test('biometric prompt sends native bridge method explicitly', function () {
    $script = file_get_contents(resource_path('js/app.js'));

    expect($script)
        ->toContain("body.set('method', method)")
        ->toContain('params[${key}]')
        ->toContain("nativeBridgeCall('Biometric.Prompt', { id: 'mobile-auth' })")
        ->not->toContain("biometric.prompt().id('mobile-auth')");
});

test('mobile buttons show processing state to prevent duplicate clicks', function () {
    $script = file_get_contents(resource_path('js/app.js'));
    $styles = file_get_contents(resource_path('css/app.css'));

    expect($script)
        ->toContain('function setButtonProcessing')
        ->toContain("button.dataset.processing = 'true'")
        ->toContain("button.setAttribute('aria-busy', 'true')")
        ->toContain('setFormProcessing(form, true)')
        ->toContain("button.matches('[data-copy-value]')")
        ->toContain("window.addEventListener('pageshow'");

    expect($styles)
        ->toContain("button[data-processing='true']")
        ->toContain("button[data-processing='true']::after")
        ->toContain('button-processing-spin');
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
        ->assertDontSee('label="Vault"', false)
        ->assertDontSee('label="Activity"', false)
        ->assertDontSee('label="Security"', false)
        ->assertDontSee('<footer', false)
        ->assertDontSee('Settings</');

    $mobileLayout = file_get_contents(resource_path('views/layouts/mobile.blade.php'));

    expect($mobileLayout)->toContain('id="home" icon="home"')
        ->not->toContain('id="vault"')
        ->not->toContain('id="activity"')
        ->not->toContain('id="security"');
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

test('profile exposes biometrics toggle based on local state', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->updateAccess(['role' => ['name' => 'Administrator']]);

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Enable biometrics')
        ->assertSee('data-biometric-form', false)
        ->assertSee('data-biometric-verified', false);

    $credentials->enableBiometrics();

    $this->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('Disable biometrics')
        ->assertSee('data-biometric-form', false)
        ->assertSee('data-biometric-verified', false);
});

test('disabling biometrics requires a successful biometric confirmation', function () {
    $credentials = app(MobileCredentialStore::class);
    $credentials->storeToken('1|sanctum-token', user_payload());
    $credentials->enableBiometrics();

    $this->post(route('settings.biometrics.disable'))
        ->assertSessionHasErrors('biometric_verified');

    expect($credentials->biometricsEnabled())->toBeTrue();

    $this->post(route('settings.biometrics.disable'), [
        'biometric_verified' => '1',
    ])->assertRedirect();

    expect($credentials->biometricsEnabled())->toBeFalse();
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
        ->assertSee('Aktifkan biometrik')
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

    $this->patch(route('profile.language.update'), ['locale' => 'fr'])
        ->assertRedirect(route('profile.edit'));

    expect($credentials->activeLocale())->toBe('fr');
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
