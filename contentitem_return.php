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
 * This script handles the content item return process.
 *
 * It processes LTI content item returns, validates the response, and either
 * triggers an error callback or dispatches the content items to the parent window.
 *
 * @package    tiny_panoptoltibutton
 * @copyright  2025 Panopto
 * @author     Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:ignore moodle.Files.RequireLogin.Missing -- Login is enforced via tiny_panoptoltibutton_require_login_or_repost() below.
require_once(dirname(__FILE__) . '/../../../../../config.php');
require_once(dirname(__FILE__) . '/locallib.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/panopto_data.php');
require_once($CFG->dirroot . '/blocks/panopto/lib/lti/panoptoblock_lti_utility.php');
require_once($CFG->dirroot . '/mod/lti/lib.php');
require_once($CFG->dirroot . '/mod/lti/locallib.php');

$courseid = required_param('course', PARAM_INT);
$id = required_param('id', PARAM_INT);
$callback = required_param('callback', PARAM_ALPHANUMEXT);
$jwt = optional_param('JWT', '', PARAM_RAW);

tiny_panoptoltibutton_require_login_or_repost($courseid);

require_sesskey();

$context = context_course::instance($courseid);

// Use the same capability gate as contentitem.php.
require_capability('tiny/panoptoltibutton:use', $context);

$config = lti_get_type_type_config($id);
$islti1p3 = $config->lti_ltiversion === LTI_VERSION_1P3;

// Require a signed LTI return.
if ($islti1p3) {
    if ($jwt === '') {
        throw new moodle_exception('errorinvalidsignature', 'tiny_panoptoltibutton');
    }
    $params = lti_convert_from_jwt($id, $jwt);
    $items = $params['content_items'] ?? '';
} else {
    $consumerkey = required_param('oauth_consumer_key', PARAM_RAW);
    $items = optional_param('content_items', '', PARAM_RAW_TRIMMED);
    lti_verify_oauth_signature($id, $consumerkey);
}

$contentitems = json_decode($items);

$errors = [];

if (!is_object($contentitems) && !is_array($contentitems)) {
    $errors[] = 'invalidjson';
}

// Reshape LTI 1.3 content only when the payload decoded.
if ($islti1p3 && empty($errors)) {
    $graphitem = $contentitems->{'@graph'}[0] ?? null;
    $placementadvice = $graphitem->placementAdvice ?? null;

    $doctarget = !empty($placementadvice->presentationDocumentTarget)
        ? $placementadvice->presentationDocumentTarget
        : (!empty($graphitem->iframe) ? 'iframe' : 'frame');

    $thumbnail = $graphitem->thumbnail ?? null;

    if ($doctarget == 'iframe' && !empty($thumbnail)) {
        // Create placementAdvice when the iframe fallback left it null.
        if (is_null($placementadvice)) {
            $placementadvice = new stdClass();
            $graphitem->placementAdvice = $placementadvice;
        }

        // These changes land on the payload.
        $placementadvice->presentationDocumentTarget = 'frame';
        $placementadvice->windowTarget = '_blank';
        $graphitem->{'@type'} = 'ContentItem';
        $graphitem->mediaType = 'text/html';
    }
}

// Provision the course for LTI.
\panopto_data::provision_course_for_lti($courseid);
?>

<script type="text/javascript">
    <?php
    /**
     * Check for errors and handle accordingly.
     *
     * @package    tiny_panoptoltibutton
     * @copyright  2025 Panopto
     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
     */
    if (count($errors) > 0) : ?>
        /**
         * Trigger the handleError callback with the errors.
         *
         * @package    tiny_panoptoltibutton
         * @copyright  2025 Panopto
         * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
         */
        parent.document.CALLBACKS.handleError(<?php echo json_encode($errors); ?>);
        <?php
        /**
         * Handle successful content item return.
         *
         * @package    tiny_panoptoltibutton
         * @copyright  2025 Panopto
         * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
         */
    else : ?>
        /**
         * Trigger the handleContent callback with the content items.
         *
         * @package    tiny_panoptoltibutton
         * @copyright  2025 Panopto
         * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
         */
        parent.document.CALLBACKS.
                <?php
                /**
                 * Trigger the handleContent callback with the content items.
                 *
                 * @package    tiny_panoptoltibutton
                 * @copyright  2025 Panopto
                 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
                 */
                echo $callback ?>(
                    <?php
                    /**
                     * Escape script delimiters.
                     *
                     * @package    tiny_panoptoltibutton
                     * @copyright  2025 Panopto
                     * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
                     */
                    echo json_encode($contentitems, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>);
        <?php
        /**
         * End of if-else statement.
         *
         * @package    tiny_panoptoltibutton
         * @copyright  2025 Panopto
         * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
         */
    endif; ?>
</script>
