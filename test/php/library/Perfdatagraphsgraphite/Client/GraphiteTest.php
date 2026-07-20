<?php

namespace Tests\Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;

use DateTime;

use PHPUnit\Framework\TestCase;

final class GraphiteTest extends TestCase
{
    public function test_parseduration()
    {
        $now = new DateTime('1986-04-26 01:23:40');

        $actual = Graphite::parseDuration($now, 'PT1H');
        $expected = '514859020';
        $this->assertEquals($expected, $actual);

        $now = new DateTime('1986-04-26 01:23:40');

        $actual = Graphite::parseDuration($now, 'P1Y');
        $expected = '483326620';
        $this->assertEquals($expected, $actual);
    }

    public function test_parseduration_with_error()
    {
        $now = new DateTime('1986-04-26 01:23:40');

        $actual = Graphite::parseDuration($now, 'phpunit');
        $expected = '514819420';
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

    public function test_filterMetrics()
    {
        $c = new Graphite(
            baseURI: 'base',
            timeout: 10,
            tlsVerify: false,
            maxDataPoints: 1000,
            hostNameTemplate: 'icinga2.$host.name$.host.$host.check_command$',
            serviceNameTemplate: 'icinga2.$host.name$.services.$service.name$.$service.check_command$',
            auth: [],
        );

        $actual = $c->filterMetrics(
            ['foo', 'bar', 'foobar', 'barfoo', 'uptime', 'excludeme'],
            ['uptime', 'foo*'],
            ['bar*', 'excludeme']
        );

        $expected = [0 => 'foo', 2 => 'foobar', 4 => 'uptime'];
        $this->assertEquals($expected, $actual);
    }

    public function test_filterMetrics_withExcludeOnly()
    {
        $c = new Graphite(
            baseURI: 'base',
            timeout: 10,
            tlsVerify: false,
            maxDataPoints: 1000,
            hostNameTemplate: 'icinga2.$host.name$.host.$host.check_command$',
            serviceNameTemplate: 'icinga2.$host.name$.services.$service.name$.$service.check_command$',
            auth: [],
        );

        $actual = $c->filterMetrics(
            ['exclude', 'include', 'excludealso', 'removeme'],
            [],
            ['exclude*', 'removeme']
        );

        $expected = [1 => 'include'];
        $this->assertEquals($expected, $actual);
    }

    public function test_filterMetrics_withIncludeOnly()
    {
        $c = new Graphite(
            baseURI: 'base',
            timeout: 10,
            tlsVerify: false,
            maxDataPoints: 1000,
            hostNameTemplate: 'icinga2.$host.name$.host.$host.check_command$',
            serviceNameTemplate: 'icinga2.$host.name$.services.$service.name$.$service.check_command$',
            auth: [],
        );

        $actual = $c->filterMetrics(
            ['exclude', 'include', 'excludealso', 'includeme', 'hi'],
            ['include*', 'hi'],
            []
        );

        $expected = [1 => 'include', 3 => 'includeme', 4 => 'hi'];
        $this->assertEquals($expected, $actual);
    }
}
