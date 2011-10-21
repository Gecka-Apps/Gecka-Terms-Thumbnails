<?php 
/*
Plugin Name: Gecka Terms Thumbnails
Plugin URI: http://gecka-apps.com
Description: Add thumbnails support to categories and any choosen taxonomies.
Version: 1.0-beta2
Author: Gecka Apps
Author URI: http://gecka.nc
Licence: GPL
Requires at least: 3.0
Tested up to: 3.2
*/

/*  Copyright 2011  Gecka  (email : contact@gecka.nc)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

require_once dirname(__FILE__) . '/settings.php';

$gecka_terms_thumbnails = Gecka_Terms_Thumbnails::instance();

class Gecka_Terms_Thumbnails {

	/**
	 * Singleton intance
	 * @var Gecka_Terms_Thumbnails
	 */
	private static $instance;
	
	/**
	 * Uri to the plugin folder
	 * @var string
	 */
	private static $plugin_url;
	
	/**
	 * Absolute path to the plugin folder
	 * @var string
	 */
    private static $plugin_path;
	
	private static $taxonomies  = array('category');
	
	/**
	 * Thumbnails sizes
	 * @var array
	 */
	private static $thumbnails_sizes = array();

	/**
	 * Plugin settings
	 * @var Gecka_Terms_Thumbnails_Settings
	 */
	public static $settings;
	
	private $error;
	
	/**
	 * Allowed mime types
	 * @var array
	 */
	public static $mimes = array('jpg|jpeg|jpe' => 'image/jpeg',
								 'gif' => 'image/gif',
								 'png' => 'image/png',);
	
	/**
	 * Private constructor (singleton)
	 */
	private function __construct() {
    	
    	self::$plugin_url 	= plugins_url('', __FILE__);
    	self::$plugin_path 	= dirname(__FILE__);

    	// init settings
    	self::$settings = Gecka_Terms_Thumbnails_Settings::instance();
    	
    	// add default thumbnails sizes
	    self::add_image_size('admin-thumbnail', 50, 50, true);    	
	    self::add_image_size('thumbnail', self::$settings->term_thumbnail_size_w, self::$settings->term_thumbnail_size_h, self::$settings->term_thumbnail_crop);    	
	    self::add_image_size('medium', self::$settings->term_medium_size_w, self::$settings->term_medium_size_h, self::$settings->term_medium_crop);    	
		
		register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
    	
    	add_action( 'plugins_loaded', array($this, 'plugins_loaded'), 5 );
    	add_action( 'after_setup_theme', array($this, 'after_setup_theme'), 5 );
    	
    	add_action( 'init', array($this, 'metadata_wpdbfix') );
    	add_action( 'switch_blog', array($this, 'metadata_wpdbfix') );
    	
    	add_filter( 'widget_categories_args', array($this, 'widget_categories_args') );
    	
    	add_action( 'admin_init', array($this, 'admin_init') );
    	
    }
    
    /**
     * Returns the singleton instance
     * @return Gecka_Terms_Thumbnails
     */
    public static function instance () {
    
    	if ( ! isset( self::$instance ) ) {
    		$class_name = __CLASS__;
    		self::$instance = new $class_name;
    	}
    
    	return self::$instance;
    
    }

	/* =Manages taxonomies terms image support
	----------------------------------------------- */
    
    /**
     * Adds thumbnail support for the specified taxonomy
     * @param string $taxonomy
     */
    public static function add_taxonomy_support( $taxonomy ) {
    
    	$taxonomies = (array)$taxonomy ;
    	self::$taxonomies = array_merge( self::$taxonomies, $taxonomies );
    	
    }
    
    /**
     * Removes thumbnail support for the specified taxonomy
     * @param string $taxonomy
     */
    public static function remove_taxonomy_support( $taxonomy ) {
		
    	$key = array_search ( $taxonomy, self::$taxonomies );
    	if( false !== $key ) unset( self::$taxonomies[$key] );
    	
    }
    
    /**
     * Return true if the specified taxonomy has terms thumbnails support
     * @param string $taxonomy
     * @return bool
     */
	public static function has_support ( $taxonomy ) {
		
		if( in_array($taxonomy, self::$taxonomies) ) return true;
		return false;
		
	}
	
	/* =Manages terms thumbnails sizes
	 ----------------------------------------------- */
	
	/**
	 * Registers a new term thumbnail size
	 * 
	 * @param string $name
	 * @param int $width
	 * @param int $height
	 * @param bool $crop
	 */
	public static function add_image_size( $name, $width = 0, $height = 0, $crop = false ) {
		self::$thumbnails_sizes[$name] = array( 'width' => absint( $width ), 'height' => absint( $height ), 'crop' => (bool) $crop );
	}
	
	/**
	 * Sets the default thumbnail size
	 * @param int $width
	 * @param int  $height
	 * @param bool $crop
	 */
	public static function set_thumbnail( $width = 0, $height = 0, $crop = false ) {
		self::add_image_size( 'thumbnail', $width, $height, $crop );
	}
	
	
	/* =Static functions to display terms thumbnails
	 ----------------------------------------------- */
	
	/**
	 * Returns true if the specified term has a thumbnail image for the specified category and size
	 *
	 * @param int $term_id
	 * @param string $size
	 */
	public static function has_term_thumbnail ( $term_id, $taxonomy, $size=null ) {
	
		$image_infos = self::get_term_image_infos( $term_id, $taxonomy );
	
		if( empty( $image_infos ) ) return  false;
		elseif( ! $size ) return true;
	
		if( isset ( $image_infos['thumbnails'][$size] ) ) return true;
	
		return false;
	}	
	
	/**
	 * Returns the specified term's thumbnail's HTML code for the specified taxonomy and size
	 * @param int $term_id
	 * @param string $taxonomy
	 * @param string  $size
	 * @param array $attr
	 */
	public static function get_the_term_thumbnail( $term_id, $taxonomy, $size = 'thumbnail', $attr = '' ) {
		
		$size = apply_filters( 'term_thumbnail_size', $size, $term_id, $taxonomy );
		
		$image = self::get_term_thumbnail($term_id, $taxonomy, $size);
		
		$term = get_term($term_id, $taxonomy);
		
		if ( $image ) {
			do_action( 'begin_fetch_term_thumbnail_html', $term_id, $taxonomy, $image, $size );
			
			list($src, $width, $height) = $image;
			$hwstring = image_hwstring($width, $height);
			
			if ( is_array($size) )
				$size = $width . 'x' . $height;
				
			$default_attr = array(
				'src'	=> $src,
				'class'	=> "attachment-$size $taxonomy-thumbnail",
				'alt'	=> trim(strip_tags( $term->name )), // Use Alt field first
				'title'	=> trim(strip_tags( $term->name )),
			);
			
			$attr = wp_parse_args($attr, $default_attr);
			$attr = apply_filters( 'get_the_term_thumbnail_attributes', $attr, $term_id, $taxonomy, $image, $size);
			$attr = array_map( 'esc_attr', $attr );
			$html = rtrim("<img $hwstring");
			foreach ( $attr as $name => $value ) {
				$html .= " $name=" . '"' . $value . '"';
			}
			$html .= ' />';
			do_action( 'end_fetch_term_thumbnail_html', $term_id, $taxonomy, $image, $size );
			
		} else {
			$html = '';
		}
		return apply_filters( 'term_thumbnail_html', $html, $term_id, $taxonomy, $image, $size, $attr );
	}
	
	/**
	 * Returns the specified term's thumbnail for the specified taxonomy and size
	 * @param int $term_id
	 * @param string $taxonomy
	 * @param string  $size
	 */
	public static function get_term_thumbnail ( $term_id, $taxonomy, $size = null ) {
		
		$infos = self::get_term_image_infos($term_id, $taxonomy);
		if(!$infos) return false;
		
		if( ! $size  ) return array($infos['url'], $infos['infos'][0], $infos['infos'][1]);
		
		if( is_array($size) ) {
			/*
			 * @TODO here we need to fing the thumbnail nearest to the asked size
			 */
		}
		
		if( !isset( $infos['thumbnails'][$size] ) ) return false;
		$infos = $infos['thumbnails'][$size];
		
		return array($infos['url'], $infos['infos'][0], $infos['infos'][1]);
		
	}
	
	/**
	 * Manage terms image meta
	 */
	
	/**
	 * Return a term's thumbnail meta data for the specified taxonomy
	 * @param int $term_id
	 * @param string $taxonomy
	 * @return array
	 */
	public static function get_term_image_infos ( $term_id, $taxonomy) {
		
		$meta_data = false;
		
		if( $taxonomy ) {
			$meta_data = get_metadata( 'term', $term_id, 'image-' . $taxonomy, true);
		}
		
		// compatibility with beta1
		if( !$meta_data ) {
			$meta_data = get_metadata( 'term', $term_id, 'image', true);
		}
		
		return $meta_data;
	}
	
	/* =Manages the terms images metadata
	 ----------------------------------------------- */
	
	/**
	 * Updates a term thumbnail metadata
	 * @param int $term_id
	 * @param sttring $taxonomy
	 * @param array $infos
	 * @return boolean
	 */
	public static function update_term_image_infos ( $term_id, $taxonomy, $infos ) {
		
		// compatibility with beta1
		if( get_metadata( 'term', $term_id, 'image', true) ) {
			delete_metadata( 'term', $term_id, 'image' );
		}
		
		return  update_metadata( 'term', $term_id, 'image-' . $taxonomy, $infos );
		
	}
	
	/**
	 * Deletes a term's thumbnail metadata
	 * @param int $term_id
	 * @param string $taxonomy
	 * @return boolean
	 */
	public static function delete_term_image_infos ( $term_id, $taxonomy ) {
		
		// compatibility with beta1
		if( get_metadata( 'term', $term_id, 'image-' . $taxonomy, true) ) {
			return delete_metadata( 'term', $term_id, 'image-' . $taxonomy );
		}
		
		// compatibility with beta1
		if( get_metadata( 'term', $term_id, 'image', true) ) {
			return delete_metadata( 'term', $term_id, 'image' );
		}
		
		return delete_metadata( 'term', $term_id, 'image-' . $taxonomy );

	}
	
	/* =Terms images and thumbnails path and url
	 ----------------------------------------------- */
	
	/**
	 * Returns the absolute path to the thumbnails folder
	 * @return string
	 */
	public static function images_dir () {
		$upload_dir_infos = wp_upload_dir();
		return $upload_dir_infos['basedir'] . '/terms-images';
	}
	
	/**
	 * Returns the URI to the thumbnails folder
	 * @return string
	 */
	public static function images_url () {
		$upload_dir_infos = wp_upload_dir();
		return $upload_dir_infos['baseurl'] . '/terms-images';
	}
	
	/* =Static functions to manage terms images
	 ----------------------------------------------- */
	
	/**
	 * Generate a term's thumbnails
	 * @param int $term_id
	 * @param string $taxonomy
	 */
	public static function generate_thumbnails ( $term_id, $taxonomy ) {
		
		$infos = self::get_term_image_infos( $term_id, $taxonomy );
		
		if ( ! $infos ) return;
		
		$thumbnails = !empty($infos['thumbnails']) ? $infos['thumbnails'] : array();
		
		// removes obsolete thumbnails
		foreach ( $thumbnails as $name => $size ) {
			if( ! isset( self::$thumbnails_sizes[$name] ) ) self::remove_term_thumbnail( $name, $term_id, $taxonomy );
		}
		
		// creates all thumbnails images
		foreach ( self::$thumbnails_sizes as $key => $size ) {
			
			if( ! empty( $thumbnails[$key] ) ) self::remove_term_thumbnail( $key, $term_id, $taxonomy );
				
			$img = image_resize( $infos['path'], $size['width'], $size['height'], $size['crop'], $key);
			
			if( ! $img || is_wp_error($img) )  continue;
			
			$file_infos = array();
			$file_infos ['name'] = basename($img);
			$file_infos ['path'] = $img;
			$file_infos ['infos'] = getimagesize($img);
			$file_infos ['url'] = self::images_url() . '/' . $taxonomy . '/' . basename($img);
			
			$infos['thumbnails'][$key] = $file_infos;
		}
		
		self::update_term_image_infos($term_id, $taxonomy, $infos);
		
	}
	
	/**
	 * Remove a term's image (and its thumbnails)
	 * @param int $term_id
	 * @param string $taxonomy
	 */
	public static function remove_term_image ( $term_id, $taxonomy ) {
		
		$infos = self::get_term_image_infos( $term_id, $taxonomy );
		
		if ( !$infos ) return;
		
		if( !empty($infos) && isset( $infos['path'] ) ) {
			
			if( false === self::remove_term_thumbnails($term_id, $taxonomy) ) return false;
			
			if( ! @ unlink($infos['path']) && file_exists($infos['path']) ) return false;
			
		}
		
		self::delete_term_image_infos($term_id, $taxonomy);	
		
		return true;
	
	}
	
	/**
	 * Removes the generated thumbnails of a term
	 * @param int $term_id
	 * @param string $taxonomy
	 * @return bool
	 */
	public static function remove_term_thumbnails ( $term_id, $taxonomy ) {
		
		$infos = self::get_term_image_infos( $term_id, $taxonomy );
		
		if ( !$infos ) return;
		
		if( empty($infos['thumbnails']) ) return true;
		
		foreach ($infos['thumbnails'] as $name => $thumbnail ) {
			if( false === self::remove_term_thumbnail($name, $term_id, $taxonomy) ) return false;
		}
		
		return true;
	
	}
	
	/**
	 * Removes aterm's thumbnail
	 * @pram string $thumbnail_name
	 * @param int $term_id
	 * @param string $taxonomy
	 * @return bool
	 */
	public static function remove_term_thumbnail ( $thumbnail_name, $term_id, $taxonomy ) {
		
		if($thumbnail_name == 'admin_thumbnail') return;
		
		$infos = self::get_term_image_infos( $term_id, $taxonomy );
		if ( !$infos ) return;
		
		if( empty($infos['thumbnails']) || empty($infos['thumbnails'][$thumbnail_name]) ) return true;
		
		$thumbnail = $infos['thumbnails'][$thumbnail_name];
		
		if ( file_exists($thumbnail['path'] ) && ! @ unlink($thumbnail['path']) ) return false;
		unset( $infos['thumbnails'][$thumbnail_name] );
		
		self::update_term_image_infos($term_id, $taxonomy, $infos);
		
		return true;
	
	}
	
	/* =Misc static functions
	----------------------------------------------- */
	
	/**
	 * Make a directory
	 * @param string $taxonomy optional category name to create a taxnonomy directory
	 */
	public static function images_mkdir ($taxonomy='') {
	
		global $wp_filesystem;
		WP_Filesystem();
	
		$dir = self::images_dir() . ($taxonomy ? '/' . $taxonomy : '');
	
		if ( ! wp_mkdir_p($dir) && ! is_dir($dir) ) // Only check to see if the Dir exists upon creation failure. Less I/O this way.
			wp_die(__('Could not create directory.'));
	
		return $dir;
	}
	
	/* =Action and filter hooks
	----------------------------------------------- */
	
	/**
	 * Checks PHP version and create the needed database table on plugin activation
	 */
	public function activation_hook () {
	    
		// checks the PHP version
		if (version_compare(PHP_VERSION, '5.0.0', '<')) {
	        deactivate_plugins( basename(dirname(__FILE__)) . '/' . basename(__FILE__) ); // Deactivate ourself
	        wp_die("Sorry, the Gecka Terms Ordering plugin requires PHP 5 or higher.");
	    }
	    
	    
	    // creates the needed database table
	    global $wpdb;
	    
	    $collate = '';
	    if($wpdb->supports_collation()) {
			if(!empty($wpdb->charset)) $collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if(!empty($wpdb->collate)) $collate .= " COLLATE $wpdb->collate";
	    }
	    
	    $sql = "CREATE TABLE IF NOT EXISTS ". $wpdb->prefix . "termmeta" ." (
	            `meta_id` bigint(20) unsigned NOT NULL auto_increment,
	            `term_id` bigint(20) unsigned NOT NULL default '0',
	            `meta_key` varchar(255) default NULL,
	            `meta_value` longtext,
	            PRIMARY KEY (meta_id),
	            KEY term_id (term_id),
	            KEY meta_key (meta_key) ) $collate;";
	    
	    $wpdb->query($sql);
	    
	}	
	
	/**
	 * Filters default thumbnails sizes and supported taxonomies
	 * Runs on the plugins_loaded action hook
	 */
	public function plugins_loaded () {
		self::$taxonomies = apply_filters( 'terms-thumbnails-default-sizes', self::$taxonomies );
		self::$thumbnails_sizes = apply_filters( 'terms-thumbnails-default-sizes', self::$thumbnails_sizes );
	}
	
	/**
	 * Filters default thumbnails sizes and supported taxonomies
	 * Runs on the after_setup_theme action hook
	 */
 	public function after_setup_theme () {
    	self::$taxonomies = apply_filters( 'terms-thumbnails-taxonomies', self::$taxonomies );
    	self::$thumbnails_sizes = apply_filters( 'terms-thumbnails-sizes', self::$thumbnails_sizes );
	}
    
	/**
	 * Sets our table name into wpdb
	 * Runs on the init and switch_blog action hooks
	 */
	public function metadata_wpdbfix () {
    	global $wpdb;
	  	$wpdb->termmeta = "{$wpdb->prefix}termmeta";
	}
    
	/**
	 * Filters the default categories widget args
	 * Run on the widget_categories_args filter hook
	 * @param array $args
	 * @return array
	 */
    public function widget_categories_args ($args) {
    	
    	// default taxonomy
    	$taxonomy = empty( $args->taxonomy ) ? 'category' : $args->taxonomy;
		
    	// the taxonomy hasn't thumbnail support, so we ignore it
    	if( ! self::has_support( $taxonomy ) ) return $args;
		
		// default thumbnail size
		if( !isset($args['show_thumbnail']) ) $args['show_thumbnail'] = 'thumbnail';

		// our custom walker to add thumbnails
		$args['walker'] = new Walker_Term();
		
    	return $args;
    }
    
    /**
     * Init the admin
     * Runs on the admin_init action hook
     */
	public function admin_init () {
		
		// adds scripts and css
		add_action ( 'admin_head-edit-tags.php', array($this, 'admin_head'));
		
		// show our admin notices
		add_action ( 'admin_notices', array($this, 'admin_notice') );
		
		// adds/removes our errors var to url on redirect
		add_filter ( 'wp_redirect', array($this, 'wp_redirect') );
		
		foreach ( self::$taxonomies as $taxonomy ) {
			
			// add a file field to terms add and edit form
			add_action( $taxonomy . '_add_form_fields', array($this, 'add_field'), 10, 2 );
			add_action( $taxonomy . '_edit_form_fields', array($this, 'edit_field'), 10, 2 );
			
			// save image on term save
			add_action( "edited_$taxonomy", array($this, 'process_upload'), 10, 2 );
			
			// generate thumbnails after a term is saved
			add_action( "edited_$taxonomy", array($this, 'generate_thumbnails_action'), 15, 2 );
			
			// delete image and thumbnails of deleted terms
			add_action( "delete_term", array($this, 'delete_term'), 5, 3 );
			
			// add images column to terms list-table
			add_filter( "manage_edit-{$taxonomy}_columns", array($this, 'edit_columns') );
			add_filter( "manage_{$taxonomy}_custom_column", array($this, 'columns'), 10, 3 );
		
		}
		
		add_action ( 'wp_ajax_delete_term_image', array($this, 'ajax_delete_term_image') );
				
	}
	
	/**
	 * Css and script on the admin terms forms
	 * Runs on the admin_head-edit-tags.php action hook
	 */
	public function admin_head () {
		
		if( empty( $_GET['taxonomy'] ) || ! self::has_support( $_GET['taxonomy'] ) ) return;
		
		
		if( isset($_GET['tag_ID']) && $term_id = (int) $_GET['tag_ID'] ) :
		?>
<script type="text/javascript">
<!--
	jQuery(document).ready(function($) {

		var nonce = '<?php echo wp_create_nonce( 'delete_term_image' ) ?>';

		$('#delete-thumb-button').click( 
			function () {
				$.post( ajaxurl, {term_id: <?php echo esc_js($term_id) ?>, taxonomy: '<?php echo esc_js($_GET['taxonomy']) ?>', action: 'delete_term_image', _nonce: nonce}, function (data) { if(data == '1') $('#term_thumbnail').hide('slow'); }  ); 
			}
		);
		
	});
//-->
</script><?php 
		endif;
		?>
<style type="text/css">
<!--
th#image {width: 55px}
.attachment-admin-thumbnail { border: 1px solid #ccc; padding: 3px; }
-->
</style><?php
		
	}
	
	/**
	 * Shows errors in admin
	 * Runs on the admin_notices action hook
	 */
	public function admin_notice () {
		
		if( empty($_GET['term_image_error']) ) return;
		
		$error = unserialize(base64_decode($_GET['term_image_error']));
		
		if( ! is_wp_error($error) ) return;
		
		echo '<div class="error">
	    	  <p><strong>' . __('Image upload error: ', 'gecka_terms_ordering') . '</strong>' . $error->get_error_message() . '</p>
	    	  </div>';
	}
	
	/**
	 * On wp_redirect, we add/remove our errors var as needed
	 * @param string $location
	 * Runs on the wp_redirect filter hook
	 */
	public function wp_redirect ($location) {
		
		$location = remove_query_arg('term_image_error', $location);
		
		if ( ! $this->error ) return $location;
		
		$location = add_query_arg( 'term_image_error', urlencode( base64_encode( serialize( $this->error ) ) ) , $location );

		return $location;
	}	
	
	/**
	 * Adds a field to the "Add term" form
	 * @param string $taxonomy
	 * Runs on the {$taxonomy}_add_form_fields action hook
	 */
	public function add_field ( $taxonomy ) {
		
		?>
		<div class="form-field">
			<label for="tag-description"><?php _e('Image', 'Gecka_Terms_Thumbnails') ?></label>
			
			<p><?php _e( 'Once you have added this new term, edit it to set its image.', 'gecka-terms-thumbnails' ); ?></p>
		</div>
		<?php
	}
	
	/**
	 * Adds a field to the "Edit term" form
	 * @param string $taxonomy
	 * Runs on the {$taxonomy}_edit_form_fields action hook
	 */
	public function edit_field ( $term, $taxonomy ) {
		
		$term_id = $term->term_id;
		
		$current = self::get_term_image_infos( $term_id, $taxonomy );
		$upload_size_unit = $max_upload_size =  wp_max_upload_size();
		$sizes = array( 'KB', 'MB', 'GB' );
		for ( $u = -1; $upload_size_unit > 1024 && $u < count( $sizes ) - 1; $u++ )
			$upload_size_unit /= 1024;
		if ( $u < 0 ) {
			$upload_size_unit = 0;
			$u = 0;
		} else {
			$upload_size_unit = (int) $upload_size_unit;
		}
		
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label for="image"><?php _ex('Image', 'Taxonomy Image'); ?></label></th>
			<td>
			<?php  if( has_term_thumbnail($term_id, $taxonomy) ) : ?>
				
			<div id="term_thumbnail">
				<p class="description"><?php printf( __( 'You already have an image defined. You can delete it or replace. To keep it, ignore the following fields.' ), $upload_size_unit, $sizes[$u] ); ?></p>
				<br>
				<?php the_term_thumbnail($term_id, $taxonomy, 'admin-thumbnail', array('style'=>'float:left; border: 1px solid #ccc; margin-right: 10px; padding: 3px; ')); ?>
				
				<input type="button" id="delete-thumb-button" value="Delete the current image" class="button-secondary action" style="width: auto">
				<br><br>
			</div>
			
			<?php endif; ?>
			<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_upload_size ?>" />
			<input type="file" id="image" name="image" style="width: 97%;" class="button-secondary action"><br />
			
			<span class="description"><?php printf( __( 'Maximum upload file size: %d%s' ), $upload_size_unit, $sizes[$u] ); ?></span>

		</tr>
		<script type="text/javascript">
		<!--
		jQuery('#edittag').attr('enctype', 'multipart/form-data').attr('encoding', 'multipart/form-data');
		//-->
		</script>
		<?php
	}
	
	/**
	 * Process the thumbnial upload
	 * Runs on the edited_$taxonomy action hook
	 * @param int $term_id
	 * @param int $tt_id
	 * @return stdClass|boolean:
	 */
	public function process_upload ( $term_id, $tt_id ) {

		// get the taxonomy and check that it supports images
		if( empty( $_POST['taxonomy'] ) || ! self::has_support( $_POST['taxonomy'] ) ) return;

		$taxonomy = $_POST['taxonomy'];
		if( ! self::has_support( $taxonomy ) ) return $term;
		
		$file = isset($_FILES['image']) ? $_FILES['image'] : null;
		if( ! $file ) return $term;

		/* create the taxonomy directory if needed */
		if( ! $dir = self::images_mkdir($taxonomy) ) return $this->upload_error( $file, "Permission error creating the terms-images/{taxonomy} folder." );

		// Courtesy of php.net, the strings that describe the error indicated in $_FILES[{form field}]['error'].
		$upload_error_strings = array( false,
			__( "The uploaded file exceeds the <code>upload_max_filesize</code> directive in <code>php.ini</code>." ),
			__( "The uploaded file exceeds the <em>MAX_FILE_SIZE</em> directive that was specified in the HTML form." ),
			__( "The uploaded file was only partially uploaded." ),
			__( "No file was uploaded." ),
			'',
			__( "Missing a temporary folder." ),
			__( "Failed to write file to disk." ),
			__( "File upload stopped by extension." ));
		
		if ( $file['error'] > 0 && $file['error'] !== 4 )
			return $this->upload_error( $file, $upload_error_strings[$file['error']] );
					
		if ( isset( $file['error'] ) && ! is_numeric( $file['error'] ) && $file['error'] && $file['error'] !== 4)
			return $this->upload_error( $file, $file['error'] );
			
		// A non-empty file will pass this test.
		if ( ! ($file['size'] > 0 ) ) return $term;
		
		// A properly uploaded file will pass this test.
		if ( ! @ is_uploaded_file( $file['tmp_name'] ) )
			return $this->upload_error($file, __( 'Specified file failed upload test.' ));
			
		// mime check
		$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::$mimes );
		extract( $wp_filetype );

		if ( ( !$type || !$ext ) && !current_user_can( 'unfiltered_upload' ) )
			return $this->upload_error($file, __( 'Sorry, this file type is not permitted for security reasons.' ));

		/* delete old image if it exists */
		if( false === self::remove_term_image($term_id, $taxonomy) )  {
			@ unlink($new_file);
			return $this->upload_error($file, __( 'An error occured when trying to remove the old image.', 'gecka-terms-ordering' ));
		}		
		
		if ( $proper_filename )
			$file['name'] = $proper_filename;
		
		if ( !$ext )
			$ext = ltrim(strrchr($file['name'], '.'), '.');

		if ( !$type )
			$type = $file['type'];
	
		/* get a unique filename */
			
		
		$filename = wp_unique_filename( $dir, $file['name'] );
		
		// Move the file to the uploads dir
		$new_file = $dir . "/$filename";
				
		/* moves uploaded file */
		if ( false === @ move_uploaded_file( $file['tmp_name'], $new_file ) )
			return
			
		$file_infos = array();
		$file_infos ['name'] = $filename;
		$file_infos ['size'] = $file['size'];
		$file_infos ['path'] = $new_file;
		$file_infos ['url'] = self::images_url() . '/' . $taxonomy . '/' . $filename;
		$file_infos ['type'] = $type;
		$file_infos ['ext']  = $ext;
		$file_infos ['infos'] = getimagesize($new_file);
		$file_infos ['thumbnails'] = array();		

		self::update_term_image_infos($term_id, $taxonomy, $file_infos);

	}
	
	/**
	 * Denerates the thumbnails of a saved term
	 * Runs on the edited_$taxonomy action hook
	 * @param unknown_type $term_id
	 * @param unknown_type $tt_id
	 */
	public function generate_thumbnails_action ($term_id, $tt_id) {
		
		$taxonomy = !empty($_POST['taxonomy']) ? $_POST['taxonomy'] : 'category';
		
		if( ! self::has_support($taxonomy) ) return;
		
		self::generate_thumbnails($term_id, $taxonomy);
	}
	
	/**
	 * Remove term image on deletetion of that term
	 * Runs on the delete_term action hook
	 * @param int $term
	 * @param int $tt_id
	 * @param string $taxonomy
	 */
	public function delete_term ( $term, $tt_id, $taxonomy ) {
		self::remove_term_image($term, $taxonomy);
	}
	
	/**
	 * Adds a new column to taxonomies supporting thumbnails
	 * Runs on the manage_edit-{$taxonomy}_columns action hook
	 */
	public function edit_columns ($columns) {
	    unset( $columns["cb"] );
	    
	    $custom_array = array(
	        'cb' => '<input type="checkbox" />',
	        'image' => __( 'Image' )
	    );
	    
	    $columns = array_merge( $custom_array, $columns );
	    
	    return $columns;
	}
	
	/**
	 * Handles the thumbnial column content
	 * Runs on the manage_{$taxonomy}_columns action hook
	 */
	public function columns ($null, $column_name, $term_id) {
		
		$taxonomy = isset( $_GET['taxonomy'] ) ? $_GET['taxonomy'] : '';
		
		if( !$taxonomy ) return '';
		
		switch ( $column_name ) {
			case 'image':
				return get_the_term_thumbnail( $term_id, $taxonomy, 'admin-thumbnail' );	
				break;
		}
		
		return '';
	}
	
	/**
	 * Handles httpr term image deletion
	 * Runs on the wp_ajax_delete_term_image action hook
	 */
	public function ajax_delete_term_image () {

		$term_id = isset($_POST['term_id']) && (int) $_POST['term_id'] ? (int) $_POST['term_id'] : '';
		$taxonomy = isset($_POST['taxonomy']) && $_POST['taxonomy'] ? $_POST['taxonomy'] : '';
		
		if( ! get_taxonomy($taxonomy) ) die(0);
		
		if( ! $term_id || ! wp_verify_nonce( $_POST['_nonce'], 'delete_term_image') ) die(0);
		
		self::remove_term_image($term_id, $taxonomy);
		
		die('1');		
	}	
	
	/**
	 * Handles upload errors
	 * @param array $file
	 * @param $message $message
	 */
	private function upload_error( &$file, $message ) {
		$this->error = new WP_Error('invalid_upload', $message, $file);
		
		return false;
	}
	
}

