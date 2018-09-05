<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class SitePersistedMultiton extends Multiton  {

    /**
     * Persist and recall this object in options
     * @param string|null $key
     * @return SitePersistedMultiton The object calling this method
     */
	protected function persist($key=null){
	    if (null==$key){
	        $key = $this->id;
        }
        if ($this->created){
	        unset($this->created);
	        $this->onCreate($key);
        }
		$class = stripslashes(get_called_class());
		update_option("vs-{$class}-{$key}",$this,false);
		return $this;
	}

    /**
     * @param string|null $key
     * @return SitePersistedMultiton
     * @throws \Exception nIf no key is passed
     */
	static protected function getPersisted($key=null){
        $class = stripslashes(get_called_class());
        if (null===$key){
            throw new \Exception("Can not retrieve {$class} without a key");
        }
		$instance = get_option("vs-{$class}-{$key}");
		return $instance;
	}

	public function onCreate($key){
	    // do nothing, and get overridden
    }
}