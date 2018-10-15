<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 24/10/2017
 * Time: 14:14
 */

namespace Waiter;


class Shortcode extends Singleton {

	public function init(){
		add_shortcode('waiter',[SoupWaiterAdmin::single(),'create_admin_page']);
	}

}