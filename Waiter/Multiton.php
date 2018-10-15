<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class Multiton extends Base  {
    protected $id;
    protected $created;


    /**
     * Get a singleton of this class.
     *
     * @param $key string
     * @oparam $create bool (default True) If False, return null if key not found
     * @return mixed
     */
	static function findOne($key,$create=true){
		global $SoupMultitonCache;

		$class = get_called_class();

		if (!isset($SoupMultitonCache[$class])){
			$SoupMultitonCache[$class] = [];
        }

		if( !isset($SoupMultitonCache[$class][$key]) || empty($SoupMultitonCache[$class][$key]) ) {
			$SoupMultitonCache[$class][$key] = static::getPersisted($key);
			if (!$SoupMultitonCache[$class][$key]) {
				if ($create){
					$SoupMultitonCache[$class][$key] = new $class();
					$SoupMultitonCache[$class][$key]->registerMultiton($key);
				} else {
					unset($SoupMultitonCache[$class][$key]);
				}
			}
		}
		return @$SoupMultitonCache[$class][$key];
	}

	static function findAll(){
		global $SoupMultitonCache;
		$i = 0;
		$class = get_called_class();

		if (    !$SoupMultitonCache ||
		        !$SoupMultitonCache[$class] ||
		        !$SoupMultitonCache[$class][0]) {
			while (static::findOne($i,false)){
				$i++;
			}
		}
		return $SoupMultitonCache[$class];
	}

	static function count(){
		return count(static::findAll());
	}

	protected function registerMultiton($key){
	    // may get overlayed by child class
        $this->id = $key;
        $this->created = true;
    }
}