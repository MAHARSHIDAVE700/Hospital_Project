<?php

class CloudinaryHelper {
    private static $cloudName = "demo"; // Default placeholder / configurable via env
    private static $apiKey = "";
    private static $apiSecret = "";

    public static function uploadImage($fileTmpPath, $folder = "hospital_avatars") {
        if (!file_exists($fileTmpPath)) {
            return false;
        }

        // If credentials exist, upload to Cloudinary API via cURL
        if (!empty(self::$apiKey) && !empty(self::$apiSecret)) {
            $timestamp = time();
            $signature = sha1("folder={$folder}&timestamp={$timestamp}" . self::$apiSecret);

            $postFields = [
                'file' => new CURLFile($fileTmpPath),
                'timestamp' => $timestamp,
                'folder' => $folder,
                'api_key' => self::$apiKey,
                'signature' => $signature
            ];

            $ch = curl_init("https://api.cloudinary.com/v1_1/" . self::$cloudName . "/image/upload");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            if (isset($data['secure_url'])) {
                return $data['secure_url'];
            }
        }

        // Fallback: Store locally in assets/uploads/
        $uploadDir = dirname(__DIR__) . '/assets/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $filename = uniqid('img_') . '_' . basename($fileTmpPath);
        $targetPath = $uploadDir . $filename;

        if (copy($fileTmpPath, $targetPath)) {
            return 'assets/uploads/' . $filename;
        }

        return false;
    }
}