if( ! function_exists('add_term_thumbnails_support') ) {
	/**
	 * Adds thumbnails support for the provided taxonomy
	 * @param string $taxonomy
	 */
	function add_term_thumbnails_support ($taxonomy) {
		Gecka_Terms_Thumbnails::add_taxonomy_support($taxonomy);
	} 
}

if( ! function_exists('remove_term_thumbnails_support') ) {
	/**
	 * Removes thumbnails support for the provided taxonomy
	 * @param string $taxonomy
	 */
	function remove_term_thumbnails_support ($taxonomy) {
		Gecka_Terms_Thumbnails::remove_taxonomy_support($taxonomy);
	} 
}

if( ! function_exists('has_term_thumbnails_support') ) {
	/**
	 * Checks if the provided taxonomy has thumbnails support
	 * @param string $taxonomy
	 * @return bool true if the taxonomy has thumbnial support, false otherwise
	 */
	function has_term_thumbnails_support ($taxonomy) {
		return Gecka_Terms_Thumbnails::has_support($taxonomy);
	} 
}

if( ! function_exists('add_term_image_size') ) {
	/**
	 * Adds a term image size
	 * @param string $name the thumbnail size name for reference
	 * @param unknown_type $width 
	 * @param unknown_type $height
	 * @param unknown_type $crop
	 */
	function add_term_image_size ( $name, $width = 0, $height = 0, $crop = false ) {
		return Gecka_Terms_Thumbnails::add_image_size ( $name, $width, $height, $crop );
	} 
}

