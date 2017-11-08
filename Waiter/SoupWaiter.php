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
class SoupWaiter extends SitePersistedSingleton {
	const REGISTRY_USER = 'soup-kitchen-registry';
	const REGISTRY_PASS = 'OpenDoor';
	const APIUSER_PASS  = 'OpenDoor';
	const SSL_VERIFY    = false; // make true on production
	const TIMEOUT       = 500; // ms

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
     * @var number $property_count The number of properties
     */
    protected $property_count;
    /**
     * @var string[] $joins the Joining words to use in topics (first is default), e.g. Best beaches ON Bornholm
     */
    protected $joins;
    /**
     * @var string[] $destinations the Joining words to use in topics (first is default), e.g. Best beaches on BORNHOLM
     */
    protected $destinations;

    protected function get_current_destination(){
        $id=0; // Default
        if (isset($_REQUEST['destination_id'])){
            $id = intval($_REQUEST['destination_id']);
        }
        return $id;
    }
	protected function get_destination(){
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
		if (0==strncasecmp($host,'https://',8)){
			$this->kitchen_host = $host;
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
		$this->set_kitchen_host('https://core.vacationsoup.com'); # 'https://staging1.privy2.com';#
		$this->kitchen_api = 'wp-json/wp/v2';
		$this->kitchen_jwt_api = 'wp-json/jwt-auth/v1';
        $this->nextMOTD = 0;
        $this->next_topic = 0; // This is an offset, not a page number
        $this->property_count = 0;
	}


	/**
	 * @param $level string error, warning, success, info
	 * @param $notice string Message
	 * @param $detail string|null Further detail on alt-text
	 */
	public function addNotice($level,$notice,$detail=null) {
		$_SESSION['soup-kitchen-notices'][] = [$level,$notice,htmlentities($detail)];
	}

	/**
	 * Called from the 'init' Wordpress action
	 *
	 * Retrieve Session variables
	 * add action and filter hooks
	 *
	 */
	public function init(){
		if(!session_id()) session_start();

		if (!isset($_SESSION['soup-kitchen-notices'])){
			$_SESSION['soup-kitchen-notices'] = [];
		}
		// add_action( 'shutdown', [$this, 'wp_async_save_post'],10,2 );
		// new SoupAsync(); // Asynchronous post saving

		// Now install the admin screens if needed
		if (is_admin()) {
			add_action('admin_notices', [$this, 'general_admin_notice']);
			if (defined('TIMBER_LOADED')) {
				SoupWaiterAdmin::single()->init();
			}
		}
	}


	/**
	 * Get the access token connecting us to the SoupKitchen
	 *
	 * Return the $_SESSION token if set, otherwise request it
	 * from the SoupKitchen, creating the user if needed, and
	 * caching the token in $_SESSION.
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
	private function api_success($response){
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
	private function api_error_message($response){
		if (is_wp_error($response)) {
			return $response->get_error_message();
		} else {
			$error = json_decode(wp_remote_retrieve_body($response));
			return $error->message;
		}
	}

	private function std_options($body = null){
		$options =  [
			'headers' => [
				'Authorization' => 'Bearer '.$this->getSoupKitchenToken(),
				'Content-Type' => 'application/json'
			],
			'timeout'   => self::TIMEOUT,
			'sslverify' => self::SSL_VERIFY
		];
		if ($body){
			$options['body'] = $body;
		}
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
		$options = $this->std_options($body);
		$response = wp_remote_post($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

		if (!$this->api_success($response)) {
			throw new \Exception ("postToKitchen({$rri}): ".
			                      $this->api_error_message($response));
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

		$options = $this->std_options();
		$response = wp_remote_get($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

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

		$options = $this->std_options(file_get_contents($image));
		$image_info = pathinfo($image);
		$options['headers']['Content-Type'] = 'image/'.$image_info['extension'];
		$options['headers']['Content-disposition'] = 'attachment; filename='.$image_info['basename'];
		$response = wp_remote_post($this->kitchen_host.'/'.$this->kitchen_api.$rri,$options);

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
	 * Print in HTML any notices in $_SESSION storage, and clear them from $_SESSION.
	 * $_SESSION['soup-kitchen-notices'] is an array of notices created by addNotice,
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
		while ($notice = array_shift($_SESSION['soup-kitchen-notices'])) {
			$detail = '';
			if (isset($notice[2])) {

				$detail = " <span title='$notice[2]'><b>".ucfirst($notice[0])."</b>:</span>";
			}
			echo "<div class='notice notice-{$notice[0]} is-dismissible'>
             <p>{$detail} {$notice[1]}</p>
             
         	</div>";
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
		if ($post->post_type == 'post'){
			try {
					update_user_meta(get_current_user_id(),"VacationSoup",date("D M j G:i:s T Y"));
					$this->syndicate_post($post);
					$this->addNotice('info','Syndicated Post to VacationSoup');
			} catch (Exception $e) {
				$this->addNotice('error','Failed to syndicate Post', $e->getMessage());
			}
		}
	}

	/**
	 *
	 * Send post to SoupKitchen
	 *
	 * @param WP_Post $post
	 *
	 *
	 */
	private function syndicate_post(WP_Post $post){
		$featured_image = get_post_thumbnail_id($post->ID);

		$kitchen = 			[
			'date_gmt' => $post->post_date_gmt,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'title'    => $post->post_title,
			'content'  => $post->post_content,
			'excerpt'  => $post->post_excerpt,
		];

		if ($featured_image){
			// Has it changed
			$kitchen_featured_image = get_post_meta($post->ID,'kitchen_local_image_id',true);
			if ($featured_image !== $kitchen_featured_image){
				update_post_meta($post->ID,'kitchen_local_image_id',$featured_image);

				$rri = '/media';
				$kitchen_media = $this->imagePostToKitchen($rri,get_attached_file($featured_image));
				update_post_meta($post->ID,'kitchen_image_id',$kitchen_media->id);

				$kitchen_image = 			[
					'date_gmt' => $post->post_date_gmt,
					'slug'     => $post->post_name,
					'status'   => $post->post_status,
					'title'    => get_the_title($featured_image),
				];
				$this->postToKitchen( $rri.'/'.$kitchen_media->id,$kitchen_image );
			}
			$kitchen['featured_media'] = $kitchen_media->id;
		}

		$id = get_post_meta($post->ID,'kitchen_id',true);
		$rri = '/posts';
		$rri .= ($id)?"/{$id}":'';

		$kitchen_post = $this->postToKitchen( $rri,$kitchen );
		update_post_meta($post->ID,'kitchen_id',$kitchen_post->id);
	}

}

