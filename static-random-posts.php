<?php
/*
Plugin Name: Static Random Posts
Plugin URI: https://wordpress.org/plugins/static-random-posts-widget/
Description: This plugin allows the display of random posts, but allows the user to determine how often the random posts are refreshed. 
Author: Ronald Huereca
Version: 2.0
Requires at least: 4.3.0
Author URI: http://www.ronalfy.com/
Text Domain: static-random-posts-widget
Domain Path: /languages
*/ 

if (!class_exists('static_random_posts')) {
    class static_random_posts	extends WP_Widget {		
			var $plugin_url = '';
			
			
			public function __construct() {
				$this->plugin_url = rtrim( plugin_dir_url(__FILE__), '/' );
				
				//Initialization stuff
				add_action('init', array( $this, 'init' ) );
				parent::__construct(
        			'srp_widget', // Base ID
        			__( 'Static Random Posts', 'text_domain' ), // Name
        			array( 'description' => __( 'Select random posts', 'static-random-posts-widget'  ), ) // Args
        		);
				//Create widget
            }
			
			/* init - Run upon WordPress initialization */
			function init() {
                $post_type_args = array(
        			'public' => true,
        			'publicly_queryable' => false,
        			'show_in_menu' => true,
        			'show_ui' => true,
        			'query_var' => true,
        			'rewrite' => false,
        			'has_archive' => false,
        			'hierarchical' => false,
        			'label' => __( 'Random Posts', 'static-random-posts-widget' ),
        			'supports' => array( 'title' )
        		);
        		register_post_type( 'srp_type', $post_type_args );
                load_plugin_textdomain( 'static-random-posts-widget', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
                add_action( 'add_meta_boxes', array( $this, 'meta_box_init' ) );
                
                add_action( 'save_post', array( $this, 'save_post' ) );
			}//end function init
			
			function save_post( $post_id ) {
                if ( wp_is_post_revision( $post_id ) ) {
                    return;
                }
                if ( 'srp_type' != get_post_type() ) {
                    return;
                }
                $post_type = isset( $_POST[ 'srp_post_types' ] ) ? $_POST[ 'srp_post_types' ] : 'none';
                update_post_meta( $post_id, '_srp_post_type', $post_type );
                if ( isset( $_POST[ 'srp_exclude_terms' ] ) ) {
                    $terms = $_POST[ 'srp_exclude_terms' ];
                    $terms_clean = array();
                    foreach( $_POST[ 'srp_exclude_terms' ]  as $term_id ) {
                        $terms_clean[] = absint( $term_id );
                    }
                    update_post_meta( $post_id, '_srp_exclude_terms', $terms_clean );  
                }
                if ( isset( $_POST[ 'srp-hard-refresh' ] ) && $_POST[ 'srp-hard-refresh' ] == '1' ) {
                    $this->get_post_ids(  $post_id, true );
                }
            }
			
			function meta_box_init() {
                add_meta_box( 'srp-post-type', __( 'Post Type', 'static-random-posts-widget' ), array( $this, 'meta_box_post_type' ) );
                
                add_meta_box( 'srp-posts', __( 'Random Posts', 'static-random-posts-widget' ), array( $this, 'meta_box_posts' ) );
                
                add_meta_box( 'srp-tax-types', __( 'Taxonomies and Types to Exclude', 'static-random-posts-widget' ), array( $this, 'meta_box_taxonomy_type' ) );
                
                
            }
            
            public function get_post_ids( $post_id, $force_refresh = false ) {
                $transient = get_transient( 'srp-' . $post_id );
                if ( !$transient || $force_refresh ) {
                    $ids = array();

                     $post_type_meta = get_post_meta( $post_id, '_srp_post_type', true );
                     $excluded_terms = (array)get_post_meta( $post_id, '_srp_exclude_terms', true  );
                     $args = array(
                        'post_type' => $post_type_meta,
                        'category__not_in',    $excluded_terms,
                        'post_status' => 'publish',
                        'orderby' => 'rand',
                        'posts_per_page' => 10
                    );
                    $posts = get_posts( $args );
                    foreach( $posts as $id => $post ) {
                        $ids[] = $post->ID;   
                    }
                    set_transient( 'srp-' . $post_id, $ids, 60 * 60 * 24 );
                    return $ids;
                }
                return $transient;
            }
            
            public function meta_box_posts() {
                global $post;
                $post_id = $post->ID;
                
               $post_ids = $this->get_post_ids( $post_id );
                
                
                ?>
                <div class="widefat">
                    <ol>
                        <?php
                        foreach( $post_ids as $id ) {
                            global $post;
                            $post = get_post( $id );
                            setup_postdata( $post );
                            ?>
                            <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
                            <?php
                        }
                        wp_reset_postdata();
                        ?>
                    </ol>
                    <input type="hidden" name="srp-hard-refresh" value="0">
                    <input type="checkbox" name="srp-hard-refresh" value="1" id="srp-hard-refresh">&nbsp;&nbsp;<label for="srp-hard-refresh"><?php esc_html_e( 'Hard Refresh', 'static-random-posts-widget' ); ?></label>
                </div>
                <?php
            }
            public function meta_box_post_type() {
                global $post;
                $post_id = $post->ID;
                ?>
                <div class="widefat">
                    <p><?php esc_html_e( 'Please choose your post type and hit "Update"', 'static-random-posts-widget' ); ?></p>
                    <select name="srp_post_types">
                        <?php
                        $post_types = get_post_types();
                        $post_type_meta = get_post_meta( $post_id, '_srp_post_type', true );
                        echo '<option value="none">None</option>';
                        foreach( $post_types as $post_type_slug => $post_type ) {
                            if ( $post_type_slug == 'srp_type' ) continue;
                            printf( '<option value="%s" %s>%s</option>', esc_attr( $post_type_slug ), selected( $post_type, $post_type_meta, false ), esc_html( $post_type ) );
                        }
                        ?>
                    </select>
                </div>
                <?php
            }
            
            public function meta_box_taxonomy_type() {
                 global $post;
                $post_id = $post->ID;
                ?>
                <div class="widefat">
                    <?php
                $post_type = get_post_meta( $post_id, '_srp_post_type', true );
                $taxonomies = get_object_taxonomies( $post_type, 'objects' );
                if ( empty( $taxonomies ) ) {
                    printf( '<p>%s</p>', esc_html__( 'There are no taxonomies for this post type.', 'static-random-posts-widget' ) );    
                }
                foreach( $taxonomies as $taxonomy_slug => $taxonomy ) {
                    ?>
                    <h2><?php echo esc_html( $taxonomy->label ); ?></h2>
                    <ul>
                    <?php
                   $excluded_terms = (array)get_post_meta( $post_id, '_srp_exclude_terms', true  );
                   $terms = get_terms( $taxonomy_slug, array( 'hide_empty' => false ) );
                   foreach( $terms as $term ) {
                       printf( '<li><input type="checkbox" value="%1$d" name="srp_exclude_terms[]" data-tax="%2$s", data-term-id="%1$s" id="srp_%1$d" %4$s >&nbsp;&nbsp;<label for="srp_%1$d">%3$s</label></li>', $term->term_id, $taxonomy_slug, $term->name, checked( true, in_array( $term->term_id, $excluded_terms ), false ) );
                   }            
                }
                    ?>
                </div>
                <?php
            }
						
						
			// widget - Displays the widget
			function widget($args, $instance) {
				extract($args, EXTR_SKIP);
				echo $before_widget;
				$post = isset( $instance[ 'post' ] ) ? $instance['post'] : 0;
				if ( !$post || $post == 0 ) {
    			    return;	
                }
                
				$title = empty($instance['title']) ? __('Random Posts', 'static-random-posts-widget') : apply_filters('widget_title', $instance['title']);
				$allow_refresh = isset( $instance[ 'allow_refresh' ] ) ? $instance[ 'allow_refresh' ] : 'false';
				
				if ( !empty( $title ) ) {
					echo $before_title . $title . $after_title;
				};
				echo "<ul class='static-random-posts'>";
                $get_post_ids = $this->get_post_ids( $post ); 
                ?>
                <ul class="static-random-posts">
                        <?php
                        foreach( $get_post_ids as $id ) {
                            global $post;
                            $post = get_post( $id );
                            setup_postdata( $post );
                            ?>
                            <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
                            <?php
                        }
                        wp_reset_postdata();
                        ?>
                    </ul>
                <?php                
				echo $after_widget;
			}
			/**
			* get_plugin_url()
			* 
			* Returns an absolute url to a plugin item
			*
			* @param		string    $path	Relative path to plugin (e.g., /css/image.png)
			* @return		string               An absolute url (e.g., http://www.domain.com/plugin_url/.../css/image.png)
			*/
			function get_plugin_url( $path = '' ) {
				$dir = $this->plugin_url;
				if ( !empty( $path ) && is_string( $path) )
					$dir .= '/' . ltrim( $path, '/' );
				return $dir;	
			} //get_plugin_url
			
			//Updates widget options
			function update($new, $old) {
    			
				$instance = $old;
				$instance['postlimit'] = intval($new['postlimit']);
				$instance['post'] = intval($new['post']);
				$instance['title'] = sanitize_text_field( $new['title'] );
				$instance[ 'allow_refresh' ] = $new[ 'allow_refresh' ] == 'true' ? 'true' : 'false';
				return $instance;
			}
						
			//Widget form
			function form($instance) {
				$instance = wp_parse_args( 
					(array)$instance, 
					array(
						'title'=> __( "Random Posts", 'static-random-posts-widget' ),
						'postlimit'=>10,
						'time'=>'',
						'post'=>'',
						'allow_refresh' => 'false',
				) );
				$postlimit = intval($instance['postlimit']);
				$posts = $instance['post'];
				$title = esc_attr($instance['title']);
				$allow_refresh = $instance[ 'allow_refresh' ];
				
				$args = array(
    				'post_type' => 'srp_type',
    				'post_status' => 'publish',
    				'posts_per_page' => '100'
                );
                $posts = get_posts( $args );
                global $post;
                printf( '<p>%s</p>', __( 'Select a Random Post', 'static-random-posts-widget' ) );
                printf( '<select name="%s">', $this->get_field_name( 'post' ) );
                printf( '<option value="0">%s</option>', __( 'None', 'static-random-posts-widget' ) );
                foreach( $posts as $post ) {
                       setup_postdata( $post );
                       printf( '<option value="%s" %s>%s</option>', absint( $post->ID ), selected( $post->ID, $instance['post'], false ), get_the_title( $post ) );
                }
                echo '</select>';
                ?>
                <p>
				<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e("Title", 'static-random-posts-widget'); ?><input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
				</label>
			</p>
			<p>
				<?php esc_html_e( 'Allow users to refresh the random posts?', 'static-random-posts-widget' ); ?>
				<input type="radio" name="<?php echo esc_attr( $this->get_field_name( 'allow_refresh' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_yes' ) ); ?>" value="true" <?php checked( 'true', $allow_refresh ); ?>/>
				<label for="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_yes' ) ); ?>"><?php esc_html_e( 'Yes', 'static-random-posts-widget' ); ?></label><br />
				<input type="radio" name="<?php echo esc_attr( $this->get_field_name( 'allow_refresh' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_no' ) ); ?>" value="false" <?php checked( 'false', $allow_refresh ); ?> />
				<label for="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_no' ) ); ?>"><?php esc_html_e( 'No', 'static-random-posts-widget' ); ?></label>
			</p>
			<p><?php _e("Please visit",'static-random-posts-widget')?> <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=srp_type' ) ); ?>"><?php _e("Static Random Posts",'static-random-posts-widget')?></a> <?php _e("to adjust the global settings",'static-random-posts-widget')?>.</p>
			<?php
			}//End function form
    }//End class
}
add_action('widgets_init', create_function('', 'return register_widget("static_random_posts");') );
?>