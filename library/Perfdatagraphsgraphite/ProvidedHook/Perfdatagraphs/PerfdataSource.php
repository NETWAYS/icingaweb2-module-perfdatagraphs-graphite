<?php

namespace Icinga\Module\Perfdatagraphsgraphite\ProvidedHook\PerfdataGraphs;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;
use Icinga\Module\Perfdatagraphsgraphite\Client\Transformer;

use Icinga\Module\Perfdatagraphs\Hook\PerfdataSourceHook;
use Icinga\Module\Perfdatagraphs\Model\PerfdataResponse;

use DateTime;
use Exception;

class PerfdataSource extends PerfdataSourceHook
{
    public function getName(): string
    {
        return 'Graphite';
    }

    public function fetchData(string $hostName, string $serviceName, string $checkCommand, string $duration, array $metrics): PerfdataResponse
    {
        // Parse the duration
        $now = new DateTime();
        $from = Graphite::parseDuration($now, $duration);

        $perfdataresponse = new PerfdataResponse();

        // Create a client and get the data from the API
        try {
            $client = Graphite::fromConfig();
        } catch (Exception $e) {
            $perfdataresponse->addError($e->getMessage());
            return $perfdataresponse;
        }

        // Call render API to get HTTP response
        $response = $client->render($hostName, $serviceName, $checkCommand, $from, $metrics);

        // Transform into the PerfdataSourceHook format
        $perfdataresponse = Transformer::transform($response, $checkCommand);

        return $perfdataresponse;
    }
}
