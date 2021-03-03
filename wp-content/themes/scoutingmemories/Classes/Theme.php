<?php


class Theme {

    public $theme_name = 'scoutingmemories';

    public function __construct() {

	    require_once get_template_directory() . '/inc/class-wp-bootstrap-navwalker.php';






	    $this->addFormidableCSS();
        $this->addjQuery3();
        $this->addPopper();

        $this->addSupport('title-tag')
             ->addSupport('custom-logo')
             ->addSupport('post-thumbnails')
             ->addSupport('customize-selective-refresh-widgets')
             ->addSupport('html5', [
                'search-form',
                'comment-form',
                'comment-list',
                'gallery',
                'caption',
                 'style',
                 'script'
            ]);
        $this->addSidebar();
	    //$this->addReBoot();








	    $this->removeStyle('bootstrap-css');

	    $this->addScript('font-awesome', '/js/font-awesome.min.js');
	    //$this->addScript('bootstrap', '/js/bootstrap.min.js');
	    $this->addScript('bootstrap', '//cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/js/bootstrap.min.js', array() ,false );

		//$theme->addScript('bs-multiselect', '/js/BsMultiSelect.min.js');
	    $this->addScript('select2', '/js/select2.min.js');



	    //$this->addStyle('bootstrap', '/css/bootstrap.min.css' );
	    $this->addStyle('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.0.0-beta2/dist/css/bootstrap.min.css','outside' );


	    $this->addStyle('scouting-memories','/css/styles.css');
	    $this->addStyle('select2', '/css/select2.min.css' );
	    $this->addStyle('select2-bootstrap', '/css/select2-bootstrap4.min.css' );
	    $this->addStyle('roboto', '//fonts.googleapis.com/css?family=Roboto+Slab&#038;ver=5.6', 'outside');





    }





    private function actionAfterSetup($function)    {
        add_action('after_setup_theme', function() use ($function) {
            $function();
        });
    }

	private function actionEnqueueScripts($function)    {
		add_action('wp_enqueue_scripts', function() use($function) {
			$function();
		});
	}




	private function addFormidableCSS() {
    	$this->actionEnqueueScripts( function() {
		    wp_enqueue_style("jquery-ui-datepicker");
	    });
		return $this;
    }




    private function addSupport($feature, $options = null) {
        $this->actionAfterSetup(function() use ($feature, $options) {
            if ($options){
                add_theme_support($feature, $options);
            } else {
                add_theme_support($feature);
            }
        });
        return $this;
    }


    private function addjQuery3() {
        $this->actionEnqueueScripts(function(){
            wp_deregister_script('jquery');
            wp_register_script('jquery', "https://code.jquery.com/jquery-3.5.1.min.js", false, null);
            wp_enqueue_script('jquery');
        });
    }



	private function addPopper() {
		$this->addScript('popper','//cdn.jsdelivr.net/npm/@popperjs/core@2.6.0/dist/umd/popper.min.js', array(), false);
	}

	private function addReBoot() {
		//$this->addScript('reboot', get_template_directory_uri() . '/js/bootstrap-reboot.min.css', array(), false);
		$this->addScript('reboot', get_template_directory_uri() . '/bootstrap/css/bootstrap-reboot.min.css', array(), false);
	}


	public function setup_hooks() {
		//add_action( 'script_loader_tag', array( &$this, 'script_loader_tag' ), 10, 3 );
		//add_action( 'wp_print_scripts', array( &$this, 'wsds_detect_enqueued_scripts' ), 10, 3 );

	}

//	public function script_loader_tag( $html, $handle, $href, $media  ) {
//
//
//		// jquery | search_ajax | reboot | popper | font-awesome | bootstrap | select2 | media-editor | media-audiovideo | frontend-js |
//
//
//		return $html;
//
//	}



	/*
* Getting script tags
* Thanks http://wordpress.stackexchange.com/questions/54064/how-do-i-get-the-handle-for-all-enqueued-scripts
*/

//add_action( 'wp_print_scripts', 'wsds_detect_enqueued_scripts' );
	public function wsds_detect_enqueued_scripts() {
		global $wp_scripts;
		foreach( $wp_scripts->queue as $handle ) :
			echo $handle . ' | ';
		endforeach;
	}


/* -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- -- */






    //  $handle (string) (Required) Name of the script. Should be unique.
    //  $src includes get_theme_file_uri()
    //  $deps   =   array('jquery') for loading jQuery scripts
    //
    public function addScript($handle, $src, $deps=array(), $file_uri=true) {
        $this->actionEnqueueScripts(function() use ($handle, $src, $deps, $file_uri) {
            $rand = rand(1, 9999999);
            if ($file_uri)
                wp_enqueue_script($handle, get_theme_file_uri() . $src, $deps, $rand);
            else
	            wp_enqueue_script($handle, $src, $deps, $rand);
        });
    }

