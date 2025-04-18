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
    protected const FIND_ENDPOINT = '/metrics/find';

    /** @var $this \Icinga\Application\Modules\Module */
    protected $client = null;

    protected string $hostNameTemplate;
    protected string $serviceNameTemplate;

    public function __construct(
        string $baseURI,
        string $username,
        string $password,
        int $timeout,
        bool $tlsVerify,
        string $hostNameTemplate,
        string $serviceNameTemplate
    ) {
        $this->client = new Client([
            'base_uri' => $baseURI,
            'timeout' => $timeout,
            'auth' => [$username, $password],
            'verify' => $tlsVerify
        ]);

        $this->hostNameTemplate = $hostNameTemplate;
        $this->serviceNameTemplate = $serviceNameTemplate;
    }

    /**
     * status calls the Graphite Metrics HTTP API to determine if Graphite is reachable.
     * We use this to validate the configuration and if the API is reachable.
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
     * findMetrics calls the Graphite find/metrics HTTP API and returns the names of the metrics.
     *
     * @param string $hostName host name for the performance data query
     * @param string $serviceName service name for the performance data query
     * @param string $checkCommand checkcommand name for the performance data query
     * @param string $from specifies the beginning for which to fetch the data
     * @param bool $isHostCheck is this a host check or not
     * @param array $includeMetrics metrics to include
     * @param array $excludeMetrics metrics to exlude
     *
     * @throws ConnectException
     * @throws RequestException
     *
     * @return array $metrics list of metrics
     */
    public function findMetrics(
        string $hostName,
        string $serviceName,
        string $checkCommand,
        string $from,
        bool $isHostCheck,
        array $includeMetrics,
        array $excludeMetrics
    ): array {
        $metricNames = '*';

        $target = $this->parseTemplate($hostName, $serviceName, $checkCommand, $isHostCheck, $metricNames);

        $query = [
            'query' => [
                'query' => $target,
                'from' => $from,
                'format' => 'treejson',
            ]
        ];

        Logger::debug('Calling findMetric API with query: %s', $query);

        $response = $this->client->request('GET', $this::FIND_ENDPOINT, $query);

        $metrics = [];
        $foundMetrics = json_decode($response->getBody(), true);

        // We just care about the name of the metric
        foreach ($foundMetrics as $metric) {
            if ($metric['text'] ?? false) {
                $metrics[] = $metric['text'];
            }
        }

        $metrics = $this->filterMetrics($metrics, $includeMetrics, $excludeMetrics);

        // TODO: This a bit hacky and obscure, but since we load everything at once
        // that can cause the memory to be exhausted. We should either
        // optimize this module, or the perfdata design in general.
        $metrics = array_slice($metrics, 0, 10);

        Logger::debug('Found and included/excluded metrics: %s', $metrics);

        return $metrics;
    }

    /**
     * render calls the Graphite Render HTTP API, decodes and returns the response.
     *
     * @param string $hostName host name for the performance data query
     * @param string $serviceName service name for the performance data query
     * @param string $checkCommand checkcommand name for the performance data query
     * @param string $from specifies the beginning for which to fetch the data
     * @param bool $isHostCheck is this a host check or not
     * @param array $metrics list of metrics to return
     *
     * @throws ConnectException
     * @throws RequestException
     *
     * @return Response
     */
    public function render(
        string $hostName,
        string $serviceName,
        string $checkCommand,
        string $from,
        bool $isHostCheck,
        array $metrics
    ): Response {
        $metricNames = '*';
        if (!empty($metrics)) {
            $m = array_map([$this, 'sanitizePath'], $metrics);
            $metricNames = '{'. implode(',', $m) . '}';
        }

        $target = $this->parseTemplate($hostName, $serviceName, $checkCommand, $isHostCheck, $metricNames) . '.{value,warn,crit}';

        $query = [
            'query' => [
                'target' => $target,
                'from' => $from,
                'format' => 'json',
            ]
        ];

        Logger::debug('Calling render API with query: %s', $query);

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
        return $now->getTimestamp();
    }

    /**
     * parseTemplate prepares the Graphite writer template for the API call.
     *
     * @param string $hostName host name for the performance data query
     * @param string $serviceName service name for the performance data query
     * @param string $checkCommand checkcommand name for the performance data query
     * @param bool $isHostCheck is this a host check or not
     * @param array $metrics list of metrics to return
     *
     * @return string
     */
    public function parseTemplate(string $hostName, string $serviceName, string $checkCommand, bool $isHostCheck, string $metricNames): string
    {
        // Sanitize query parameters for Graphite
        $hostName = self::sanitizePath($hostName);
        $serviceName = self::sanitizePath($serviceName);
        $checkCommand = self::sanitizePath($checkCommand);

        // Build the query string based on the service we are given
        $template = str_replace(
            ['$host.name$', '$service.name$', '$service.check_command$'],
            [$hostName, $serviceName, $checkCommand],
            $this->serviceNameTemplate
        );

        if ($isHostCheck) {
            $template = str_replace(
                ['$host.name$', '$host.check_command$'],
                [$hostName, $checkCommand],
                $this->hostNameTemplate
            );
        }

        return $template . sprintf('.perfdata.%s', $metricNames);
    }

    public function filterMetrics(array $metrics, array $includeMetrics, array $excludeMetrics): array
    {
        // Then reduce it to only include the ones that are requested via the custom variable
        if (!empty($includeMetrics)) {
            // Resolve all wildcards in the list and leave only the matching metrics.
            $metricsIncluded = array_filter($metrics, function ($metric) use ($includeMetrics) {
                foreach ($includeMetrics as $pattern) {
                    if (fnmatch($pattern, $metric)) {
                        return true;
                    }
                }
                return false;
            });

            $metrics = $metricsIncluded;
        }

        // Finally remove all that are explicitly to be removed
        if (!empty($excludeMetrics)) {
            $metricsExcluded = array_filter($metrics, function ($metric) use ($excludeMetrics) {
                foreach ($excludeMetrics as $pattern) {
                    if (fnmatch($pattern, $metric)) {
                        return false;
                    }
                }
                return true;
            });

            $metrics = $metricsExcluded;
        }

        return $metrics;
    }
    /**
     * fromConfig returns a new Graphite Client from this module's configuration
     *
     * @param Config $moduleConfig configuration to load (used for testing)
     * @return $this
     */
    public static function fromConfig(Config $moduleConfig = null): Graphite
    {
        $default = [
            'api_url' => 'http://localhost:8081',
            'api_timeout' => 10,
            'api_username' => '',
            'api_password' => '',
            'api_tls_insecure' => false,
            'writer_host_name_template' => 'icinga2.$host.name$.host.$host.check_command$',
            'writer_service_name_template' => 'icinga2.$host.name$.services.$service.name$.$service.check_command$',
        ];

        // Try to load the configuration
        if ($moduleConfig === null) {
            try {
                Logger::debug('Loaded Perfdata Graphs Graphite module configuration to get Config');
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
        $hostNameTemplate = $moduleConfig->get('graphite', 'writer_host_name_template', $default['writer_host_name_template']);
        $serviceNameTemplate = $moduleConfig->get('graphite', 'writer_service_name_template', $default['writer_service_name_template']);

        return new static($baseURI, $username, $password, $timeout, $tlsVerify, $hostNameTemplate, $serviceNameTemplate);
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

        $replace = [
            '/\s+/' => '_',
            '/\//' => '_',
            '/\./' => '_',
            '/,/' => '\,',
        ];

        return preg_replace(
            array_keys($replace),
            array_values($replace),
            trim($path)
        );
    }
}
