<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Application\Config;
use Icinga\Application\Logger;

use GuzzleHttp\Client;
use DateInterval;
use DateTime;
use Exception;

/**
 * Graphite handles calling the API and returning the data.
 */
class Graphite
{
    protected const ENDPOINT = '/render';

    /**
     * request calls the Graphite Render HTTP API, decodes and returns the data.
     *
     * @param string $hostName host name for the performance data query
     * @param string $serviceName service name for the performance data query
     * @param string $checkCommand checkcommand name for the performance data query
     * @param string $from specifies the beginning for which to fetch the data
     * @return array
     */
    public function request(string $hostName, string $serviceName, string $checkCommand, string $from, array $metrics): array
    {
        $config = self::loadConfig();

        // TODO Auth and TLS configuration
        $client = new Client([
            'base_uri' => $config['graphite_api_url'],
            'timeout' => $config['graphite_api_timeout'],
        ]);

        // Sanitize query parameters for Graphite
        $hostName = self::sanitizePath($hostName);
        $serviceName = self::sanitizePath($serviceName);
        $checkCommand = self::sanitizePath($checkCommand);

        $metricNames = '*';
        if (!empty($metrics)) {
            $metricNames = '{'. implode(',', $metrics) . '}';
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

        $response = $client->request('GET', $this::ENDPOINT, $query);

        // Parse the JSON response
        // TODO: Might be best to stream the data, instead if one big GULP
        $data = json_decode($response->getBody(), true);

        return $data;
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
     * loadConfig fetches this module's configuration.
     *
     * @param Config $moduleConfig configuration to load (used for testing)
     * @return array
     */
    public static function loadConfig(Config $moduleConfig = null): array
    {
        $default = [
            'graphite_api_url' => 'http://localhost:8081',
            'graphite_api_timeout' => '10',
        ];

        // Try to load the configuration
        if ($moduleConfig === null) {
            try {
                $moduleConfig = Config::module('graphsgraphite');
            } catch (Exception $e) {
                Logger::error('Failed to load Graphs Graphite module configuration: %s', $e);
                return $default;
            }
        }

        $config = [
            'graphite_api_url' => rtrim($moduleConfig->get('general', 'graphite_api_url', $default['graphite_api_url']), '/'),
            'graphite_api_timeout' => $moduleConfig->get('general', 'graphite_api_timeout', $default['graphite_api_timeout']),
        ];

        return $config;
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

        // TODO extend this list to include all cases
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
