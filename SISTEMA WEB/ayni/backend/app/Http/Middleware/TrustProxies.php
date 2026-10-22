<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /** Solo detrás de un proxy propio (el Nginx de CloudPanel): AYNI_PROXIES_CONFIABLES=127.0.0.1 */
    public function __construct()
    {
        $confiables = (string) config('ayni.proxies_confiables');
        $this->proxies = $confiables === '' ? null : array_map('trim', explode(',', $confiables));
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
