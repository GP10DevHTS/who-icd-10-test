<?php

namespace App\Console\Commands;

use App\Models\IcdEntity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IcdSyncCommand extends Command
{
    protected $signature = 'icd:sync';
    protected $description = 'Sync only disease-level ICD entities (leaf nodes) from WHO API into local database';

    protected string $token;
    protected int $tokenIssuedAt;
    protected int $tokenExpiresIn;

    public function handle()
    {
        $this->info("🔐 Requesting access token...");

        $tokenResponse = Http::asForm()->post('https://icdaccessmanagement.who.int/connect/token', [
            'client_id' => env('ICD_CLIENT_ID'),
            'client_secret' => env('ICD_CLIENT_SECRET'),
            'grant_type' => 'client_credentials',
            'scope' => 'icdapi_access',
        ]);

        if (!$tokenResponse->ok()) {
            $this->error("❌ Failed to get access token");
            $this->line($tokenResponse->body());
            return 1;
        }

        $this->token = $tokenResponse['access_token'];
        $this->tokenIssuedAt = time();
        $this->tokenExpiresIn = $tokenResponse['expires_in'];

        $this->info("✅ Token acquired. Starting sync of disease nodes only...");
        $this->syncEntity('entity');

        $this->info("🎉 ICD disease sync completed.");
        return 0;
    }

    protected function refreshTokenIfNeeded(): bool
    {
        if (time() >= $this->tokenIssuedAt + $this->tokenExpiresIn - 300) {
            $this->info("🔄 Token near expiry. Refreshing...");

            $tokenResponse = Http::asForm()->post('https://icdaccessmanagement.who.int/connect/token', [
                'client_id' => env('ICD_CLIENT_ID'),
                'client_secret' => env('ICD_CLIENT_SECRET'),
                'grant_type' => 'client_credentials',
                'scope' => 'icdapi_access',
            ]);

            if (!$tokenResponse->ok()) {
                $this->error("❌ Failed to refresh token");
                return false;
            }

            $this->token = $tokenResponse['access_token'];
            $this->tokenIssuedAt = time();
            $this->tokenExpiresIn = $tokenResponse['expires_in'];

            $this->info("✅ Token refreshed.");
        }

        return true;
    }

    protected function syncEntity(string $entityId, ?int $parentWhoId = null)
    {
        if (!$this->refreshTokenIfNeeded()) {
            return;
        }

        $url = "https://id.who.int/icd/{$entityId}";

        $response = Http::withToken($this->token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Accept-Language' => 'en',
                'API-Version' => 'v2'
            ])
            ->get($url);

        if (!$response->ok()) {
            $this->warn("⚠️ Failed to fetch entity: {$entityId}");
            return;
        }

        $data = $response->json();

        $whoId = (int) basename($data['@id']);
        $title = $data['title']['@value'] ?? null;
        $definition = $data['definition']['@value'] ?? null;
        $code = $data['code'] ?? null;
        $children = $data['child'] ?? [];
        $releaseId = $data['releaseId'] ?? null;
        $releaseDate = $data['releaseDate'] ?? null;

        // Only process leaf nodes (diseases)
        if (empty($children)) {
            $rawJson = json_encode($data);
            $existing = IcdEntity::where('who_id', $whoId)->first();

            if (!$existing || $existing->raw_json !== $rawJson) {
                IcdEntity::updateOrCreate(
                    ['who_id' => $whoId],
                    [
                        'parent_who_id' => $parentWhoId,
                        'title' => $title,
                        'definition' => $definition,
                        'code' => $code,
                        'release_id' => $releaseId,
                        'release_date' => $releaseDate,
                        'raw_json' => $data,
                    ]
                );

                $this->line("💾 Saved disease: {$title}");
            } else {
                $this->line("✅ Skipped unchanged disease: {$title}");
            }
        }

        // Always continue recursion
        foreach ($children as $childUrl) {
            $childId = basename($childUrl);
            $this->syncEntity("entity/{$childId}", $whoId);
        }
    }
}
