<?php
/**
 * Plugin Name: S3 Bucket Listing
 * Description: Connect to AWS / DigitalOcean Spaces and display bucket content using S3 protocol
 *
 *
 * Author: MOvs
 * Version: 1.0
 */
require_once (plugin_dir_path(__FILE__) . '../vendor/autoload.php');

use Aws\S3\S3Client;

class S3BucketListing
{

    private $client;

    private $att;

    private $objects;

    // Regexp to parse URLs. Here we should write all pages (and 2nd level pages) , where shrotcode placed
    private $url_regexp = '^/?(files(?:\/(?:ea|_jdbc|_product))?)(/.*)?/?$';

    private static $instance = null;

    private function __construct()
    {
        $this->init();
    }

    private function __clone()
    {}

    public static function getInstance(): S3BucketListing
    {
        if (is_null(self::$instance)) {
            self::$instance = new S3BucketListing();
        }
        return self::$instance;
    }

    private function init()
    {
        if (! get_option('s3_bucket_listing_endpoint')) {
            return;
        }
        add_action('init', array(
            $this,
            'do_rewrite'
        ));
        add_action('template_redirect', array(
            $this,
            'pre_process_shortcode'
        ), 1);

        $this->client = new Aws\S3\S3Client([

            'version' => 'latest',
            'region' => get_option('s3_bucket_listing_region'),
            'endpoint' => get_option('s3_bucket_listing_endpoint'),
            'credentials' => [
                'key' => get_option('s3_bucket_listing_key'),
                'secret' => get_option('s3_bucket_listing_secret')
            ]
        ]);

        add_shortcode('s3_bucket_listing', function ($att) {
            if (empty($att['bucket'])) {
                echo 'Bucket parametr missing. Use [s3_bucket_listing bucket="my_bucket"]';
                return;
            }

            if (! $this->objects['KeyCount'] && ! (isset($this->objects['CommonPrefixes']) && count($this->objects['CommonPrefixes'])) && ! empty($this->att['dir'])) {
                $this->print_filesystem_content();
            } else {
                $this->print_s3_bucket_content();
            }
        });
    }

    private function print_filesystem_content()
    {
        (get_query_var('dir')) ? $prefix = trim(urldecode(get_query_var('dir')), '/') . '/' : $prefix = '';
        preg_match('#' . $this->url_regexp . '#', $_SERVER['REQUEST_URI'], $matches);
        if (isset($matches[1]))
            $home_url = $matches[1];
        else
            $home_url = '';
        echo '<div class="s3_bucket_listing_breadcrumbs">';
        if ($prefix != '') {
            $parts = explode('/', $prefix);
            echo '<a class="folder-home" href="/' . $home_url . '/"></a>';
            $parts_url = '';
            foreach ($parts as $k => $part) {
                if ($part) {
                    echo ' > ';
                    $parts_url .= $part . '/';
                    echo '<a href="/' . $home_url . '/' . $parts_url . '">' . $part . '</a>';
                }
            }
        }
        echo '</div>';

        if ((is_file($this->att['dir'] . $prefix)) && file_exists($this->att['dir'] . $prefix)) {
            // This is a file, which already sent by pre_process_shortcode()
            exit();
        } elseif (is_dir($this->att['dir'] . '/' . $prefix)) {
            $dir = $this->att['dir'] . '/' . $prefix;
        } else {
            // This is 404. Already set by pre_process_shortcode()
            exit();
        }

        $files = scandir($dir);

        echo '<table class="s3_listing_files">';
        $dirs = '';
        $dir_files = '';
        foreach ($files as $file) {
            if (($file === '.') || (($file === '..') && ($prefix == ''))) {
                continue;
            } elseif ($file === '..') {
                $class = 'folder-home';
            } elseif (is_dir($this->att['dir'] . '/' . $prefix . '/' . $file)) {
                $class = 'folder';
            } else {
                $class = '';
            }

            if (is_dir($this->att['dir'] . '/' . $prefix . '/' . $file)) {
                $dirs .= '<tr><td><a class="' . $class . '" href="' . $file . '">' . $file . '</a></td><td>' . (($file === '..') ? '' : date('Y-m-d H:i:s', filemtime($this->att['dir'] . '/' . $prefix . '/' . $file))) . '</td><td>' . (is_dir($this->att['dir'] . '/' . $prefix . '/' . $file) ? '' : $this->filesize_formatted(filesize($this->att['dir'] . '/' . $prefix . '/' . $file))) . '</td></tr>';
            } else {
                $dir_files .= '<tr><td><a href="' . $file . '">' . $file . '</a></td><td>' . (($file === '..') ? 'sss' : date('Y-m-d H:i:s', filemtime($this->att['dir'] . '/' . $prefix . '/' . $file))) . '</td><td>' . (is_dir($this->att['dir'] . '/' . $prefix . '/' . $file) ? '' : $this->filesize_formatted(filesize($this->att['dir'] . '/' . $prefix . '/' . $file))) . '</td></tr>';
            }
        }
        echo $dirs;
        echo $dir_files;
        echo '</table>';
    }

