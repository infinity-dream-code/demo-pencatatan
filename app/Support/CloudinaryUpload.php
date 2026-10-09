<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CloudinaryUpload
{
    /**
     * Upload image to Cloudinary folder (default: ponpes_markaz).
     *
     * @return array{url: string, public_id: string, secure_url: string}
     */
    public static function image(UploadedFile $file, ?string $folder = null): array
    {
        $cloudName = (string) config('services.cloudinary.cloud_name');
        $apiKey = (string) config('services.cloudinary.api_key');
        $apiSecret = (string) config('services.cloudinary.api_secret');
        $folder = $folder ?: (string) config('services.cloudinary.folder', 'ponpes_markaz');

        if ($cloudName === '' || $apiKey === '' || $apiSecret === '') {
            throw new RuntimeException('Konfigurasi Cloudinary belum lengkap.');
        }

        $timestamp = time();
        $paramsToSign = [
            'folder' => $folder,
            'timestamp' => $timestamp,
        ];
        ksort($paramsToSign);

        $toSign = [];
        foreach ($paramsToSign as $key => $value) {
            $toSign[] = $key . '=' . $value;
        }
        $signature = sha1(implode('&', $toSign) . $apiSecret);

        $endpoint = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";

        $response = Http::timeout(60)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post($endpoint, [
                'api_key' => $apiKey,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'folder' => $folder,
            ]);

        if (!$response->successful()) {
            Log::error('Cloudinary upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Upload foto ke Cloudinary gagal.');
        }

        $data = $response->json();
        $url = (string) ($data['secure_url'] ?? $data['url'] ?? '');
        if ($url === '') {
            throw new RuntimeException('Cloudinary tidak mengembalikan URL foto.');
        }

        return [
            'url' => $url,
            'secure_url' => $url,
            'public_id' => (string) ($data['public_id'] ?? ''),
        ];
    }
}
