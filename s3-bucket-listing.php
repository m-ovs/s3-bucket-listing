<?php
/**
 * Plugin Name: S3 Bucket Listing
 * Description: Connect to AWS / DigitalOcean Spaces and display bucket content using S3 protocol
 *
 *
 * Author: MOvs
 * Version: 1.0
 */
require_once (plugin_dir_path(__FILE__) . 'vendor/autoload.php');

require_once  ('includes/S3BucketListing.php');
require_once  ('includes/settings.php');

if (! is_admin()) {
    add_action('wp_enqueue_scripts', function () {
        wp_enqueue_style('s3_bucket_listing',  plugins_url(). '/s3-bucket-listing/theme/theme.css', array(), '1.0', 'screen');
    });
} 
$S3BucketListing = S3BucketListing::getInstance(); 
$S3BucketListingSettings = S3BucketListingSettings::getInstance();



function s3BucketListingFlushRules()
{
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 's3BucketListingFlushRules');
register_deactivation_hook(__FILE__, 's3BucketListingFlushRules');
