<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 17/09/2017
 * Time: 16:50
 */

namespace Waiter;

class SocialProvider extends SitePersisted {
// Add anything we know we need for all social providers

	/**
	 * Default display name as Cased (gets overriden for things that behave like twitter
	 */
	protected function get_display_name(){
		return ucfirst($this->name);
	}
}