	public function removeScript($handle) {
		$this->actionEnqueueScripts(function() use ($handle) {
			wp_deregister_script($handle);
		});
	}
	public function removeStyle($handle) {
		$this->actionEnqueueScripts(function() use ($handle) {
			wp_dequeue_style($handle);
			wp_deregister_style($handle);
		});
	}


    public function addStyle($handle, $src, $uri = null) {
        $this->actionEnqueueScripts(function() use ( $uri, $handle, $src) {
            $rand = rand(1, 9999999);

            if ($uri) { wp_enqueue_style($handle, $src, array(), $rand); }
            else { wp_enqueue_style($handle, get_theme_file_uri(). $src, array(), $rand); }

        });
    }



    public function addNavMenus($locations = array()) {
        $this->actionAfterSetup(function() use ($locations){
            register_nav_menus($locations);
        });
    }









	/**
	 * @param WP_Query|null $wp_query
	 * @param bool $echo
	 * @param array $params
	 *
	 * @return string|null
	 *
	 * Accepts a WP_Query instance to build pagination (for custom wp_query()),
	 * or nothing to use the current global $wp_query (eg: taxonomy term page)
	 * - Tested on WP 5.4.1
	 * - Tested with Bootstrap 4.4
	 * - Tested on Sage 9.0.9
	 *
	 * INSTALLATION:
	 * add this file content to your theme function.php or equivalent
	 *
	 * USAGE:
	 *     <?php echo bootstrap_pagination(); ?> //uses global $wp_query
	 * or with custom WP_Query():
	 *     <?php
	 *      $query = new \WP_Query($args);
	 *       ... while(have_posts()), $query->posts stuff ... endwhile() ...
	 *       echo bootstrap_pagination($query);
	 *     ?>
	 */
	public static function bootstrap_pagination( \WP_Query $wp_query = null, $echo = true, $params = [] ) {
		if ( null === $wp_query ) {
			global $wp_query;
		}

		$add_args = [];

		//add query (GET) parameters to generated page URLs
		/*if (isset($_GET[ 'sort' ])) {
			$add_args[ 'sort' ] = (string)$_GET[ 'sort' ];
		}*/




		$pages = paginate_links( array_merge( [
				'base'         => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
				'format'       => '?paged=%#%',
				'current'      => max( 1, get_query_var( 'paged' ) ),
				'total'        => $wp_query->max_num_pages,
				'type'         => 'array',
				'show_all'     => false,
				'end_size'     => 3,
				'mid_size'     => 1,
				'prev_next'    => true,
				'prev_text'    => __( '« Prev' ),
				'next_text'    => __( 'Next »' ),
				'add_args'     => $add_args,
				'add_fragment' => ''
			], $params )
		);





		if ( is_array( $pages ) ) {
			//$current_page = ( get_query_var( 'paged' ) == 0 ) ? 1 : get_query_var( 'paged' );
			$pagination = '<div class="pagination justify-content-center mt-5"><ul class="pagination">';

			foreach ( $pages as $page ) {
				$pagination .= '<li class="page-item' . (strpos($page, 'current') !== false ? ' active' : '') . '"> ' . str_replace('page-numbers', 'page-link', $page) . '</li>';
			}

			$pagination .= '</ul></div>';

			if ( $echo ) {
				echo $pagination;
			} else {
				return $pagination;
			}
		}

		return null;
	}

	/**
	 * Notes:
	 * AJAX:
	 * - When used with wp_ajax (generate pagination HTML from ajax) you'll need to provide base URL (or it'll be admin-ajax URL)
	 * - Example for a term page: bootstrap_pagination( $query, false, ['base' => get_term_link($term) . '?paged=%#%'] )
	 *
	 * Images as next/prev:
	 * - You can use image as next/prev buttons
	 * - Example: 'prev_text' => '<img src="' . get_stylesheet_directory_uri() . '/assets/images/prev-arrow.svg">',
	 *
	 * Add query parameters to page URLs
	 * - If you need custom URL parameters on your page URLS, use the "add_args" attribute
	 * - Example (before paginate_links() call):
	 * $arg = [];
	 * if (isset($_GET[ 'sort' ])) {
	 *  $args[ 'sort' ] = (string)$_GET[ 'sort' ];
	 * }
	 * ...
	 * 'add_args'     => $args,
	 */















    /**
     * Register widget area.
     *
     * @link https://developer.wordpress.org/themes/functionality/sidebars/#registering-a-sidebar
     */
    public function addSidebar($name = 'Sidebar', $id = 'sidebar-1' ){
        register_sidebar(
            array(
                'name'          => esc_html__( $name, $this->theme_name ),
                'id'            => $id,
                'description'   => esc_html__( 'Add widgets here.', $this->theme_name ),
                'before_widget' => '<section id="%1$s" class="widget %2$s">',
                'after_widget'  => '</section>',
                'before_title'  => '<h2 class="widget-title">',
                'after_title'   => '</h2>',
            )
        );

    }




}