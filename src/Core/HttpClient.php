<?php

declare(strict_types=1);

namespace Bastivan\UniversalApi\Core;

use Exception;

/**
 * Class HttpClient
 * Developed by Bastivan Consulting
 *
 * Un wrapper autour de cURL pour standardiser les appels sortants,
 * gérer les timeouts et les retries, et faciliter les logs.
 */
class HttpClient
{
    private int $timeout;
    private int $retries;
    private array $defaultHeaders;

    public function __construct(array $defaultHeaders = [])
    {
        $this->timeout = (int) Config::get('HTTP_CLIENT_TIMEOUT', 30);
        $this->retries = (int) Config::get('HTTP_CLIENT_RETRIES', 3);
        $this->defaultHeaders = $defaultHeaders;
    }

    /**
     * Envoie une requête GET.
     *
     * @param string $url L'URL de la requête
     * @param array $params Les paramètres de requête (query string)
     * @param array $headers Les en-têtes HTTP supplémentaires
     * @return array La réponse (status, headers, body)
     */
    public function get(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $this->request('GET', $url, [], $headers);
    }

    /**
     * Envoie une requête POST.
     */
    public function post(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('POST', $url, $data, $headers);
    }

    /**
     * Envoie une requête PUT.
     */
    public function put(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('PUT', $url, $data, $headers);
    }

    /**
     * Envoie une requête PATCH.
     */
    public function patch(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('PATCH', $url, $data, $headers);
    }

    /**
     * Envoie une requête DELETE.
     */
    public function delete(string $url, array $data = [], array $headers = []): array
    {
        return $this->request('DELETE', $url, $data, $headers);
    }

    /**
     * Exécute la requête HTTP avec gestion des retries et logs.
     *
     * @param string $method Méthode HTTP (GET, POST, etc.)
     * @param string $url URL de la requête
     * @param array|string $data Données à envoyer (body)
     * @param array $headers En-têtes HTTP spécifiques
     * @return array Résultat contenant 'status', 'headers', 'body'
     * @throws Exception Si tous les essais échouent
     */
    public function request(string $method, string $url, array|string $data = [], array $headers = []): array
    {
        $attempt = 0;
        $lastException = null;
        $method = strtoupper($method);

        // Fusionner les headers
        $finalHeaders = array_merge($this->defaultHeaders, $headers);

        // Préparer le body
        $body = $data;
        $isJson = false;

        // Détection Content-Type JSON
        foreach ($finalHeaders as $h) {
            if (stripos($h, 'Content-Type: application/json') !== false) {
                $isJson = true;
                break;
            }
        }

        if (is_array($data)) {
            if ($isJson) {
                $body = json_encode($data);
            } elseif (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $body = http_build_query($data);
            }
        }

        do {
            $attempt++;
            try {
                return $this->executeCurl($method, $url, $body, $finalHeaders);
            } catch (Exception $e) {
                $lastException = $e;
                Logger::log('warning', "HTTP Client Retry {$attempt}/{$this->retries}", [
                    'url' => $url,
                    'method' => $method,
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $this->retries) {
                    break;
                }

                // Backoff exponentiel : 1s, 2s...
                sleep((int) pow(2, $attempt - 1));
            }
        } while ($attempt < $this->retries);

        Logger::log('error', "HTTP Client Failed after {$attempt} attempts", [
            'url' => $url,
            'method' => $method,
            'error' => $lastException ? $lastException->getMessage() : 'Unknown error'
        ]);

        throw $lastException;
    }

    /**
     * Wrapper bas niveau pour cURL.
     */
    private function executeCurl(string $method, string $url, string|array $body, array $headers): array
    {
        $ch = curl_init();
        $responseHeaders = [];

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$responseHeaders) {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) >= 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }
                return $len;
            },
            // Suivre les redirections
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5
        ];

        if (!empty($body) && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $options);

        Logger::log('info', "HTTP Client Request", [
            'method' => $method,
            'url' => $url,
            // On ne loggue pas les headers ou le body par sécurité (credentials)
            // sauf si nécessaire pour débug
        ]);

        $start = microtime(true);
        $responseBody = curl_exec($ch);
        $duration = microtime(true) - $start;

        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($errno) {
            throw new Exception("cURL Error ({$errno}): {$error}");
        }

        // Considérer les erreurs 5xx comme des exceptions pour déclencher le retry
        // 4xx sont des erreurs client, on ne retry pas par défaut
        if ($statusCode >= 500) {
            throw new Exception("HTTP Error {$statusCode}");
        }

        Logger::log('info', "HTTP Client Response", [
            'status' => $statusCode,
            'duration' => round($duration, 4) . 's',
            'url' => $url
        ]);

        return [
            'status' => $statusCode,
            'headers' => $responseHeaders,
            'body' => $responseBody
        ];
    }
}
