<?php

namespace App\Domain\Helper;

use App\Domain\Builder\TableQueryBuilder;

class TableHelper
{
    public function __construct(
        protected ApiHelper $api,
        protected TableQueryBuilder $tableQuery
    )
    {
    }

    public function handleIndex(
        string $endpoint,
        array $queryParams,
        array $allowedFilters = [],
        array $allowedSorts = [],
        array $extras = []
    ): array
    {
        $rawPerPage = (int)($queryParams['perPage'] ?? 25);
        $perPage = in_array($rawPerPage, [10, 20, 50, 100]) ? $rawPerPage : 25;
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $params = $this->tableQuery->buildParams(
            $queryParams,
            $allowedFilters,
            $allowedSorts,
            $perPage
        );
        $response = $this->api->get($endpoint, $params);
        $items = $response['member'] ?? [];
        $meta = $this->tableQuery->buildPaginationMeta($response, $page, $perPage);

        $sortMeta = [];
        foreach($allowedSorts as $field) {
            $sortMeta[$field] = $this->tableQuery->sortUrl($queryParams, $field);
        }

        return array_merge([
            'items' => $items,
            'meta' => $meta,
            'queryParams' => $queryParams,
            'sortMeta' => $sortMeta
        ], $extras);
    }

    public function handleRelated(
        string $endpoint,
        array $queryParams,
        array $fixedFilters, // ex: ['car.id' => 5]
        array $allowedFilters = [], // filtres que l'utilisateur peut manipuler
        array $allowedSorts = [],
        int $defaultPerPage = 25,
        array $extras = []
    ): array
    {
        $rawPerPage = (int)($queryParams['perPage'] ?? $defaultPerPage);
        $perPage = in_array($rawPerPage, [10, 20, 50, 100]) ? $rawPerPage : $defaultPerPage;
        $page = max(1, (int)($queryParams['page'] ?? 1));
        $params = $this->tableQuery->buildParams(
            $queryParams,
            $allowedFilters,
            $allowedSorts,
            $perPage
        );
        // Merge APRÈS buildParams — les fixedFilters ne peuvent pas être écrasés
        // par les queryParams utilisateur
        $params = array_merge($params, $fixedFilters);
        $response = $this->api->get($endpoint, $params);
        $items = $response['member'] ?? [];
        $meta = $this->tableQuery->buildPaginationMeta($response, $page, $perPage);

        $sortMeta = [];
        foreach ($allowedSorts as $field) {
            $sortMeta[$field] = $this->tableQuery->sortUrl($queryParams, $field);
        }

        return array_merge([
            'items' => $items,
            'meta' => $meta,
            'queryParams' => $queryParams,
            'sortMeta' => $sortMeta
        ], $extras);
    }
}