<?php

namespace App\Modules\Auth\Support;

use Illuminate\Support\Carbon;

class DeviceDetector
{
    /**
     * Formats an active database session into a human-readable structure.
     */
    public static function formatSession(object $session): array
    {
        $platform = 'Desconocido';
        $ua = (string) ($session->user_agent ?? '');

        if (preg_match('/windows|win32/i', $ua)) {
            $platform = 'Windows';
        } elseif (preg_match('/macintosh|mac os x/i', $ua)) {
            $platform = 'Mac OS';
        } elseif (preg_match('/android/i', $ua)) {
            $platform = 'Android';
        } elseif (preg_match('/iphone|ipad|ipod/i', $ua)) {
            $platform = 'iOS';
        } elseif (preg_match('/linux/i', $ua)) {
            $platform = 'Linux';
        }

        $browser = 'Navegador web';
        if (preg_match('/edg/i', $ua)) {
            $browser = 'Microsoft Edge';
        } elseif (preg_match('/chrome|crios/i', $ua) && ! preg_match('/opr|opera/i', $ua)) {
            $browser = 'Google Chrome';
        } elseif (preg_match('/firefox|fxios/i', $ua)) {
            $browser = 'Mozilla Firefox';
        } elseif (preg_match('/safari/i', $ua) && ! preg_match('/chrome|crios/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/opr|opera/i', $ua)) {
            $browser = 'Opera';
        }

        $device = "{$browser} ({$platform})";
        $lastActivity = isset($session->last_activity)
            ? Carbon::createFromTimestamp($session->last_activity)->locale('es')->diffForHumans()
            : 'hace poco';

        return [
            'device' => $device,
            'ip' => $session->ip_address ?? 'Desconocida',
            'last_active' => $lastActivity,
            'last_activity_timestamp' => $session->last_activity ?? null,
        ];
    }
}
