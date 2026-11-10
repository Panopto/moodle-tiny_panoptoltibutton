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

/**
 * Regression tests for the TinyMCE Panopto wrapper page.
 *
 * The fragment parsers live in panoptowrapper.html because that page is served
 * statically. PHPUnit cannot execute them, so these tests lock the invariants
 * that stop the URL fragment from becoming HTML or an iframe target.
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_panoptoltibutton;

/**
 * Regression tests for the TinyMCE Panopto wrapper page.
 *
 * @package    tiny_panoptoltibutton
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class wrapper_test extends \advanced_testcase {
    /**
     * Load the wrapper HTML from the plugin directory.
     *
     * @return string
     */
    protected function wrapper_html(): string {
        global $CFG;

        return file_get_contents(
            $CFG->dirroot . '/lib/editor/tiny/plugins/panoptoltibutton/panoptowrapper.html'
        );
    }

    /**
     * Fragment error text must be inserted as text, not HTML.
     *
     * @coversNothing
     */
    public function test_wrapper_does_not_render_fragment_as_html(): void {
        $html = $this->wrapper_html();

        $this->assertStringContainsString("$('#errorcontainer').text(unprovisionederror)", $html);
        $this->assertStringNotContainsString("$('#errorcontainer').html(unprovisionederror)", $html);
    }

    /**
     * The content-item iframe must not be built from fragment-supplied wwwroot or path values.
     *
     * @coversNothing
     */
    public function test_wrapper_builds_iframe_from_page_location(): void {
        $html = $this->wrapper_html();

        $this->assertStringContainsString('function getMoodleRoot()', $html);
        $this->assertStringContainsString("var CONTENT_ITEM_PATH = PLUGIN_PATH + 'contentitem.php'", $html);
        $this->assertStringContainsString('moodleRoot + CONTENT_ITEM_PATH', $html);
        $this->assertStringNotContainsString("getHashValue('wwwroot')", $html);
        $this->assertStringNotContainsString("getHashValue('contentitempath')", $html);
    }

    /**
     * Course id, tool id and resource base read from the fragment must be constrained.
     *
     * @coversNothing
     */
    public function test_wrapper_validates_fragment_values(): void {
        $html = $this->wrapper_html();

        $this->assertStringContainsString('function getPositiveIntHashValue(key)', $html);
        $this->assertStringContainsString('function getAlphanumericHashValue(key)', $html);
        $this->assertStringContainsString('function getToolHashValue()', $html);
        $this->assertStringContainsString('var courseid = getPositiveIntHashValue(\'courseid\')', $html);
        $this->assertStringContainsString('tool = getToolHashValue()', $html);
        $this->assertStringContainsString('resourceBase = getAlphanumericHashValue(\'resourcebase\')', $html);
    }

    /**
     * Thumbnail URLs in inserted markup must be quoted so a space cannot break out of src.
     *
     * @coversNothing
     */
    public function test_thumbnail_src_is_quoted_in_placement_template(): void {
        global $CFG;

        $js = file_get_contents(
            $CFG->dirroot . '/lib/editor/tiny/plugins/panoptoltibutton/js/panopto_tinymce_panoptoltibutton.js'
        );

        $this->assertStringContainsString('<img src="{{thumbnailId}}"', $js);
        $this->assertStringNotContainsString('<img src={{thumbnailId}}', $js);
    }

    /**
     * The return page checks the sesskey, the signature, and script escaping.
     *
     * @coversNothing
     */
    public function test_contentitem_return_requires_sesskey_and_escapes_json(): void {
        global $CFG;

        $src = file_get_contents(
            $CFG->dirroot . '/lib/editor/tiny/plugins/panoptoltibutton/contentitem_return.php'
        );

        $this->assertStringContainsString('require_sesskey()', $src);
        $this->assertStringContainsString('lti_verify_oauth_signature(', $src);
        $this->assertStringContainsString("throw new moodle_exception('errorinvalidsignature', 'tiny_panoptoltibutton')", $src);
        $this->assertStringContainsString(
            'JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT',
            $src
        );
    }
}