    private function print_s3_bucket_content()
    {
        $root = preg_replace('#([^\/]+)\/?$#', '$1/', $this->att['root']); // add trailing slash
        (get_query_var('dir')) ? $prefix = trim(urldecode(get_query_var('dir')), '/') . '/' : $prefix = '';

        preg_match('#' . $this->url_regexp . '#', $_SERVER['REQUEST_URI'], $matches);
        $home_url = $matches[1] . '/';
        // echo 'Index of ' . (! empty($prefix) ? $prefix : '/');
        echo '<div class="s3_bucket_listing_breadcrumbs">';
        if (! empty($prefix)) {
            echo '<a class="folder-home" href="/' . $home_url . '"></a>';
            $parts = explode('/', $prefix);
            $parts_url = '';
            foreach ($parts as $part) {
                if ($part) {
                    echo ' > ';
                    $parts_url .= $part . '/';
                    echo '<a href="/' . $home_url . $parts_url . '">' . $part . '</a>';
                }
            }
        }
        echo '</div>';

        if (isset($this->objects['CommonPrefixes'])) {
            if (empty($prefix)) {
                $commonPrefixes = array_reverse($this->objects['CommonPrefixes']);
            } else {
                $commonPrefixes = $this->objects['CommonPrefixes'];
            }
        }

        echo '<table class="s3_listing_files">';
        // echo '<tr><td><a class="folder-home" href="/'.$home_url.'">.</a>' . '</td><td></td><td></td></tr>';
        if (! empty($prefix)) {
            echo '<tr><td><a class="folder-home" href="/' . $home_url . preg_replace('#[^\/]+\/$#', '', $prefix) . '">..</a>' . '</td><td></td><td></td></tr>';
        }
        if (isset($commonPrefixes)) {
            foreach ($commonPrefixes as $obj) {
                $obj_prefix = preg_replace('#^' . $root . '#', '', $obj['Prefix']);
                echo '<tr><td><a class="folder" href="/' . $home_url . $obj_prefix . '">' . preg_replace('#^' . $prefix . '#', '', preg_replace('#/$#', '', $obj_prefix)) . '</a>' . "</td><td></td><td></td></tr>";
            }
        }
        if (isset($this->objects['Contents'])) {
            foreach ($this->objects['Contents'] as $obj) {
                (! empty(get_option('s3_bucket_listing_domain'))) ? $url = get_option('s3_bucket_listing_domain') . '/' : ($url = get_option('s3_bucket_listing_endpoint') . '/' . $this->att['bucket'] . '/' . $root);

                $key = preg_replace('#^' . $root . $prefix . '#', '', $obj['Key']);
                echo '<tr><td><a href="' . $url . $prefix . $key . '">' . $key . '</a></td><td>' . $obj['LastModified']->format('Y-m-d H:i:s') . '</td><td>' . $this->filesize_formatted($obj['Size']) . '</td></tr>';
            }
        }
        echo '</table>';
    }

