<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Behind Cloudflare Tunnel every request reaches the app from cloudflared on
 * the same machine (127.0.0.1), and Cloudflare names the visitor in the
 * CF-Connecting-IP header. Only then is that header believed: it becomes the
 * single forwarded address, so a visitor cannot pass off a made-up
 * X-Forwarded-For as their address (to dodge the school-network lock on gate
 * tablets or the login throttle).
 */
class UseCloudflareClientIp
{
    /**
     * Proxies allowed to tell the app who the visitor is: cloudflared or a
     * reverse proxy on the server itself.
     */
    public const TRUSTED_PROXIES = ['127.0.0.1', '::1'];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $fromLocalProxy = in_array($request->server->get('REMOTE_ADDR'), self::TRUSTED_PROXIES, true);
        $visitor = $request->headers->get('CF-Connecting-IP');

        if ($fromLocalProxy && $visitor !== null && filter_var($visitor, FILTER_VALIDATE_IP) !== false) {
            $request->headers->set('X-Forwarded-For', $visitor);
        }

        return $next($request);
    }
}
