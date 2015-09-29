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
				
				add_action('wp_print_scripts', array( &$this,'add_post_scripts' ),1000 );
				//Ajax
				add_action( 'wp_ajax_refreshstatic', array(&$this, 'ajax_refresh_static_posts') );
				add_action( 'wp_ajax_nopriv_refreshstatic', array(&$this, 'ajax_refresh_static_posts'));
				parent::__construct(
        			'srp_widget', // Base ID
        			__( 'Static Random Posts', 'text_domain' ), // Name
        			array( 'description' => __( 'Select random posts', 'static-random-posts-widget'  ), ) // Args
        		);
				//Create widget
            }
			
			//Build new posts and send back via Ajax
			function ajax_refresh_static_posts() {
				check_ajax_referer('refreshstaticposts');
				if ( isset($_POST['number']) ) {
					$number = absint($_POST['number']);
					$action = sanitize_text_field( $_POST['action'] );
					$name = sanitize_text_field( $_POST['name'] );
					
					//Get the SRP widgets
					$settings = get_option($name);
					$widget = $settings[$number];
					
					//Get the new post IDs
					$widget = $this->build_posts(intval($widget['postlimit']),$widget);
					$post_ids = $widget['posts'];
					
					//Save the settings
					$settings[$number] = $widget;
					
					//Only save if user is admin
					if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
						update_option($name, $settings);
						
						//Let's clean up the cache
						//Update WP Super Cache if available
						if(function_exists("wp_cache_clean_cache")) {
							@wp_cache_clean_cache('wp-cache-');
						}
					}
					//Build and send the response
					die( $this->print_posts( $post_ids, false ) );
				}
				exit;			
			} //end ajax_refresh_static_posts
			
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
				$title = empty($instance['title']) ? __('Random Posts', 'staticRandom') : apply_filters('widget_title', $instance['title']);
				$allow_refresh = isset( $instance[ 'allow_refresh' ] ) ? $instance[ 'allow_refresh' ] : 'false';
				
				if ( !empty( $title ) ) {
					echo $before_title . $title . $after_title;
				};
				//Get posts
				$post_ids = $this->get_posts($instance);
				if (!empty($post_ids)) {
					echo "<ul class='static-random-posts' id='static-random-posts-{$this->number}'>";
					$this->print_posts($post_ids);
					echo "</ul>";
					if (current_user_can('administrator') || 'true' == $allow_refresh ) {
						$refresh_url = esc_url( wp_nonce_url(admin_url("admin-ajax.php?action=refreshstatic&number=$this->number&name=$this->option_name"), "refreshstaticposts"));
						echo "<br /><a href='$refresh_url' class='static-refresh'>" . __("Refresh...",'staticRandom') . "</a>";
					}
				}
				echo $after_widget;
			}
			
			//Prints or returns the LI structure of the posts
			function print_posts($post_ids,$echo = true) {
				if (empty($post_ids)) { return ''; }
				$posts = get_posts("include=$post_ids");
				$posts_string = '';
				foreach ($posts as $post) {
					$posts_string .= "<li><a href='" . get_permalink($post->ID) . "' title='". esc_attr($post->post_title) ."'>" . esc_html($post->post_title) ."</a></li>\n";
				}
				if ($echo) {
					echo $posts_string;
				} else {
					return $posts_string;
				}
			}
			
			//Returns the post IDs of the posts to retrieve
			function get_posts($instance, $build = false) {
				//Get post limit
				$limit = intval($instance['postlimit']);
				
				$all_instances = $this->get_settings();
				//If no posts, add posts and a time
				if (empty($instance['posts'])) {
					//Build the new posts
					$instance = $this->build_posts($limit,$instance);
					$all_instances[$this->number] = $instance;
					update_option( $this->option_name, $all_instances );
				}  elseif(($instance['time']-time()) <=0) {
					//Check to see if the time has expired
					//Rebuild posts
					$instance = $this->build_posts($limit,$instance);
					$all_instances[$this->number] = $instance;
					update_option( $this->option_name, $all_instances );
				} elseif ($build == true) {
					//Build for the heck of it
					$instance = $this->build_posts($limit,$instance);
					$all_instances[$this->number] = $instance;
					update_option( $this->option_name, $all_instances );
				}
				if (empty($instance['posts'])) {
					$instance['posts'] = '';
				}
				return $instance['posts'];
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
	
			//Builds and saves posts for the widget
			function build_posts($limit, $instance) {
				//Get categories to exclude
				$cats = @implode(',', $this->adminOptions['categories']);
				
				$posts = get_posts("cat=$cats&showposts=$limit&orderby=rand"); //get posts by random
				$post_ids = array();
				foreach ($posts as $post) {
					array_push($post_ids, $post->ID);
				}
				$post_ids = implode(',', $post_ids);
				$instance['posts'] = $post_ids;
				$instance['time'] = time()+(60*intval($this->adminOptions['minutes']));
				
				return $instance;
			}
			
			//Updates widget options
			function update($new, $old) {
				$instance = $old;
				$instance['postlimit'] = intval($new['postlimit']);
				$instance['title'] = sanitize_text_field( $new['title'] );
				$instance[ 'allow_refresh' ] = $new[ 'allow_refresh' ] == 'true' ? 'true' : 'false';
				return $instance;
			}
						
			//Widget form
			function form($instance) {
				$instance = wp_parse_args( 
					(array)$instance, 
					array(
						'title'=> __( "Random Posts", 'staticRandom' ),
						'postlimit'=>5,
						'posts'=>'', 
						'time'=>'',
						'allow_refresh' => 'false',
				) );
				$postlimit = intval($instance['postlimit']);
				$posts = $instance['posts'];
				$title = esc_attr($instance['title']);
				$allow_refresh = $instance[ 'allow_refresh' ];
				?>
			<p>
				<label for="<?php echo esc_attr($this->get_field_id('title')); ?>"><?php _e("Title", 'staticRandom'); ?><input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>" name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text" value="<?php echo esc_attr($title); ?>" />
				</label>
			</p>
			<p>
				<label for="<?php echo esc_attr($this->get_field_id('postlimit')); ?>"><?php _e("Number of Posts to Show", 'staticRandom'); ?><input class="widefat" id="<?php echo esc_attr($this->get_field_id('postlimit')); ?>" name="<?php echo esc_attr($this->get_field_name('postlimit')); ?>" type="text" value="<?php echo esc_attr($postlimit); ?>" />
				</label>
			</p>
			<p>
				<?php esc_html_e( 'Allow users to refresh the random posts?', 'staticRandom' ); ?>
				<input type="radio" name="<?php echo esc_attr( $this->get_field_name( 'allow_refresh' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_yes' ) ); ?>" value="true" <?php checked( 'true', $allow_refresh ); ?>/>
				<label for="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_yes' ) ); ?>"><?php esc_html_e( 'Yes', 'staticRandom' ); ?></label><br />
				<input type="radio" name="<?php echo esc_attr( $this->get_field_name( 'allow_refresh' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_no' ) ); ?>" value="false" <?php checked( 'false', $allow_refresh ); ?> />
				<label for="<?php echo esc_attr( $this->get_field_id( 'allow_refresh_no' ) ); ?>"><?php esc_html_e( 'No', 'staticRandom' ); ?></label>
			</p>
			<p><?php _e("Please visit",'staticRandom')?> <a href="options-general.php?page=static-random-posts.php"><?php _e("Static Random Posts",'staticRandom')?></a> <?php _e("to adjust the global settings",'staticRandom')?>.</p>
			<?php
			}//End function form
			
			//Add scripts to the front-end of the blog
			function add_post_scripts() {
				//Only load the widget if the widget is showing
				if ( !is_active_widget(true, $this->id, $this->id_base) || is_admin() ) { return; }
				
				//queue the scripts
				wp_enqueue_script("wp-ajax-response");
				wp_enqueue_script('static_random_posts_script', $this->get_plugin_url( '/js/static-random-posts.js' ), array( "jquery" ) , 1.0);
				wp_localize_script( 'static_random_posts_script', 'staticrandomposts', $this->get_js_vars());
			}
			//Returns various JavaScript vars needed for the scripts
			function get_js_vars() {
				return array(
					'SRP_Loading' => esc_js(__('Loading...', 'staticRandom')),
					'SRP_Refresh' => esc_js(__('Refresh...', 'staticRandom')),
					'SRP_AjaxUrl' =>  admin_url('admin-ajax.php')
				);
			} //end get_js_vars
			/*END UTILITY FUNCTIONS*/
    }//End class
}
add_action('widgets_init', create_function('', 'return register_widget("static_random_posts");') );
?>