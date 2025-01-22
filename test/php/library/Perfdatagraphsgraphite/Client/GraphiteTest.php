<?php

namespace Tests\Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;
use Icinga\Application\Config;

use DateTime;

use PHPUnit\Framework\TestCase;

final class GraphiteTest extends TestCase
{
    public function test_loadconfig_withconfig()
    {
        $c = Config::fromArray(['general' => ['graphite_api_url' => 'http://foobar', 'graphite_api_timeout' => '123']]);
        $actual = Graphite::loadConfig($c);

        $expected = [
            'graphite_api_url' => 'http://foobar',
            'graphite_api_timeout' => '123'
        ];

        $this->assertEquals($expected, $actual);
    }

    public function test_loadconfig_withoutconfig()
    {
        $c = Config::fromArray(['no' => 'config']);
        $actual = Graphite::loadConfig($c);

        $expected = [
            'graphite_api_url' => 'http://localhost:8081',
            'graphite_api_timeout' => '10'
        ];

        $this->assertEquals($expected, $actual);
    }

    public function test_parseduration()
    {
        $now = new DateTime('1986-04-26 01:23:40');

        $actual = Graphite::parseDuration($now, 'PT1H');
        $expected = '00:23_19860426';
        $this->assertEquals($expected, $actual);

        $now = new DateTime('1986-04-26 01:23:40');

        $actual = Graphite::parseDuration($now, 'P1Y');
        $expected = '01:23_19850426';
        $this->assertEquals($expected, $actual);
    }

    public function test_parseduration_with_error()
    {
        $now = new DateTime('1986-04-26 01:23:40');

        $actual = Graphite::parseDuration($now, 'phpunit');
        $expected = '13:23_19860425';
        $this->assertEquals($expected, $actual);
    }

   public function test_sanitizepath()
   {
        $actual = Graphite::sanitizePath('hostname.internal.fqdn');
        $expected = 'hostname_internal_fqdn';
        $this->assertEquals($expected, $actual);

        $actual = Graphite::sanitizePath('disk /');
        $expected = 'disk__';
        $this->assertEquals($expected, $actual);

        $actual = Graphite::sanitizePath('/usr/share/local/foo');
        $expected = '_usr_share_local_foo';
        $this->assertEquals($expected, $actual);
   }
}
