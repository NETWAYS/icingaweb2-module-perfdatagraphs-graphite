<?php

namespace Tests\Icinga\Module\Perfdatagraphsgraphite\Client;

use Icinga\Module\Perfdatagraphsgraphite\Client\Graphite;

use DateTime;

use PHPUnit\Framework\TestCase;

final class GraphiteTest extends TestCase
{
    public function test_parsetemplate()
    {
        $c = new Graphite('base', 'user', 'passed', 10, false, 'icinga2.$host.name$.host.$host.check_command$', 'icinga2.$host.name$.services.$service.name$.$service.check_command$');

        $actual = $c->parseTemplate('myhost', 'myservice', 'checkme', false, 'foobar');
        $expected = 'icinga2.myhost.services.myservice.checkme.perfdata.foobar';
        $this->assertEquals($expected, $actual);

        $actual = $c->parseTemplate('myhost', 'hostalive', 'checkme', true, '*');
        $expected = 'icinga2.myhost.host.checkme.perfdata.*';
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
