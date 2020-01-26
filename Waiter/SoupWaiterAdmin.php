<?php
namespace Waiter;
require_once (SOUP_PATH . "soup-pixabay-images.php");

use Timber\Timber;
use Timber\Term as TimberTerm;
use Timber\User as User;

require_once( ABSPATH . 'wp-includes/pluggable.php' );

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 03/07/2017
 * Time: 21:02
 */
class SoupWaiterAdmin extends Singleton {
	/**
	 * @var boolean $hasFeaturedImages
	 */
	protected $hasFeaturedImages;
	/**
	 * @var string[] $pixabay_images_gallery_languages left this in when porting code from pixabay just in case
	 */
	protected $pixabay_images_gallery_languages;
	/**
	 * @var string|null $tab_message For messages at the top of a tab, such as why we are here
	 */
	protected $tab_message;
	/**
	 * @var array of INPUT IDs that need the required CLASS
	 */
	protected $required;
	/**
	 * @var string|null $requested_tab Only set if the tab is overridden in sanitise_tab
	 */
	protected $requested_tab;
	/**
	 * @var string|null Error message for internal display
	 */
	protected $error_msg;
	/**
	 * Called from the 'init' Wordpress action
	 *
	 * Retrieve Session variables
	 * add action and filter hooks
	 *
	 */

