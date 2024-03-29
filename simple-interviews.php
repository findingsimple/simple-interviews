<?php
/*
Plugin Name: Simple Interviews
Plugin URI: http://plugins.findingsimple.com
Description: Build a library of interviews.
Version: 1.0
Author: Finding Simple ( Jason Conroy & Brent Shepherd )
Author URI: http://findingsimple.com
License: GPL2
*/
/*
Copyright 2012  Finding Simple  (email : plugins@findingsimple.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'Simple_Interviews' ) ) :

/**
 * So that themes and other plugins can customise the text domain, the Simple_Interviews
 * should not be initialized until after the plugins_loaded and after_setup_theme hooks.
 * However, it also needs to run early on the init hook.
 *
 * @author Jason Conroy <jason@findingsimple.com>
 * @package Simple Interviews
 * @since 1.0
 */
function initialize_interviews(){
	Simple_Interviews::init();
}
add_action( 'init', 'initialize_interviews', -1 );

/**
 * Plugin Main Class.
 *
 * @package Simple Interviews
 * @author Jason Conroy <jason@findingsimple.com>
 * @since 1.0
 */
class Simple_Interviews {

	static $text_domain;

	static $post_type_name;

	static $admin_screen_id;
	
	/**
	 * Initialise
	 */
	public static function init() {
		global $wp_version;

		self::$text_domain = apply_filters( 'simple_interviews_text_domain', 'Simple_Interviews' );

		self::$post_type_name = apply_filters( 'simple_interviews_post_type_name', 'simple_interview' );

		self::$admin_screen_id = apply_filters( 'simple_interviews_admin_screen_id', 'simple_interview' );

		add_action( 'init', array( __CLASS__, 'register' ) );
		
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );
		
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		
		add_action( 'save_post', array( __CLASS__, 'save_meta' ), 10, 1 );
		
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_styles_and_scripts' ) );
		
		add_shortcode( 'quote', array( __CLASS__, 'shortcode_interview') );
		
		register_widget('WP_Widget_Interview');
		
		add_image_size( 'interview-admin-thumb', 60, 60, false );

		add_filter( 'manage_edit-' . self::$post_type_name . '_columns' , array( __CLASS__, 'add_thumbnail_column') , 10 );
		
		add_action( 'manage_' . self::$post_type_name . '_posts_custom_column' , array( __CLASS__, 'thumbnail_column_contents') , 10, 2 );

		add_filter( 'enter_title_here', __CLASS__ . '::change_default_title' );

		add_filter( 'admin_post_thumbnail_html', __CLASS__ . '::change_featured_image_metabox_text' );

		add_filter( 'gettext', __CLASS__ . '::change_featured_image_link_text' );

		add_action( 'add_meta_boxes_' . self::$post_type_name, __CLASS__ . '::rename_featured_image_metabox' );

