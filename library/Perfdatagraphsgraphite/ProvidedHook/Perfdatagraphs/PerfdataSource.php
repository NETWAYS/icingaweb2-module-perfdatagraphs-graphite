<?php

namespace Icinga\Module\Perfdatagraphsgraphite\ProvidedHook\PerfdataGraphs;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;
use Icinga\Module\Perfdatagraphsgraphite\Client\Transformer;

use Icinga\Module\Perfdatagraphs\Hook\PerfdataSourceHook;

use DateTime;
use Exception;

class PerfdataSource extends PerfdataSourceHook
{
    public function getName(): string
    {
        return 'Graphite';
    }

    public function fetchData(string $hostName, string $serviceName, string $checkCommand, string $duration, array $metrics): array
    {
        // Parse the duration
        $now = new DateTime();
        $from = Graphite::parseDuration($now, $duration);
        // Create a client and get the data from the API
        try {
            $client = Graphite::fromConfig();
        } catch (Exception $e) {
            // TODO What should we return here?
            return [];
        }
        $response = $client->render($hostName, $serviceName, $checkCommand, $from, $metrics);
        // Transform into the PerfdataSourceHook format
        $d = Transformer::transform($response, $checkCommand);

        return $d;
    }
}
