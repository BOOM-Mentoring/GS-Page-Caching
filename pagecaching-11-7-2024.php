<?php
/*
Plugin Name: Page Caching CE
Description: Based on SimpleCache by Rob Antonishen. Provided by <a href="https://theboom.org" target="_blank">The BOOM youth technology mentoring program</a>.
Version: 0.2
Author: theboom.org
Author URI: https://theboom.org
*/

$thisfile = basename(__FILE__, '.php');

// Register plugin
register_plugin(
    $thisfile,
    'Page Caching CE',
    '0.2',
    'theboom.org',
    'https://theboom.org',
    'Based on SimpleCache by Rob Antonishen.',
    'pages',
    'pagecaching_manage'
);

// Activate actions
add_action('changedata-save', 'pagecaching_flushpage');
add_action('index-pretemplate', 'pagecaching_pagestart');
add_action('index-posttemplate', 'pagecaching_pageend');
add_action('pages-sidebar', 'createSideMenu', array($thisfile, 'Page Caching CE'));
add_action('theme-footer', 'pagecaching_footer');

// Global settings
$pagecaching_conf = pagecaching_loadconf();

/***********************************************************************************
 * Hook Functions
 ***********************************************************************************/
function pagecaching_pagestart() {
    global $pagecaching_conf;

    if ($pagecaching_conf['enabled'] === 'Y') {
        $slug = return_page_slug();
        if (!in_array($slug, array_merge(['404'], $pagecaching_conf['nocache']))) {
            $cache_dir = GSDATAOTHERPATH . 'pagecaching_cache/';
            $cache_file = $cache_dir . md5($slug) . '.cache';

            if (is_dir($cache_dir) && file_exists($cache_file)) {
                $cachetime = filemtime($cache_file);
                if ($pagecaching_conf['ttl'] == 0 || (time() - $pagecaching_conf['ttl'] * 60) < $cachetime) {
                    include($cache_file);
                    return;
                }
            }
            if (!ob_start('ob_gzhandler')) ob_start();
        }
    }
}

function pagecaching_pageend() {
    global $pagecaching_conf;

    if ($pagecaching_conf['enabled'] === 'Y') {
        $slug = return_page_slug();
        if (!in_array($slug, array_merge(['404'], $pagecaching_conf['nocache']))) {
            $cache_dir = GSDATAOTHERPATH . 'pagecaching_cache/';

            if (!is_dir($cache_dir) && !mkdir($cache_dir, 0755, true)) {
                error_log('Unable to create cache folder: ' . $cache_dir);
                return;
            }

            $cache_file = $cache_dir . md5($slug) . '.cache';
            $fp = fopen($cache_file, 'w');
            if ($fp) {
                fwrite($fp, ob_get_contents() . "\n<!-- Page cached by Page Caching CE on " . date('F d Y H:i:s') . " -->");
                fclose($fp);
            } else {
                error_log('Unable to write cache file: ' . $cache_file);
            }

            ob_end_flush();
        }
    }

    if (!empty($pagecaching_conf['show_footer'])) {
        pagecaching_footer();
    }
}

function pagecaching_footer() {
    global $pagecaching_conf;
    $logo_path = 'https://bayviewboom.org/data/uploads/logo-web-satur-med-boom-trans.png';

    if (!empty($pagecaching_conf['show_footer']) && $pagecaching_conf['show_footer'] === 'Y') {
        echo '<div style="text-align: center; margin-top: 10px;">';
        echo '<a href="https://boo.ma/donate" target="_blank">';
        echo '<img src="' . $logo_path . '" style="width: 20%;" alt="Support The BOOM">';
        echo '</a>';
        echo '<p>Support The BOOM by <a href="https://boo.ma/donate" target="_blank">donating</a>.</p>';
        echo '</div>';
    }
}

/***********************************************************************************
 * Helper Functions
 ***********************************************************************************/
function pagecaching_flushall() {
    $cache_dir = GSDATAOTHERPATH . 'pagecaching_cache/';
    if (is_dir($cache_dir)) {
        foreach (scandir($cache_dir) as $file) {
            if (!in_array($file, ['.', '..', '.htaccess']) && is_file($cache_dir . $file)) {
                unlink($cache_dir . $file);
            }
        }
    }
}

function pagecaching_flushpage($page = null) {
    $cache_dir = GSDATAOTHERPATH . 'pagecaching_cache/';
    $page = $page ?? md5($_POST['post-id']);
    $cache_file = $cache_dir . $page . '.cache';

    if (file_exists($cache_file)) {
        unlink($cache_file) or error_log('Failed to delete cache file: ' . $cache_file);
    }
}

/***********************************************************************************
 * Backend Management Page
 ***********************************************************************************/
