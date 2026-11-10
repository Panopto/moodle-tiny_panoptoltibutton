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
 * Unit tests for Tiny Panopto LTI plugininfo.
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_panoptoltibutton;

/**
 * Unit tests for Tiny Panopto LTI plugininfo.
 *
 * @package    tiny_panoptoltibutton
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class plugininfo_test extends \advanced_testcase {
    /**
     * Skip when plugininfo.php would fatal on a missing block_panopto require.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();

        $utility = $CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php';
        if (!file_exists($utility)) {
            $this->markTestSkipped('block_panopto is not installed');
        }
    }

    /**
     * Build the editor configuration array for a user in a course.
     *
     * @param \stdClass $course the course
     * @param \stdClass $user the user to become
     * @return array plugin configuration
     */
    protected function configuration_for(\stdClass $course, \stdClass $user): array {
        global $COURSE, $PAGE;

        $this->setUser($user);
        $COURSE = $course;
        $context = \context_course::instance($course->id);
        $PAGE->set_course($course);
        $PAGE->set_context($context);
        $PAGE->set_url(new \moodle_url('/course/view.php', ['id' => $course->id]));

        return plugininfo::get_plugin_configuration_for_context($context, [], []);
    }

    /**
     * Install a Panopto LTI tool that get_course_tool() selects for a course.
     *
     * @return int tool type id
     */
    protected function create_matching_panopto_tool(): int {
        global $CFG;

        require_once($CFG->dirroot . '/mod/lti/locallib.php');

        $server = 'test.panopto.example';
        $toolurl = 'https://' . $server . '/Panopto/LTI/LTI.aspx';
        set_config('automatic_operation_target_server', $server, 'block_panopto');

        // The tool domain and custom parameters are what get_course_tool() matches on.
        return (int) lti_add_type(
            (object) [
                'name' => 'Panopto test tool',
                'baseurl' => $toolurl,
                'state' => LTI_TOOL_STATE_CONFIGURED,
            ],
            (object) [
                'lti_toolurl' => $toolurl,
                'lti_password' => 'secret-password',
                'lti_resourcekey' => 'resource-key',
                'lti_customparameters' => 'panopto_course_embed_tool=1',
            ]
        );
    }

    /**
     * Configuration includes the course id and omits the tool secrets.
     *
     * @covers \tiny_panoptoltibutton\plugininfo::get_plugin_configuration_for_context
     */
    public function test_configuration_includes_course_and_omits_secrets(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $typeid = $this->create_matching_panopto_tool();

        // Confirm the password and service salt were stored.
        $stored = $DB->get_records_menu('lti_types_config', ['typeid' => $typeid], '', 'name, value');
        $this->assertSame('secret-password', $stored['password']);
        $this->assertNotEmpty($stored['servicesalt']);

        $user = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $config = $this->configuration_for($course, $user);

        $this->assertSame((int) $course->id, (int) $config['courseid']);
        $this->assertSame($typeid, (int) $config['tool']->id);
        $this->assertIsArray($config['tool']->config);
        $this->assertArrayHasKey('resourcekey', $config['tool']->config);
        $this->assertArrayNotHasKey('password', $config['tool']->config);
        $this->assertArrayNotHasKey('servicesalt', $config['tool']->config);
    }

    /**
     * Every granted role receives the tool.
     *
     * @covers \tiny_panoptoltibutton\plugininfo::get_plugin_configuration_for_context
     */
    public function test_configuration_gives_tool_to_granted_roles(): void {
        $this->resetAfterTest();

        $toolid = $this->create_matching_panopto_tool();
        $course = $this->getDataGenerator()->create_course();

        foreach (['student', 'teacher', 'editingteacher', 'manager'] as $archetype) {
            $user = $this->getDataGenerator()->create_and_enrol($course, $archetype);
            $config = $this->configuration_for($course, $user);

            $this->assertSame(
                $toolid,
                (int) ($config['tool']->id ?? 0),
                "the {$archetype} role should hold tiny/panoptoltibutton:use"
            );
        }
    }

    /**
     * A configured tool is returned only to users who hold tiny/panoptoltibutton:use.
     *
     * @covers \tiny_panoptoltibutton\plugininfo::get_plugin_configuration_for_context
     */
    public function test_configuration_withholds_tool_without_capability(): void {
        $this->resetAfterTest();

        $toolid = $this->create_matching_panopto_tool();
        $course = $this->getDataGenerator()->create_course();

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $allowed = $this->configuration_for($course, $teacher);
        $this->assertObjectHasProperty('id', $allowed['tool']);
        $this->assertSame($toolid, (int) $allowed['tool']->id);

        // A custom role has no archetypes.
        $this->getDataGenerator()->create_role(['shortname' => 'nopanoptotiny']);
        $user = $this->getDataGenerator()->create_and_enrol($course, 'nopanoptotiny');
        $denied = $this->configuration_for($course, $user);

        $this->assertObjectNotHasProperty('id', $denied['tool']);
    }

    /**
     * The editor is told to save marker links where filter_panopto is on.
     *
     * @covers \tiny_panoptoltibutton\plugininfo::get_plugin_configuration_for_context
     */
    public function test_configuration_flags_active_panopto_filter(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        filter_set_global_state('panopto', TEXTFILTER_ON);
        $this->assertTrue($this->configuration_for($course, $user)['panoptofilteractive']);

        // Off but available on the site, and turned on in the course.
        filter_set_global_state('panopto', TEXTFILTER_OFF);
        filter_set_local_state('panopto', \context_course::instance($course->id)->id, TEXTFILTER_ON);
        $this->assertTrue($this->configuration_for($course, $user)['panoptofilteractive']);
    }

    /**
     * The editor keeps saving iframes where filter_panopto is off.
     *
     * @covers \tiny_panoptoltibutton\plugininfo::get_plugin_configuration_for_context
     */
    public function test_configuration_does_not_flag_inactive_panopto_filter(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');

        filter_set_global_state('panopto', TEXTFILTER_DISABLED);
        $this->assertFalse($this->configuration_for($course, $user)['panoptofilteractive']);

        // On for the site, and turned off in the course.
        filter_set_global_state('panopto', TEXTFILTER_ON);
        filter_set_local_state('panopto', \context_course::instance($course->id)->id, TEXTFILTER_OFF);
        $this->assertFalse($this->configuration_for($course, $user)['panoptofilteractive']);
    }
}
