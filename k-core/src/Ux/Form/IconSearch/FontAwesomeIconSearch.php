<?php

declare(strict_types=1);

namespace Kopling\Core\Ux\Form\IconSearch;

use Kopling\Core\Http\GraphQl\GraphQlClient;

/**
 * Backs `Ux\Form\IconPicker`'s search endpoint -- queries Font Awesome's public GraphQL API
 * (`searchPaginated`, see https://docs.fontawesome.com/apis/graphql/query-fields) for icon
 * ids/labels matching a term, then renders each result's actual SVG locally via Blade Icons
 * (the same `owenvoke/blade-fontawesome` package `Kopling\Core\Ux\Icon` already renders
 * through) rather than trusting the API for markup -- the API is only ever a name/label search
 * index here, never a source of truth for what actually renders.
 *
 * Solid style only (`fas-{id}`) for v1: the vast majority of common icons have one, and a result
 * id that doesn't resolve locally (a brands-only or Pro-only icon the free-tier search still
 * surfaces) is silently skipped rather than shown broken -- same "never let one bad entry break
 * the whole result set" rule `Theme::resolve()`'s per-row validation already follows.
 */
class FontAwesomeIconSearch
{
    protected const ENDPOINT = 'https://api.fontawesome.com';

    protected const VERSION = '7.x';

    protected GraphQlClient $client;

    public function __construct()
    {
        $this->client = new GraphQlClient(self::ENDPOINT, config('kopling-core.font_awesome_token'));
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
