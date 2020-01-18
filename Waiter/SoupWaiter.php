<?php
namespace Waiter;

use WP_Post;
use Exception;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 23/06/2017
 * Time: 16:41
 */
class SoupWaiter extends PersistedSingleton {
	const REGISTRY_USER = 'soup-kitchen-registry';
	const REGISTRY_PASS = 'OpenDoor';
	const APIUSER_PASS  = 'OpenDoor';
	const SSL_VERIFY    = false; // make true on production
	const TIMEOUT       = 30000; // ms

	const SOUP_KITCHEN  = 'https://vacationsoup.com';
	//const SOUP_KITCHEN  = 'https://soup.freevacationrentalwebsite.com';

	/**
	 * @var string $kitchen_host Base URL of host providing SoupKitchen
	 */
	protected $kitchen_host;
	/**
	 * @var string $kitchen_api Kitchen API partial URL
	 */
	protected $kitchen_api;
	/**
	 * @var string $kitchen_api Kitchen API partial URL
	 */
	protected $social_api;
	/**
	 * @var string $kitchen_jwt_api Kitchen AUTH API partial URL
	 */
	protected $kitchen_jwt_api;
	/**
	 * @var number $kitchen_user The User we are known as in the Kitchen
	 */
	protected $kitchen_user;
	/**
	 * @var string $kitchen_token The Token to identify us in the Kitchen
	 */
	protected $kitchen_token;
	/**
	 * @var string $owner_name to show on posts
	 */
	protected $owner_name;
	/**
     * @var number $nextMOTD the next MOTD page to request
     */
    protected $nextMOTD;
    /**
     * @var number $next_topic the next topic item (offset from 0)
     */
    protected $next_topic;
    /**
     * @ var number $property_count The number of properties
     */
    //protected $property_count;
    /**
     * @var string[] $joins the Joining words to use in topics (first is default), e.g. Best beaches ON Bornholm
     */
    protected $joins;
    /**
     * @var string[] $destinations the Destinations to use in topics (first is default), e.g. Best beaches on BORNHOLM
     */
	protected $destinations;
	/**
	 * @var int $destinations the Destinations to use in topics (first is default), e.g. Best beaches on BORNHOLM
	 */
	protected $current_destination;

	/**
	 * @var boolean Whether to skip automnatic post syndicating. Hack to prevent double saving
	 */
	protected $skipSyndicate;
	/**
	 * @var string|null $kitchen_sync the count of posts that need synch
	 */
	protected $kitchen_sync;
	/**
	 * @var bool Called if the host install configures authentication for us, e.g. writers.vs.com
	 */
    protected $host_authentication = false;
	/**
	 * @param boolean $value Is this site multi-user? Configurations will be stored per-user
	 */
    public static function set_multiuser($value){
        update_option('vs-multiuser',($value)?true:false,true);
        SoupWaiter::single(true); // purge the cache so it reloads properly
    }

	/**
	 * @return bool if we support authentication for the user
	 */
    public function canAuthenticate(){
        if ($this->multiuser && !$this->host_authentication) return true;
        else return false;
    }
	/**
	 * @return boolean Is this site configured for multi-user
	 */
    public static function is_multiuser(){
        return (get_option('vs-multiuser'))?true:false;
    }

	/**
	 * @return int
	 */
	public function get_current_destination(){
		if (!$this->current_destination){
			if (isset($_REQUEST['destination_id'])){
				$this->current_destination = intval($_REQUEST['destination_id']);
			} else {
				$this->current_destination = 0;
            }
        }
		return $this->current_destination;
	}

	/**
	 * @return string[]
	 */
	public function get_destination(){
		return $this->destinations[$this->get_current_destination()];
	}

	protected function get_destinations_for_property(){
		$main = $this->get_current_destination();
		$mainDest = $this->destinations[$main];
		$destinations = [];
		foreach ($this->destinations as $destination){
			if ($destination['property'] == $mainDest['property']){
				$destinations[] = $destination;
			}
		}
		return $destinations;
	}