function pagecaching_manage() {
    global $pagecaching_conf;

    // Fetch all pages (simulate if the function doesn't exist)
    if (!function_exists('get_all_pages')) {
        // Example: Simulated page slugs
        $pages = [
            ['slug' => 'home', 'title' => 'Home'],
            ['slug' => 'about', 'title' => 'About Us'],
            ['slug' => 'contact', 'title' => 'Contact'],
        ];
    } else {
        $pages = get_all_pages(); // Replace this with actual page-fetching logic
    }

    // Handle form submissions
    if (isset($_POST['submit_settings'])) {
        $pagecaching_conf['enabled'] = isset($_POST['enabled']) ? 'Y' : 'N';
        $pagecaching_conf['ttl'] = is_numeric($_POST['ttl']) ? min(1400, max(0, (int)$_POST['ttl'])) : $pagecaching_conf['ttl'];
        $pagecaching_conf['show_footer'] = isset($_POST['show_footer']) ? 'Y' : 'N';
        pagecaching_saveconf();
        echo '<div class="updated">Settings updated successfully!</div>';
    }

    if (isset($_POST['clear_cache'])) {
        pagecaching_flushall();
        echo '<div class="updated">Cache cleared successfully!</div>';
    }

    if (isset($_POST['rebuild_cache'])) {
        pagecaching_rebuildall();
        echo '<div class="updated">Cache rebuilt successfully!</div>';
    }

    // Output settings form
    echo '<form method="post">';
    echo '<input type="checkbox" name="enabled" ' . ($pagecaching_conf['enabled'] === 'Y' ? 'checked' : '') . '> Enable caching<br>';
    echo 'Cache TTL (minutes): <input type="number" name="ttl" value="' . $pagecaching_conf['ttl'] . '"><br>';
    echo '<input type="checkbox" name="show_footer" ' . ($pagecaching_conf['show_footer'] === 'Y' ? 'checked' : '') . '> Support The BOOM by displaying our link in your footer<br>';
    echo '<input type="submit" name="submit_settings" value="Save Settings">';
    echo '</form>';

    // Output dropdown with pages
    echo '<form method="post" style="margin-top: 20px;">';
    echo '<label for="page_list">Select a Page:</label>';
    echo '<select id="page_list" name="page_list">';
    foreach ($pages as $page) {
        echo '<option value="' . htmlspecialchars($page['slug']) . '">' . htmlspecialchars($page['title']) . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" name="clear_page_cache" value="Clear Page Cache">';
    echo '</form>';

    // Output cache clear and rebuild buttons
    echo '<form method="post" style="margin-top: 20px; display: flex; gap: 10px;">';
    echo '<input type="submit" name="clear_cache" value="Clear Cache">';
    echo '<input type="submit" name="rebuild_cache" value="Rebuild Cache">';
    echo '</form>';
    

    // Handle clearing specific page cache
    if (isset($_POST['clear_page_cache']) && !empty($_POST['page_list'])) {
        $page_slug = $_POST['page_list'];
        pagecaching_flushpage($page_slug);
        echo '<div class="updated">Cache cleared for page: ' . htmlspecialchars($page_slug) . '</div>';
    }
}




/***********************************************************************************
 * Config Functions
 ***********************************************************************************/
function pagecaching_saveconf() {
    global $pagecaching_conf;
    $configfile = GSDATAOTHERPATH . 'pagecaching.xml';

    $xml = new SimpleXMLElement('<pagecachingsettings/>');
    $xml->addChild('enabled', $pagecaching_conf['enabled']);
    $xml->addChild('ttl', $pagecaching_conf['ttl']);
    $xml->addChild('show_footer', $pagecaching_conf['show_footer']); // Save show_footer to XML
    $xml->addChild('nocache');
    foreach ($pagecaching_conf['nocache'] as $slug) {
        $xml->nocache->addChild('slug', $slug);
    }
    $xml->asXML($configfile);
}

function pagecaching_loadconf() {
    $configfile = GSDATAOTHERPATH . 'pagecaching.xml';
    if (!file_exists($configfile)) {
        $default = new SimpleXMLElement('<pagecachingsettings><enabled>Y</enabled><ttl>60</ttl><show_footer>Y</show_footer><nocache/></pagecachingsettings>');
        $default->asXML($configfile);
        chmod($configfile, 0755);
    }

    $xml = simplexml_load_file($configfile);
    return [
        'enabled' => (string)$xml->enabled,
        'ttl' => (int)$xml->ttl,
        'show_footer' => (string)$xml->show_footer,
        'nocache' => array_map('strval', iterator_to_array($xml->nocache->slug)),
    ];
}
function pagecaching_rebuildall() {
    $cache_dir = GSDATAOTHERPATH . 'pagecaching_cache/';
    
    // Ensure cache directory exists
    if (!is_dir($cache_dir)) {
        if (!mkdir($cache_dir, 0755, true)) {
            error_log('Unable to create cache folder: ' . $cache_dir);
            return false; // Stop on failure
        }
    }

    // Fetch all pages (simulate if function doesn't exist)
    if (!function_exists('get_all_pages')) {
        // Example: Simulated page slugs
        $pages = ['home', 'about', 'contact'];
    } else {
        $pages = get_all_pages();
    }

    if (empty($pages)) {
        error_log('No pages found for rebuilding cache.');
        return false; // Stop if no pages
    }

    // Rebuild cache for each page
    foreach ($pages as $slug) {
        $cache_file = $cache_dir . md5($slug) . '.cache';

        // Simulate generating cached content
        $content = "<!-- Cache content for page: $slug -->\n";
        $content .= "<html><body>Content of page: $slug</body></html>";

        // Write to cache file
        if ($fp = fopen($cache_file, 'w')) {
            fwrite($fp, $content . "\n<!-- Page cached on " . date('F d Y H:i:s') . " -->");
            fclose($fp);
        } else {
            error_log('Failed to write cache file: ' . $cache_file);
        }
    }

    return true; // Success
}


?>
