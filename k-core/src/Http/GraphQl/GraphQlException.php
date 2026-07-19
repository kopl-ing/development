<?php

declare(strict_types=1);

namespace Kopling\Core\Http\GraphQl;

/**
 * A 2xx HTTP response that still carries a GraphQL-level `errors` array -- distinct from
 * `Illuminate\Http\Client\RequestException`, which `GraphQlClient::query()` lets propagate
 * unchanged for actual non-2xx failures.
 */
class GraphQlException extends \RuntimeException
{
}
