<?php

/**
 * @package Gecka Terms Thumbnails
 */
class Gecka_Terms_Thumbnails_Settings {

	private static $instance;

	private static $settings;

	private static $defaults = array(
		'term_image_max_w'      => 1024,
		'term_image_max_h'      => 1024,
		'term_thumbnail_size_w' => 50,
		'term_thumbnail_size_h' => 50,
		'term_thumbnail_crop'   => 1,
		'term_medium_size_w'    => 150,
		'term_medium_size_h'    => 150,
		'term_medium_crop'      => 0,
		'use_wp_media'          => false
	);

	private function __construct() {

		foreach ( self::$defaults as $key => $value ) {
			self::$settings[ $key ] = get_option( $key, $value );
		}

		add_action( 'admin_init', array( $this, 'admin_init' ) );

	}

	/***************************************************************************
	 * Static functions
	 **************************************************************************/

	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			$class_name     = __CLASS__;
			self::$instance = new $class_name;
		}

		return self::$instance;

	}

	public function __get( $key ) {
		if ( isset( self::$settings[ $key ] ) ) {
			return self::$settings[ $key ];
		}

		return;
	}

	public function __set( $key, $value ) {
		if ( isset( self::$defaults[ $key ] ) ) {
			self::$settings[ $key ] = $value;
		}

		return;
	}

	public function __save() {
		foreach ( self::$settings as $key => $value ) {
			if ( isset( self::$defaults[ $key ] ) ) {
				update_option( $key, $value );
			}
		}
	}

	/***************************************************************************
	 * Actions and filters hooks
	 **************************************************************************/
	public function admin_init() {

		register_setting( 'media', 'term_image_max_w', 'intval' );
		register_setting( 'media', 'term_image_max_h', 'intval' );
		add_settings_field( 'term_image_max_w', __( 'Term image max size', 'gecka-terms-thumbnails' ), array(
			$this,
			'field_image_max_size'
		), 'media' );

		register_setting( 'media', 'term_thumbnail_size_w', 'intval' );
		register_setting( 'media', 'term_thumbnail_size_h', 'intval' );
		register_setting( 'media', 'term_thumbnail_crop' );
		add_settings_field( 'term_thumbnail_size_w', __( 'Term thumbnail max size', 'gecka-terms-thumbnails' ), array(
			$this,
			'field_thumbnail_max_size'
		), 'media' );

		register_setting( 'media', 'term_medium_size_w', 'intval' );
		register_setting( 'media', 'term_medium_size_h', 'intval' );
		register_setting( 'media', 'term_medium_crop' );
		add_settings_field( 'term_medium_size_w', __( 'Term medium thumbnail max size', 'gecka-terms-thumbnails' ), array(
			$this,
			'field_medium_max_size'
		), 'media' );

	}

	public function field_image_max_size() {

		?>
		<label for="term_image_max_w"><?php _e( 'Max Width' ); ?></label>
		<input name="term_image_max_w" type="text" id="term_image_max_w"
		       value="<?php esc_attr_e( $this->term_image_max_w ); ?>" class="small-text"/>
		<label for="term_image_max_h"><?php _e( 'Max Height' ); ?></label>
		<input name="term_image_max_h" type="text" id="term_image_max_h"
		       value="<?php esc_attr_e( $this->term_image_max_h ); ?>" class="small-text"/>
		<br>
		<span
			class="description"><?php _e( 'If one is set, any uploaded original term image will be proportionally resized to this max width.', 'gecka-terms-thumbnails' ); ?></span>
		<?php

	}

	public function field_thumbnail_max_size() {

		?>
		<label for="term_thumbnail_size_w"><?php _e( 'Max Width' ); ?></label>
		<input name="term_thumbnail_size_w" type="text" id="term_thumbnail_size_w"
		       value="<?php esc_attr_e( $this->term_thumbnail_size_w ); ?>" class="small-text"/>
		<label for="term_thumbnail_size_h"><?php _e( 'Max Height' ); ?></label>
		<input name="term_thumbnail_size_h" type="text" id="term_thumbnail_size_h"
		       value="<?php esc_attr_e( $this->term_thumbnail_size_h ); ?>" class="small-text"/>
		<br>
		<input id="term_thumbnail_crop" type="checkbox" <?php checked( $this->term_thumbnail_crop, 1 ); ?> value="1"
		       name="term_thumbnail_crop">
		<label
			for="term_thumbnail_crop"><?php _e( 'Crop thumbnail to exact dimensions (normally thumbnails are proportional)' ) ?></label>
		<?php

	}

	public function field_medium_max_size() {

		?>
		<label for="term_medium_size_w"><?php _e( 'Max Width' ); ?></label>
		<input name="term_medium_size_w" type="text" id="term_medium_size_w"
		       value="<?php esc_attr_e( $this->term_medium_size_w ); ?>" class="small-text"/>
		<label for="term_medium_size_h"><?php _e( 'Max Height' ); ?></label>
		<input name="term_medium_size_h" type="text" id="term_medium_size_h"
		       value="<?php esc_attr_e( $this->term_medium_size_h ); ?>" class="small-text"/>
		<br>
		<input id="term_medium_crop" type="checkbox" <?php checked( $this->term_medium_crop, 1 ); ?> value="1"
		       name="term_medium_crop">
		<label
			for="term_medium_crop"><?php _e( 'Crop thumbnail to exact dimensions (normally thumbnails are proportional)' ) ?></label>
		<?php

	}

}
