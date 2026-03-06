<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core\Middleware;

use Bastivan\UniversalApi\Core\Config;
use Bastivan\UniversalApi\Controllers\ErrorController;

/**
 * Class SecurityMiddleware
 * Developed by Bastivan Consulting
 *
 * Handles Security headers, HTTPS enforcement, and CDN IP resolution.
 */
class SecurityMiddleware
{
    public static function handle(): void
    {
        // 0. Security Headers ("Defense in Depth")
        // HSTS: Tell clients to always use HTTPS (1 year)
        header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

        // CSP: Prevent XSS and other injections.
        // We allow 'unsafe-inline' for styles because the landing page uses it.
        // We allow cdnjs and unsafe-inline scripts for Swagger UI documentation.
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; img-src 'self' https://cdnjs.cloudflare.com data:; frame-ancestors 'none'");

        // X-Content-Type-Options: Prevent MIME-sniffing
        header("X-Content-Type-Options: nosniff");

        // 1. Force HTTPS
        // In most production environments with load balancers (AWS, K8s), logic might differ (X-Forwarded-Proto).
        // But the requirement says "SSL Certificate Mandatory, No HTTP".

        $isHttps = false;
        $actualRemoteIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $isTrustedProxy = \Bastivan\UniversalApi\Core\TrustedProxyResolver::isTrusted($actualRemoteIp);

        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443) {
            $isHttps = true;
        } elseif ($isTrustedProxy && !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
            $isHttps = true;
        }

        // If strict SSL is enforced and we are not on HTTPS, we should block or redirect.
        // For an API, blocking with 403 Forbidden or 426 Upgrade Required is often better than redirecting POSTs.
        if (Config::get('APP_ENV') === 'production' && !$isHttps) {
            $controller = new ErrorController();
            $controller->forbidden(['message' => 'HTTPS is required.']);
        }

        // 2. Block Legacy SSL/TLS (Best effort application level check)
        // Usually handled by Nginx/Ingress, but we can check SSL_PROTOCOL if exposed by server.
        if (isset($_SERVER['SSL_PROTOCOL'])) {
            $protocol = $_SERVER['SSL_PROTOCOL'];
            if (preg_match('/(SSLv3|TLSv1\.0|TLSv1\.1)/', $protocol)) {
                $controller = new ErrorController();
                $controller->forbidden(['message' => 'TLS 1.2+ is required.']);
            }
        }

