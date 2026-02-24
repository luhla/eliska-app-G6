<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Http\FileUpload;
use Nette\Utils\Random;

final class FileUploadService
{
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
    ];

    private const ALLOWED_AUDIO_MIMES = [
        'audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav',
        'audio/x-wav', 'audio/aac', 'audio/x-m4a', 'video/webm',
    ];

    private const MIME_TO_EXT = [
        'image/jpeg'   => 'jpg',
        'image/png'    => 'png',
        'image/gif'    => 'gif',
        'image/webp'   => 'webp',
        'image/svg+xml' => 'svg',
        'audio/webm'   => 'webm',
        'video/webm'   => 'webm',
        'audio/ogg'    => 'ogg',
        'audio/mpeg'   => 'mp3',
        'audio/mp4'    => 'mp4',
        'audio/x-m4a'  => 'm4a',
        'audio/wav'    => 'wav',
        'audio/x-wav'  => 'wav',
        'audio/aac'    => 'aac',
    ];

    public function __construct(
        private readonly string $wwwDir,
    ) {
    }

    public function saveUploadedImage(FileUpload $upload, string $userUid): string
    {
        if (!$upload->isOk() || !$upload->isImage()) {
            throw new \InvalidArgumentException('Neplatný obrázek.');
        }

        $mime = $this->detectMime($upload->getTemporaryFile());
        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            throw new \InvalidArgumentException("Nepodporovaný typ obrázku: $mime");
        }

        $ext = self::MIME_TO_EXT[$mime] ?? 'jpg';
        $filename = Random::generate(32) . '.' . $ext;
        $dir = $this->ensureDir("uploads/images/$userUid");

        $upload->move("$dir/$filename");

        return "uploads/images/$userUid/$filename";
    }

    public function saveUploadedAudio(FileUpload $upload, string $userUid): string
    {
        if (!$upload->isOk()) {
            throw new \InvalidArgumentException('Neplatný audio soubor.');
        }

        $mime = $this->detectMime($upload->getTemporaryFile());
        if (!in_array($mime, self::ALLOWED_AUDIO_MIMES, true)) {
            throw new \InvalidArgumentException("Nepodporovaný typ zvuku: $mime");
        }

        $ext = self::MIME_TO_EXT[$mime] ?? 'webm';
        $filename = Random::generate(32) . '.' . $ext;
        $dir = $this->ensureDir("uploads/audio/$userUid");

        $upload->move("$dir/$filename");

        return "uploads/audio/$userUid/$filename";
    }

    /** Save base64 data URI as image (from webcam) */
    public function saveBase64Image(string $dataUri, string $userUid): string
    {
        [$mime, $data] = $this->parseDataUri($dataUri);

        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            throw new \InvalidArgumentException("Nepodporovaný typ obrázku: $mime");
        }

        $ext = self::MIME_TO_EXT[$mime] ?? 'jpg';
        $filename = Random::generate(32) . '.' . $ext;
        $dir = $this->ensureDir("uploads/images/$userUid");

        file_put_contents("$dir/$filename", $data);

        return "uploads/images/$userUid/$filename";
    }

    /** Save base64 data URI as audio (from MediaRecorder) */
    public function saveBase64Audio(string $dataUri, string $userUid): string
    {
        [$mime, $data] = $this->parseDataUri($dataUri);

        if (!in_array($mime, self::ALLOWED_AUDIO_MIMES, true)) {
            throw new \InvalidArgumentException("Nepodporovaný typ zvuku: $mime");
        }

        $ext = self::MIME_TO_EXT[$mime] ?? 'webm';
        $filename = Random::generate(32) . '.' . $ext;
        $dir = $this->ensureDir("uploads/audio/$userUid");

        file_put_contents("$dir/$filename", $data);

        return "uploads/audio/$userUid/$filename";
    }

    /** Download pictogram from ARASAAC CDN and save locally */
    public function downloadArasaacImage(int $arasaacId, string $userUid): string
    {
        $url = "https://static.arasaac.org/pictograms/{$arasaacId}/{$arasaacId}_2500.png";

        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'user_agent' => 'Eliskapp/1.0',
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            throw new \RuntimeException("Nelze stáhnout piktogram ID $arasaacId z ARASAAC.");
        }

        $filename = 'arasaac_' . $arasaacId . '_' . Random::generate(8) . '.png';
        $dir = $this->ensureDir("uploads/images/$userUid");

        file_put_contents("$dir/$filename", $raw);

        return "uploads/images/$userUid/$filename";
    }

    public function deleteFile(string $relativePath): void
    {
        $full = $this->wwwDir . '/' . ltrim($relativePath, '/');
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function getPublicPath(string $relativePath): string
    {
        return '/' . ltrim($relativePath, '/');
    }

    // ------------------------------------------------------------------

    private function ensureDir(string $relative): string
    {
        $dir = $this->wwwDir . '/' . $relative;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private function detectMime(string $file): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        return $finfo->file($file) ?: 'application/octet-stream';
    }

    private function parseDataUri(string $dataUri): array
    {
        if (!str_starts_with($dataUri, 'data:')) {
            throw new \InvalidArgumentException('Neplatné data URI.');
        }
        $comma = strpos($dataUri, ',');
        $meta = substr($dataUri, 5, $comma - 5);
        $base64 = substr($dataUri, $comma + 1);
        $mime = explode(';', $meta)[0];
        return [$mime, base64_decode($base64)];
    }
}
