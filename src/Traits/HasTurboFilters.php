<?php

namespace Vendor\TurboFilter\Traits;

use Illuminate\Support\Facades\Request;
use Vendor\TurboFilter\Core\FilterEngine;

trait HasTurboFilters{
    /**
     * Scope para aplicar filtros con TurboFilter.
    */
    function scopeFilter($query, ?array $filters = null, ?array $payload = null){
        [$filters, $payload, $connections] = $this->prepareFilterEngine($filters, $payload);
        $engine = new FilterEngine($query, $filters, $payload, $connections);
        return $engine->applyFilter();
    }

    /**
     * Scope para paginar o traer registros según request o payload.
    */
    function scopeGetOrPaginate($query, ?array $payload = null){
        [, $payload, $connections] = $this->prepareFilterEngine(null, $payload);
        $engine = new FilterEngine($query, [], $payload, $connections);
        return $engine->applyGetOrPaginate();
    }

    /**
     * Scope para obtener registros personalizados con filtros.
    */
    function scopeCustomGet($query, ?array $filters = null, ?array $payload = null){
        [$filters, $payload, $connections] = $this->prepareFilterEngine($filters, $payload);
        $engine = new FilterEngine($query, $filters, $payload, $connections);
        return $engine->applyCustomGet();
    }

    /**
     * Prepara los parámetros comunes para FilterEngine.
    */
    private function prepareFilterEngine(?array $filters, ?array $payload): array{
        // Filtros: si no se pasan, se toman del modelo o se inicializan vacío
        $filters = $filters ?? [];

        // Payload: si no se pasa, usar request()->all()
        if ($payload === null) {
            $payload = Request::capture() ? Request::all() : [];
        }

        // Conexiones: obtener de config o usar ['mysql'] por defecto
        $connections = config('turbo-filter.connections_to_check', ['mysql']);

        return [$filters, $payload, $connections];
    }
}
