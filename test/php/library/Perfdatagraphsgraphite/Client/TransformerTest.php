<?php

namespace Tests\Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphsgraphite\Client\Transformer;

use SplFixedArray;

use PHPUnit\Framework\TestCase;

final class TransformerTest extends TestCase
{
    protected function loadTestdata(string $file)
    {
        $data = [];

        if (file_exists($file)) {
            $jsonContent = file_get_contents($file);
            $data = json_decode($jsonContent, true);
        }

        return $data;
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

        $expected = [
            [
                'title' => 'available_upgrades',
                'timestamps' => SplFixedArray::fromArray([1731838620, 1731838680, 1731838740, 1731838800, 1731838860]),
                'series' =>
                    [
                        [
                            'name' => 'value',
                            'data' => SplFixedArray::fromArray([626, 626, 626, 626, 626]),
                        ],
                    ]
            ],
            [
                'title' => 'critical_updates',
                'timestamps' => SplFixedArray::fromArray([1731838620, 1731838680, 1731838740, 1731838800, 1731838860]),
                'series' =>
                    [
                        [
                            'name' => 'value',
                            'data' => SplFixedArray::fromArray([211, 211, 211, 211, 211]),
                        ],
                    ]
            ]
        ];

        $this->assertEquals($expected, $actual);
    }

    public function test_transform_with_warn_and_crit()
    {
        $input = $this->loadTestdata('test/testdata/hostalive.json');

        $actual = Transformer::transform($input);

        $expected = [
            [
                'title' => 'pl',
                'timestamps' => SplFixedArray::fromArray([1731848460,1731848520,1731848580,1731848640,1731848700]),
                'series' =>
                    [
                        [
                            'name' => 'value',
                            'data' => SplFixedArray::fromArray([0, 0, 0, 0, null]),
                        ],
                        [
                            'name' => 'warning',
                            'data' => SplFixedArray::fromArray([80, 80, 80, 80, null]),
                        ],
                        [
                            'name' => 'critical',
                            'data' => SplFixedArray::fromArray([100, 100, 100, 100, null]),
                        ],
                    ]
            ],
            [
                'title' => 'rta',
                'timestamps' => SplFixedArray::fromArray([1731848460,1731848520,1731848580,1731848640,1731848700]),
                'series' =>
                    [
                        [
                            'name' => 'value',
                            'data' => SplFixedArray::fromArray([7,5,8,7,null]),
                        ],
                        [
                            'name' => 'warning',
                            'data' => SplFixedArray::fromArray([3,3,3,3,null]),
                        ],
                        [
                            'name' => 'critical',
                            'data' => SplFixedArray::fromArray([5,5,5,5,null]),
                        ],
                    ]
            ]
        ];

        // var_dump($actual[1]['series'][2]);

        $this->assertEquals($expected, $actual);
    }

}
