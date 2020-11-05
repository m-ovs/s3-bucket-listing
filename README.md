# s3-bucket-listing
Wordpress plugin to display AWS / DigitalOcean Spaces bucket public content on wordpress page 

Usage of plugin:

1. Set up credentials on plugin settings page.
2. Use shortcode [s3_bucket_listing bucket="my_bucket" root="my_folder"] to display content of your bucket "my_bucket" optionaly starting from prefix ( folder ) named "my_folder".
Bucket param is obligatory. 
