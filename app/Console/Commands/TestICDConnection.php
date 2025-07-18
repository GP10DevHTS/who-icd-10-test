<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestICDConnection extends Command
{
    protected $signature = 'icd:test';
    protected $description = 'Test WHO ICD API connection and fetch root entity';

    public function handle()
    {
        // Step 1: Get Access Token
        $tokenResponse = Http::asForm()->post('https://icdaccessmanagement.who.int/connect/token', [
            'client_id' => env('ICD_CLIENT_ID'),
            'client_secret' => env('ICD_CLIENT_SECRET'),
            'grant_type' => 'client_credentials',
            'scope' => 'icdapi_access',
        ]);

        if (!$tokenResponse->ok()) {
            $this->error('Failed to get access token');
            $this->line($tokenResponse->body());
            return 1;
        }

        $accessToken = $tokenResponse['access_token'];
        $this->info('Access token retrieved.');

        // Step 2: Fetch Root ICD Entity
        $response = Http::withToken($accessToken)
            ->withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'en',
                'API-Version' => 'v2'
            ])
            ->get('https://id.who.int/icd/entity');

        if (!$response->ok()) {
            $this->error('Failed to fetch ICD root entity');
            $this->line($response->body());
            return 1;
        }

        $data = $response->json();
        $this->info("Root Entity: " . ($data['title']['@value'] ?? 'No Title'));

        $this->info("Child Entities:");
        foreach ($data['child'] ?? [] as $childUrl) {
            $this->line("- " . $childUrl);
        }

        return 0;
    }
}
