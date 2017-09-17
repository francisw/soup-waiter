<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class SItePersisted extends Singleton  {
	/**
	 * Persist this class
	 * @return self The object calling this method
	 */
	protected function persist(){
		$class = get_called_class();
		update_site_option("vs-{$class}",$this);
		return $this;
	}
}