		add_filter( 'image_size_names_choose', __CLASS__ . '::remove_image_size_options' );
		
	}

	/**
	 * Register the post type
	 */
	public static function register() {
		
		$labels = array(
			'name' => _x('Interviews', 'post type general name', self::$text_domain ),
			'singular_name' => _x('Interview', 'post type singular name', self::$text_domain ),
			'add_new' => _x('Add New', 'Interview', self::$text_domain ),
			'add_new_item' => __('Add New Interview', self::$text_domain ),
			'edit_item' => __('Edit Interview', self::$text_domain ),
			'new_item' => __('New Interview', self::$text_domain ),
			'view_item' => __('View Interview', self::$text_domain ),
			'search_items' => __('Search Interviews', self::$text_domain ),
			'not_found' =>  __('No interviews found', self::$text_domain ),
			'not_found_in_trash' => __('No interviews found in Trash', self::$text_domain ),
			'parent_item_colon' => ''
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'query_var' => true,
			'has_archive' => true,
			'rewrite' => array( 'slug' => 'interviews', 'with_front' => false ),
			'capability_type' => 'post',
			'hierarchical' => false,
			'menu_position' => null,
			'taxonomies' => array(''),
			'supports' => array('title', 'editor', 'thumbnail', 'custom-fields', 'excerpt')
		); 

		register_post_type( self::$post_type_name , $args );
	}

	/**
	 * Filter the "post updated" messages
	 *
	 * @param array $messages
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		global $post;

		$messages[ self::$post_type_name ] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => sprintf( __('Interview updated. <a href="%s">View interview</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			2 => __('Custom field updated.', self::$text_domain ),
			3 => __('Custom field deleted.', self::$text_domain ),
			4 => __('Interview updated.', self::$text_domain ),
			/* translators: %s: date and time of the revision */
			5 => isset($_GET['revision']) ? sprintf( __('Interview restored to revision from %s', self::$text_domain ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => sprintf( __('Interview published. <a href="%s">View interview</a>', self::$text_domain ), esc_url( get_permalink($post->ID) ) ),
			7 => __('Interview saved.', self::$text_domain ),
			8 => sprintf( __('Interview submitted. <a target="_blank" href="%s">Preview interview</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
			9 => sprintf( __('Interview scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview interview</a>', self::$text_domain ),
			  // translators: Publish box date format, see http://php.net/date
			  date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post->ID) ) ),
			10 => sprintf( __('Interview draft updated. <a target="_blank" href="%s">Preview interview</a>', self::$text_domain ), esc_url( add_query_arg( 'preview', 'true', get_permalink($post->ID) ) ) ),
		);

		return $messages;
	}

	/**
	 * Enqueues the necessary scripts and styles for the plugins
	 *
	 * @since 1.0
	 */
	public static function enqueue_admin_styles_and_scripts() {
				
		if ( is_admin() ) {
	
			wp_register_style( 'simple-interviews', self::get_url( '/css/simple-interviews-admin.css', __FILE__ ) , false, '1.0' );
			wp_enqueue_style( 'simple-interviews' );
		
		}
		
	}
	
	/**
	 * Add the interview meta box
	 *
	 * @wp-action add_meta_boxes
	 */
	public static function add_meta_box() {
		add_meta_box( 'interview-meta', __( 'Interview Meta', self::$text_domain  ), array( __CLASS__, 'do_meta_box' ), self::$post_type_name , 'normal', 'high' );
	}

	/**
	 * Output the interview meta box HTML
	 *
	 * @param WP_Post $object Current post object
	 * @param array $box Metabox information
	 */
	public static function do_meta_box( $object, $box ) {
		wp_nonce_field( basename( __FILE__ ), 'interview-meta' );
?>

		<p>
			<label for="interview-date"><?php _e( 'Interview Date:', self::$text_domain ); ?></label>
			<br />
			<input type="text" name="interview-date" id="interview-date"
				value="<?php echo esc_attr( get_post_meta( $object->ID, '_interview-date', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
		</p>

		<p>
			<label for="interviewee-name"><?php _e( 'Interviewee Name:', self::$text_domain ); ?></label>
			<br />
			<input type="text" name="interviewee-name" id="interviewee-name"
				value="<?php echo esc_attr( get_post_meta( $object->ID, '_interviewee-name', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
		</p>

		<p>
			<label for="interviewee-company"><?php _e( 'Interviewee Company:', self::$text_domain ); ?></label>
			<br />
			<input type="text" name="interviewee-company" id="interviewee-company"
				value="<?php echo esc_attr( get_post_meta( $object->ID, '_interviewee-company', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
		</p>
		
		<p>
			<label for="interviewer-name"><?php _e( 'Interviewer Name:', self::$text_domain ); ?></label>
			<br />
			<input type="text" name="interviewer-name" id="interviewer-name"
				value="<?php echo esc_attr( get_post_meta( $object->ID, '_interviewer-name', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
			<p><span style="color:#aaa;">Can be used within theme templates instead of the WP author/user assigned to the post.</span></p>
		</p>

		<p>
			<label for="interviewer-company"><?php _e( 'Interviewer Company:', self::$text_domain ); ?></label>
			<br />
			<input type="text" name="interviewer-company" id="interviewer-company"
				value="<?php echo esc_attr( get_post_meta( $object->ID, '_interviewer-company', true ) ); ?>"
				size="30" tabindex="30" style="width: 99%;" />
			<p><span style="color:#aaa;">Can be used within theme templates for additional information about the interview.</span></p>
		</p>

<?php
	}

	/**
	 * Save the interview metadata
	 *
	 * @wp-action save_post
	 * @param int $post_id The ID of the current post being saved.
	 */
	public static function save_meta( $post_id ) {

		/* Verify the nonce before proceeding. */
		if ( !isset( $_POST['interview-meta'] ) || !wp_verify_nonce( $_POST['interview-meta'], basename( __FILE__ ) ) )
			return $post_id;

		$meta = array(
			'interview-date',
			'interviewee-name',
			'interviewee-company',
			'interviewer-name',
			'interviewer-company'
		);

		foreach ( $meta as $meta_key ) {
			$new_meta_value = $_POST[$meta_key];

			/* Get the meta value of the custom field key. */
			$meta_value = get_post_meta( $post_id, '_' . $meta_key , true );

			/* If there is no new meta value but an old value exists, delete it. */
			if ( '' == $new_meta_value && $meta_value )
				delete_post_meta( $post_id, '_' . $meta_key , $meta_value );

			/* If a new meta value was added and there was no previous value, add it. */
			elseif ( $new_meta_value && '' == $meta_value )
				add_post_meta( $post_id, '_' . $meta_key , $new_meta_value, true );

			/* If the new meta value does not match the old value, update it. */
			elseif ( $new_meta_value && $new_meta_value != $meta_value )
				update_post_meta( $post_id, '_' . $meta_key , $new_meta_value );
		}
	}

	/**
	 * Build interview shortcode.
	 *
	 * @since 1.0
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Interviews
	 *
	 */
	 
	public static function shortcode_interview( $atts, $content = null ) {
	
		extract( shortcode_atts( 
			array(	'id' => ''
			) , $atts)
		);
		
		$content = '';
	
		return self::interviews_remove_wpautop( $content );
	
	}

	/**
	 * Replaces WP autop formatting 
	 *
	 * @since 1.0
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Interviews
	 */
	public static function interviews_remove_wpautop($content) { 
		$content = do_shortcode( shortcode_unautop( $content ) ); 
		$content = preg_replace( '#^<\/p>|^<br \/>|<p>$#', '', $content);
		return $content;
	}
	
	/**
	 * Helper function to get the URL of a given file. 
	 * 
	 * As this plugin may be used as both a stand-alone plugin and as a submodule of 
	 * a theme, the standard WP API functions, like plugins_url() can not be used. 
	 *
	 * @since 1.0
	 * @return array $post_name => $post_content
	 */
	public static function get_url( $file ) {

		// Get the path of this file after the WP content directory
		$post_content_path = substr( dirname( str_replace('\\','/',__FILE__) ), strpos( __FILE__, basename( WP_CONTENT_DIR ) ) + strlen( basename( WP_CONTENT_DIR ) ) );

		// Return a content URL for this path & the specified file
		return content_url( $post_content_path . $file );
	}	
	
	/**
	 * Add a column to the manage pages page to display the interviewee thumbnail. 
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 * @package Simple Interviews
	 */
	public static function add_thumbnail_column( $columns ) {
	
  		$columns_start = array_slice( $columns, 0, 1, true );
  		$columns_end   = array_slice( $columns, 1, null, true );

  		$columns = array_merge(
    		$columns_start,
    		array( 'logo' => __( '', self::$text_domain ) ),
    		$columns_end
  		);
	
		return $columns;
		
	}	
	
	/**
	 * Add the interviewee thumbnail to the custom column on the manage page.
	 * 
	 * @since 1.0
	 * @author Jason Conroy
	 * @package Simple Interviews
	 */
	function thumbnail_column_contents( $column_name, $post_id ) {
				
		if ( $column_name != 'logo' )
			return;
				
		if ( function_exists('the_post_thumbnail') )
			echo '<a href="' . get_edit_post_link( $post_id ) . '" title="' . __( 'Edit Sponsor', self::$text_domain ) . '">' . get_the_post_thumbnail( $post_id, 'interview-admin-thumb' ) . '</a>';
					
	}

	/**
	 * Replaces the "Enter title here" text
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Interviews
	 * @since 1.0
	 */
	public static function change_default_title( $title ){
		$screen = get_current_screen();

		if  ( self::$post_type_name == $screen->post_type )
			$title = __( 'Enter Interview Title', self::$text_domain );

		return $title;
	}
	
	/**
	 * Replaces the 'Featured Image' label with 'Interview Thumbnail' on the Edit page for the simple_interview post type.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Interviews
	 * @since 1.0
	 */
	public static function change_featured_image_metabox_text( $metabox_html ) {

		if ( get_post_type() == self::$post_type_name )
			$metabox_html = str_replace( 'featured image', esc_attr__( 'interview thumbnail', self::$text_domain ), $metabox_html );

		return $metabox_html;
		
	}


	/**
	 * Changes the 'Use as featured image' link text on the media panel
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Interviews
	 * @since 1.0
	 */
	public static function change_featured_image_link_text( $text ) {
		global $post;

		if ( $text == 'Use as featured image' ) {

			if ( isset( $_GET['post_id'] ) )
				$calling_post_id = absint( $_GET['post_id'] );
			elseif ( isset( $_POST ) && count( $_POST ) && is_object( $post ) )
				$calling_post_id = $post->post_parent;
			else
				$calling_post_id = 0;

			if ( get_post_type( $calling_post_id ) == self::$post_type_name )
				$text = __( "Use as the interview thumbnail", self::$text_domain );

		}

		return $text;
	}


	/**
	 * Renames the "Featured Image" metabox to "Interview Thumbnail"
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Interviews
	 * @since 1.0
	 */
	public static function rename_featured_image_metabox() {

		remove_meta_box( 'postimagediv', self::$post_type_name, 'side' );

		add_meta_box( 'postimagediv', __( "Interview Thumbnail", self::$text_domain ), 'post_thumbnail_meta_box', self::$post_type_name, 'side', 'low' );

	}	

	/**
	 * Remove admin thumbnail size from the list of available sizes in the media uploader
	 *
	 * @author Jason Conroy <jason@findingsimple.com>
	 * @package Simple Interviews
	 * @since 1.0
	 */	
	public static function remove_image_size_options( $sizes ){
	 
		unset($sizes['interview-admin-thumb']);
		
		return $sizes;
	 
	}

}

