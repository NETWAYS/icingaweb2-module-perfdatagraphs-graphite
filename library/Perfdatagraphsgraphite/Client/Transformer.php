<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphs\Model\PerfdataResponse;
use Icinga\Module\Perfdatagraphs\Model\PerfdataSet;
use Icinga\Module\Perfdatagraphs\Model\PerfdataSeries;

use Generator;
use SplFixedArray;

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
        // TODO there's probably more to do here
        if ($checkCommand === 'disk') {
            $t = str_replace('_', '/', $t);
        }

        return $t;
    }

    /**
     * getSeries returns a single Graphite dataseries as a list of datapoints.
     * Used to find 'warn' and 'crit', but we can probably reuse this for 'value'.
     *
     * @param array
     * @param string
     * @return SplFixedArray
     */
    protected static function getSeries(array $data, string $target): SplFixedArray
    {
        $series = array_filter($data, function ($item) use ($target) {
            if ($item['target'] === $target) {
                return $item;
            }
        });

        if (empty($series)) {
            return SplFixedArray::fromArray([]);
        }

        $datapoints = $series[array_key_first($series)]['datapoints'];

        $datapointGenerator = function ($datapoints) {
            $idx = 0;
            foreach ($datapoints as list($value)) {
                yield [$idx, $value];
                $idx++;
            }
        };

        $values = new SplFixedArray(count($datapoints));

        foreach ($datapointGenerator($datapoints) as $point) {
            $values[$point[0]] = $point[1];
        }

        return $values;
    }

    /**
     * getDataset transforms each dataset into the required format and yields the finalized dataset.
     *
     * @param array $data the data to transform
     * @param string $checkCommand name of the checkcommand, could be useful
     * @return Generator
     */
    protected static function getDataset(array $data, string $checkCommand = ''): Generator
    {
        // TODO There's some duplication here, but that's fine for now.
        $datapointGenerator = function ($datapoints) {
            $idx = 0;
            foreach ($datapoints as list($value, $timestamp)) {
                yield [$idx, $value, $timestamp];
                $idx++;
            }
        };

        // A dataset is single perfdata target, e.g. rta, pl, disk /.
        // A series is a list of datapoints with a label (value, warn, crit)
        foreach ($data as $dataset) {
            $name = $dataset['target'];

            // Only .value is a dataset for us
            // .crit and .warn get added to this dataset as a series
            if (!str_ends_with($name, '.value')) {
                continue;
            }

            $finalizedDataset = new PerfdataSet(self::updateTitle($name, $checkCommand));

            // Create the values and timestamp arrays for this dataset
            // Decided to use an SplFixedArray since there might be lots of data
            $values = new SplFixedArray(count($dataset['datapoints']));
            $timestamps = new SplFixedArray(count($dataset['datapoints']));

            foreach ($datapointGenerator($dataset['datapoints']) as $point) {
                $values[$point[0]] = $point[1];
                $timestamps[$point[0]] = $point[2];
            }

            $valuesSeries = new PerfdataSeries('value', $values);

            $finalizedDataset->setTimestamps($timestamps);
            $finalizedDataset->addSeries($valuesSeries);

            // Get the warn and crit for this dataset and add it
            // Since for graphite it's a unrelated dateset, we gotta find them first.
            // I don't like this... maybe there's better way.
            $warnName = preg_replace('/\.value$/', '.warn', $dataset['target']);
            $warnSeries = self::getSeries($data, $warnName);
            if (count($warnSeries) > 0) {
                $warnSeries = new PerfdataSeries('warning', $warnSeries);
                $finalizedDataset->addSeries($warnSeries);
            }

            $critName = preg_replace('/\.value$/', '.crit', $dataset['target']);
            $critSeries = self::getSeries($data, $critName);
            if (count($critSeries) > 0) {
                $critSeries = new PerfdataSeries('critical', $critSeries);
                $finalizedDataset->addSeries($critSeries);
            }

            yield $finalizedDataset;
        }
    }

    /**
     * transform takes the Graphite API response and transforms it into the
     * output format we need.
     *
     * @param GuzzleHttp\Psr7\Response $response the data to transform
     * @param string $checkCommand name of the checkcommand
     * @return PerfdataResponse
     */
    public static function transform(Response $response, string $checkCommand = ''): PerfdataResponse
    {
        $pfr = new PerfdataResponse();

        if (empty($response)) {
            return $pfr;
        }

        // Parse the JSON response
        // TODO: Might be best to stream the data, instead if one big GULP
        $data = json_decode($response->getBody(), true);

        foreach (self::getDataset($data, $checkCommand) as $dataset) {
            $pfr->addDataset($dataset);
        }

        return $pfr;
    }
}