    private function filesize_formatted($size)
    {
        $units = array(
            'B',
            'KB',
            'MB',
            'GB',
            'TB',
            'PB',
            'EB',
            'ZB',
            'YB'
        );
        $power = $size > 0 ? floor(log($size, 1024)) : 0;
        return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
    }

    public function do_rewrite()
    {
        add_rewrite_rule($this->url_regexp, 'index.php?pagename=$matches[1]&dir=$matches[2]', 'top');
        add_rewrite_tag('%dir%', '([^&]+)');

        if (! in_array('index.php?pagename=$matches[1]&dir=$matches[2]', get_option('rewrite_rules'))) {
            flush_rewrite_rules();
        }
    }

    /*
     * Send file from filesystem to user by hook "template_redirect."
     * We have to send file before headers sent. But all shortcodes are proccessed by hook "the_content", where we have headers already sent
     * Also here we are reading S3 bucket contents
     */
    public function pre_process_shortcode()
    {
        if (! is_singular())
            return;

        global $post;
        if (! empty($post->post_content)) {
            $regex = get_shortcode_regex();
            preg_match_all('/' . $regex . '/', $post->post_content, $matches);
            if (! empty($matches[2]) && in_array('s3_bucket_listing', $matches[2])) {
                (get_query_var('dir')) ? $prefix = trim(urldecode(get_query_var('dir')), '/') . '/' : $prefix = '';
                $this->att = shortcode_parse_atts($matches[3][0]);
                if (isset($this->att['root'])) {
                    $root = trim($this->att['root'], '/') . '/';
                    if ($root == '/')
                        $root = '';
                    $params = array(
                        'Bucket' => $this->att['bucket'],
                        'Delimiter' => '/',
                        'Prefix' => $root . $prefix
                    );
                    $this->objects = $objects = $this->client->listObjectsV2($params);
                    while ($objects['IsTruncated']) {
                        $params['ContinuationToken'] = $objects['NextContinuationToken'];
                        $objects = $this->client->listObjectsV2($params);
                        $CommonPrefixes = array_merge($this->objects['CommonPrefixes'], $objects['CommonPrefixes']);
                        $Contents = array_merge($this->objects['Contents'], $objects['Contents']);
                        $this->objects['CommonPrefixes'] = $CommonPrefixes;
                        $this->objects['Contents'] = $Contents;
                        $this->objects['KeyCount'] += $objects['KeyCount'];
                    }
                }  
                // If not found in S3 Bucket let's search in filesystem
                if (! $this->objects['KeyCount'] && ! (isset($this->objects['CommonPrefixes']) && count($this->objects['CommonPrefixes']))) {
                    // prevent sending files with .. in the filename
                    if (strpos(get_query_var('dir'), '..') !== false) {
                        global $wp_query;
                        $wp_query->set_404();
                        status_header(404);
                        get_template_part(404);
                        exit();
                    }

                    $this->att['dir'] = preg_replace('#([^\/]+)\/?$#', '$1/', $this->att['dir']); // add trailing slash
                    $prefix = trim($prefix, '/');

                    if ((is_file($this->att['dir'] . $prefix)) && file_exists($this->att['dir'] . $prefix)) {
                        $file = $this->att['dir'] . $prefix;
                        // clean buffer(s)
                        ob_end_clean();
                        header('Content-Description: File Transfer');
                        header('Content-Type: application/octet-stream');
                        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
                        header('Expires: 0');
                        header('Cache-Control: must-revalidate');
                        header('Pragma: public');
                        header('Content-Length: ' . filesize($file));
                        readfile($file);
                        exit();
                    } elseif (is_dir($this->att['dir'] . $prefix)) {
                        // Ok, this is dir in file system. Goto do_shortcode()
                    } else {
                        // not found in s3 bucket & not found in file system = 404
                        global $wp_query;
                        $wp_query->set_404();
                        status_header(404);
                        get_template_part(404);
                        exit();
                    }
                }
            }
        }
    }
}