endif;

/**
 * Quote widget class
 *
 * @since 1.0
 */
class WP_Widget_Interview extends WP_Widget {

	function __construct() {
	
		$widget_ops = array('classname' => 'widget_interview', 'description' => __('Display an interview'));
		
		$control_ops = array('width' => 400, 'height' => 350);
		
		parent::__construct('interview', __('Interview'), $widget_ops, $control_ops);
		
	}

	function widget( $args, $instance ) {
		
		$cache = get_transient( 'widget_simple_interviews' );
				
		if ( ! is_array( $cache ) )
			$cache = array();

		if ( ! isset( $args['widget_id'] ) )
			$args['widget_id'] = $this->id;
		
		if ( isset( $cache[ $args['widget_id'] ] ) ) {
			echo $cache[ $args['widget_id'] ];
			return;
		}	
	
		extract($args);
		
		$output = '';
		
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		
		if ( ! empty( $instance['ids'] ) )
			$ids = split(',' , str_replace (" ", "", $instance['ids'] ) );
		
		if ( empty( $instance['number'] ) || ! $number = absint( $instance['number'] ) )
 			$number = 5;
		
		//default args
		$query_args = array(
			'post_type' => 'simple_quote',
			'posts_per_page' => $number
		);
		
		//if ids set get specific ids and remove posts_per_page limit
		if ( !empty ( $ids )  ) {
			$query_args['post__in'] = $ids;
			$query_args['posts_per_page'] = -1;
		}
		
		if ( !empty ( $instance['randomize'] ) ) {
			$query_args['orderby'] = 'rand';
		}
		
		//run query
		$quotes = get_posts( $query_args );
				
		// If user has entered a list of IDs display in the order entered
		if ( empty ( $instance['randomize'] ) && !empty ( $ids ) ) {
		
			$sorted_list = array();
		
			foreach( $ids as $id ) :
				foreach( $quotes as $quote ) :		
					
					if( $quote->ID == $id )
						$sorted_list[] = $quote;			
					
				endforeach;
			endforeach;
		
			$quotes = $sorted_list;
		
		}
		
		$output .= $before_widget;
		
		if ( !empty( $title ) ) $output .= $before_title . $title . $after_title; 
		
		$count = 1;
		
		if ( !empty ( $quotes ) ) :
		 
			$output .= '<div class="quotewidget">';
			
			foreach( $quotes as $quote ) : 
			
				$output .= '<blockquote>';
				
				if ( !empty( $instance['curly-quotes'] ) )
					$output .= '<span class="blockquote-open">&#8220;</span>';
				
				$output .= apply_filters( 'the_content', $quote->post_content ); 

				if ( !empty( $instance['curly-quotes'] ) )
					$output .= '<span class="blockquote-close">&#8221;</span>';
				
				$output .= '</blockquote><!-- blockquote -->';
				
				if ( !empty( $instance['source-link'] ) )
					$output .= '<cite class="source">' . Simple_Quotes::get_source_link( $quote->ID ) . '</cite><!-- cite -->';
				else
					$output .= '<cite class="source">' . Simple_Quotes::get_source( $quote->ID ) . '</cite><!-- cite -->';					
					
			endforeach;
			
			if ( !empty( $instance['archive-link'] ) )
				$output .= '<a href="' . get_post_type_archive_link( Simple_Quotes::$post_type_name ) . '" title="' . __('Read more quotes', Simple_Quotes::$text_domain ) . '" class="read-more">' . __('Read more quotes', Simple_Quotes::$text_domain ) . '</a>';
		
			$output .= '</div><!-- .quotewidget -->';
		
		endif; //end if !empty ( $quotes );
		
		$output .= $after_widget;
		
		echo $output;
		
		//cache output
		$cache[ $args['widget_id'] ] = $output;
		
		set_transient( 'widget_simple_interviews', $cache, 60*60*12 );
		
	}