	protected function set_kitchen_user($user){
		$this->kitchen_user = $user;
		$this->kitchen_token = null;
		$this->persist();
	}

	/**
	 * @param $host string The base url, including scheme, which must be https
	 *
	 * @return $this
	 * @throws \Exception if not SSL
	 */
	public function set_kitchen_host($host){
		global $wpdb;

		if (0==strncasecmp($host,'https://',8)){
			if ($this->kitchen_host!='' &&  // It's not being 'changed', just set
			    $this->kitchen_host!=$host){ // And it's value is changing
				// Kitchen has changed, reset the synchronised post history
				$sql = "
					DELETE FROM $wpdb->postmeta
					WHERE meta_key in (
					'soup_local_image_id',
					'kitchen_id',
					'kitchen_image_id',
					'kitchen_url'
					)
				";
				$wpdb->get_results( $sql );
				$this->kitchen_token = null;
			}

			$this->kitchen_host = $host;
			$this->persist();
		} else {
			throw new \Exception("SoupKitchen location must begin https://, but got ".$host);
		}
		return $this; // By convention
	}
	/**
	 * To provide external access to it from __GET
	 * @return string
	 */
	protected function get_kitchen_token(){
		return $this->getSoupKitchenToken();
	}

	/**
	 * Get the access token from a password
	 * @param string $password the password to set
	 * @return $this
	 */
	protected function set_kitchen_password($password){
		$this->getSoupKitchenToken($password);
		return $this; // by convention
	}

	/**
	 * Expose whether the Kitchen is up
	 *
	 * use as SoupWaiter::single()->authorised?
	 * @return bool
	 */
	protected function is_authorised(){
		try {
			$this->getSoupKitchenToken();
			return true;
		} catch (\Exception $e){
			return false;
		}
	}
	/**
	 * Expose whether the Kitchen is up
	 *
	 * use as SoupWaiter::single()->connected?
	 * @return bool
	 */
	protected function is_connected(){
		try {
			$this->getToKitchen('');
			return true;
		} catch (Exception $e){
			return false;
		}
	}
	/**
	 * @param $folder
	 * @return string
	 */
	protected function get_base_url($folder=''){
		$folder .= '/';
		$image_url=null; // This was static, but with persistence will fail on hostname/schema change
		if (!$image_url){
			$mydir = explode('/',__DIR__);
			$uplevels = count(explode('\\',__NAMESPACE__));
			$dir = array_slice($mydir,0,0-$uplevels);
			$dir[] = "{$folder}FakeFile";
			$image_url = implode('/',$dir);
		}
		return plugin_dir_url($image_url);
	}
	/**
	 * @return string
	 */
	protected function get_image_url(){
		return $this->get_base_url('img');
	}
	/**
	 * SoupWaiter constructor.
	 */
	public function __construct(){
	}


	/**
	 * @param $level string error, warning, success, info
	 * @param $notice string Message
	 * @param $detail string|null Further detail on alt-text
	 */
	public function addNotice($level,$notice,$detail=null) {
		$_COOKIE['soup-kitchen-notices'][] = [$level,$notice,htmlentities($detail)];
	}

