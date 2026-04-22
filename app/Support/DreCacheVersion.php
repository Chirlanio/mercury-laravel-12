<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Gerencia a "version key" do cache da DRE.
 *
 * Estratégia (arquitetura §6): o driver `database` do Laravel não suporta
 * tags. Em vez de invalidar chaves individuais, mantemos uma versão global
 * e embedamos o número dela em cada chave de matriz:
 *
 *   dre:matrix:v{version}:{md5(filter)}
 *
 * Ao invalidar (ex: um mapping salvo), simplesmente incrementamos `dre:cache_version`.
 * Todas as chaves antigas ficam órfãs no cache e expiram pelo TTL natural
 * (10 minutos). Zero query adicional por invalidation — um único INCR.
 *
 * A chave da versão é compartilhada entre requests e workers por estar no
 * mesmo store do cache. Em ambiente tenant, o próprio `CacheManager` do
 * stancl/tenancy adiciona o prefixo por tenant — isolamento é grátis.
 */
class DreCacheVersion
{
    public const KEY = 'dre:cache_version';

    public static function current(): int
    {
        $store = self::store();
        $value = $store->get(self::KEY);

        if ($value === null) {
            $store->forever(self::KEY, 1);

            return 1;
        }

        return (int) $value;
    }

    /**
     * Incrementa a versão (invalida todas as entradas DRE de uma vez).
     *
     * Se o cache driver ainda não tem a chave, seed em 1 antes do increment
     * para manter a semântica atomic-ish — `Cache::increment` em chave
     * ausente retorna false em alguns drivers.
     */
    public static function invalidate(): int
    {
        $store = self::store();

        if ($store->get(self::KEY) === null) {
            $store->forever(self::KEY, 1);
        }

        $new = $store->increment(self::KEY);

        return (int) ($new !== false ? $new : self::current());
    }

    /**
     * Resolve o store a usar. Em tenant, o `CacheManager` do stancl/tenancy
     * envolve o default com tagging — e o driver `database` não suporta.
     * Usamos `file` explicitamente, igual `CentralRoleResolver` faz.
     *
     * Em ambiente de teste, o cache default é `array` (sem tagging) e
     * funciona sem o override — o próprio `Cache::store('file')` também
     * resolve, mas para manter as asserções dos testes consistentes
     * deixamos o driver de teste quando configurado.
     */
    private static function store(): Repository
    {
        // Em test env (array) não há CacheManager da tenancy — usar default.
        if (app()->environment('testing') && config('cache.default') === 'array') {
            return Cache::store();
        }

        return Cache::store('file');
    }
}
