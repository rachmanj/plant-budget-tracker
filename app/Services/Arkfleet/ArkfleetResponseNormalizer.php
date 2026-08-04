<?php

namespace App\Services\Arkfleet;

use Psr\Http\Message\ResponseInterface;

class ArkfleetResponseNormalizer
{
    public function normalize(ResponseInterface $response): array
    {
        $body = json_decode((string) $response->getBody(), true) ?? [];

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
            return ['data' => $body['data'], 'meta' => $body['meta'] ?? null];
        }

        return ['data' => $body, 'meta' => null];
    }
}