	/**
	 * Called from the 'plugins_loaded' Wordpress action
	 *
	 * Retrieve Session variables
	 * add action and filter hooks
	 *
	 */
	public function init(){
	    static $already_called = false;
	    if ($already_called || !is_user_logged_in()){
	        return;
        } else {
	        $already_called = true;
        }

        $this->kitchen_sync = null;
		if (!$this->kitchen_api){
			$this->set_kitchen_host(self::SOUP_KITCHEN);
			$this->kitchen_api = 'wp-json/wp/v2';
			$this->kitchen_jwt_api = 'wp-json/jwt-auth/v1';
			$this->nextMOTD = 0;
			$this->next_topic = 0; // This is an offset, not a page number
		}

		$wp_footer = 'wp_footer';
		if (is_admin()){
		    $wp_footer = 'admin_footer';
        }

		add_action( 'save_post', [$this, 'wp_async_save_post'],10,2 );
		add_action( 'before_delete_post', [$this, 'async_delete_post'],10,1 );
		add_action( 'trash_post', [$this, 'async_delete_post'],10,1 );
		add_action(  $wp_footer, [$this,'do_kitchen_sync'],10);
		add_action(  'wp_head', [$this,'inject_canonical'],10);

		// Now install the admin screens if needed
		if (is_admin()) {
			add_action('admin_notices', [$this, 'general_admin_notice']);
			if (defined('TIMBER_LOADED')) {
				SoupWaiterAdmin::single()->init();
			}
			Shortcode::single()->init();
		}
	}

	/**
	 * If this post was created for the Soup, show the link
	 */
	public function inject_canonical (){
	    global $post;
	    static $runOnce = true;

	    if ($runOnce && isset($post)){
	        $runOnce = false;
	        if (get_post_meta($post->ID,"kitchen_id",true)){
	            $canonical = get_post_meta($post->ID,"kitchen_url",true);
	            if ($canonical){
		            echo "<link rel='canonical' href='{$canonical}' />";
                }
            }

	    }
    }

	/**
	 * Get the access token connecting us to the SoupKitchen
	 *
	 * Return the token if set, otherwise request it
	 * from the SoupKitchen, creating the user if needed, and
	 * caching the token in $_COOKIE.
	 *
	 * Admin NOTICE on Creating the account (should be once only)
	 * @param $password string|null The password
	 * @return string the Token
	 * @throws \Exception On Requesting Token from Kitchen
	 */
	private function getSoupKitchenToken($password=null){
		if ($password) {
			// Clear the existing token
			$this->kitchen_token = null;
			$this->persist();

			# Identify ourselves to our Chef du Jour
			$request = [
				'body' => [
					'username'  => $this->kitchen_user,
					'password'  => $password
				],
				'timeout'   => self::TIMEOUT,
				'sslverify' => self::SSL_VERIFY
			];
			$auth = $this->kitchen_host.'/'.$this->kitchen_jwt_api.'/token';
			$response = wp_remote_post($auth, $request);

			if (!$this->api_success($response)) {
				ob_start();
				var_dump($response);
				$out = ob_get_clean();
				echo '<!-- FW '.$this->api_error_message($response).' / '.htmlentities($out).' -->';
				throw new \Exception($this->api_error_message($response));
			}
			$tokenResponse = json_decode( wp_remote_retrieve_body( $response ) );
			$this->kitchen_token = $tokenResponse->token;
			$this->persist();
		}
		if (!$this->kitchen_token){
			throw new \Exception("No token available for access to Kitchen");
		}
		return $this->kitchen_token;
	}

	/**
	 *
	 * Return whether the wp_remote_* $response is successful, treating
	 * hard errors (bad params, 500+) and negative response (403) all as errors
	 * allowing
	 *      if ($this->api_success($response)) {
	 *         ... you know you have a valid, positive $response here ...
	 *
	 * @param $response
	 *
	 * @return bool
	 */
	public function api_success($response){
		if (is_wp_error( $response )) return FALSE;
		if (wp_remote_retrieve_response_code($response) > 399) return FALSE;
		return TRUE;
	}

	/**
	 *
	 * Return the error message from a wp_remote_* $response
	 * Usually called after $this->api_success() has returned an error
	 *
	 * @param \WP_Error|array $response
	 *
	 * @return mixed
	 */
	public function api_error_message($response){
		if (is_wp_error($response)) {
			return $response->get_error_message();
		} else {
			$error = json_decode(wp_remote_retrieve_body($response));
			return $error->message;
		}
	}

