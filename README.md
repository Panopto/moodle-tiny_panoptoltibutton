# Panopto LTI button for TinyMCE

Adds an **Add Panopto Video** button to Moodle’s **TinyMCE** editor so teachers and students can insert a Panopto video into any TinyMCE field (labels, forum posts, quiz questions, and so on).

This is the editor button to use on Moodle **5.0 and later**. Sites still using Atto can use the [Atto Panopto button](https://github.com/Panopto/Panopto-LTI-Button-for-Atto) instead.

## Who it is for

- **Teachers and students** click **Add Panopto Video** in TinyMCE and pick a video.
- **Administrators** install the plugin, enable TinyMCE, and make sure the Panopto block is set up.

## Requirements

- A supported **Moodle** version. Each [GitHub release](https://github.com/Panopto/moodle-tiny_panoptoltibutton/releases) lists the Moodle versions that zip supports — check those notes and pick the release that matches your site.
- The [Panopto block](https://github.com/Panopto/Moodle-2.0-Plugin-for-Panopto)
- Moodle’s **External tool (LTI)** module and a Panopto LTI tool
- The **TinyMCE** editor enabled (**Site administration → Plugins → Text editors → Manage editors**)
- The course **provisioned** with Panopto through the block

The button only lists LTI tools whose URL matches the Panopto server that course is provisioned to.

## Installation

1. Download a release from [GitHub](https://github.com/Panopto/moodle-tiny_panoptoltibutton/releases).
2. In Moodle, go to **Site administration → Plugins → Install plugins**.
3. Drop the zip onto the install page and complete the upgrade.
4. Go to **Site administration → Plugins → Text editors → TinyMCE editor** and confirm **Add Panopto Video** is listed.
5. Put TinyMCE at the top of **Manage editors** if it is not already the default.

The button then appears in TinyMCE on the site. Users who should see it need the `tiny/panoptoltibutton:use` capability (teachers and students have it by default on a new install).

## Usage

1. In a TinyMCE field, put the cursor where the video should go.
2. Click **Add Panopto Video**.
3. Select the session in the Panopto window. The embed is inserted into the editor.

If you see an error asking you to provision the course, add the Panopto block to that course and provision a folder first.

## For developers

### AMD modules

From the Moodle root, after `npm install`:

```
cd lib/editor/tiny/plugins/panoptoltibutton/amd
npx grunt amd
```

### Development mode

To load unminified JavaScript in the browser, disable JS caching in config.php:

```
$CFG->cachejs = false;
```

### Releasing

1. Bump `$plugin->version` in `version.php`.
2. Zip the plugin (omit `.git` and `node_modules`) and install it as a new release.
