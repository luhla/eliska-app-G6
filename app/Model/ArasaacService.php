<?php

declare(strict_types=1);

namespace App\Model;

final class ArasaacService
{
    private const API_BASE = 'https://api.arasaac.org/v1';
    private const CDN_BASE = 'https://static.arasaac.org/pictograms';

    public function search(string $keyword, string $locale = 'cs'): array
    {
        $url = self::API_BASE . '/pictograms/' . urlencode($locale) . '/search/' . urlencode($keyword);

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Eliskapp/1.0',
                'ignore_errors' => true,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);

        if ($json === false || $json === '') {
            return [];
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }

        $results = [];
        foreach (array_slice($data, 0, 40) as $item) {
            $id = $item['_id'] ?? null;
            if (!$id) {
                continue;
            }
            $keyword = $item['keywords'][0]['keyword'] ?? '';
            $results[] = [
                'id'          => $id,
                'label'       => $keyword,
                'preview_url' => $this->getPreviewUrl($id),
            ];
        }

        return $results;
    }

    public function getPreviewUrl(int $id): string
    {
        return self::CDN_BASE . "/{$id}/{$id}_500.png";
    }

    public function getPictogramUrl(int $id): string
    {
        return self::CDN_BASE . "/{$id}/{$id}_2500.png";
    }
}