	/**
	 * @return string[] Options needed
	 * @throws Exception
	 */
	public function std_options(){
		$options =  [
			'headers' => [
				'Authorization' => 'Bearer '.$this->getSoupKitchenToken(),
				'Content-Type' => 'application/json'
			],
			'timeout'   => self::TIMEOUT,
			'sslverify' => self::SSL_VERIFY
		];
		return $options;
	}

	/**
	 * Send a POST JSON API request to the SoupKitchen, always returns a valid response
	 * and throws an exception on an invalid HTTP response (e.g. 403) or error response
	 *
	 * @param string $rri Relative Resource Indicator
	 * @param array $body Body to be sent, in array format
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function postToKitchen($rri,$body){
		global $soup_debug;
		$startTime = time();

		$options = $this->std_options();
		$options['body'] = json_encode($body);
		$response = wp_remote_post($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

		$elapsed = (time() - $startTime);
		$soup_debug[] = "postToKitchen({$rri}): {$elapsed}";
		if (!$this->api_success($response)) {
			throw new \Exception ("postToKitchen({$rri}): ".
			                      $this->api_error_message($response));
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}
	/**
	 * Send a POST JSON API request to the SoupKitchen, always returns a valid response
	 * and throws an exception on an invalid HTTP response (e.g. 403) or error response
	 *
	 * @param string $rri Relative Resource Indicator
	 * @param array $body Body to be sent, in array format
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function deleteFromKitchen($rri,$body=null){
		global $soup_debug;
		$startTime = time();

		$options = $this->std_options();
		//$options['body'] = json_encode($body);
		$options['method'] = 'DELETE';
		$options['force'] = 'true';
		//$rri .= '?force=true';
		$response = wp_remote_request($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

		$elapsed = (time() - $startTime);
		$soup_debug[] = "deleteFromKitchen({$rri}): {$elapsed}";

		if (!$this->api_success($response)) {
		    // Ignore delete errors, nothing we can do anyway (like 'already deleted post')
			// throw new \Exception ("postToKitchen({$rri}): ".
			//                      $this->api_error_message($response));
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}
	/**
	 * Send a GET API request to the SoupKitchen, always returns a valid response
	 * and throws an exception on an invalid HTTP response (e.g. 403) or error response
	 *
	 * @param string $rri Relative Resource Indicator
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function getToKitchen($rri){
		global $soup_debug;
		$startTime = time();

		$options = $this->std_options();
		$response = wp_remote_get($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

		$elapsed = (time() - $startTime);
		$soup_debug[] = "getToKitchen({$rri}): {$elapsed}";

		if (!$this->api_success($response)) {
			throw new \Exception ("getToKitchen({$rri}): ".$this->api_error_message($response));
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}
	/**
	 * Send an API request to the SoupKitchen, always returns a valid response
	 * and throws an exception on an invalid HTTP response (e.g. 403) or error response
	 *
	 * @param string $rri Relative Resource Indicator
	 * @param string $image to send
	 * @param null|string $token if identifying as other (e.g. registrar) user
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function imagePostToKitchen($rri,$image,$token=null){
		global $soup_debug;
        $startTime = time();

		$options = $this->std_options();
		$image_info = pathinfo($image);
		$options['headers']['Content-Type'] = 'image/'.$image_info['extension'];
		$options['headers']['Content-disposition'] = 'attachment; filename='.$image_info['basename'];
		$options['body'] = file_get_contents($image);
		$response = wp_remote_post($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

		$elapsed = (time() - $startTime);
		$soup_debug[] = "imagePostToKitchen({$rri}): {$elapsed}";

		if (!$this->api_success($response)) {
			throw new \Exception ("imagePostToKitchen({$rri}): ".$this->api_error_message($response));
		}

		return json_decode( wp_remote_retrieve_body( $response ) );
	}
	/*
	 * EXTERNAL ENTRY  POINTS FROM HERE
	 */

