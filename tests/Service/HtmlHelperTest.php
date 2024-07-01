<?php

namespace App\Tests\Service;

use App\Service\HtmlHelper;
use PHPUnit\Framework\TestCase;

final class HtmlHelperTest extends TestCase
{
    private HtmlHelper $helper;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->helper = new HtmlHelper();
    }

    /**
     * @dataProvider dataGetSection
     */
    public function testGetSection(
        string $html,
        string $title,
        ?string $tagName,
        ?bool $useRegex,
        ?bool $includeHeading,
        ?string $expected
    ): void {
        $actual = $this->helper->getSection($html, $title, tagName: $tagName ?? 'h3', useRegex: $useRegex ?? false,
            includeHeading: $includeHeading ?? false);
        $this->assertSame($expected, $actual);
    }

    public function testElement2separatorMulti(): void
    {
        $html = '<p><strong>Titel</strong>: Tryk af viftekort LIV guide<br><strong>Produkt</strong><br>viftekort- som ledetrådene- lille lommeformat: 200 stk.<br><strong>Kommentar til opgaven</strong>: Fil er uden skæremærker. Efter opsætning hos jer, send gerne retur til godkendelse. <br><strong>Uploadede filer</strong><br>ny-liv-viftekort-til-tryk-2.0.pdf (OK)</p>';

        $actual = $html;
        foreach ([
            'p' => ['', '; '],
            'strong' => ['; ', ': '],
        ] as $elementName => [$start, $end]) {
            $actual = $this->helper->element2separator($actual, $elementName, $start, $end);
        }

        $expected = 'Titel: : Tryk af viftekort LIV guide<br>; Produkt: <br>viftekort- som ledetrådene- lille lommeformat: 200 stk.<br>; Kommentar til opgaven: : Fil er uden skæremærker. Efter opsætning hos jer, send gerne retur til godkendelse. <br>; Uploadede filer: <br>ny-liv-viftekort-til-tryk-2.0.pdf (OK)';

        $this->assertEquals($expected, $actual);
    }

    /**
     * @dataProvider dataElement2separator
     */
    public function testElement2separator(
        string $html,
        string $elementName,
        string $start,
        string $end,
        string $expected
    ): void {
        $actual = $this->helper->element2separator($html, $elementName, $start, $end);
        $this->assertSame($expected, $actual);
    }

    /**
     * Provider for testElement2separator.
     */
    public static function dataElement2separator(): iterable
    {
        yield [
            '<p><strong>xxx</strong>xxx<br /><strong>xxx</strong><br />xxx<br />xxx<br />xxx<br /><strong>xxx</strong>xxx<br /><br />xxx<br />xxx<br />xxx<br /><br />xxx<br />xxx<br />xxx<br />xxx<br /><br />xxx<br /><br />xxx<br /><strong>xxx</strong><br />xxx<br />xxx<br />xxx</p>',
            'strong',
            '',
            '',
            '<p>xxxxxx<br />xxx<br />xxx<br />xxx<br />xxx<br />xxxxxx<br /><br />xxx<br />xxx<br />xxx<br /><br />xxx<br />xxx<br />xxx<br />xxx<br /><br />xxx<br /><br />xxx<br />xxx<br />xxx<br />xxx<br />xxx</p>',
        ];

        yield [
            '<p><strong>xxx</strong>xxx<br /><strong>xxx</strong><br />xxx<br />xxx<br />xxx<br /><strong>xxx</strong>xxx<br /><br />xxx<br />xxx<br />xxx<br /><br />xxx<br />xxx<br />xxx<br />xxx<br /><br />xxx<br /><br />xxx<br /><strong>xxx</strong><br />xxx<br />xxx<br />xxx</p>',
            'strong',
            '',
            ': ',
            '<p>xxx: xxx<br />xxx: <br />xxx<br />xxx<br />xxx<br />xxx: xxx<br /><br />xxx<br />xxx<br />xxx<br /><br />xxx<br />xxx<br />xxx<br />xxx<br /><br />xxx<br /><br />xxx<br />xxx: <br />xxx<br />xxx<br />xxx</p>',
        ];

        yield [
            '<p><strong>xxx</strong>xxx<br /><strong>xxx</strong><br />xxx<br />xxx<br />xxx<br /><strong>xxx</strong>xxx<br /><br />xxx<br />xxx<br />xxx<br /><br />xxx<br />xxx<br />xxx<br />xxx<br /><br />xxx<br /><br />xxx<br /><strong>xxx</strong><br />xxx<br />xxx<br />xxx</p>',
            'br',
            '; ',
            '',
            '<p><strong>xxx</strong>xxx; <strong>xxx</strong>; xxx; xxx; xxx; <strong>xxx</strong>xxx; xxx; xxx; xxx; xxx; xxx; xxx; xxx; xxx; xxx; <strong>xxx</strong>; xxx; xxx; xxx</p>',
        ];

        yield [
            '<p><strong>xxx</strong>xxx<br><strong>xxx</strong><br>xxx<br>xxx<br>xxx<br><strong>xxx</strong>xxx<br><br>xxx<br>xxx<br>xxx<br><br>xxx<br>xxx<br>xxx<br>xxx<br><br>xxx<br><br>xxx<br><strong>xxx</strong><br>xxx<br>xxx<br>xxx</p>',
            'br',
            '; ',
            '',
            '<p><strong>xxx</strong>xxx; <strong>xxx</strong>; xxx; xxx; xxx; <strong>xxx</strong>xxx; xxx; xxx; xxx; xxx; xxx; xxx; xxx; xxx; xxx; <strong>xxx</strong>; xxx; xxx; xxx</p>',
        ];
    }

    /**
     * Provider for testGetSection.
     *
     * @return iterable
     */
    public static function dataGetSection(): iterable
    {
        yield [
            '<h3>Profil</h3>
<p><strong>Navn</strong>: Test Testersen</p>

<h3>Opgavebeskrivelse</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Betaling</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Levering</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Interne</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
',
            'Opgavebeskrivelse',
            null,
            null,
            null,

            '
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

',
        ];

        yield [
            '<h3>Profil</h3>
<p><strong>Navn</strong>: Test Testersen</p>

<h3>Opgavebeskrivelse</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Betaling</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Levering</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Interne</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
',
            'Hest',
            null,
            null,
            null,

            null,
        ];

        yield [
            '<h3>Profil</h3>
<p><strong>Navn</strong>: Test Testersen</p>

<h3>Opgavebeskrivelse</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Betaling</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Levering</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

<h3>Interne</h3>
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>
',
            '/beskrivelse/',
            null,
            true,
            null,

            '
<p><strong>Titel</strong>: Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.</p>

',
        ];
    }
}