        // 3. Resolve Real IP from CDNs (Cloudflare, Gcore, Nginx Proxy)
        // We MUST verify that the request actually comes from a trusted proxy before accepting header spoofing.
        $actualRemoteIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (\Bastivan\UniversalApi\Core\TrustedProxyResolver::isTrusted($actualRemoteIp)) {
            if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                // Cloudflare
                $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } elseif (isset($_SERVER['HTTP_X_GCORE_REMOTE_IP'])) {
                // Gcore
                $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_GCORE_REMOTE_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                // Standard Proxy - Take the first IP in the list
                $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $_SERVER['REMOTE_ADDR'] = trim($ips[0]);
            }
        }

        // 4. HTTP Method Restriction (Surface Attack Reduction)
        // Allows limiting allowed HTTP methods globally (e.g. GET, POST only).
        // Default: Allow all standard methods.
        $allowedMethodsRaw = Config::get('SECURITY_ALLOWED_METHODS', '');
        if (!empty($allowedMethodsRaw)) {
            $allowedMethods = array_map('trim', array_map('strtoupper', explode(',', $allowedMethodsRaw)));
            $currentMethod = $_SERVER['REQUEST_METHOD'];

            if (!in_array($currentMethod, $allowedMethods, true)) {
                $controller = new ErrorController();
                // 405 Method Not Allowed is more appropriate than 403, but 403 works for "Security Policy"
                http_response_code(405);
                $controller->forbidden(['message' => "Method $currentMethod not allowed by security policy."]);
                exit;
            }
        }

        // 5. CSRF Protection (Origin / Referer Check)
        // Since CacheMiddleware adds support for Cookies in cache keys, and custom Auth might use Cookies,
        // we must protect against CSRF for state-changing methods when Cookies are present.
        if (isset($_SERVER['HTTP_COOKIE']) && !empty($_SERVER['HTTP_COOKIE'])) {
            $method = $_SERVER['REQUEST_METHOD'];
            $unsafeMethods = ['POST', 'PUT', 'DELETE', 'PATCH'];

            if (in_array($method, $unsafeMethods, true)) {
                $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
                $referer = $_SERVER['HTTP_REFERER'] ?? null;

                // Determine Target Origin
                // Re-evaluate HTTPS status to be safe within this block context
                $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;

                // Rely on the locally calculated $isSecure to avoid undefined variable issues
                $scheme = $isSecure ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $targetOrigin = $scheme . '://' . $host;

                // Trusted Origins (Configurable)
                $trustedOriginsRaw = Config::get('CSRF_TRUSTED_ORIGINS', '');
                // Normalize config: trim and remove trailing slashes
                $trustedOrigins = array_filter(array_map(function($url) {
                    return rtrim(trim($url), '/');
                }, explode(',', $trustedOriginsRaw)));

                $trustedOrigins[] = $targetOrigin; // Always trust self

                $isCsrfSafe = false;

                // Check Origin
                if ($origin) {
                    // Origin usually includes scheme and host (and port)
                    if (in_array($origin, $trustedOrigins, true)) {
                        $isCsrfSafe = true;
                    }
                }
                // Fallback to Referer if Origin is missing
                elseif ($referer) {
                    foreach ($trustedOrigins as $trusted) {
                        // Secure Check: Ensure referer matches trusted origin exactly or starts with trusted origin followed by '/'
                        // Using strncmp for PHP < 8.0 compatibility instead of str_starts_with
                        $trustedSlash = $trusted . '/';
                        if ($referer === $trusted || strncmp($referer, $trustedSlash, strlen($trustedSlash)) === 0) {
                            $isCsrfSafe = true;
                            break;
                        }
                    }
                }

                if (!$isCsrfSafe) {
                    $controller = new ErrorController();
                    $controller->forbidden(['message' => 'CSRF verification failed. Invalid Origin or Referer.']);
                    exit;
                }
            }
        }

        // 6. IP Access Control (Deny All / Whitelist)
        $strategy = Config::get('SECURITY_ACCESS_STRATEGY', 'ALLOW_ALL');

        if ($strategy === 'WHITELIST') {
            $allowedIpsRaw = Config::get('SECURITY_ALLOWED_IPS', '');
            $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsRaw)));
            $clientIp = $_SERVER['REMOTE_ADDR'];

            $isAllowed = false;

            // Simple exact match check
            if (in_array($clientIp, $allowedIps, true)) {
                $isAllowed = true;
            }

            // If we want to support CIDR later, we would add that logic here.

            if (!$isAllowed) {
                // If the list is empty and strategy is WHITELIST, we effectively deny everyone.
                // But usually localhost might be implicitly allowed or explicitly required in the list.
                // We adhere to strict "Deny all except custom IPs".

                $controller = new ErrorController();
                $controller->forbidden(['message' => 'Access denied by security policy.']);
                // forbidden() method likely exits the script
                exit;
            }
        }
    }

    /**
     * Checks security options for a specific route.
     *
     * @param array $securityOptions
     */
    public static function checkRouteSecurity(array $securityOptions): void
    {
        // IP Access Control
        if (isset($securityOptions['allowed_ips']) && is_array($securityOptions['allowed_ips'])) {
            $allowedIps = $securityOptions['allowed_ips'];
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

            if (!in_array($clientIp, $allowedIps, true)) {
                http_response_code(403);
                echo "403 Forbidden - Access Denied by Route Policy - Bastivan Consulting";
                exit;
            }
        }

        // HTTP Method Restriction
        if (isset($securityOptions['allowed_methods']) && is_array($securityOptions['allowed_methods'])) {
            $allowedMethods = array_map('strtoupper', $securityOptions['allowed_methods']);
            $currentMethod = $_SERVER['REQUEST_METHOD'];

            if (!in_array($currentMethod, $allowedMethods, true)) {
                http_response_code(405);
                echo "405 Method Not Allowed - Allowed: " . implode(', ', $allowedMethods) . " - Bastivan Consulting";
                exit;
            }
        }
    }
}
