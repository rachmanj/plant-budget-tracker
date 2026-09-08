<?php

namespace App\Services\Arkfleet;

use Illuminate\Http\Client\Response as HttpClientResponse;
use Psr\Http\Message\ResponseInterface;

class ArkfleetResponseNormalizer
{
    public function normalize(HttpClientResponse|ResponseInterface $response): array
    {
        $body = $response instanceof HttpClientResponse
            ? ($response->json() ?? [])
            : (json_decode((string) $response->getBody(), true) ?? []);

        if (is_array($body) && array_key_exists('current_page', $body)) {
            return [
                'data' => $body['data'] ?? [],
                'meta' => [
                    'current_page' => $body['current_page'],
                    'last_page' => $body['last_page'],
                    'per_page' => $body['per_page'],
                    'total' => $body['total'],
                ],
            ];
        }

        if (is_array($body) && array_key_exists('data', $body)) {
            $meta = $body['meta'] ?? null;
            if ($meta === null && array_key_exists('count', $body)) {
                $meta = ['count' => $body['count'], 'total' => $body['count']];
            }

            return ['data' => $body['data'], 'meta' => $meta];
        }

        return ['data' => $body, 'meta' => null];
    }
}
