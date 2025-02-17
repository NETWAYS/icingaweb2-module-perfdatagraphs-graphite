<?php

namespace Tests\Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphsgraphite\Client\Transformer;

use SplFixedArray;

use GuzzleHttp\Psr7\Response;

use PHPUnit\Framework\TestCase;

final class TransformerTest extends TestCase
{
    protected function loadTestdata(string $file)
    {
        $data = [];

        $response = new Response(
            404
        );

        if (file_exists($file)) {
            $jsonContent = file_get_contents($file);
            $response = new Response(
                200,
                ['Content-Type' => 'application/json'],
                $jsonContent,
            );
        }

        return $response;
    }

    public function test_updatetitle()
    {
        $actual = Transformer::updateTitle('icinga2.homestead.host.hostalive.perfdata.pl.value', 'hostalive');
        $expected = 'pl';

        $this->assertEquals($expected, $actual);

        $actual = Transformer::updateTitle('icinga2.homestead.services.disk__.disk.perfdata._.value', 'disk');
        $expected = '/';

        $this->assertEquals($expected, $actual);
    }

    public function test_transform()
    {
        $input = $this->loadTestdata('test/testdata/apt.json');

        $actual = Transformer::transform($input);
        $expected = '{"errors":[],"data":[{"title":"available_upgrades","unit":"","timestamps":[1731838620,1731838680,1731838740,1731838800,1731838860],"series":[{"name":"value","values":[626,626,626,626,626]}]},{"title":"critical_updates","unit":"","timestamps":[1731838620,1731838680,1731838740,1731838800,1731838860],"series":[{"name":"value","values":[211,211,211,211,211]}]}]}';

        $this->assertEquals($expected, json_encode($actual));
    }

    public function test_transform_with_emtpy()
    {
        $input = $this->loadTestdata('test/testdata/hostalive_empty.json');

        $actual = Transformer::transform($input);
        $expected = '{"errors":[],"data":[{"title":"rta","unit":"","timestamps":[1731848460,1731848520,1731848580,1731848640,1731848700],"series":[{"name":"value","values":[7,5,8,7,null]},{"name":"warning","values":[3,3,3,3,null]},{"name":"critical","values":[5,5,5,5,null]}]}]}';

        $this->assertEquals($expected, json_encode($actual));
    }

    public function test_transform_with_warn_and_crit()
    {
        $input = $this->loadTestdata('test/testdata/hostalive.json');

        $actual = Transformer::transform($input);

        $expected = '{"errors":[],"data":[{"title":"pl","unit":"","timestamps":[1731848460,1731848520,1731848580,1731848640,1731848700],"series":[{"name":"value","values":[0,0,0,0,null]},{"name":"warning","values":[80,80,80,80,null]},{"name":"critical","values":[100,100,100,100,null]}]},{"title":"rta","unit":"","timestamps":[1731848460,1731848520,1731848580,1731848640,1731848700],"series":[{"name":"value","values":[7,5,8,7,null]},{"name":"warning","values":[3,3,3,3,null]},{"name":"critical","values":[5,5,5,5,null]}]}]}';

        $this->assertEquals($expected, json_encode($actual));
    }
}
