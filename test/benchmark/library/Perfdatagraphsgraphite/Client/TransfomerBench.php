<?php

namespace Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphsgraphite\Client\Transformer;

use GuzzleHttp\Psr7\Response;

class TransfomerBench
{
    protected function loadTestdata(string $file)
    {
        $data = [];

        $response = new Response(404);

        if (!file_exists($file)) {
            throw new Exception($file);
        }

        $jsonContent = file_get_contents($file);
        $response = new Response(
            200,
            ['Content-Type' => 'application/json'],
            $jsonContent
        );

        return $response;
    }

    /**
     * @Revs(100)
     * @Iterations(10)
     */
    public function benchTransform()
    {
        $input = $this->loadTestdata('test/testdata/load.json');
        $actual = Transformer::transform($input);
    }
}
