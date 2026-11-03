<?php

namespace App\Utils;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OTOServiceUtils
{
    public function getBaseUrl(): string
    {
        $url = config('services.oto.url');
        if (empty($url)) {
            $url = env('OTO_API_URL', 'https://api.tryoto.com/rest/v2');
        }
        return rtrim($url, '/');
    }

    public static function sanitizeCity(?string $cityInput): string
    {
        $city = trim($cityInput ?? '');
        if (empty($city) || $city === 'مدينة غير محددة') {
            return 'Riyadh';
        }

        $mapping = [
            'الرياض' => 'Riyadh',
            'riyadh' => 'Riyadh',
            'مكة' => 'Makkah',
            'makkah' => 'Makkah',
            'mecca' => 'Makkah',
            'جده' => 'Jeddah',
            'جدة' => 'Jeddah',
            'jeddah' => 'Jeddah',
            'المدينة' => 'Madinah',
            'مدينه' => 'Madinah',
            'مدينة' => 'Madinah',
            'madinah' => 'Madinah',
            'medina' => 'Madinah',
            'القصيم' => 'Qassim',
            'قصيم' => 'Qassim',
            'qassim' => 'Qassim',
            'الشرقية' => 'Dammam',
            'الشرقيه' => 'Dammam',
            'eastern province' => 'Dammam',
            'eastern' => 'Dammam',
            'الدمام' => 'Dammam',
            'دمام' => 'Dammam',
            'dammam' => 'Dammam',
            'الخبر' => 'Khobar',
            'khobar' => 'Khobar',
            'عسير' => 'Asir',
            'asir' => 'Asir',
            'تبوك' => 'Tabuk',
            'tabuk' => 'Tabuk',
            'حائل' => 'Hail',
            'hail' => 'Hail',
            'الحدود الشمالية' => 'Northern Borders',
            'northern' => 'Northern Borders',
            'نجران' => 'Najran',
            'najran' => 'Najran',
            'الباحة' => 'Al Baha',
            'باحة' => 'Al Baha',
            'baha' => 'Al Baha',
            'جيزان' => 'Jizan',
            'جازان' => 'Jizan',
            'jizan' => 'Jizan',
            'jazan' => 'Jizan',
            'الجوف' => 'Al Jouf',
            'جوف' => 'Al Jouf',
            'jouf' => 'Al Jouf',
            'الطائف' => 'Taif',
            'طائف' => 'Taif',
            'taif' => 'Taif',
            'ينبع' => 'Yanbu',
            'yanbu' => 'Yanbu',
            'أبها' => 'Abha',
            'ابها' => 'Abha',
            'abha' => 'Abha',
            'عرعر' => 'Arar',
            'arar' => 'Arar',
            'الهفوف' => 'Hofuf',
            'hofuf' => 'Hofuf',
            'الأحساء' => 'Al Ahsa',
            'احساء' => 'Al Ahsa',
            'ahsa' => 'Al Ahsa',
        ];

        $lower = mb_strtolower($city, 'UTF-8');
        if (isset($mapping[$lower])) {
            return $mapping[$lower];
        }

        foreach ($mapping as $needle => $targetCity) {
            if (mb_stripos($city, $needle, 0, 'UTF-8') !== false) {
                return $targetCity;
            }
        }

        return 'Riyadh';
    }

    public function getRefreshToken(): string
    {
        $token = config('services.oto.refresh_token');
        if (empty($token)) {
            $token = env('OTO_REFRESH_TOKEN', 'AMf-vBwsG7J61J_1EkBNW_wnKdQc4Xyalpz59J1QittknHfsekYzdv-1sDxoeD1oaw5_OBxmnVtjkwzm7nAUsfkEZoZpmMQtAINMhJLIWxAiJ1xnX9IY4ksBrIGoiGFG1ULhV8nT-a7ucNxD28bjK-cf6bOPEVWYpVDdQToxKpvgEXp2yQTujA3HT5XMIo_x31f1k6I41WA3pdKzsrwSCU_NQSijp1oBxQ');
        }
        return $token ?? '';
    }

