<?php

namespace Tests\Icinga\Module\Perfdatagraphsgraphite\ProvidedHook\Graphs;

use Icinga\Module\Perfdatagraphsgraphite\ProvidedHook\Perfdatagraphs\PerfdataSource;

use PHPUnit\Framework\TestCase;

final class PerfdataSourceTest extends TestCase
{
    public function test_getName()
    {
        $pfs = new PerfdataSource();

        $actual = $pfs->getName();
        $expected = 'Graphite';

        $this->assertEquals($expected, $actual);
    }
}
