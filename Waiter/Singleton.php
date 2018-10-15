<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class Singleton extends Base  {
	/**
	 * Get a singleton of this class.
	 * @param bool $purge True to purge the cache
	 *
	 * @return Singleton
	 */
	static function single($purge=false){
		static $single = [];
		$class = get_called_class();

		if ($purge) {
			$single = [];
		}

		if( empty($single[$class]) ) {
			$single[$class] = static::getPersisted();
			if (!$single[$class]) {
				$single[$class] = new $class();
			}
		}
		return $single[$class];
	}
}