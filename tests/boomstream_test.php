<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace filter_boomstream;

/**
 * Unit tests for the Boomstream filter (no calls to the Boomstream API are made).
 *
 * @package    filter_boomstream
 * @category   test
 * @copyright  2026 HWD LTD <support@boomstream.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \filter_boomstream\boomstream
 */
final class boomstream_test extends \advanced_testcase {
    /**
     * Text is returned untouched while the plugin is not configured.
     */
    public function test_unconfigured_filter_does_nothing(): void {
        $this->resetAfterTest();
        set_config('key', '', 'filter_boomstream');
        set_config('subscription', '', 'filter_boomstream');

        $text = '[boomstream media[Am0TlUow]]';
        $this->assertSame($text, (new boomstream())->filter($text));
    }

    /**
     * Text without Boomstream content is returned untouched.
     */
    public function test_unrelated_text_is_untouched(): void {
        $this->resetAfterTest();
        set_config('key', 'testkey', 'filter_boomstream');
        set_config('subscription', 'TESTSUBS', 'filter_boomstream');

        $text = '<p>Hello <iframe src="https://example.com/Am0TlUow"></iframe></p>';
        $this->assertSame($text, (new boomstream())->filter($text));
    }

    /**
     * Shortcodes are expanded into iframe and adaptive embeds.
     */
    public function test_expand_shortcodes(): void {
        $this->resetAfterTest();
        set_config('hostname', 'play.boomstream.net', 'filter_boomstream');

        $filter = new boomstream();
        $debug = '';
        $overrides = [];

        $html = $filter->expand_shortcodes('[boomstream media[Am0TlUow] size[800x450]]', $debug, $overrides);
        $this->assertSame('<iframe width="800" height="450" src="https://play.boomstream.net/Am0TlUow" ' .
            'frameborder="0" allowfullscreen></iframe>', $html);
        $this->assertSame([], $overrides);

        $html = $filter->expand_shortcodes(
            '[boomstream media[Am0TlUow] mode[adaptive] subscription[N5wLwvlW]]',
            $debug,
            $overrides
        );
        $this->assertStringContainsString('<div style="width:100%;">', $html);
        $this->assertStringContainsString('src="https://play.boomstream.net/Am0TlUow/config.jsonp"', $html);
        $this->assertStringContainsString('data-boomstream-code="Am0TlUow"', $html);
        $this->assertSame(['Am0TlUow' => 'N5wLwvlW'], $overrides);

        // In adaptive mode the size only limits the width, the height follows the video aspect ratio.
        $html = $filter->expand_shortcodes('[boomstream media[Am0TlUow] mode[adaptive] size[800x450]]', $debug, $overrides);
        $this->assertStringContainsString('<div style="width:100%;max-width:800px;">', $html);
        $this->assertStringNotContainsString('height', $html);
    }

    /**
     * Invalid shortcodes are left as they are.
     */
    public function test_invalid_shortcodes_are_kept(): void {
        $this->resetAfterTest();

        $filter = new boomstream();
        $debug = '';
        $overrides = [];

        $text = '[boomstream media[bad<code>] size[800x450]]';
        $this->assertSame($text, $filter->expand_shortcodes($text, $debug, $overrides));
        $text = '[boomstream media[Am0TlUow] subscription["><script>]]';
        $filter->expand_shortcodes($text, $debug, $overrides);
        $this->assertSame([], $overrides);
    }
}
