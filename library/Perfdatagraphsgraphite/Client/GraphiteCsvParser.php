<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

use GuzzleHttp\Psr7\Stream;

/**
 * GraphiteCsvParser takes a CSV Stream and returns nice little GraphiteRecords
 */
class GraphiteCsvParser
{
    private $response;
    private $resource;
    private $stream;

    public $closed;

    public function __construct(Stream $response)
    {
        $this->response = $response;
        $this->resource = $response->detach();
        $this->closed = false;
    }

    public function each()
    {
        try {
            while (($csv = fgetcsv($this->resource)) !== false) {
                $name = explode('.', $csv[0]);
                $timestamp = strtotime($csv[1]);
                $value = $csv[2] === '' ? null: floatval($csv[2]);

                // name and name of the series
                $seriesname = array_slice($name, -2);

                // Just in case
                if (count($seriesname) < 2) {
                    return null;
                }

                $metricname = $seriesname[0]; # load1
                $seriesname = $seriesname[1]; # value,warn,crit

                $result = new GraphiteRecord($seriesname, $metricname, $timestamp, $value);

                if ($result instanceof GraphiteRecord) {
                    yield $result;
                }
            }
        } finally {
            $this->closeConnection();
        }
    }

    private function closeConnection(): void
    {
        # Close CSV Parser
        $this->closed = true;
        if (isset($this->response)) {
            $this->response->close();
        }
        if (is_resource($this->resource)) {
            fclose($this->resource);
        }

        unset($this->response);
        unset($this->resource);
    }
}
