<?php

declare(strict_types=1);

namespace Kopling\Core\Http\GraphQl;

use Illuminate\Support\Facades\Http;

/**
 * A thin, generic GraphQL client -- POSTs `{query, variables}` to any endpoint, optionally with
 * a bearer token, and returns the decoded `data` payload. Not tied to any one API: Font Awesome's
 * icon search (`Ux\Form\IconPicker`'s own search endpoint, see `Kopling\Core\Ux\Form\IconSearch\
 * FontAwesomeIconSearch`) is the first real caller, but nothing here knows anything about Font
 * Awesome specifically -- that lives entirely in its own caller-owned class, the same separation
 * `EmojiPicker`/`TagInput` already keep from whatever backs their own search/data.
 */
class GraphQlClient
{
    public function __construct(
        protected string $endpoint,
        protected ?string $token = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $variables
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Http\Client\RequestException on a non-2xx HTTP response.
     * @throws GraphQlException on a 2xx response that still carries GraphQL-level `errors`.
     */
    public function query(string $query, array $variables = []): array
    {
        $request = Http::acceptJson();

        if ($this->token !== null && $this->token !== '') {
            $request = $request->withToken($this->token);
        }

        $response = $request->post($this->endpoint, [
            'query' => $query,
            'variables' => $variables,
        ]);

        $response->throw();

        $payload = $response->json();

        if (! empty($payload['errors'])) {
            throw new GraphQlException(
                implode('; ', array_column($payload['errors'], 'message')),
            );
        }

        return $payload['data'] ?? [];
    }
}