	function update( $new_instance, $old_instance ) {
	
		$instance = $old_instance;
		
		$instance['title'] = strip_tags($new_instance['title']);
				
		$instance['number'] = absint( $new_instance['number'] );

		$instance['ids'] = strip_tags($new_instance['ids']);
		
		$instance['randomize'] = isset($new_instance['randomize']);
		
		$instance['curly-quotes'] = isset($new_instance['curly-quotes']);
		
		$instance['source-link'] = isset($new_instance['source-link']);
		
		$instance['archive-link'] = isset($new_instance['archive-link']);
		
		//flush cache
		delete_transient( 'widget_simple_quotes' );
		
		return $instance;
		
	}

	function form( $instance ) {
	
		$instance = wp_parse_args( (array) $instance, array( 'title' => '', 'ids' => '' ) );
		
		$title = strip_tags($instance['title']);
				
		$number = isset($instance['number']) ? absint($instance['number']) : 5;
		
		$ids = strip_tags($instance['ids']);

?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of quotes to show:'); ?></label>
			<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('ids'); ?>"><?php _e('Quote IDs: (optional - overrides number of quotes above)'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('ids'); ?>" name="<?php echo $this->get_field_name('ids'); ?>" type="text" value="<?php echo esc_attr($ids); ?>" />
		</p>
		<p>
			<input id="<?php echo $this->get_field_id('randomize'); ?>" name="<?php echo $this->get_field_name('randomize'); ?>" type="checkbox" <?php checked(isset($instance['randomize']) ? $instance['randomize'] : 0); ?> />
			&nbsp;<label for="<?php echo $this->get_field_id('randomize'); ?>"><?php _e('Randomize quotes'); ?></label>
		</p>
		<p>
			<input id="<?php echo $this->get_field_id('curly-quotes'); ?>" name="<?php echo $this->get_field_name('curly-quotes'); ?>" type="checkbox" <?php checked(isset($instance['curly-quotes']) ? $instance['curly-quotes'] : 0); ?> />
			&nbsp;<label for="<?php echo $this->get_field_id('curly-quotes'); ?>"><?php _e('Include extra curly quotes spans'); ?></label>
		</p>
		<p>
			<input id="<?php echo $this->get_field_id('source-link'); ?>" name="<?php echo $this->get_field_name('source-link'); ?>" type="checkbox" <?php checked(isset($instance['source-link']) ? $instance['source-link'] : 0); ?> />
			&nbsp;<label for="<?php echo $this->get_field_id('source-link'); ?>"><?php _e('Link source name to source url'); ?></label>
		</p>
		<p>
			<input id="<?php echo $this->get_field_id('archive-link'); ?>" name="<?php echo $this->get_field_name('archive-link'); ?>" type="checkbox" <?php checked(isset($instance['archive-link']) ? $instance['archive-link'] : 0); ?> />
			&nbsp;<label for="<?php echo $this->get_field_id('archive-link'); ?>"><?php _e('Display link to quote archive'); ?></label>
		</p>
<?php
	}
	
}