<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Application\Config;
use Icinga\Application\Logger;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

use DateInterval;
use DateTime;
use Exception;

/**
 * Graphite handles calling the API and returning the data.
 */
class Graphite
{
    protected const RENDER_ENDPOINT = '/render';
    protected const METRICS_ENDPOINT = '/metrics';

    /** @var $this \Icinga\Application\Modules\Module */
    protected $client = null;

    public function __construct(string $baseURI, string $username, string $password, int $timeout, bool $tlsVerify)
    {
        $this->client = new Client([
            'base_uri' => $baseURI,
            'timeout' => $timeout,
            'auth' => [$username, $password],
            'verify' => $tlsVerify
        ]);
    }

    /**
     * status calls the Graphite Metrics HTTP API to determine if Graphite is reachable.
     *
     * @return array
     */
    public function status(): array
    {
        try {
            $response = $this->client->request('GET', $this::METRICS_ENDPOINT);
            return ['output' =>  $response->getBody()->getContents()];
        } catch (ConnectException $e) {
            return ['output' => 'Connection error: ' . $e->getMessage(), 'error' => true];
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                return ['output' => 'HTTP error: ' . $e->getResponse()->getStatusCode() . ' - ' .
                                      $e->getResponse()->getReasonPhrase(), 'error' => true];
            } else {
                return ['output' => 'Request error: ' . $e->getMessage(), 'error' => true];
            }
        } catch (Exception $e) {
            return ['output' => 'General error: ' . $e->getMessage(), 'error' => true];
        }

        return ['output' => 'Unknown error', 'error' => true];
    }

    /**
     * render calls the Graphite Render HTTP API, decodes and returns the response.
     *
     * @param string $hostName host name for the performance data query
     * @param string $serviceName service name for the performance data query
     * @param string $checkCommand checkcommand name for the performance data query
     * @param string $from specifies the beginning for which to fetch the data
     * @param array $metrics list of metrics to return
     * @return Response
     */
    public function render(string $hostName, string $serviceName, string $checkCommand, string $from, array $metrics): Response
    {
        // Sanitize query parameters for Graphite
        $hostName = self::sanitizePath($hostName);
        $serviceName = self::sanitizePath($serviceName);
        $checkCommand = self::sanitizePath($checkCommand);

        $metricNames = '*';
        if (!empty($metrics)) {
            $m = array_map([$this, 'sanitizePath'], $metrics);
            $metricNames = '{'. implode(',', $m) . '}';
        }

        // Build the query string based on the service we are given
        $target = sprintf('icinga2.%s.services.%s.%s.perfdata.%s.{value,warn,crit}', $hostName, $serviceName, $checkCommand, $metricNames);

        if ($serviceName === 'hostalive') {
            $target = sprintf('icinga2.%s.host.hostalive.perfdata.%s.{value,warn,crit}', $hostName, $metricNames);
        }

        $query = [
            'query' => [
                'target' => $target,
                'from' => $from,
                'format' => 'json',
            ]
        ];

        $response = $this->client->request('GET', $this::RENDER_ENDPOINT, $query);

        return $response;
    }

    /**
     * parseDuration parses the duration string from the frontend
     * into something we can use with the Graphite API (from parameter).
     *
     * @param string $duration ISO8601 Duration
     * @param string $now current time (used in testing)
     * @return string
     */
    public static function parseDuration(\DateTime $now, string $duration): string
    {
        try {
            $int = new DateInterval($duration);
        } catch (Exception $e) {
            Logger::error('Failed to parse date interval: %s', $e);
            $int = new DateInterval('PT12H');
        }

        // Subtract the inverval from the current time so that we have
        // the 'from' parameter for graphite
        $now->sub($int);
        return $now->format('H:i_Ymd');
    }

    /**
     * fromConfig returns a new Graphite Client from this module's configuration
     *
     * @param Config $moduleConfig configuration to load (used for testing)
     * @return $this
     */
    public static function fromConfig(Config $moduleConfig = null)
    {
        $default = [
            'api_url' => 'http://localhost:8081',
            'api_timeout' => 10,
            'api_username' => '',
            'api_password' => '',
            'api_tls_insecure' => false,
        ];

        // Try to load the configuration
        if ($moduleConfig === null) {
            try {
                $moduleConfig = Config::module('perfdatagraphsgraphite');
            } catch (Exception $e) {
                Logger::error('Failed to load Perfdata Graphs Graphite module configuration: %s', $e);
                return $default;
            }
        }

        $baseURI = rtrim($moduleConfig->get('graphite', 'api_url', $default['api_url']), '/');
        $timeout = (int) $moduleConfig->get('graphite', 'api_timeout', $default['api_timeout']);
        $username = $moduleConfig->get('graphite', 'api_username', $default['api_username']);
        $password = $moduleConfig->get('graphite', 'api_password', $default['api_password']);
        $tlsVerify = (bool) $moduleConfig->get('graphite', 'api_tls_insecure', $default['api_tls_insecure']);

        return new static($baseURI, $username, $password, $timeout, $tlsVerify);
    }

    /**
     * sanitizePath prepares a a path string for Carbon.
     * Since Carbon stores metrics using dot delimited paths
     *
     * Made this static for now since there might be other places
     * this could be useful and I didn't want to have a Util class.
     *
     * @param string $path the path string to sanitize
     * @return string The sanitized path string or an empty one.
     */
    public static function sanitizePath(string $path): string
    {
        if (!is_string($path) || empty($path)) {
            return '';
        }

        // TODO: extend this list to include all cases
        $replace = [
            '/\s+/' => '_',
            '/\//' => '_',
            '/\./' => '_',
        ];

        return preg_replace(
            array_keys($replace),
            array_values($replace),
            trim($path)
        );
    }
}
