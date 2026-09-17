<?php
namespace App\Tracking;
final class Ga4ConsentProfile {
    public static function for(string $mode): array { return match ($mode) {
        'full' => ['analytics_storage'=>'granted','ad_storage'=>'granted','ad_user_data'=>'granted','ad_personalization'=>'granted'],
        'partial' => ['analytics_storage'=>'granted','ad_storage'=>'denied','ad_user_data'=>'denied','ad_personalization'=>'denied'],
        'none' => ['analytics_storage'=>'denied','ad_storage'=>'denied','ad_user_data'=>'denied','ad_personalization'=>'denied'],
    }; }
}