	/**
	 *
	 * Print in HTML any notices in $_COOKIE storage, and clear them from $_COOKIE.
	 * $_COOKIE['soup-kitchen-notices'] is an array of notices created by addNotice,
	 * each notice of the form
	 *      array( severity, notice [, notice_detail] )
	 * For example:
	 *      array( 'success', "User Created" )
	 *      [notice appears] User Created
	 *
	 * and:
	 *      array( 'warning', "Out of date plugin", "ver 1.23 can cause spontaneous combustion" )
	 *      [notice appears] Warning: Out of date plugin
	 *                       ^^^^^^^: title attr, on hover displays "ver 1.23 can ..."
	 *
	 * Action admin-notices entry point
	 */
	public function general_admin_notice(){
	    $notices = @$_COOKIE['soup-kitchen-notices']?:[];

		while ($notice = array_shift($notices)) {
			$detail = '';
			if (isset($notice[2])) {

				$detail = " <span title='$notice[2]'><b>".ucfirst($notice[0])."</b>:</span>";
			}
			echo "<div class='notice soup notice-{$notice[0]} is-dismissible'>
             <p>{$detail} {$notice[1]}</p>
             
         	</div>";
		}
	}

	/**
	 * @param $post_id
	 *
	 * @throws Exception
	 */
	public function async_delete_post($post_id){
		$kitchen_image_id = get_post_meta($post_id,"kitchen_image_id",true);
		if ($kitchen_image_id){
			// $this->deleteFromKitchen("/media/$kitchen_image_id");
			// delete_post_meta($post_id, "kitchen_image_id");
		}
		$kitchen_id = get_post_meta($post_id,"kitchen_id",true);
		if ($kitchen_id){
			$this->deleteFromKitchen("/posts/$kitchen_id");
			delete_post_meta($post_id, "kitchen_id");
		}
	}

	/**
	 *
	 * Syndicate Post on publish and update
	 *
	 * Action wp_async_save_post entry point
	 *
	 * @param $id
	 * @param $post \WP_Post The post
	 *
	 * @internal param string $newStatus
	 * @internal param string $oldStatus
	 */
	public function wp_async_save_post( $id, \WP_Post $post ) {
		static $notice_sent = false;
		if ($this->skipSyndicate) return;
		if ($post->post_type == 'post'){
			try {
					update_user_meta(get_current_user_id(),"VacationSoup",date("D M j G:i:s T Y"));
					$post_id = $this->syndicate_post($post);
					if ($post_id){
						$this->addNotice('info','Syndicated Post to VacationSoup');
					}
			} catch (Exception $e) {
				$this->addNotice('error','Failed to syndicate Post', $e->getMessage());
				$notice_sent = true;
			}
		}
	}


