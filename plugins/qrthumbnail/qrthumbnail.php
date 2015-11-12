<?php
/**
 * QR Code as thumbnail.
 *
 * This plugin creates QR codes as thumbnails.
 */



define('libQRCode', TRUE);

require_once(PluginManager::$PLUGINS_PATH .'/qrthumbnail/libqrcode.combined.php');

define('QRTHUMBNAIL_PATH', 'qrthumbnail/qr/');

define('QR_CACHEABLE', false);         // use cache - more disk reads but less CPU power, masks and format templates are stored there
// define('QR_CACHE_DIR', dirname(__FILE__).DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR);
define('QR_CACHE_DIR', false);         // used when QR_CACHEABLE === true
define('QR_LOG_DIR', false);           // default error logs dir

define('QR_FIND_BEST_MASK', true);     // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_FROM_RANDOM', 2);      // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_DEFAULT_MASK', 2);          // when QR_FIND_BEST_MASK === false

define('QR_PNG_MAXIMUM_SIZE',  128);  // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images



// Create QR directory if it doesn't exist
// Commented out as I'm creating in the repo, don't need this slowing stuff down
//if (!is_dir(QRTHUMBNAIL_PATH)) mkdir(QRTHUMBNAIL_PATH, 0755);



if (!is_writable(PluginManager::$PLUGINS_PATH .'/'.QRTHUMBNAIL_PATH)) {
    // Cannot write to qrthumbnail path
    $GLOBALS['plugins']['errors'][] = 'QRThumbnail plugin error: '.
        'No write access to the directory plugins/'.QRTHUMBNAIL_PATH.'. '.
        'This is required for the QRThumbnail plugin to work properly.';
}



function get_qrthumbnail_key($url) {
    return md5($url);
}

function get_qrthumbnail_url($key) {
    return 'plugins/'.QRTHUMBNAIL_PATH.'/'.$key.'.png';
}

function get_qrthumbnail_file($key) {
    return PluginManager::$PLUGINS_PATH .'/'.QRTHUMBNAIL_PATH.'/'.$key.'.png';
}

function gen_qrcode($url, $outFile) {
    if (!file_exists($outFile)) {
        // Generate the QR code
        QRcode::png($url, $outFile);
    }

    return $key;
}

/**
 * Hook render_linklist.
 *
 * Template placeholders:
 *   - action_plugin: next to 'private only' button.
 *   - plugin_start_zone: page start
 *   - plugin_end_zone: page end
 *   - link_plugin: icons below each links.
 *   - thumbnail_plugin: thumbnails on the right.
 *
 * Data:
 *   - _LOGGEDIN_: true/false
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_qrthumbnail_render_linklist($data) {
    // link_plugin (for each link)
    foreach ($data['links'] as &$value) {
        $key = get_qrthumbnail_key($value['url']);
        $pngFile = get_qrthumbnail_file($key);

        if (!empty($value['qrthumbnail']) && $key != $value['qrthumbnail']) {
            // Old image
            $pngFileOld = get_qrthumbnail_file($value['qrthumbnail']);
            if (file_exists($pngFileOld)) unlink($pngFileOld);
            unset($pngFileOld);
            $value['qrthumbnail'] = '';
        }

        if (empty($value['qrthumbnail'])) {
            $value['qrthumbnail'] = $key;
        }

        if (!file_exists($pngFile)) {
            gen_qrcode($value['url'], $pngFile);
        }

        $qrcodeimg = get_qrthumbnail_url($value['qrthumbnail']);

        $html = file_get_contents(PluginManager::$PLUGINS_PATH .'/qrthumbnail/qrthumbnail.html');
        $html = sprintf($html, $qrcodeimg, $qrcodeimg);

        $value['thumbnail_plugin'][] = $html;
    }

    return $data;
}

/**
 * Hook render_editlink.
 *
 * Template placeholders:
 *   - field_plugin: add link fields after tags.
 *
 * @param array $data data passed to plugin
 *
 * @return array altered $data.
 */
function hook_qrthumbnail_render_editlink($data) {
    // Delete old thumbnail QR (just in case)
    if (!empty($data['link']['qrthumbnail'])) {
        $pngFile = get_qrthumbnail_file($data['link']['qrthumbnail']);
        if (file_exists($pngFile)) unlink($pngFile);
    }

    $data['link']['qrthumbnail']='';

    return $data;
}

/**
 * Hook savelink.
 *
 * Triggered when a link is save (new or edit).
 *
 * @param array $data contains the new link data.
 *
 * @return array altered $data.
 */
function hook_qrthumbnail_save_link($data) {
    $key = get_qrthumbnail_key($data['url']);
    $pngFile = get_qrthumbnail_file($key);

    $data['qrthumbnail'] = gen_qrcode($data['url'], $pngFile);

    return $data;
}

/**
 * Hook delete_link.
 *
 * Triggered when a link is deleted.
 *
 * @param array $data contains the link to be deleted.
 *
 * @return array altered data.
 */
function hook_qrthumbnail_delete_link($data) {
    if (strpos($data['url'], 'youtube.com') !== false) {
        exit('You can not delete a YouTube link. Don\'t ask.');
    }

    // Delete old thumbnail QR (just in case)
    if (!empty($data['qrthumbnail'])) {
        $pngFile = get_qrthumbnail_file($data['qrthumbnail']);
        if (file_exists($pngFile)) unlink($pngFile);
    }

    $data['qrthumbnail']='';
}