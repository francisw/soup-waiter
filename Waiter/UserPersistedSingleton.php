<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class UserPersistedSingleton extends Singleton  {
    /**
     * Persist and recall this object in options
     * @param null $key
     * @return UserPersistedSingleton The object calling this method
     */
	protected function persist($key=null){
		$key = self::_persistedName();
		$user = get_current_user_id();

		update_user_meta($user,$key,$this);
		return $this;
	}
	static protected function getPersisted($key=null){
		$user = get_current_user_id();
		
		$persisted = get_user_meta($user,self::_persistedName(),true);
		if (!$persisted){
			$persisted = get_option(self::_persistedName(true));
			if ($persisted){
				update_user_meta($user,self::_persistedName(),$persisted);
				delete_option(self::_persistedName(true));
			}
		} 
			
		return $persisted;
	}
	static private function _persistedName($raw=false){
		$persistedName = 'vs-'.get_called_class();

		if ($raw) {
			return $persistedName;
		} else {
			return stripslashes($persistedName);
		}
	}
}