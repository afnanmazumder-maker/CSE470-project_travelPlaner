<?php

namespace App\Services;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Facades\Http;

class AiService
{
    public function recommend(array $weights, array $attractions, int $limit, ?string $budgetBand = null): ?array
    {
        return $this->post('/recommendations', [
            'interest_weights' => (object) $weights,
            'attractions' => $attractions,
            'limit' => $limit,
            'budget_band' => $budgetBand,
        ]);
    }

    public function updateWeights(array $weights, array $tags, string $action): ?array
    {
        return $this->post('/weights/update', [
            'interest_weights' => (object) $weights,
            'tags' => $tags,
            'action' => $action,
        ]);
    }

    private function post(string $path, array $payload): ?array
    {
        try {
            return Http::baseUrl(config('services.ai.url'))
                ->timeout(5)
                ->acceptJson()
                ->post($path, $payload)
                ->throw()
                ->json();
        } catch (HttpClientException) {
            return null;
        }
    }
}