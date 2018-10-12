<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 17/09/2017
 * Time: 16:50
 */

namespace Waiter;

class SocialProvider extends PersistedSingleton {
// Add anything we know we need for all social providers

	/**
	 * Default display name as Cased (gets overriden for things that behave like twitter)
	 * @return string
	 */
	protected function get_display_name(){
		return ucfirst($this->name);
	}

	/**
	 * Default NickName (nName)
	 * @return string
	 */
	protected function get_nName() { return get_called_class().':'.SoupWaiter::single()->kitchen_user; }

	/**
	 * Default font-awesome unique identifier (override if name won't do)
	 * NB: this has 'fa-' prepended and '-square' appended
	 * @return string
	 */
	protected function get_fa_name(){
		return "fa-".$this->name."-square";
	}

	public function toArray(){
		$arr = [];
		foreach ($this as $key => $val){
			$arr[$key] = $val;
		}
		return $arr;
	}

}