<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form\IconSearch;

use Kopling\Core\Http\GraphQl\GraphQlClient;

/**
 * Backs `Ux\Form\IconPicker`'s search endpoint -- queries Font Awesome's public GraphQL API for
 * icon ids/labels matching a term, then renders each result's SVG locally via Blade Icons rather
 * than trusting the API for markup. Solid style only (`fas-{id}`) for v1; a result that doesn't
 * resolve locally is silently skipped rather than shown broken.
 */
class FontAwesomeIconSearch
{
    protected const ENDPOINT = 'https://api.fontawesome.com';

    protected const VERSION = '7.x';

    protected GraphQlClient $client;

    public function __construct()
    {
        $this->client = new GraphQlClient(self::ENDPOINT);
    }

    /**
     * @return array<int, array{id: string, label: string, icon: string}>
     */
    public function search(string $term, int $limit = 24): array
    {
        $data = $this->client->query(
            query: <<<'GRAPHQL'
                query IconSearch($query: String!, $version: String!, $pageSize: Int) {
                    searchPaginated(version: $version, query: $query, pageSize: $pageSize) {
                        icons {
                            id
                            label
                        }
                    }
                }
                GRAPHQL,
            variables: [
                'query' => $term,
                'version' => self::VERSION,
                'pageSize' => max(1, min(50, $limit)),
            ],
        );

        $icons = $data['searchPaginated']['icons'] ?? [];

        $results = [];

        foreach ($icons as $icon) {
            $svg = IconRenderer::svg($icon['id']);

            if ($svg === null) {
                continue;
            }

            $results[] = [
                'id' => $icon['id'],
                'label' => $icon['label'],
                'icon' => $svg,
            ];
        }

        return $results;
    }
}
