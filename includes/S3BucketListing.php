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
            $root = ! empty($att['root']) ? $att['root'] : '';
            $root = preg_replace('#([^\/]+)\/?$#', '$1/', $root); // add trailing slash

            preg_match('#^/?(.*?)/(.*)/?$#', $_SERVER['REQUEST_URI'], $matches);
            $home_url = $matches[1] . '/';
            (get_query_var('dir')) ? $prefix = urldecode(sanitize_text_field(get_query_var('dir'))) . '/' : $prefix = '';
            $objects = $this->client->listObjectsV2([
                'Bucket' => $att['bucket'],
                'Delimiter' => '/',
                'Prefix' => $root . $prefix
            ]);
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
            echo '<table class="s3_listing_files">';
            // echo '<tr><td><a class="folder-home" href="/'.$home_url.'">.</a>' . '</td><td></td><td></td></tr>';
            if (! empty($prefix)) {
                echo '<tr><td><a class="folder-home" href="/' . $home_url . preg_replace('#[^\/]+\/$#', '', $prefix) . '">..</a>' . '</td><td></td><td></td></tr>';
            }
            if (isset($objects['CommonPrefixes'])) {
                foreach ($objects['CommonPrefixes'] as $obj) {
                    $obj_prefix = preg_replace('#^' . $root . '#', '', $obj['Prefix']);
                    echo '<tr><td><a class="folder" href="/' . $home_url . $obj_prefix . '">' . preg_replace('#^' . $prefix . '#', '', $obj_prefix) . '</a>' . "</td><td></td><td></td></tr>";
                }
            }
            if (isset($objects['Contents'])) {
                foreach ($objects['Contents'] as $obj) {
                    (! empty(get_option('s3_bucket_listing_domain'))) ? $url = get_option('s3_bucket_listing_domain') . '/' : ($url = get_option('s3_bucket_listing_endpoint') . '/' . $att['bucket'] . '/' . $root);

                    $key = preg_replace('#^' . $root . $prefix . '#', '', $obj['Key']);
                    echo '<tr><td><a href="' . $url . $prefix . $key . '">' . $key . '</a></td><td>' . $obj['LastModified']->format('Y-m-d H:i:s') . '</td><td>' . $this->filesize_formatted($obj['Size']) . '</td></tr>';
                }
            }
            echo '</table>';
        });
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
        $page = get_page_by_path($_SERVER['REQUEST_URI']);
        if (! isset($page->ID)) {
            preg_match('#^/?(.*?)/(.+)/?$#', $_SERVER['REQUEST_URI'], $matches);
            $parent_page = get_page_by_path($matches[1]);
            if (!empty($parent_page->post_content)) {
                $content = $parent_page->post_content;
                if (preg_match('/\[s3_bucket_listing(.*?)\]/i', $content)) {
                    add_rewrite_rule('^/?(.*?)/(.+)/?$', 'index.php?pagename=$matches[1]&dir=$matches[2]', 'top');
                    add_rewrite_tag('%dir%', '([^&]+)');
                }
            }
        }
    }
}