if( ! function_exists('set_term_thumbnail') ) {
	/**
	 * Sets the default thumbnail size
	 * @param unknown_type $width
	 * @param unknown_type $height
	 * @param unknown_type $crop
	 */
	function set_term_thumbnail ( $width = 0, $height = 0, $crop = false ) {
		return Gecka_Terms_Thumbnails::set_thumbnail( $width, $height, $crop );
	} 
}

if( ! function_exists('has_term_thumbnail') ) {
	/**
	 * Checks if the secified term has a thumbnail image for the specified taxonomy and size
	 * @param int $term_id the term ID
	 * @param string $taxonomy the taxonomy name 
	 * @param string $size the thumbnail size
	 */
	function has_term_thumbnail ( $term_id, $taxonomy, $size=null ) {
		return Gecka_Terms_Thumbnails::has_term_thumbnail( $term_id, $taxonomy, $size=null );	
	}
}

if( ! function_exists('the_term_thumbnail') ) {
	/**
	 * Prints the specified term's thumbnail HTML code for the specified taxonomy and size
	 * @param int $term_id the term ID
	 * @param string $taxonomy the taxonomy name
	 * @param string $size the thumbnail size
	 * @param array $attr additionnal attributes
	 */
	function the_term_thumbnail ( $term_id, $taxonomy, $size = 'thumbnail', $attr = '') {
		echo Gecka_Terms_Thumbnails::get_the_term_thumbnail( $term_id, $taxonomy, $size, $attr );	
	}
}