	public function init(){
		$this->pixabay_images_gallery_languages = array('cs' => 'Čeština', 'da' => 'Dansk', 'de' => 'Deutsch', 'en' => 'English', 'es' => 'Español', 'fr' => 'Français', 'id' => 'Indonesia', 'it' => 'Italiano', 'hu' => 'Magyar', 'nl' => 'Nederlands', 'no' => 'Norsk', 'pl' => 'Polski', 'pt' => 'Português', 'ro' => 'Română', 'sk' => 'Slovenčina', 'fi' => 'Suomi', 'sv' => 'Svenska', 'tr' => 'Türkçe', 'vi' => 'Việt', 'th' => 'ไทย', 'bg' => 'Български', 'ru' => 'Русский', 'el' => 'Ελληνική', 'ja' => '日本語', 'ko' => '한국어', 'zh' => '简体中文');

		Timber::$locations = untrailingslashit(SOUP_PATH );

		// enables auto featuring image on post create
		$this->hasFeaturedImages = get_theme_support( 'post-thumbnails' );

		// Tried attaching this to send_headers hook but it didn't fire
		$this->add_header_cors();

		add_action( 'admin_init', [$this,'process_post_data']);
		add_action( 'admin_init', [$this,'remove_menu_pages']);
		add_action( 'wp_ajax_soup', [ $this, 'ajax_controller' ] );
		add_action( 'wp_ajax_servicecheck', [ $this, 'do_servicecheck' ] );
		add_action( 'wp_ajax_soup_resync', [ $this, 'do_priv_soup_resync' ] );
		add_action( 'wp_ajax_soup_create', [ $this, 'do_ajax_create_data' ] );
		add_action( 'wp_ajax_soup_recent', [ $this, 'do_ajax_recent_posts' ] );
		add_action( 'wp_ajax_soup_new_edit', [ $this, 'do_ajax_new_edit' ] );
		add_action( 'wp_ajax_nopriv_soup_resync', [ $this, 'do_nopriv_soup_resync' ] );
		add_action( 'wp_ajax_soup_resync_progress', [ $this, 'do_soup_resync_progress' ] );
		add_action( 'wp_ajax_waiter_soup_delete_posts', [ $this, 'do_waiter_soup_delete_posts' ] );
		add_action( 'wp_ajax_nopriv_soup_resync_progress', [ $this, 'do_soup_resync_progress' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_styles' ] );
		add_filter('pre_post_title', [ $this, 'mask_empty']);
		add_filter('pre_post_content', [ $this, 'mask_empty']);
		add_filter('wp_insert_post_data', [ $this, 'unmask_empty']);
		add_filter('query_vars', [ $this, 'add_query_vars']);

		if (!isset($_COOKIE['soup-kitchen-notices'])){
			$_COOKIE['soup-kitchen-notices'] = [];
		}
	}

	public function remove_menu_pages() {
		global $menu;

		if (is_admin() && current_user_can('author')){
			foreach ($menu as $menu_item){
				switch($menu_item[2]){
					case 'vacation-soup-admin':
					case 'vacation-soup-admin-owner':
					case 'vacation-soup-admin-property':
					case 'vacation-soup-admin-settings':
					case 'vacation-soup-admin-release':
					case 'vacation-soup-admin-create':
					//case 'index.php':
						break;
					default:
						remove_menu_page($menu_item[2]);
				}
			}
		}
	}

	public function add_query_vars($aVars) {
		$aVars[] = "edit_mode"; // represents the name of the product category as shown in the URL
		return $aVars;
	}

	public function add_header_cors() {
		header( 'Access-Control-Allow-Origin: *' );
	}

	public function mask_empty($value)
	{
		if ( empty($value) ) {
			return ' ';
		}
		return $value;
	}

	public function unmask_empty($data)
	{
		if ( ' ' == $data['post_title'] ) {
			$data['post_title'] = '';
		}
		if ( ' ' == $data['post_content'] ) {
			$data['post_content'] = '';
		}
		return $data;
	}
	/**
	 * Register our stylesheets.
	 */
	public function register_styles() {
		wp_register_style( 'vs-bootstrap', SOUP_URL . 'css/bootstrap-grid.min.css' );
		wp_register_style( 'vacation-soup', SOUP_URL . 'css/vs-admin.css' );
	}


	/**
	 * Process AJAX data
	 * If there is a do_{tab}_ajax function, call it, otherwise do the default
	 * which is update name with value, where name is Class.property for Singleton, or Class[id].property
	 * for Multiton
	 *
	 * Set the property to NewValue, on the Singleton or Multiton class Class
	 * It is up to the class to decide if to persist
	 *
	 * @internal Singleton::class $single
	 */
	public function ajax_controller(){
		$response=[];

		if (!empty($_REQUEST) &&
		    !empty($_REQUEST['_vs_nonce'])) {
			$tab = $this->sanitisedTab();
			check_admin_referer( 'vacation-soup','_vs_nonce' );

			$controller = "do_{$tab}_ajax";
			try {
				if (method_exists($this,$controller)){
					$response = $this->$controller();
				} else {
					// Default behaviour
					if (!isset($_REQUEST['name']) || !isset($_REQUEST['value'])) {
						throw new \Exception('missing parameters: name or value');
					}
					$attr = $_REQUEST['name'];
					$class = 'SoupWaiter'; // Default
					$check = explode('.',$attr);
					if (2 == count($check)){    // Then it's class.member
							$class = $check[0];
							$attr = $check[1];
					}
                    preg_match('#\[(.*?)\]#', $class, $id);
					if ($id){
					    $id = intval($id[1]); // extract the numeric id
                    } else {
						$id = null;
					}
					$class = substr($class,0,strcspn($class,"[]"));
					$class = __NAMESPACE__ . '\\' . $class;
					if (class_exists($class)){
						if (null===$id){
						    if (method_exists($class,'single')){
                                $obj = $class::single();
                            } else throw new \Exception("Class '{$class}' must be a Singleton, as no ID provided");
                        } else {
                            if (method_exists($class,'findOne')){
                                $obj = $class::findOne($id);
                            } else throw new \Exception("Class '{$class}' must be a Multiton, as ID provided");
                        }
						$obj->$attr = stripslashes($_REQUEST['value']);  // Objects can throw, e.g. if $key or value invalid
						$response['success'] = true;
						try {
							$response[$_REQUEST['name']] = $obj->$attr;
						} catch (\Exception $e){
							$response[$_REQUEST['name']] = null; // Allowed for write-only attributes (like password)
						}
					} else throw new \Exception("Class '{$class}' not found'");
				}
			} catch (\Exception $e){
				$response = [
					'success' => false,
					'error' => [
						'message' => $e->getMessage(),
						'code' => $e->getCode()
					]
				];
			}
		} else {
			$response['error'] = ['message'=>'not for me','code'=>0];
		}
		header("Content-type: application/json");
		echo json_encode($response);
		wp_die();
	}

	protected function fail_service_stub($service){
		return false;
	}

	protected function service_url(&$service){
		$html = wp_remote_get($service['testurl'],['timeout'=>20, 'sslverify' => false]);
		if (is_wp_error($html)){
			$service['message'] = $html->get_error_message();
			return false;
		}
		if ($html['response']) {
			if (200 == $html['response']['code']){
				$service['message'] = 'Service did not return acceptable message';
				return (strpos($html['body'],$service['search']));
			} else {
				$service['message'] = "Service returned error {$html['response']['code']}: {$html['response']['message']}";
				return false;
			}
		} else {
			$service['message'] = 'No response from service';
			return false;
		}
	}

    protected function get_service($handle){
	    $groupedServices = $this->get_services();
	    foreach ($groupedServices as $group => $services){
	        if (isset($services[$handle])){
	            return $services[$handle];
            }
        }
        return null;
    }
	protected function get_services(){
	    return
            [
                "basic"=>[
                    "auth" => [
                        "type" =>   "prop",
                        "title"=>   "Authorisation",
                        "message"=> "Vacation Soup user or password needs setting",
                        "prop"=>    [SoupWaiter::single(),'authorised']
                    ],
                    "post-kitchen" => [
                        "type" =>   "prop",
                        "title"=>   "Soup Syndication",
                        "prop"=>    [SoupWaiter::single(),'connected']
                    ],
                    "post-social" => [
                        "type" =>   "prop",
                        "title"=>   "Social Posting",
                        "message"=> "Vacation Soup user or password needs setting",
                        "prop"=>    [SoupWaiter::single(),'authorised']
                    ],
                    "vacation-soup" => [
                        "type" =>   "func",
                        "title"=>   "Vacation Soup",
                        "message"=> "Service is not live",
	                    "url"=>     "https://vacationsoup.com",
                        "call"=>    [$this,'service_url'],
	                    "testurl" =>  SoupWaiter::single()->kitchen_host.'/travel-guide/traveller',
	                    "search" => 'Traveller'
                    ],
                    "community" => [
                        "type" =>   "func",
                        "title" =>   "Community",
                        "url" => "https://community.vacationsoup.com",
                        "message"=> "Service is not live",
                        "call"=>    [$this,'service_url'],
                        "testurl" => "https://community.vacationsoup.com",
                        "search" => 'Latest News'
                    ]
                 ],
               "premium"=> [
                    "soup-trade" => [
                        "type" =>   "func",
                        "title" =>   "Soup Links To Your Site",
                        "message"=> "Premium Service not subscribed",
                        "call"=>    [$this,'service_url'],
                        "testurl" =>  SoupWaiter::single()->kitchen_host.'/travel-guide/traveller/',
                        "search" => 'Traveller'
                    ],
                    "learn" => [
                        "type" =>   "func",
                        "title" =>   "Learning Centre",
                        "url" => "https://learn.vacationsoup.com",
                        "message"=> "Premium Service not subscribed",
                        "call"=>    [$this,'service_url'],
                        "testurl" => "https://learn.vacationsoup.com",
                        "search" => 'The Learning Center'
                    ],
                    "full-publication" => [
                        "type" =>   "func",
                        "title" =>   "Soup Advertising",
                        "message"=> "Premium Service not subscribed",
                        "call"=>    [$this,'service_url'],
                        "testurl" =>  SoupWaiter::single()->kitchen_host.'/travel-guide/traveller',
                        "search" => 'Traveller'
                    ]
                ]
            ];

    }

    /**
     * Ajax call to check a particular service
     *
     * Initially designed as a framework to support replacing the values here with Kitchen supplied values
     * if needed, so the kitchen can re-direct some services if needed.
     *
     */
    public function do_servicecheck(){
	    session_write_close(); // prevent locking

		try {
            $result = [
                'success' => true,  // Default to ajax call success
                'status' => 'nok',   // default to failed service test
	            'message' => 'Unknown Error'
			];
            $service = $this->get_service($_REQUEST["service"]);
            if (!$service){
            	$message = esc_html($_REQUEST["service"]);
                throw new \Exception("Service unknown: {$message}");
            }
            switch($service["type"]){
                case "group":
                    $result["group"] = $service["group"];
                    break;
                case "func":
                    $method = $service["call"][1];
                    $object = $service["call"][0];
                    if (!method_exists($object,$method)){
                        throw new \Exception("Service check error: {$object}->{$method} not found");
                    }
                    if ($object->$method($service)){
	                    $result["status"] = 'ok';
	                    $result["message"] = 'Service connected & up';
                    } else {
	                    if (isset($service["message"])){
		                    $result['message'] = $service["message"];
	                    }
                    }
                    break;
                case "prop":
                    $prop = $service["prop"][1];
                    $object = $service["prop"][0];
                    if ($object->$prop){
                        $result["status"] = 'ok';
	                    $result["message"] = 'Service connected & up';
                    } else {
	                    if (isset($service["message"])){
		                    $result['message'] = $service["message"];
	                    }
                    }
                    break;
            }
            if (isset($service["url"])){
                $result["url"] = $service["url"];
            }
		} catch (\Exception $e){
			$result =  [
				'success' => false,
				'error' => [
					'message' => $e->getMessage(),
					'code' => $e->getCode()
				]
			];
		}
	    header("Content-type: application/json");
	    echo json_encode($result);
		wp_die();
	}
	/**
	 * Process POST data
	 * If the POST array is populated form Vacation Soup, process it
	 */
	public function process_post_data(){
		if (isset($_REQUEST['page']) && 0===strncmp($_REQUEST['page'],'vacation-soup-admin',18) &&
		    !wp_doing_ajax() &&
		    !empty($_POST)) {

			check_admin_referer( 'vacation-soup','_vs_nonce' );

			$tab = $this->sanitisedSrcTab();
			$controller = "process_{$tab}_data";
			return ($this->$controller());
		}
	}

	/**
	 * First add us to the screens and menus
	 */
	public function admin_menu()
	{
		// This page will be under "Settings"
		$page = add_menu_page(
			'Vacation Soup',
			'Vacation Soup',
			'publish_posts',
			'vacation-soup-admin-create',
			 null, // this allows the 1st sub-menu to be called
			'dashicons-admin-site',
			4
		);
		add_action( "admin_print_styles-{$page}", [ $this, 'admin_enqueue_styles' ] );
		$page = add_submenu_page(
			'vacation-soup-admin-create',
			'Create Post',
			'Create',
			'publish_posts',
			'vacation-soup-admin-create',
			array( $this, 'create_admin_page_create' )
		);
		add_action( "admin_print_styles-{$page}", [ $this, 'admin_enqueue_styles' ] );
		$page = add_submenu_page(
			'vacation-soup-admin-create',
			'Owner Details',
			'Owner Details',
			'publish_posts',
			'vacation-soup-admin-owner',
			array( $this, 'create_admin_page_owner' )
		);
		add_action( "admin_print_styles-{$page}", [ $this, 'admin_enqueue_styles' ] );
		$page = add_submenu_page(
			'vacation-soup-admin-create',
			'Property Details',
			'Property Details',
			'publish_posts',
			'vacation-soup-admin-property',
			array( $this, 'create_admin_page_property' )
		);
		add_action( "admin_print_styles-{$page}", [ $this, 'admin_enqueue_styles' ] );
		if (SoupWaiter::is_multiuser()){
			$capability = 'publish_posts';
		} else {
			$capability = 'administrator';
		}
		$page = add_submenu_page(
			'vacation-soup-admin-create',
			'Vacation Soup Settings',
			'Connect',
			$capability,
			'vacation-soup-admin-settings',
			array( $this, 'create_admin_page_connect' )
		);
		add_action( "admin_print_styles-{$page}", [ $this, 'admin_enqueue_styles' ] );
		$page = add_submenu_page(
			'vacation-soup-admin-create',
			'Release Notes',
			'Release Notes',
			'publish_posts',
			'vacation-soup-admin-release',
			array( $this, 'create_admin_page_release' )
		);
		add_action( "admin_print_styles-{$page}", [ $this, 'admin_enqueue_styles' ] );

	}
	public function admin_enqueue_styles(){
		wp_enqueue_style( 'vs-bootstrap' );
		wp_enqueue_style( 'vacation-soup' );
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style('vs-admin-ui-css','https://ajax.googleapis.com/ajax/libs/jqueryui/1.9.0/themes/base/jquery-ui.css',false,"1.9.0",false);
		wp_enqueue_style('vs-font-awesome', '//maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css');
	}

	/**
	 * @param $tab
	 *
	 * Validate (and correct) the paramater (or $_POST['tab']) to be a valid tab name
	 *
	 * @return string $tab sanitised
	 */
	private function sanitisedTab($tab=null){
		$default =  'create';
		$tabs = ['create','owner','property','connect','release'];
		$this->tab_message = '';
		$required = [];
		SoupWaiter::single()->needs_syndication(); // Checks for and sets members

		if (!$tab) {
			if (isset($_REQUEST['requested-tab'])){
				$tab = $_REQUEST['requested-tab'];
				$this->requested_tab = $tab;
			} elseif (isset($_REQUEST['tab'])){
				$tab = $_REQUEST['tab'];
				$this->requested_tab = $tab;
			} else {
				$tab = $default;
			}
		}
		if (!in_array($tab,$tabs)){
			$tab = $default;
		}
		// Now lets see if that tab is allowed
		if ('create'==$tab){ // create needs everything else to be completed
			$w = SoupWaiter::single();
			$version = file_get_contents(SOUP_PATH.'VERSION');
			if ($version !== get_user_meta(get_current_user_id(),'vs-waiter-release',true)) {
				$tab = 'release';
				$required = [];
				$msg = "You have installed a new Waiter plugin version";
				update_user_meta(get_current_user_id(),'vs-waiter-release',$version);
			}
			elseif (empty($w->owner_name)) {
				$tab = 'owner';
				$required = ['owner_name'];
				$msg = "the Owner's name is needed for posts";
			}
			else {
				if (0==Property::count()) {
					$tab = 'property';
					$msg = "at least one property must be created";
					$required = ['title-0','destination-0','latitude-0','longitude-0'];
				}
				elseif (empty($this->get_properties()[0]->latitude) ||
				        empty($this->get_properties()[0]->longitude)) {
					$tab = 'property';
					$required = ['latitude-0','longitude-0'];
					$msg = "the location (latitude/longitude) of the property is needed";
				}
				elseif (!$w->connected) {
					$tab = 'connect';
					$required = ['kitchen_user','kitchen_password'];
					$msg = "you need to enter your Vacation Soup credentials";
				}
			}

			if ($this->requested_tab!=$tab) {
				$this->required = $required;
				if (isset($msg)) $this->tab_message = "you have been redirected here because {$msg}.";
			} else {
				$this->requested_tab = null;
			}
		}
		return $tab;
	}
	public function sanitisedSrcTab(){
		$tab = $this->sanitisedTab();
		if (isset($_REQUEST['requested-tab']) && isset($_REQUEST['tab'])){
			$tab = esc_html($_REQUEST['tab']); // Use the one the data came from
		}
		return $tab;
	}
	/**
	 * Admin page callback
	 * Set default tab and pass to twig with appropriate context
	 */
	public function create_admin_page() {
		$tab = $this->sanitisedTab();
		$fn_context = "get_{$tab}_context";

		// ...and go do That Voodoo that You Do ... so well!
		Timber::render( array( "admin/{$tab}.twig" ), $this->$fn_context() );
	}
	public function create_admin_page_create() {
		$this->assignTab('create');
		return $this->create_admin_page();
	}
	public function create_admin_page_owner() {
		$this->assignTab('owner');
		return $this->create_admin_page();
	}
	public function create_admin_page_property() {
		$this->assignTab('property');
		return $this->create_admin_page();
	}
	public function create_admin_page_connect() {
		$this->assignTab('connect');
		return $this->create_admin_page();
	}
	public function create_admin_page_release() {
		$this->assignTab('release');
		return $this->create_admin_page();
	}

	private function assignTab($tab){
		$_GET['tab']=$_REQUEST['tab']=$tab;
	}


	public function process_create_data( ){
		if (!isset($_POST['post_status']) ||
		    (!in_array($_POST['post_status'],['publish','draft']))
		){
			return null;
		}
		$postId = wp_insert_post($_POST,false);
		delete_user_meta(get_current_user_id(),'_vs-new-post-id');
		// SoupWaiter::single()->wp_async_save_post($postId,get_post($postId));
		if (isset($_GET['p'])){ // if it was an edit, remove the query param
			header("Location: {$_SERVER['PHP_SELF']}?page=vacation-soup-admin-create");
			exit;
		}
	}
	/**
	 * Handle the Ajax callback to save the current post (fires on every change)
	 */
	public function do_ajax_new_edit() {
		check_admin_referer( 'vacation-soup','_vs_nonce' );


		$post_id = get_user_meta(get_current_user_id(),'_vs-new-post-id',true);
		delete_user_meta(get_current_user_id(),'_vs-new-post-id');

		if ('auto-draft' == get_post_status($post_id)){
			wp_update_post([
					'ID' => $post_id,
					'post_status' => 'draft']
			);
		}

		header("Content-type: application/json");
		echo json_encode([
			'success' => true,
		]);
		wp_die();
	}
	/**
	 * Handle the Ajax callback to save the current post (fires on every change)
	 */
	public function do_ajax_create_data() {
		check_admin_referer( 'vacation-soup','_vs_nonce' );
		$id = $this->do_create_data();
		header("Content-type: application/json");
		echo json_encode([
			'success' => true,
			'data' => [
				'ID' => $id
			]
		]);
		wp_die();
	}
	/**
	 * Create a post using the admin/VS/create page
	 */
	public function do_create_data( ){
		if (!isset($_POST['post_status']) || !in_array($_POST['post_status'],['publish','draft','auto-draft'])){
			return null;
		}
		$error_obj = true;

		$newpost = $_POST;

		// When publishing add byline
		if (in_array($_POST['post_status'],['publish','future']) &&
			stripos($newpost['post_content'],'autocreated byline')===false){
			$newpost['post_content'] .= "<p class='autocreated byline'>Travel Tip created by ".SoupWaiter::single()->owner_name." in association with <a href='https://vacationsoup.com'>Vacation Soup</a></p>";
		}

		$waiter = SoupWaiter::single();
		$waiter->skipSyndicate = true; // Rather than lots of updates to VS, make changes then sync
		$postId = wp_insert_post($newpost,$error_obj);
		if (!is_wp_error($postId)){
			wp_set_post_tags($postId, $_POST['tags']);
			wp_set_post_categories( $postId, $_POST['post_category']);
			set_post_thumbnail($postId,$_POST['featured_image']);
			update_post_meta($postId,'topic',$_POST['topic']);
			update_post_meta($postId,'conceal',$_POST['conceal']);
			$latitude = $_POST['latitude'];
			$longitude = $_POST['longitude'];
			if ($_POST['latitude_entry'] && $_POST['longitude_entry']) {
				$latitude = $_POST['latitude_entry'];
				$longitude = $_POST['longitude_entry'];
			}
			update_post_meta($postId,'latitude',$latitude);
			update_post_meta($postId,'longitude',$longitude);
			update_post_meta($postId,'destination_id',$_POST['destination_id']);
		} else return false;
		// Because we skipped syndication above, we now need to do it
		$waiter->skipSyndicate = false;
		$postId = wp_update_post($newpost,$error_obj);


		unset($_POST['post_status']); // Stop anything else from processing this post
		return $postId; // Causes a fall-through to create the page if we want, as we are not redirecting after persistence
	}

	public function process_owner_data(){}
	public function process_connect_data(){
		if (isset($_REQUEST['action']) && 'servicecheck' == $_REQUEST['action']) return;
		try {
			foreach (['kitchen_user','kitchen_password'] as $field){
				if (isset($_REQUEST['SoupWaiter_'.$field])){
					SoupWaiter::single()->$field = $_REQUEST['SoupWaiter_'.$field];
				}
			}
		} catch (\Exception $e){
			$this->error_msg = 'The username and password combination are not recognized';
		}
	}
	public function process_property_data(){}
	/**
	 * get base context
	 *
	 * @param string $tab
	 *
	 * @return array
	 */
	private function get_context($tab){
		global $wpdb;

		$context = Timber::get_context();
		$context['tab'] = $tab;     // Used to decide current and next actions
		$context['soup'] = SoupWaiter::single();   // Exposing the waiter and soup.admin (in twig) is the SoupWaiterAdmin
		$context['admin'] = $this;
		$context['current_user'] = new User();
		$context['userType'] = (current_user_can('administrator'))?'administrator':'author';
		$context['offsetTZ'] = isset($_COOKIE['offsetTZ'])?$_COOKIE['offsetTZ']:0;
		return $context;
	}

	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_create_context(){
		// Grab the basics
		$context = $this->get_context('create');

		$new_post_id = get_user_meta(get_current_user_id(),'_vs-new-post-id',true);
		if (isset($_GET['p']) && $_GET['p'] > 0) {
			if ($new_post_id) {
				$old_post = get_post($new_post_id);
				if (empty($old_post->post_content) && !has_post_thumbnail($new_post_id)) {
					wp_trash_post($new_post_id);
				} else {
					if ('auto-draft' == get_post_status($new_post_id)) {
						wp_update_post( [
								'ID'          => $new_post_id,
								'post_status' => 'draft'
							]
						);
					}
				}
			}
			$new_post_id = $_GET['p'];
			update_user_meta(get_current_user_id(),'_vs-new-post-id',$new_post_id);
		}
		if ($new_post_id) {
			$test_post = get_post($new_post_id);
			if ($test_post) {
				$new_post = $test_post->to_array();
				if ('trash' === $test_post->post_status){
					$new_post = null; // Force create new
				}
			}
		}
		if (isset($new_post)) {
			$new_post['edit_mode'] = 'edit';
			$new_post['tags'] = wp_get_post_tags( $new_post_id, array( 'fields' => 'names' ) );
			$new_post['cats'] = wp_get_post_categories( $new_post_id, array( 'fields' => 'ids' ) );
			$new_post['featured_image'] = get_post_thumbnail_id( $new_post_id );
			if ($new_post['featured_image']){
				$new_post['featured_image_img'] = get_the_post_thumbnail( $new_post_id, 'full' );
			}
			$topic = get_post_meta($new_post_id,'topic',true);
			$new_post['conceal'] = get_post_meta($new_post_id,'conceal',true);
			$new_post['topic'] = $topic;
			$latitude = get_post_meta($new_post_id,'latitude',true);
			$longitude = get_post_meta($new_post_id,'longitude',true);
			if ($latitude !== SoupWaiter::single()->get_destination()['latitude']){
				$new_post['latitude_entry'] = $latitude;
				$new_post['longitude_entry'] = $longitude;
			}
			$new_post['latitude'] = SoupWaiter::single()->get_destination()['latitude'];
			$new_post['longitude'] = SoupWaiter::single()->get_destination()['longitude'];

			$dest_id = get_post_meta($new_post_id,'destination_id',true);
			SoupWaiter::single()->current_destination = $dest_id;
			$new_post['destination_id'] = $dest_id;
		} else {
			$error_obj = null;
			$new_post_id = wp_insert_post(['post_status'=>'auto-draft'],$error_obj);
			update_user_meta(get_current_user_id(),'_vs-new-post-id',$new_post_id);
			$new_post = get_post($new_post_id)->to_array();
			$new_post['edit_mode'] = 'create';
			$new_post['tags'] =[];
			$new_post['cats'] = [];
			$new_post['conceal'] = 0;
			$new_post['featured_image'] = '';
			$new_post['topic'] = 0;
			$new_post['latitude'] = SoupWaiter::single()->get_destination()['latitude'];
			$new_post['longitude'] = SoupWaiter::single()->get_destination()['longitude'];
			$new_post['latitude_entry'] = '';
			$new_post['longitude_entry'] = '';
			$new_post['destination_id'] = SoupWaiter::single()->get_current_destination();
		}
		$context['new_post']= $new_post;
		// Available Tags for all posts
        // In case the destination is multiple words

		$context['permTags'][] = 'VacationSoup';
		$doneOne = false;
		// Allows rendering the lat/long
		$context['destination'] = SoupWaiter::single()->get_destination();
        foreach (SoupWaiter::single()->destinations_for_property as $destination) {
	        $render = '';
	        foreach (explode(' ',preg_replace('/[^\w]+/ui', ' ', $destination['rendered'])) as $word){
		        $render .= ucfirst($word);
	        }
	        $dest = '';
	        foreach (explode(' ',preg_replace('/[^\w]+/ui', ' ', $destination['destination'])) as $word){
		        $dest .= ucfirst($word);
	        }

	        $context['permTags'][] = $dest;

	        if ($context['destination'] == $destination){
		        foreach (['Holiday','Vacation'] as $holiday){
			        $context['permTags'][] = $holiday.$render;
		        }
	        }
        }

		add_filter('tiny_mce_before_init', [$this,'mce_autosave_mod']);
		add_filter('wp_dropdown_cats',[$this,'make_select_multiple'],10,2);

		$context['posts'] = $this->get_recent_posts(1);
		$context['next_posts'] = 2;

		$context['recent_topics']=[];
		foreach ($context['posts'] as $post){
			if (isset($post->topic) && $post->topic > 0 && !is_array($post->topic)){
				$context['recent_topics'][] = $post->topic;
			}
		}

		return $context;
	}
	public function do_ajax_recent_posts(){
		check_admin_referer( 'vacation-soup','_vs_nonce' );
		$context = $this->get_context('create');

		$context['posts'] = $this->get_recent_posts($_GET['p']);
		$context['next_posts'] = 1 + $_GET['p'];

		Timber::render( array( "admin/recent_posts.twig" ), $context );

		wp_die();
	}
	private function get_recent_posts($page) {
		$new_post_id = isset($_REQUEST['current'])?$_REQUEST['current']:get_user_meta(get_current_user_id(),'_vs-new-post-id',true);
		$args = array(
			'posts_per_page' => 6,
			'paged' => $page,
			'orderby' => 'post_date',
			'order' => 'DESC',
			'post__not_in' => [$new_post_id],
			'post_type' => 'post',
			'post_status' => 'draft, publish, future',
			'suppress_filters' => true
		);
		if (SoupWaiter::is_multiuser()) {
			$args['author'] = get_current_user_id();
		}
		return Timber::get_posts( $args );
	}

	public function mce_autosave_mod( $init ) {
		$init['setup'] = "function(ed){ ed.on( 'NodeChange', function(e){ setFeaturedImage(ed) } ) }";
		return $init;
	}
	public function make_select_multiple( $select, $args ) {
		if (isset($args['multiple']) && $args['multiple']){
			$select = str_ireplace('<select ','<select multiple ',$select );
		}
		return $select;
	}

	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_dash_context(){
		// Grab the basics
		$context = $this->get_context('dash');

		return $context;
	}

	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_list_context(){
		// Grab the basics
		$context = $this->get_context('list');

		return $context;
	}

	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_owner_context(){
		// Grab the basics
		$context = $this->get_context('owner');

		return $context;
	}
	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_release_context(){
		// Grab the basics
		$context = $this->get_context('release');
		$context['version'] = file_get_contents(SOUP_PATH.'VERSION');
		$context['release_note'] = 'https://vacationsoup.com/waiter-'.str_replace('.','-',$context['version']).'/';

		return $context;
	}

    /**
     * @return array of Properties
     * Also populates the SoupWaiter::single()->properties
     */
	public function get_properties(){
        $propertyCount = Property::count();
        static $properties = [];
        $destinations = []; // Might as well set these up too
		$deduped = [];
        $dCount = 0;

        if (empty ($properties)) {
	        for ( $i = 0; $i < ( $propertyCount ); $i ++ ) {
		        $property     = Property::findOne( $i );
		        $properties[] = $property;

		        if ( ! empty( $property->destination ) && !isset($deduped[$property->destination])) {
			        $deduped[$property->destination] = $destinations[ $dCount ] = [
				        'id'          => $dCount,
				        'property'    => $i,
				        'latitude'    => "{$property->latitude}",
				        'longitude'   => "{$property->longitude}",
				        'rendered'    => "{$property->join} {$property->destination}",
				        'destination' => "{$property->destination}"
			        ];
			        $dCount ++;
		        }
		        if ( ! empty( $property->destination2 )  && !isset($deduped[$property->destination2])) {
			        $deduped[$property->destination] = $destinations[ $dCount ] = [
				        'id'          => $dCount,
				        'property'    => $i,
				        'latitude'    => "{$property->latitude}",
				        'longitude'   => "{$property->longitude}",
				        'rendered'    => "{$property->join2} {$property->destination2}",
				        'destination' => "{$property->destination2}"
			        ];
			        $dCount ++;
		        }
		        if ( ! empty( $property->destination3 )  && !isset($deduped[$property->destination3])) {
			        $deduped[$property->destination] = $destinations[ $dCount ] = [
				        'id'          => $dCount,
				        'property'    => $i,
				        'latitude'    => "{$property->latitude}",
				        'longitude'   => "{$property->longitude}",
				        'rendered'    => "{$property->join3} {$property->destination3}",
				        'destination' => "{$property->destination3}"
			        ];
			        $dCount ++;
		        }

	        }
	        sort($destinations);
	        SoupWaiter::single()->destinations = $destinations;
        }
        return $properties;
    }
	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_property_context(){
		// Grab the basics
		$context = $this->get_context('property');
        $properties = $this->get_properties();
		$newAllowed = 1;

        for ($i=0;$i<(count($properties));$i++){
            $property = $properties[$i];
            if (empty($property->title)){ // Then this will be the 'New' property
                $newAllowed = 0;
            }
        }
        $context['properties'] = $properties;
        if ($newAllowed){
            $context['properties'][] = Property::findOne($i);
        }

		return $context;
	}
	/**
	 * @returns mixed[] The context for this tab
	 */
	private function get_connect_context(){
		// Grab the basics
		$context = $this->get_context('connect');
		if (!soup_ping(['bool'=>true])){
			$this->error_msg = "<h2>Error</h2>Unable to connect to vacationsoup.com, please contact your hosting provider with the following error:\n".soup_ping();
		}
		return $context;
	}

	public function do_nopriv_soup_resync(){
		// Apply a key check from kitchen TODO
		// $this->do_soup_resync();
	}
	public function do_priv_soup_resync(){
		check_admin_referer( 'vacation-soup','_vs_nonce' );
		$this->do_soup_resync();
	}

	private function do_soup_resync(){
		// All this ajax to be called by the kitchen to trigger it
		session_write_close(); // prevent locking
		try {
			$completed = SoupWaiter::single()->syndicate_some_posts(false);
			update_option('vs-resynch',"Sent $completed posts, ".date("D M j G:i:s T Y"),false);
			$response = [
				'success' => true,
				'progress' => $completed
			];
		} catch (\Exception $e){
			update_option('vs-resynch',"Failed ".date("D M j G:i:s T Y"),false);
			update_option('vs-resynch-error',$e,false);
			$response = [
				'success' => false,
				'error' => [
					'message' => $e->getMessage(),
					'code' => $e->getCode()
				]
			];
		}
		header("Content-type: application/json");
		echo json_encode($response);
		die();
	}

	public function do_soup_resync_progress(){
		// check_admin_referer( 'vacation-soup','_vs_nonce' ); to allow for kitchen-side checking
		session_write_close(); // prevent locking
		$progress = get_option('vs-resynch-progress');//,[ 'total'=>0, 'processed'=>0, 'progress'=>0 ]);
		header("Content-type: application/json");
		echo json_encode($progress);
		die();
	}

	/* Not Yet Implemented */
	public function do_waiter_soup_delete_posts(){
		// check_admin_referer( 'vacation-soup','_vs_nonce' ); to allow for kitchen-side checking
		session_write_close(); // prevent locking

		$waiter = SoupWaiter::single();
		$options = $waiter->std_options();
		$response = wp_remote_get($waiter->kitchen_host.'/admin-ajax.php?action=soup_delete_posts',$options);

		if (!$waiter->api_success($response)) {
			$response = [
				'success' => false,
				'error' => [
					'message' => $waiter->api_error_message($response),
					'code' => 0
				]
			];
		} else {
			$response = json_decode( wp_remote_retrieve_body( $response ) );
		}

		// Not yet implemented

		header("Content-type: application/json");
		echo json_encode($response);
		die();
	}

}
