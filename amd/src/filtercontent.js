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
 * Saves Panopto videos as filter_panopto marker links.
 *
 * Moodle removes iframes when it cleans text, including before it shows a forum post for editing
 * again. Where filter_panopto is active, Panopto iframes are saved as marker links, which Moodle
 * keeps and the filter shows as videos. While editing, the markers are shown as iframes again.
 *
 * @module     tiny_panoptoltibutton/filtercontent
 * @copyright  2026 Panopto
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';
import {get_string as getString} from 'core/str';
import {component} from './common';
import {getTool, isPanoptoFilterActive} from './options';

// Class of the links that filter_panopto shows as videos.
const markerClass = 'panopto-embed';

// Iframe attributes and the marker link parameters that keep them.
const dimensions = {width: 'displaywidth', height: 'displayheight'};

// A width or height that filter_panopto accepts, in pixels.
const dimensionPattern = /^[1-9][0-9]{0,3}$/;

/**
 * Parse a URL, returning it only if it opens the Panopto launch page of this site.
 *
 * The scheme is not compared, as filter_panopto does not compare it.
 *
 * @param {string} value
 * @returns {URL|null}
 */
const getLaunchUrl = (value) => {
    if (!value) {
        return null;
    }

    const launchUrl = new URL(`${Config.wwwroot}/lib/editor/tiny/plugins/panoptoltibutton/view.php`);
    try {
        const url = new URL(value, launchUrl);
        const isLaunch = ['http:', 'https:'].includes(url.protocol)
            && url.host === launchUrl.host
            && url.pathname === launchUrl.pathname
            && !url.username
            && !url.password;

        return isLaunch ? url : null;
    } catch (error) {
        return null;
    }
};

/**
 * Parse a URL, returning it only if it launches the Panopto tool of the course, which is when
 * filter_panopto shows a marker link as a video.
 *
 * @param {TinyMCE} editor
 * @param {string} value
 * @returns {URL|null}
 */
const getCourseToolLaunchUrl = (editor, value) => {
    const toolId = String(getTool(editor)?.id ?? '');
    const url = toolId ? getLaunchUrl(value) : null;

    return url?.searchParams.get('ltitypeid') === toolId ? url : null;
};

/**
 * Get the text inside a parsed node.
 *
 * @param {AstNode} node
 * @returns {string}
 */
const getText = (node) => {
    let text = '';
    for (let child = node.firstChild; child; child = child.next) {
        text += child.type === 3 ? child.value : getText(child);
    }

    return text;
};

export const getSetup = async() => {
    const defaultTitle = await getString('panopto_video_default_title', component);

    return (editor) => {
        if (!isPanoptoFilterActive(editor)) {
            return;
        }

        // The parser and serializer are created after the plugins.
        editor.on('PreInit', () => {
            const {Node} = editor.editorManager.html;

            // Save Panopto iframes as marker links, keeping their size in the link. Iframes of any other
            // tool stay iframes, as filter_panopto would show their markers as links.
            editor.serializer.addNodeFilter('iframe', (nodes) => nodes.forEach((node) => {
                const url = getCourseToolLaunchUrl(editor, node.attr('src'));
                if (!url) {
                    return;
                }

                Object.entries(dimensions).forEach(([attribute, parameter]) => {
                    url.searchParams.delete(parameter);
                    if (dimensionPattern.test(node.attr(attribute))) {
                        url.searchParams.set(parameter, node.attr(attribute));
                    }
                });

                const marker = Node.create('a', {'class': markerClass, href: url.toString()});
                const text = Node.create('#text');
                text.value = node.attr('title')?.trim() || defaultTitle;
                marker.append(text);
                node.replace(marker);
            }));

            // Show marker links as iframes while editing, where filter_panopto shows them as videos:
            // when they launch the Panopto tool of the course.
            editor.parser.addNodeFilter('a', (nodes) => nodes.forEach((node) => {
                const isMarker = (node.attr('class') || '').split(/\s+/).includes(markerClass);
                const url = isMarker ? getCourseToolLaunchUrl(editor, node.attr('href')) : null;
                if (!url) {
                    return;
                }

                const size = {};
                Object.entries(dimensions).forEach(([attribute, parameter]) => {
                    if (dimensionPattern.test(url.searchParams.get(parameter))) {
                        size[attribute] = url.searchParams.get(parameter);
                    }
                    url.searchParams.delete(parameter);
                });

                node.replace(Node.create('iframe', {
                    src: url.toString(),
                    title: getText(node).trim() || defaultTitle,
                    ...size,
                    allowfullscreen: 'true',
                }));
            }));
        });
    };
};
