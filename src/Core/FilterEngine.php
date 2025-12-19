<?php

namespace Vendor\TurboFilter\Core;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class FilterEngine{

    public function __construct(protected Builder $query, protected array $filters, protected array $payload, protected array $connections) {}

    public function applyCustomGet(){
        $this->applyFilter($this->query);
        return $this->applyGetOrPaginate($this->query);
    }

    public function applyFilter(): Builder{
        $id = data_get($this->payload, 'id') ?? data_get($this->payload['by'] ?? [], 'id');

        if ($id && gettype($id) !== "array") {
            return $this->query->whereId($id);
        }

        $this->query->where(function ($query) {
            if (!empty($this->payload['search']) && $this->filters) {
                $query->where(function ($subQuery) {
                    $this->byOrSearchFilter(
                        $subQuery,
                        $this->filters,
                        $this->payload['search'],
                        'search'
                    );
                });
            }

            if (!empty($this->payload['by']) && $this->filters) {
                $query->where(function ($subQuery) {
                    $this->byOrSearchFilter(
                        $subQuery,
                        $this->filters,
                        $this->payload['by'],
                        'by'
                    );
                });
            }
        });

        return $this->query;
    }

    public function applyGetOrPaginate(){
        // Sort
        if (!empty($payload['orderby'])) {
            foreach ($payload['orderby'] as $key => $value) {
                [$column, $order] = is_array($value) ? [$value[0], $value[1]] : [$key, $value];

                $order = strtolower($order);
                if ($this->hasColumnInAnyConnection($this->getTable(), $column) && in_array($order, ['asc', 'desc'])) {
                    $this->query->orderBy($column, $order);
                }
            }
        }

        // Export
        if (!!data_get($this->payload['by'] ?? [], 'export')) {
            return $this->query->get();
        }

        // Search by ID
        $id = data_get($this->payload, 'id') ?? data_get($this->payload['by'] ?? [], 'id');
        if ($id !== null && !is_array($id)) {
            return $this->query->firstOrFail();
        }

        // First register
        if (!empty($this->payload['first'])) {
            return $this->query->first() ?? '';
        }

        // Pagination
        if (!empty($this->payload['paginate'])) {
            $perPage = intval($this->payload['paginate']);
            if ($perPage) {
                return $this->query->paginate($perPage);
            }
        } elseif (isset($this->payload['length']) && isset($this->payload['start'])) {
            return $this->query->paginate(
                intval($this->payload['length']),
                ['*'],
                'page',
                (intval($this->payload['start']) / intval($this->payload['length'])) + 1
            );
        }

        // Default: get all
        return $this->query->get();
    }

    private function byOrSearchFilter(Builder $q, array $filters, mixed $payloadSegment, string $type): Builder{
        foreach ($filters as $key => $filter) {
            $pos = strpos($filter, ':');
            $value = $type === 'search' ? '%' . $payloadSegment . '%' : $payloadSegment;
            $operator = $type === 'search' ? 'like' : '=';

            if ($pos === false) {
                // filtro directo
                if ($type === 'by') {
                    $value = data_get($payloadSegment, $filter);
                    if ($value === null) {
                        continue;
                    }
                }
                $this->simpleWhere($q, $filter, $value, $key, $operator);
            } else {
                // filtro por relación
                $relation = substr($filter, 0, $pos);
                $params = explode(',', substr($filter, $pos + 1));

                if (!empty($params)) {
                    if ($type === 'by' && $relation !== 'codigo') {
                        $params = array_filter($params, fn($param) => !empty(data_get($payloadSegment, $param)));
                    } elseif ($type === 'by' && $relation === 'codigo') {
                        $params = ['codigo'];
                    }

                    $method = $type === 'search' ? 'orWhereHas' : 'whereHas';
                    if (!empty($params)) {
                        $q->$method($relation, fn($q) => $this->coreFilter($q, $params, $operator, $payloadSegment, $type));
                    }
                }
            }
        }

        return $q;
    }

    public function coreFilter(Builder $q, array $params, string $operator, array $payloadSegment, string $type): void{
        foreach ($params as $key1 => $param) {
            $table = $q->getModel()->getTable();

            if ($type === 'by') {
                $requestValue = $table !== 'codigos' 
                    ? data_get($payloadSegment, $param)
                    : data_get($payloadSegment, 'codigo');
            } else {
                $requestValue = $payloadSegment ?? null;
            }

            $value = $type === 'search' ? '%' . $requestValue . '%' : $requestValue;

            if ($table === 'codigos') {
                if ($value !== null) {
                    $codigo = explode('-', $value);

                    if (count($codigo) > 2 && (strlen($codigo[2]) === 2 || strlen($codigo[2]) === 4)) {
                        $codigo[2] = strlen($codigo[2]) === 2
                            ? Carbon::createFromFormat('y', $codigo[2])->format('Y')
                            : $codigo[2];
                    }

                    $q->when(isset($codigo[0]), fn($q) => $q->where('siglas', 'LIKE', '%' . trim($codigo[0]) . '%'));
                    $q->when(isset($codigo[1]), fn($q) => $q->where('numeral', 'LIKE', '%' . trim($codigo[1]) . '%'));
                    $q->when(isset($codigo[2]), fn($q) => $q->where('year', 'LIKE', '%' . trim($codigo[2]) . '%'));
                }
            } else {
                if ($table !== 'codigos' && $this->hasColumnInAnyConnection($table, $param) && $value !== null) {
                    $this->simpleWhere($q, $param, $value, $key1, $operator);
                }
            }
        }
    }

    private function simpleWhere(Builder $q, string $param, mixed $value, int $key, string $operator = 'like'): void{
        if ($value === '' || $value === null || $value === 'null') return;

        $table = $q->getModel()->getTable();

        if ($key === 0 || $operator !== 'like') {
            if (!is_array($value)) {
                $q->where($table . '.' . $param, $operator, $value);
            } else {
                $q->whereIn($table . '.' . $param, $value);
            }
        } else {
            $q->orWhere($table . '.' . $param, $operator, $value);
        }
    }

    private function hasColumnInAnyConnection(string $table, string $column): bool{
        $connections = $this->connections;

        foreach ($connections as $connection) {
            try {
                if (Schema::connection($connection)->hasTable($table)) {
                    if (Schema::connection($connection)->hasColumn($table, $column)) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return false;
    }
}
