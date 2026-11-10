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
 * This file launches LTI-tools enabled to be launched from a rich text editor
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2023 Panopto
 * @author     Panopto with contributions from Adis Hrnjica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// @codingStandardsIgnoreLine
global $DB, $CFG, $COURSE;
if (empty($CFG)) {
    // @codingStandardsIgnoreLine
    require_once(dirname(__FILE__) . '/../../../../../config.php');
}

require_once($CFG->dirroot . '/blocks/panopto/lib/block_panopto_lib.php');
require_once($CFG->libdir . '/accesslib.php'); // Access control functions.
require_once($CFG->dirroot . '/mod/lti/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php');
require_once(dirname(__FILE__) . '/locallib.php');

$contenturl = optional_param('contenturl', '', PARAM_URL);

// Allow an empty content URL. Reject any other unknown host.
$contentverified = empty($contenturl) || panopto_is_allowed_panopto_host($contenturl);

if ($contentverified) {
    $resourcelinkid = required_param('resourcelinkid', PARAM_ALPHANUMEXT);
    $ltitypeid = required_param('ltitypeid', PARAM_INT);
    $customdata = optional_param('custom', '', PARAM_RAW_TRIMMED);

    // Without a course, this falls back to the site course so the capability check always runs.
    [$course, $context] = tiny_panoptoltibutton_get_launch_course();
    $courseid = $course->id;

    // Authenticate before the LTI 1.3 branch, which exits.
    require_login($course, true);
    require_capability('mod/lti:view', $context);

    // Only launch a configured Panopto tool, so the type id cannot launch any other site tool.
    $ltitype = $DB->get_record('lti_types', ['id' => $ltitypeid], '*', MUST_EXIST);
    if (!tiny_panoptoltibutton_is_launchable_tool($ltitype)) {
        throw new moodle_exception('invalid_lti_tool', 'tiny_panoptoltibutton');
    }

    $lti = new stdClass();
    $lti->course = $courseid;

    $lti->id = $resourcelinkid;
    $lti->typeid = $ltitypeid;
    $lti->launchcontainer = LTI_LAUNCH_CONTAINER_WINDOW;
    $lti->toolurl = $contenturl;
    $lti->custom = \panoptoblock_lti_utility::filter_launch_custom_parameters(
        $customdata,
        'tiny_panoptoltibutton'
    );
    $lti->instructorcustomparameters = [];
    $lti->debuglaunch = false;

    // LTI 1.3 login request.
    $config = lti_get_type_type_config($ltitypeid);
    if ($config->lti_ltiversion === LTI_VERSION_1P3) {
        if (!isset($SESSION->lti_initiatelogin_status)) {
            echo lti_initiate_login(
                $courseid,
                \panoptoblock_lti_utility::build_playback_launch_hint(
                    'tiny_panoptoltibutton',
                    '',
                    $ltitypeid,
                    $resourcelinkid,
                    $contenturl,
                    $lti->custom
                ),
                $lti,
                $config
            );
            exit;
        } else {
            unset($SESSION->lti_initiatelogin_status);
        }
    }

    // Set the platform-owned flag the allowlist omits.
    $lti->custom->grading_not_supported = true;

    echo \panoptoblock_lti_utility::launch_tool($lti);
} else {
    echo get_string('invalid_content_host', 'tiny_panoptoltibutton');
}