	/**
	 *
	 * Send post to SoupKitchen
	 *
	 * @param WP_Post $post
	 *
	 * @param bool|string $force Force recreating post & image
	 *
	 * @return bool if we decided not to syndicate (as opposed to errors that are thrown)
	 * @throws Exception
	 */
	public function syndicate_post(WP_Post $post, $force=false){
		if (!in_array($post->post_status ,['draft','publish','future'])){
			return false;
		}
		if (get_post_meta($post->ID,'conceal',true)){
			if (get_post_meta($post->ID,'kitchen_id',true)){
				$this->async_delete_post($post->ID);
			}
			return false;
		}
		if ( 'draft' === $post->post_status ){
			if (get_post_meta($post->ID,'kitchen_id',true)){
				$this->async_delete_post($post->ID);
			}
			return false;
		}

		$featured_image = get_post_thumbnail_id($post->ID);
		if (!$featured_image){
			return false;
		}

		$kitchen = 			[
			'date_gmt' => $post->post_date_gmt,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'title'    => $post->post_title,
			'content'  => $post->post_content,
			'excerpt'  => $post->post_excerpt
		];
		$fi = get_post_meta($post->ID,'kitchen_image_id',true);
		if ($fi) {
			$kitchen['featured_media'] = $fi;
		}


		// Has featured image changed
		$kitchen_featured_image = get_post_meta($post->ID,'soup_local_image_id',true);
		if ( !$kitchen_featured_image ||                        // if there is no image in soup yet
		     ($force && 'image'===$force ) ||                   // or we are doing a forced re-send
		     $featured_image !== $kitchen_featured_image){      // or the featured image has changed
			update_post_meta($post->ID,'soup_local_image_id',$featured_image);

			try {
				$rri           = '/media';
				$kitchen_media = $this->imagePostToKitchen( $rri, get_attached_file( $featured_image ) );
				if ( ! $kitchen_media ) {
					return false;
				}
				update_post_meta( $post->ID, 'kitchen_image_id', $kitchen_media->id );

				$kitchen_image = [
					'date_gmt' => $post->post_date_gmt,
					'slug'     => $post->post_name,
					'status'   => $post->post_status,
					'title'    => get_the_title( $featured_image ),
				];
				$this->postToKitchen( $rri . '/' . $kitchen_media->id, $kitchen_image );
				$kitchen['featured_media'] = $kitchen_media->id;
			} catch (\Exception $e){

				return false;
			}
		}

		if ($force) {
			$id = false;
		} else {
			$id = get_post_meta($post->ID,'kitchen_id',true);
		}

		$rri = '/posts';
		$rri .= ($id)?"/{$id}":'';

		$postTags = wp_get_post_tags($post->ID);
		$tags = [];
		foreach ($postTags as $postTag){
			$tags[] = $postTag->name;
		}

		$status = $post->post_status;
		if (str_word_count($post->post_content) < 100){
			$status = 'pending';
		}

		$default = Property::findOne(0);
		$latitude = get_post_meta($post->ID,'latitude',true);
		$longitude = get_post_meta($post->ID,'longitude',true);
		if (!$latitude || !$longitude){
			update_post_meta($post->ID,'latitude',$default->latitude);
			update_post_meta($post->ID,'longitude',$default->longitude);
		}

		$kitchen['status'] = $status;
		$kitchen['tags_list'] = $tags;
		$kitchen['waiter_url'] = get_post_permalink($post);
		$kitchen['waiter_id'] = $post->ID;
		$kitchen['topic'] = get_post_meta($post->ID,'topic')[0];
		$kitchen['latitude'] = get_post_meta($post->ID,'latitude')[0];
		$kitchen['longitude'] = get_post_meta($post->ID,'longitude')[0];
		$kitchen['waiter_destination_id'] = get_post_meta($post->ID,'destination_id')[0];

		$kitchen_post = $this->postToKitchen( $rri,$kitchen );

		update_post_meta($post->ID,'kitchen_id',$kitchen_post->id);
		update_post_meta($post->ID,'kitchen_url',$kitchen_post->link); //$this->kitchen_host.'/'.$kitchen_post->slug);
		return $post->ID;
	}

