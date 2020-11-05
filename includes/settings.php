<?php

class S3BucketListingSettings
{
    private static $instance = null;

    private function __construct()
    {
        $this->init();
    }

    private function __clone()
    {}

    public static function getInstance(): S3BucketListingSettings
    {
        if (is_null(self::$instance)) {
            self::$instance = new S3BucketListingSettings();
        }
        return self::$instance;
    }

    private function init()
    {
        add_filter('plugin_action_links', array($this, 'plugin_action_links' ), 10, 2);
        add_action('admin_menu', array( $this, 'options_menu'));
        add_action('admin_init', array($this, 'page_init'));
        
    }

    public function plugin_action_links($links, $file)
    {
        $settings_link = '<a href="' . admin_url('options-general.php?page=s3_bucket_listing') . '">' . esc_html__('Settings', 's3_bucket_listing') . '</a>';
        if ($file == 's3-bucket-listing/s3-bucket-listing.php')
            array_unshift($links, $settings_link);

        return $links;
    }

    public function options_menu()
    {
        add_options_page(__('S3 Bucket Listing', 's3_bucket_listing'), __('S3 Bucket Listing', 's3_bucket_listing'), 'manage_options', 's3_bucket_listing', array(
            $this,
            'settings_page'
        ));
    }

    public function s3_bucket_listing_section()
    {}

    public function s3_bucket_listing_key_input()
    {
        ?>
		<input type="text" name="s3_bucket_listing_key"	value="<?php echo get_option('s3_bucket_listing_key'); ?>" />
		<label> Your API access key</label>
		<?php
    }

    public function s3_bucket_listing_secret_input()
    {
        ?>
		<input type="text" name="s3_bucket_listing_secret"	value="<?php echo get_option('s3_bucket_listing_secret'); ?>" />
		<label> Your API secret</label>
		<?php
    }
    public function s3_bucket_listing_region_input()
    {
        ?>
		<input type="text" name="s3_bucket_listing_region"	value="<?php echo get_option('s3_bucket_listing_region'); ?>" />
		<label> Your storage region, example: <em>us-east-1</em></label>
		<?php
    }

    public function s3_bucket_listing_endpoint_input()
    {
        ?>
		<input type="text" name="s3_bucket_listing_endpoint"	value="<?php echo get_option('s3_bucket_listing_endpoint'); ?>" />
		<label> Storge endpoint. Example: <em>https://nyc3.digitaloceanspaces.com</em></label>
		<?php
    }
    public function s3_bucket_listing_domain_input()
    {
        ?>
		<input type="text" name="s3_bucket_listing_domain"	value="<?php echo get_option('s3_bucket_listing_domain'); ?>" />
		<label> Your attached domain. If empty the downloads links will be set to default endpoint url. Example: <em>https://downloads.mydomain.com</em></label>
		<?php
    }
    public function page_init()
    {
        register_setting('s3_bucket_listing', 's3_bucket_listing_key');
        register_setting('s3_bucket_listing', 's3_bucket_listing_secret');
        register_setting('s3_bucket_listing', 's3_bucket_listing_region');
        register_setting('s3_bucket_listing', 's3_bucket_listing_endpoint');
        register_setting('s3_bucket_listing', 's3_bucket_listing_domain');
        
        add_settings_section('s3_bucket_listing_section', __('S3 Bucket Listing Settings'), array($this, 's3_bucket_listing_section'), 's3_bucket_listing');
        add_settings_field('s3_bucket_listing_key', __('Key'), array($this, 's3_bucket_listing_key_input'), 's3_bucket_listing', 's3_bucket_listing_section');
        add_settings_field('s3_bucket_listing_secret', __('Secret'), array($this, 's3_bucket_listing_secret_input'), 's3_bucket_listing', 's3_bucket_listing_section');
        add_settings_field('s3_bucket_listing_region', __('Region'), array($this, 's3_bucket_listing_region_input'), 's3_bucket_listing', 's3_bucket_listing_section');
        add_settings_field('s3_bucket_listing_endpoint', __('Endpoint'), array($this, 's3_bucket_listing_endpoint_input'), 's3_bucket_listing', 's3_bucket_listing_section');
        add_settings_field('s3_bucket_listing_domain', __('Attached Domain'), array($this, 's3_bucket_listing_domain_input'), 's3_bucket_listing', 's3_bucket_listing_section');
    }
    
    public function settings_page() {
        ?>
        <p>Usage of plugin: </p>
        <p>1. Set up connect credentials on this page.<br />
        2. Use shortcode [s3_bucket_listing bucket="my_bucket" root="my_folder"] to display content of your bucket "my_bucket" starting from prefix ( folder ) named "my_folder".<br />
		Bucket param is obligatory. If root param is not set you will see the entire content of the bucket.
		</p>            
		<hr />
        <form method="post" action="options.php">
            <?php
            settings_fields('s3_bucket_listing');
            do_settings_sections('s3_bucket_listing');
            submit_button();
            ?>
        </form>
        
        <?php 
    }
    
}