if( ! function_exists('get_the_term_thumbnail') ) {
	/**
	 * Returns the specified term's thumbnail HTML code for the specified taxonomy and size
	 * @param int $term_id the term ID
	 * @param string $taxonomy the taxonomy name
	 * @param string $size the thumbnail size
	 * @param array $attr additionnal attributes
	 */
	function get_the_term_thumbnail ( $term_id, $taxonomy, $size = 'thumbnail', $attr = '' ) {
		return Gecka_Terms_Thumbnails::get_the_term_thumbnail( $term_id, $taxonomy, $size, $attr );	
	}
}

if( ! function_exists('get_term_thumbnail') ) {
	/**
	 * Returns the specified term's thumbnail for the specified taxonomy and size
	 * @param int $term_id the term ID
	 * @param string $taxonomy the taxonomy name
	 * @param string $size the thumbnail size
	 * @param array $attr additionnal attributes
	 */
	function get_term_thumbnail ($term_id, $size) {
		return Gecka_Terms_Thumbnails::get_term_thumbnail($term_id, $taxonomy, $size);	
	}
}

if ( ! function_exists('wp_list_terms') ) {
	/**
	 * Returns an HTML list of terms
	 * @param array $args
	 * @return Ambigous <string, boolean, mixed>
	 */
	function wp_list_terms ( $args ) {
		
		wp_parse_args($args);
		if( ! isset($args['walker']) || ! is_a( $args['walker'], 'Walker') ) $args['walker'] = new Walker_Term();
		
		return wp_list_categories( $args );
	}
}

