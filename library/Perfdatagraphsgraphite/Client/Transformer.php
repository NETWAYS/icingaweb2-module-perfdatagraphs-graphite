<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphs\Model\PerfdataResponse;
use Icinga\Module\Perfdatagraphs\Model\PerfdataSet;
use Icinga\Module\Perfdatagraphs\Model\PerfdataSeries;

use Icinga\Application\Logger;

use GuzzleHttp\Psr7\Response;

/**
 * Transformer handles all data transformation.
 */
class Transformer
{
    /**
     * getDataset transforms each dataset into the required format and yields the finalized dataset.
     * Since the names in Graphite are: 'icinga2.homestead.services.disk__.disk.perfdata._.value'
     * and in the front end we want something cleaner.
     *
     * @param string $title Graphite dataset target name
     * @param string $checkCommand name of the check command in case we want special cases
     * @return string
     */
    public static function updateTitle(string $title, string $checkCommand = ''): string
    {
        // Remove everything the frontend does not care about
        $t = preg_replace('/\.value$/', '', preg_replace('/.+perfdata\./', '', $title));

        // Replace characters to make it make sense in the frontend
        if ($checkCommand === 'disk') {
            $t = str_replace('_', '/', $t);
        }

        return $t;
    }

    /**
     * transform takes the Graphite API response and transforms it into the
     * output format we need.
     *
     * @param GuzzleHttp\Psr7\Response $response the data to transform
     * @param array $metrics list of metrics
     * @param string $checkCommand name of the checkcommand
     * @return PerfdataResponse
     */
    public static function transform(Response $response, array $metrics, string $checkCommand = ''): PerfdataResponse
    {
        $pfr = new PerfdataResponse();

        if (empty($response)) {
            Logger::warning('Did not receive data in response');
            return $pfr;
        }

        $stream = new GraphiteCsvParser($response->getBody());

        // Create PerfdataSeries and add to PerfdataSet
        $valueseries = [];
        $warnseries = [];
        $critseries = [];
        $timestamps = [];

        foreach ($stream->each() as $record) {
            // Create a new array to store the values
            if ($record->getSeriesName() === 'value' && !isset($valueseries[$record->getMetricName()])) {
                $valueseries[$record->getMetricName()] = [];
            }

            if ($record->getSeriesName() === 'warn' && !isset($warnseries[$record->getMetricName()])) {
                $warnseries[$record->getMetricName()] = [];
            }

            if ($record->getSeriesName() === 'crit' && !isset($critseries[$record->getMetricName()])) {
                $critseries[$record->getMetricName()] = [];
            }

            if ($record->getSeriesName() === 'value') {
                $valueseries[$record->getMetricName()][] = $record->getValue();
            }

            if ($record->getSeriesName() === 'warn') {
                $warnseries[$record->getMetricName()][] = $record->getValue();
            }

            if ($record->getSeriesName() === 'crit') {
                $critseries[$record->getMetricName()][] = $record->getValue();
            }

            if (!isset($timestamps[$record->getMetricName()])) {
                $timestamps[$record->getMetricName()] = [];
            }

            // We only need to do this once
            if ($record->getSeriesName() === 'value') {
                $timestamps[$record->getMetricName()][] = $record->getTimestamp();
            }
        }

        // For each metrics create PerfdataSet
        // TODO: Skip if there are no values in the values Serie (all null)?
        foreach ($metrics as $metric) {
            // Do we have something for this metric
            if (!array_key_exists($metric, $timestamps)) {
                continue;
            }

            $s = new PerfdataSet(self::updateTitle($metric, $checkCommand));

            $s->setTimestamps($timestamps[$metric]);

            if (array_key_exists($metric, $valueseries)) {
                $series = new PerfdataSeries('value', $valueseries[$metric]);
                $s->addSeries($series);
            }

            if (array_key_exists($metric, $warnseries)) {
                $series = new PerfdataSeries('warning', $warnseries[$metric]);
                $s->addSeries($series);
            }
            if (array_key_exists($metric, $critseries)) {
                $series = new PerfdataSeries('critical', $critseries[$metric]);
                $s->addSeries($series);
            }

            $pfr->addDataset($s);
        }

        return $pfr;
    }
}
