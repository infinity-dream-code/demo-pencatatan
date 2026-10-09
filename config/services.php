<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    "smartpayment" => [
        "notice_push_url" => env(
            "SMARTPAYMENT_NOTICE_PUSH_URL",
            "http://mobile.smartpayment.co.id:8888/YogyaMuallimaat/Token/eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJNRVRIT0QiOiJOb3RpY2VQdXNoIn0.Zhzaxz9T9pq2tJgfpgh9ldJ0HEKUGS3mnd9RnSISu6Y",
        ),
    ],

    "turnstile" => [
        "enabled" => env("TURNSTILE_ENABLED", true),
        "site_key" => env("TURNSTILE_SITE_KEY")
            ?: env(
                env("APP_ENV", "production") === "local"
                    ? "TURNSTILE_SITE_KEY_LOCAL"
                    : "TURNSTILE_SITE_KEY_PROD",
            ),
        "secret_key" => env("TURNSTILE_SECRET_KEY")
            ?: env(
                env("APP_ENV", "production") === "local"
                    ? "TURNSTILE_SECRET_KEY_LOCAL"
                    : "TURNSTILE_SECRET_KEY_PROD",
            ),
    ],

    "portal_sso" => [
        "secret" => env("PORTAL_SSO_SECRET", "a7c2a8a9b3c4a5a6a7a8a9b0c1a2a3"),
    ],

    "cloudinary" => [
        "cloud_name" => env("CLOUDINARY_CLOUD_NAME", "smartpayict"),
        "api_key" => env("CLOUDINARY_API_KEY"),
        "api_secret" => env("CLOUDINARY_API_SECRET"),
        "folder" => env("CLOUDINARY_FOLDER", "ponpes_markaz"),
    ],

];
