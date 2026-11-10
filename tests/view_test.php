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
 * Unit tests for the view.php launch checks.
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tiny_panoptoltibutton;

/**
 * Unit tests for the view.php launch checks.
 *
 * @package    tiny_panoptoltibutton
 * @category   test
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class view_test extends \advanced_testcase {
    /**
     * Load the helpers, or skip when block_panopto is missing.
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

        require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');
        require_once($utility);
        require_once($CFG->dirroot . '/mod/lti/locallib.php');
        require_once($CFG->dirroot . '/lib/editor/tiny/plugins/panoptoltibutton/locallib.php');
    }

    /**
     * Clear the course parameter a test may have set.
     *
     * @return void
     */
    protected function tearDown(): void {
        unset($_GET['course'], $_SERVER['HTTP_REFERER']);
        parent::tearDown();
    }

    /**
     * A launch without a course is checked against the site course, which a student cannot pass.
     *
     * @covers ::tiny_panoptoltibutton_get_launch_course
     */
    public function test_launch_without_course_requires_capability_on_site_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        [$launchcourse, $context] = tiny_panoptoltibutton_get_launch_course();

        $this->assertEquals(SITEID, $launchcourse->id);
        $this->assertEquals(\context_course::instance(SITEID)->id, $context->id);

        $this->setAdminUser();
        require_capability('mod/lti:view', $context);

        $this->setUser($student);
        $this->expectException(\required_capability_exception::class);
        require_capability('mod/lti:view', $context);
    }

    /**
     * A launch with a course id is checked against that course.
     *
     * @covers ::tiny_panoptoltibutton_get_launch_course
     */
    public function test_launch_with_course_uses_that_course(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $outsider = $this->getDataGenerator()->create_user();
        $_GET['course'] = $course->id;

        [$launchcourse, $context] = tiny_panoptoltibutton_get_launch_course();

        $this->assertEquals($course->id, $launchcourse->id);
        $this->assertEquals(\context_course::instance($course->id)->id, $context->id);
        $this->assertTrue(has_capability('mod/lti:view', $context, $student));
        $this->assertFalse(has_capability('mod/lti:view', $context, $outsider));
    }

    /**
     * Content copied from another course launches against the course of the page showing it,
     * for a student enrolled only there.
     *
     * @covers ::tiny_panoptoltibutton_get_launch_course
     * @covers ::tiny_panoptoltibutton_get_referer_course_id
     */
    public function test_launch_uses_course_of_referring_activity(): void {
        global $CFG;

        $this->resetAfterTest();

        $original = $this->getDataGenerator()->create_course();
        $copy = $this->getDataGenerator()->create_course();
        $page = $this->getDataGenerator()->create_module('page', ['course' => $copy->id]);
        $this->setUser($this->getDataGenerator()->create_and_enrol($copy, 'student'));
        $_GET['course'] = $original->id;
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;

        [$launchcourse, $context] = tiny_panoptoltibutton_get_launch_course();

        $this->assertEquals($copy->id, $launchcourse->id);
        $this->assertEquals(\context_course::instance($copy->id)->id, $context->id);
    }

    /**
     * A user who views the page's course as a guest, which gives no mod/lti:view, launches against
     * the course stored in the embed, where they are enrolled.
     *
     * @covers ::tiny_panoptoltibutton_get_launch_course
     */
    public function test_launch_falls_back_to_stored_course_for_guest_access(): void {
        global $CFG, $USER;

        $this->resetAfterTest();

        $original = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $this->setUser($this->getDataGenerator()->create_and_enrol($original, 'student'));

        // Guest access to the other course, as enrol_guest grants it.
        $othercontext = \context_course::instance($other->id);
        load_temp_course_role($othercontext, $CFG->guestroleid);
        $USER->enrol['tempguest'][$other->id] = time() + HOURSECS;
        $this->assertTrue(can_access_course($other));
        $this->assertFalse(has_capability('mod/lti:view', $othercontext));

        $_GET['course'] = $original->id;
        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . $other->id;

        [$launchcourse, $context] = tiny_panoptoltibutton_get_launch_course();

        $this->assertEquals($original->id, $launchcourse->id);
        $this->assertTrue(has_capability('mod/lti:view', $context));
    }

    /**
     * Course and section pages identify the course too.
     *
     * @covers ::tiny_panoptoltibutton_get_referer_course_id
     */
    public function test_referer_course_from_course_and_section_pages(): void {
        global $CFG, $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course(['numsections' => 1]);
        $sectionid = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 1]);

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/view.php?id=' . $course->id;
        $this->assertEquals($course->id, tiny_panoptoltibutton_get_referer_course_id());

        $_SERVER['HTTP_REFERER'] = $CFG->wwwroot . '/course/section.php?id=' . $sectionid;
        $this->assertEquals($course->id, tiny_panoptoltibutton_get_referer_course_id());
    }

    /**
     * Referers that do not identify a course on this site are ignored.
     *
     * @covers ::tiny_panoptoltibutton_get_referer_course_id
     */
    public function test_referer_course_ignores_unknown_referers(): void {
        global $CFG;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $referers = [
            'https://evil.example/course/view.php?id=' . $course->id,
            $CFG->wwwroot . '.evil.example/course/view.php?id=' . $course->id,
            $CFG->wwwroot . '/my/index.php?id=' . $course->id,
            $CFG->wwwroot . '/course/view.php?id[]=' . $course->id,
            $CFG->wwwroot . '/course/view.php?id=999999',
            $CFG->wwwroot . '/course/view.php',
        ];

        foreach ($referers as $referer) {
            $_SERVER['HTTP_REFERER'] = $referer;
            $this->assertSame(0, tiny_panoptoltibutton_get_referer_course_id(), $referer);
        }
    }

    /**
     * Only configured tools on a Panopto server can be launched.
     *
     * @covers ::tiny_panoptoltibutton_is_launchable_tool
     */
    public function test_only_configured_panopto_tools_are_launchable(): void {
        $this->resetAfterTest();

        set_config('automatic_operation_target_server', 'test.panopto.example', 'block_panopto');
        $panoptourl = 'https://test.panopto.example/Panopto/LTI/LTI.aspx';

        $this->assertTrue(tiny_panoptoltibutton_is_launchable_tool(
            (object) ['state' => LTI_TOOL_STATE_CONFIGURED, 'baseurl' => $panoptourl]
        ));
        $this->assertFalse(tiny_panoptoltibutton_is_launchable_tool(
            (object) ['state' => LTI_TOOL_STATE_CONFIGURED, 'baseurl' => 'https://othertool.example/launch']
        ));
        $this->assertFalse(tiny_panoptoltibutton_is_launchable_tool(
            (object) ['state' => LTI_TOOL_STATE_PENDING, 'baseurl' => $panoptourl]
        ));
    }

    /**
     * View.php must run both checks before it launches.
     *
     * @coversNothing
     */
    public function test_view_runs_launch_checks(): void {
        global $CFG;

        $src = file_get_contents($CFG->dirroot . '/lib/editor/tiny/plugins/panoptoltibutton/view.php');

        $this->assertStringContainsString('tiny_panoptoltibutton_get_launch_course()', $src);
        $this->assertStringContainsString("require_capability('mod/lti:view', \$context)", $src);
        $this->assertStringContainsString('tiny_panoptoltibutton_is_launchable_tool($ltitype)', $src);
        $this->assertStringNotContainsString('require_login();', $src);
    }

    /**
     * The editor must not override core's iframe sandboxing setting.
     *
     * @coversNothing
     */
    public function test_configuration_does_not_override_iframe_sandboxing(): void {
        global $CFG;

        $amd = $CFG->dirroot . '/lib/editor/tiny/plugins/panoptoltibutton/amd';

        $this->assertStringNotContainsString('sandbox_iframes', file_get_contents($amd . '/src/configuration.js'));
        $this->assertStringNotContainsString('sandbox_iframes', file_get_contents($amd . '/build/configuration.min.js'));
    }
}