/**
 * Create HTML list of categories.
 *
 * @package WordPress
 * @since 2.1.0
 * @uses Walker
 */
class Walker_Term extends Walker_Category {

	/**
	 * @see Walker::start_el()
	 * @since 2.1.0
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $category Category data object.
	 * @param int $depth Depth of category in reference to parents.
	 * @param array $args
	 */
	function start_el(&$output, $category, $depth, $args) {
		extract($args);

		$cat_name = esc_attr( $category->name );
		$cat_name = apply_filters( 'list_cats', $cat_name, $category );
		
		$link = '<a href="' . esc_attr( get_term_link($category) ) . '" ';
		if ( $use_desc_for_title == 0 || empty($category->description) )
			$link .= 'title="' . esc_attr( sprintf(__( 'View all posts filed under %s' ), $cat_name) ) . '"';
		else
			$link .= 'title="' . esc_attr( strip_tags( apply_filters( 'category_description', $category->description, $category ) ) ) . '"';
		$link .= '>';

		if( !empty($args['show_thumbnail']) && has_term_thumbnail($category->term_id, $category->taxonomy, $args['show_thumbnail']) ) {
			
			if( ! empty($args['thumbnail_position']) && $args['thumbnail_position'] === 'inside' ) 
				$link .= get_the_term_thumbnail($category->term_id, $category->taxonomy, $args['show_thumbnail']);
		
			else
				$link = $link . get_the_term_thumbnail($category->term_id, $category->taxonomy, $args['show_thumbnail']). '</a> ' . $link;
		}		
		
		$link .= $cat_name .'</a>';

		if ( !empty($feed_image) || !empty($feed) ) {
			$link .= ' ';

			if ( empty($feed_image) )
				$link .= '(';

			$link .= '<a href="' . get_term_feed_link( $category->term_id, $category->taxonomy, $feed_type ) . '"';

			if ( empty($feed) ) {
				$alt = ' alt="' . sprintf(__( 'Feed for all posts filed under %s' ), $cat_name ) . '"';
			} else {
				$title = ' title="' . $feed . '"';
				$alt = ' alt="' . $feed . '"';
				$name = $feed;
				$link .= $title;
			}

			$link .= '>';

			if ( empty($feed_image) )
				$link .= $name;
			else
				$link .= "<img src='$feed_image'$alt$title" . ' />';

			$link .= '</a>';

			if ( empty($feed_image) )
				$link .= ')';
		}

		if ( !empty($show_count) )
			$link .= ' (' . intval($category->count) . ')';

		if ( !empty($show_date) )
			$link .= ' ' . gmdate('Y-m-d', $category->last_update_timestamp);

		if ( 'list' == $args['style'] ) {
			$output .= "\t<li";
			$class = 'cat-item cat-item-' . $category->term_id;
			if ( !empty($current_category) ) {
				$_current_category = get_term( $current_category, $category->taxonomy );
				if ( $category->term_id == $current_category )
					$class .=  ' current-cat';
				elseif ( $category->term_id == $_current_category->parent )
					$class .=  ' current-cat-parent';
			}
			$output .=  ' class="' . $class . '"';
			$output .= ">$link\n";
		} else {
			$output .= "\t$link<br />\n";
		}
	}


}

	