	/**
	 * @param bool $force
	 *
	 * @return int number of posts sent
	 * @throws Exception
	 */
	public function syndicate_all_posts($force=true) {
		update_option('vs-resynch-progress',[
			'total'=>0,
			'processed'=>0,
			'progress'=>0
		],true);

		$default = Property::findOne(0);

		$args     = [
			'post_status' => 'publish',
			'post_type'   => 'post',
			'orderby'     => 'date',
			'order'       => 'DESC',
			'posts_per_page' => -1
		];
		$allposts = new \WP_Query( $args );

		$total = count($allposts->posts);
		$processed = 0;
		$reported = 0;

		update_option('vs-resynch-progress',[
			'total'=>$total,
			'processed'=>0,
			'progress'=>0
		],true);

		foreach ( $allposts->posts as $post ) {
			$latitude = get_post_meta($post->ID,'latitude',true);
			$longitude = get_post_meta($post->ID,'longitude',true);
			if (!$latitude || !$longitude){
				update_post_meta($post->ID,'latitude',$default->latitude);
				update_post_meta($post->ID,'longitude',$default->longitude);
			}
			$this->syndicate_post( $post, $force );
			$progress=intval(++$processed*100/$total);
			if ($reported < $progress){
				$reported = $progress;
				update_option('vs-resynch-progress',[
					'total'=>$total,
					'processed'=>$processed,
					'progress'=>$progress
				],true);
				@set_time_limit(30); // Try to stop us timing out
			}
		}
		return $total;
	}
	/**
     *
     * With the 1-per-page slow sync, this now only ever syncs 1 post
	 * @param bool $force
	 *
	 * @return int number of posts sent
	 * @throws Exception
	 */
	public function syndicate_some_posts($force=true) {

		$default = Property::findOne(0);

		$args     = [
			'post_status' => 'publish',
			'post_type'   => 'post',
			'orderby'     => 'date',
			'order'       => 'DESC',
			'posts_per_page' => 1,
			'meta_query' => [
				'relation' => 'AND',
				[
					'key' => 'kitchen_id',
					'compare' => 'NOT EXISTS'
				],
				[
					'key' => '_thumbnail_id',
					'compare' => 'EXISTS'
				]
			]
		];
		$allposts = new \WP_Query( $args );

		foreach ( $allposts->posts as $post ) {
			$latitude = get_post_meta($post->ID,'latitude',true);
			$longitude = get_post_meta($post->ID,'longitude',true);
			if (!$latitude || !$longitude){
				update_post_meta($post->ID,'latitude',$default->latitude);
				update_post_meta($post->ID,'longitude',$default->longitude);
			}
			$this->syndicate_post( $post, $force );
		}
		return count($allposts->posts);
	}
	/**
	 * echo Javascript to trigger kitchenSync
	 */
	public function do_kitchen_sync() {
		if ($this->needs_syndication()) { // If there are posts to synch
			?>
            <script>  // do_kitchen_sync
                jQuery(function () {
                    jQuery.ajax({
                        type: "post",
                        context: this,
                        url: (typeof(ajaxurl) !== 'undefined')?ajaxurl:'/wp-admin/admin-ajax.php',
                        data: {
                            'action': 'soup_resync',
                            _vs_nonce: '<?php echo wp_create_nonce("vacation-soup"); ?>'
                        }
                    });
                });
            </script>
			<?php
		}
	}

	/**
	 * Find out if there are any posts to synch
	 */
	public function needs_syndication(){
		global $wpdb,$table_prefix;

		if (null === $this->kitchen_sync && !isset($_GET['bypass'])  &!defined('SOUP_SYNC_BYPASS')){
            // Faster version than WP_Query generates. Slow speed caused problems for Terry
			$query = "
SELECT  count(*)
FROM {$table_prefix}posts AS p
  LEFT JOIN {$table_prefix}postmeta AS pm_kid ON (
    pm_kid.post_id = p.ID AND
    pm_kid.meta_key = 'kitchen_id'
    )
  LEFT JOIN {$table_prefix}postmeta AS pm_tid ON (
    pm_tid.post_id = p.ID AND
    pm_tid.meta_key = '_thumbnail_id'
    )
WHERE ( pm_kid.post_id IS NULL AND 
        pm_tid.post_id IS NOT NULL)
      AND p.post_type = 'post'
      AND p.post_status = 'publish'";

			$this->kitchen_sync = $wpdb->get_var($query);

			/* $args     = [
				'post_status' => 'publish',
				'post_type'   => 'post',
				'posts_per_page' => -1,
				'fields' => 'ids',
				'no_found_rows' => true,
				'meta_query' => [
					'relation' => 'AND',
					[
						'key' => 'kitchen_id',
						'compare' => 'NOT EXISTS'
					],
					[
						'key' => '_thumbnail_id',
						'compare' => 'EXISTS'
					]
				]
			];
			$posts_count = new \WP_Query( $args );

			if (isset($posts_count)){
				$this->kitchen_sync = $posts_count->post_count;
			}*/
		}
		return $this->kitchen_sync;
	}

}

