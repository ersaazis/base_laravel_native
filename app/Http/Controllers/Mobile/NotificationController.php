<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Mobile\Concerns\HandlesMobileApiErrors;
use App\Services\MobileApiClient;
use App\Services\MobileApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    use HandlesMobileApiErrors;

    public function __construct(private readonly MobileApiClient $api) {}

    public function index(): View
    {
        $payload = $this->notificationPayload(30);

        return view('mobile.notifications.index', [
            'notifications' => $this->normalizeNotifications($payload['notifications']),
        ]);
    }

    public function status(): JsonResponse
    {
        $payload = $this->notificationPayload(30);

        return response()->json([
            'unread_count' => $payload['unread_count'],
        ]);
    }

    public function read(string $notification): RedirectResponse
    {
        try {
            $this->api->authenticated('patch', "/profile/notifications/{$notification}/read");

            return back()->with('status', __('mobile.notifications.one_read'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    public function readAll(): RedirectResponse
    {
        try {
            $this->api->authenticated('patch', '/profile/notifications/read-all');

            return back()->with('status', __('mobile.notifications.all_read'));
        } catch (MobileApiException $exception) {
            return $this->backWithApiError($exception);
        }
    }

    /**
     * @param  array<int, mixed>  $notifications
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNotifications(array $notifications): array
    {
        return array_values(array_filter(array_map(function (mixed $notification): ?array {
            if (! is_array($notification)) {
                return null;
            }

            $notification['level_key'] = $this->levelKey($notification['level'] ?? 0);

            return $notification;
        }, $notifications)));
    }

    private function levelKey(mixed $level): string
    {
        if (is_numeric($level)) {
            return match ((int) $level) {
                1 => 'success',
                2 => 'warning',
                3 => 'error',
                default => 'info',
            };
        }

        return in_array($level, ['info', 'success', 'warning', 'error'], true) ? $level : 'info';
    }

    /**
     * @return array{notifications: array<int, mixed>, unread_count: int}
     */
    private function notificationPayload(int $limit): array
    {
        try {
            $response = $this->api->authenticated('get', '/profile/notifications', ['limit' => $limit]);
            $data = $response['data'] ?? [];

            $notifications = is_array($data) && array_is_list($data) ? $data : ($data['notifications'] ?? []);
            $notifications = is_array($notifications) ? $notifications : [];
            $unreadCount = $this->unreadCount($data, $notifications);

            return [
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ];
        } catch (MobileApiException $exception) {
            report($exception);

            return [
                'notifications' => [],
                'unread_count' => 0,
            ];
        }
    }

    /**
     * @param  array<int, mixed>  $notifications
     */
    private function unreadCount(mixed $data, array $notifications): int
    {
        if (is_array($data) && is_numeric($data['unread_count'] ?? null)) {
            return max(0, (int) $data['unread_count']);
        }

        return count(array_filter($notifications, fn (mixed $notification): bool => is_array($notification)
            && ! (bool) ($notification['read'] ?? $notification['read_at'] ?? false)));
    }
}
