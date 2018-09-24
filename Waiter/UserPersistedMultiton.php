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

		$key = self::_persistedName($id);
		$user = get_current_user_id();


		$persisted = get_user_meta($user,$key,true);
		if (!$persisted){
			$persisted = get_option(get_called_class());
			if ($persisted){
				update_user_meta($user,$key,$persisted);
				delete_option(get_called_class());
			}
		}


		return $persisted;
	}
	static private function _persistedName($key){
		$class = get_called_class();
		return stripslashes("vs-{$class}-{$key}");
	}

	public function onCreate($key){
	    // do nothing, and get overloaded if needed
    }
}