    public function getAccessTokenOTO()
    {
        $cachedToken = cache()->get('oto_access_token');
        if (!empty($cachedToken)) {
            return $cachedToken;
        }

        try {
            $response = Http::timeout(10)->post(
                $this->getBaseUrl() . '/refreshToken',
                [
                    'refresh_token' => $this->getRefreshToken(),
                ]
            );

            if ($response->ok()) {
                $data = $response->json();
                $token = $data['access_token'] ?? '';
                if (!empty($token)) {
                    // OTO tokens last `expires_in` seconds (3600). Keep ours a little shorter so we never
                    // hand out one that is about to expire; a longer cache means 401 "Jwt is expired".
                    $lifetime = max(60, (int) ($data['expires_in'] ?? 3600) - 300);
                    cache()->put('oto_access_token', $token, now()->addSeconds($lifetime));
                }
                return $token;
            }

            Log::error('OTO Refresh Token Failed: ' . $response->status() . ' - ' . $response->body());
            return '';
        } catch (\Exception $e) {
            Log::error('OTO Refresh Token Exception: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * POSTs to OTO with the bearer token. If OTO answers 401 (token expired or revoked) the cached
     * token is dropped and the call is repeated once with a fresh one.
     */
    private function authorizedPost(string $path, array $body, $accessToken)
    {
        $send = fn ($token) => Http::timeout(15)->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->post($this->getBaseUrl() . $path, $body);

        $response = $send($accessToken);

        if ($response->status() === 401) {
            cache()->forget('oto_access_token');
            $fresh = $this->getAccessTokenOTO();
            if (!empty($fresh)) {
                $response = $send($fresh);
            }
        }

        return $response;
    }

    public function checkDeliveryFeeAndGetCheapest($accessToken, $originCity, $destinationCity, $width, $length, $height, $weight)
    {
        $companies = $this->checkDeliveryFeeAndGetCompanies($accessToken, $originCity, $destinationCity, $width, $length, $height, $weight);
        return $companies[0] ?? null;
    }

    /**
     * Every delivery option OTO offers for this shipment, cheapest first. Returns an empty array
     * when OTO has nothing (or the call fails).
     */
    public function checkDeliveryFeeAndGetCompanies($accessToken, $originCity, $destinationCity, $width, $length, $height, $weight): array
    {
        $w = (float) ($width ?: 10);
        $l = (float) ($length ?: 10);
        $h = (float) ($height ?: 10);
        $wt = (float) ($weight ?: 1);

        $dataBody = [
            'originCity' => self::sanitizeCity($originCity),
            'destinationCity' => self::sanitizeCity($destinationCity),
            'boxes' => [
                [
                    'boxName' => 'Box1',
                    'width' => $w,
                    'length' => $l,
                    'height' => $h,
                    'weight' => $wt,
                ]
            ],
            'width' => $w,
            'length' => $l,
            'height' => $h,
            'weight' => $wt,
            'isCod' => true,
        ];

        try {
            $response = $this->authorizedPost('/checkOTODeliveryFee', $dataBody, $accessToken);

            if ($response->ok()) {
                $result = $response->json();
                if (isset($result['success']) && $result['success'] == false) {
                    Log::warning('OTO checkDeliveryFee Warning: ', $result);
                    return [];
                }

                $companies = $result['deliveryCompany'] ?? [];
                if (empty($companies) || !is_array($companies)) {
                    return [];
                }

                return collect($companies)->sortBy(fn ($c) => (float) ($c['price'] ?? PHP_INT_MAX))->values()->all();
            }

            Log::error('OTO checkDeliveryFee Error: ' . $response->status() . ' - ' . $response->body());
            return [];
        } catch (\Exception $e) {
            Log::error('OTO checkDeliveryFee Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function createOrder($orderData, $token)
    {
        try {
            return $this->authorizedPost('/createOrder', $orderData, $token)->json();
        } catch (\Exception $e) {
            Log::error('OTO createOrder Exception: ' . $e->getMessage());
            return null;
        }
    }
}
