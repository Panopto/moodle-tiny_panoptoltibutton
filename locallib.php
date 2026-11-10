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
 * Local library functions for the Panopto TinyMCE LTI button.
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Ensure the user is logged in, or perform a cross-site repost if cookies were blocked.
 *
 * @param int $courseid Course to require login for after repost succeeds.
 */
function tiny_panoptoltibutton_require_login_or_repost(int $courseid): void {
    global $PAGE, $_POST;

    $context = context_course::instance($courseid);
    $PAGE->set_pagelayout('popup');
    $PAGE->set_context($context);

    if (method_exists('panoptoblock_lti_utility', 'require_login_or_repost')) {
        panoptoblock_lti_utility::require_login_or_repost($courseid, $context);
        return;
    }

    if (!empty($_POST['repost'])) {
        unset($_POST['repost']);
    } else if (!isloggedin()) {
        header_remove('Set-Cookie');
        $output = $PAGE->get_renderer('mod_lti');
        $page = new \mod_lti\output\repost_crosssite_page($_SERVER['REQUEST_URI'], $_POST);
        echo $output->header();
        echo $output->render($page);
        echo $output->footer();
        exit;
    }

    require_login($courseid);
}

/**
 * Return the course of the page the embed is displayed on, read from the referer.
 *
 * The iframe's referer is the page that holds it. Recognised pages are a course page, a course
 * section page and any activity's view.php. Only referers on this site are read.
 *
 * @return int The course id, or 0 when the referer does not identify a course.
 */
function tiny_panoptoltibutton_get_referer_course_id(): int {
    global $CFG, $DB;

    // Get_local_referer() returns '' for any referer that is not on this site.
    $referer = get_local_referer(false);
    if (strpos($referer, $CFG->wwwroot . '/') !== 0) {
        return 0;
    }

    $path = strtok(substr($referer, strlen($CFG->wwwroot)), '?#');
    parse_str(parse_url($referer, PHP_URL_QUERY) ?? '', $query);

    // The is_scalar check guards against ?id[]=1, which would make clean_param throw.
    if (empty($query['id']) || !is_scalar($query['id'])) {
        return 0;
    }

    $id = clean_param($query['id'], PARAM_INT);
    if ($id <= 0) {
        return 0;
    }

    if ($path === '/course/view.php') {
        $courseid = $DB->get_field('course', 'id', ['id' => $id]);
    } else if ($path === '/course/section.php') {
        $courseid = $DB->get_field('course_sections', 'course', ['id' => $id]);
    } else if (preg_match('#^/mod/[a-z0-9_]+/view\.php$#', $path)) {
        $courseid = $DB->get_field('course_modules', 'course', ['id' => $id]);
    } else {
        $courseid = 0;
    }

    return (int) $courseid;
}

/**
 * Return the course a view.php launch is checked against, falling back to the site course.
 *
 * The course of the page showing the embed comes first, when the user can launch the embed
 * there. The course id stored in the embed markup is the course the embed was authored in, which
 * is wrong once the content is copied, imported or restored into another course. A user who only
 * views that page as a guest, without mod/lti:view, still launches against the stored course.
 * Every launch then has a course context for the capability check, including requests without
 * a course id or course referer.
 *
 * @return array The course record and its context.
 */
function tiny_panoptoltibutton_get_launch_course(): array {
    global $PAGE;

    $referercourseid = tiny_panoptoltibutton_get_referer_course_id();
    if ($referercourseid > 0) {
        $course = get_course($referercourseid);
        if (can_access_course($course, null, 'mod/lti:view', true)) {
            $context = context_course::instance($course->id);
            $PAGE->set_context($context);

            return [$course, $context];
        }
    }

    [$courseid, $course, $context] = \panoptoblock_lti_utility::resolve_launch_course();

    if (empty($courseid)) {
        $course = get_site();
        $context = context_course::instance($course->id);
    }

    return [$course, $context];
}

/**
 * Whether view.php may launch a tool type: it must be a configured tool on a Panopto server.
 *
 * @param stdClass $ltitype Record from the lti_types table.
 * @return bool
 */
function tiny_panoptoltibutton_is_launchable_tool(stdClass $ltitype): bool {
    return $ltitype->state == LTI_TOOL_STATE_CONFIGURED
        && panopto_is_allowed_panopto_host($ltitype->baseurl);
}
