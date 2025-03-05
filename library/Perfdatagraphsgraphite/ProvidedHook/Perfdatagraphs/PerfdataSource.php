<?php

namespace Icinga\Module\Perfdatagraphsgraphite\ProvidedHook\PerfdataGraphs;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;
use Icinga\Module\Perfdatagraphsgraphite\Client\Transformer;

use Icinga\Module\Perfdatagraphs\Hook\PerfdataSourceHook;
use Icinga\Module\Perfdatagraphs\Model\PerfdataRequest;
use Icinga\Module\Perfdatagraphs\Model\PerfdataResponse;

use Icinga\Application\Logger;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;

use DateTime;
use Exception;

class PerfdataSource extends PerfdataSourceHook
{
    public function getName(): string
    {
        return 'Graphite';
    }

    public function fetchData(PerfdataRequest $req): PerfdataResponse
    {
        // Parse the duration
        $now = new DateTime();
        $from = Graphite::parseDuration($now, $req->getDuration());

        $perfdataresponse = new PerfdataResponse();

        // Create a client and get the data from the API
        try {
            $client = Graphite::fromConfig();
        } catch (Exception $e) {
            $perfdataresponse->addError($e->getMessage());
            return $perfdataresponse;
        }

        // Let's fetch the data from the Graphite API
        try {
            // Call find API to get a list of the metrics
            $metrics = $client->findMetrics(
                $req->getHostname(),
                $req->getServicename(),
                $req->getCheckcommand(),
                $from,
                $req->isHostCheck(),
                $req->getIncludeMetrics(),
                $req->getExcludeMetrics()
            );

            // Call render API to get HTTP response
            $response = $client->render(
                $req->getHostname(),
                $req->getServicename(),
                $req->getCheckcommand(),
                $from,
                $req->isHostCheck(),
                $metrics
            );
        } catch (ConnectException $e) {
            $perfdataresponse->addError($e->getMessage());
        } catch (RequestException $e) {
            $perfdataresponse->addError($e->getMessage());
        } catch (Exception $e) {
            $perfdataresponse->addError($e->getMessage());
        }

        // Why even bother when we have errors here
        if ($perfdataresponse->hasErrors()) {
            return $perfdataresponse;
        }

        // Transform into the PerfdataSourceHook format
        $perfdataresponse = Transformer::transform($response, $req->getCheckcommand());

        return $perfdataresponse;
    }
}
