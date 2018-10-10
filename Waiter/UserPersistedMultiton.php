<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class UserPersistedMultiton extends Multiton  {

    /**
     * Persist and recall this object in options
     * @param string|null $id
     * @return UserPersistedMultiton The object calling this method
     */
	protected function persist($id=null){
	    if (null==$id){
	        $id = $this->id;
        }

		$key = self::_persistedName($id);
		$user = get_current_user_id();

		if ($this->created){
	        unset($this->created);
	        $this->onCreate($id);
        }

		update_user_meta($user,$key,$this);
		return $this;
	}

    /**
     * @param string|null $id
     * @return UserPersistedMultiton
     * @throws \Exception nIf no key is passed
     */
	static protected function getPersisted($id=null){
        if (null===$id){
            throw new \Exception("Can not retrieve ".get_called_class()." without a key");
        }

		$user = get_current_user_id();

		$persisted = get_user_meta($user,self::_persistedName($id),true);
		if (!$persisted){
			$persisted = get_option(self::_persistedName($id,true));
			if ($persisted){
				update_user_meta($user,self::_persistedName($id),$persisted);
				delete_option(self::_persistedName($id,true));
			}
		}

		return $persisted;
	}

	/**
	 * @param integer $key Index for which multiton
	 * @param bool $raw=false use raw name (including \ in class names, as was used for option storage originally)
	 *
	 * @return string
	 */
	static private function _persistedName($key,$raw=false){
		$class = get_called_class();
		$persistedName = "vs-{$class}-{$key}";

		if ($raw) {
			return $persistedName;
		} else {
			return stripslashes($persistedName);
		}
	}

	public function onCreate($key){
	    // do nothing, and get overloaded if needed
    }
}