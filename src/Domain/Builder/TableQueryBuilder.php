<?php

namespace App\Domain\Builder;

class TableQueryBuilder
{
    /**
     * Construit les query params pour l'API Platform.
     *
     * @param array $allowedFilters  ['inputKey' => 'api.filter.key', ...]
     * @param array $allowedSorts    ['libelle', 'createdAt', ...]
     */
    public function buildParams(
        array $queryParams,
        array $allowedFilters,
        array $allowedSorts,
        int $defaultPerPage = 25
    ): array
    {
        $params = [];
        // ── Pagination ──────────────────────────────────────────
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $rawPerPage = (int) ($queryParams['perPage'] ?? $defaultPerPage);
        $perPage = in_array($rawPerPage, [10, 20, 50, 100]) ? $rawPerPage : $defaultPerPage;

        $params['page'] = $page;
        $params['itemsPerPage'] = $perPage;

        // ── Filtres ─────────────────────────────────────────────
        foreach ($allowedFilters as $inputKey => $apiKey) {
            $value = trim($queryParams[$inputKey] ?? '');
            if ($value !== '') {
                $params[$apiKey] = $value;
            }
        }

        // ── Tri ─────────────────────────────────────────────────
        $sort = $queryParams['sort'] ?? null;
        $dir  = strtolower($queryParams['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($sort && in_array($sort, $allowedSorts, true)) {
            $params['order'][$sort] = $dir;
        }

        return $params;
    }

    /**
     * Métadonnées de pagination pour le template Twig.
     */
    public function buildPaginationMeta(array $apiResponse, int $page, int $perPage): array
    {
        $total = $apiResponse['totalItems'] ?? 0;
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => max(1, $totalPages),
            'from' => $total > 0 ? ($page - 1) * $perPage + 1 : 0,
            'to' => min($page * $perPage, $total),
        ];
    }

    /**
     * Infos de tri pour un champ : params URL, état actif, direction courante.
     */
    public function sortUrl(array $queryParams, string $field): array
    {
        $currentSort = $queryParams['sort'] ?? null;
        $currentDir  = $queryParams['dir']  ?? 'asc';
        $newDir = ($currentSort === $field && $currentDir === 'asc') ? 'desc' : 'asc';

        return [
            'params' => array_merge($queryParams, ['sort' => $field, 'dir' => $newDir, 'page' => 1]),
            'active' => $currentSort === $field,
            'dir' => $currentSort === $field ? $currentDir : null,
        ];
    }
}
