<?php

namespace Waiter;

use Waiter\SoupWaiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 17/09/2017
 * Time: 13:30
 *
 * I would like to use class constants here but that creates a PHP 7.1 dependency
 */
class TW extends SocialProvider {
	//protected $nName;
	protected function get_name() { return "twitter"; }
	protected function get_display_name(){
		return $this->name; // Don't uppercase first letter (which is default)
	}
	protected function get_nName() { return "tw:".SoupWaiter::single()->kitchen_user; }
	protected $appKey;
	protected $appSec;
	protected $accessToken;
	protected $accessTokenSec;
}
/*
Fields sent on an API request

action:nxs_snap_aj
nxsact:setNTset
nxs_mqTest:'
_wp_http_referer:/~francisw/sandbox/wp-admin/options-general.php?page=NextScripts_SNAP.php
nxs_wp_http_referer:/~francisw/sandbox/wp-admin/options-general.php?page=NextScripts_SNAP.php
nxsSsPageWPN_wpnonce:d5a1c77e29
_wpnonce:d5a1c77e29
isOut:1
apDoSFB0:0
fb[0][do]:1
fb[0][nName]:fb1234
fb[0][appKey]:app_id_for_1234
fb[0][appSec]:sec_for_1234
fb[0][msgFormat]:New post (%TITLE%) has been published on %SITENAME%
fb[0][imgUpl]:T
fb[0][postType]:A
fb[0][attachVideo]:N
fb[0][do]:1
*/
