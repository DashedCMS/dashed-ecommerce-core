<?php

declare(strict_types=1);

namespace Dashed\DashedEcommerceCore\Services\Meta\Audiences;

use Illuminate\Support\Str;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;

/**
 * Dunne laag over de Custom Audience-endpoints van de Marketing API.
 * Gegevens gaan altijd gehasht mee, in het schema EMAIL + PHONE.
 */
class MetaAudienceApi
{
    public const SCHEMA = ['EMAIL', 'PHONE'];

    /** Foutcodes van Meta die om een limiet of tijdelijke storing gaan. */
    private const RETRYABLE_CODES = [1, 2, 4, 17, 32, 341, 613, 80003];

    public function __construct(
        private readonly string $accessToken,
        private readonly string $graphVersion = 'v26.0',
    ) {
    }

    public static function for(MetaAudienceSettings $settings): self
    {
        return new self((string) $settings->accessToken(), $settings->graphVersion());
    }

    public function createAudience(string $adAccountId, string $name, string $description = ''): string
    {
        $response = $this->send('post', "act_{$adAccountId}/customaudiences", [
            'name' => $name,
            'subtype' => 'CUSTOM',
            'description' => $description,
            'customer_file_source' => 'USER_PROVIDED_ONLY',
        ]);

        $id = (string) ($response['id'] ?? '');
        if ($id === '') {
            throw new MetaAudienceException(__('Meta gaf geen id terug voor de nieuwe doelgroep.'));
        }

        return $id;
    }

    /** @param list<array{0: string, 1: string}> $rows */
    public function addUsers(string $audienceId, array $rows): array
    {
        return $this->send('post', "{$audienceId}/users", ['payload' => $this->payload($rows)]);
    }

    /** @param list<array{0: string, 1: string}> $rows */
    public function removeUsers(string $audienceId, array $rows): array
    {
        return $this->send('delete', "{$audienceId}/users", ['payload' => $this->payload($rows)]);
    }

    /**
     * Eén batch van een vervangsessie. Meta wist de oude leden pas na de
     * laatste batch, dus een afgebroken sessie laat de doelgroep heel.
     *
     * @param list<array{0: string, 1: string}> $rows
     */
    public function replaceUsers(string $audienceId, array $rows, int $sessionId, int $batchSeq, bool $lastBatch, int $estimatedTotal): array
    {
        return $this->send('post', "{$audienceId}/usersreplace", [
            'payload' => $this->payload($rows),
            'session' => json_encode([
                'session_id' => $sessionId,
                'batch_seq' => $batchSeq,
                'last_batch_flag' => $lastBatch,
                'estimated_num_total' => $estimatedTotal,
            ]),
        ]);
    }

    public static function newSessionId(): int
    {
        return random_int(1, PHP_INT_MAX);
    }

    /** @param list<array{0: string, 1: string}> $rows */
    private function payload(array $rows): string
    {
        return (string) json_encode([
            'schema' => self::SCHEMA,
            'data' => array_values($rows),
        ]);
    }

    /** @return array<string, mixed> */
    private function send(string $method, string $path, array $params): array
    {
        $url = "https://graph.facebook.com/{$this->graphVersion}/{$path}";
        $params['access_token'] = $this->accessToken;

        try {
            /** @var Response $response */
            $response = Http::asForm()->timeout(60)->{$method}($url, $params);
        } catch (ConnectionException $e) {
            throw new MetaAudienceException(__('Geen verbinding met Meta: :fout', ['fout' => Str::limit($e->getMessage(), 200)]), true);
        }

        if ($response->successful()) {
            return (array) $response->json();
        }

        $error = (array) $response->json('error', []);
        $code = (int) ($error['code'] ?? 0);
        $message = (string) ($error['error_user_msg'] ?? $error['message'] ?? $response->body());

        $retryable = $response->status() === 429
            || $response->serverError()
            || in_array($code, self::RETRYABLE_CODES, true)
            || (bool) ($error['is_transient'] ?? false);

        throw new MetaAudienceException(
            __('Meta antwoordde met :status (code :code): :melding', [
                'status' => $response->status(),
                'code' => $code,
                'melding' => Str::limit($message, 300),
            ]),
            $retryable,
        );
    }
}
