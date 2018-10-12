<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class PersistedSingleton extends Singleton  {
    /**
     * Persist and recall this object in options
     *
     * @param null $key
     *
     * @return PersistedSingleton The object calling this method
     */
	protected function persist($key=null){

		if (SoupWaiter::is_multiuser()){
			update_user_meta(get_current_user_id(),self::userMetaName(),$this);
		} else {
			update_option(self::optionName(),$this,'yes');
		}

		return $this;
	}

	/**
	 * @param null $key Not used, Only maintained for fingerprint
	 *
	 * @return PersistedSingleton
	 */
	static protected function getPersisted($key=null){
		$user = get_current_user_id();

		$t1 = self::optionName();

		if (SoupWaiter::is_multiuser()) {
			$persisted = get_user_meta($user,self::userMetaName(),true);
			if (!$persisted){
				// If no user level record, check for option record and import it if found
				$persisted = get_option(self::optionName());
				if ($persisted){
					update_user_meta($user,self::userMetaName(),$persisted);
					delete_option(self::optionName());
				}
			}
		} else {
			$persisted = get_option(self::optionName());
			if (!$persisted){
				// If no option record, check for first user record and import it if found
				$persisted = get_user_meta($user,self::userMetaName(),true);
				if ($persisted){
					update_option(self::optionName(),$persisted,'yes');
					delete_user_meta($user,self::userMetaName());
				}
			}
		}

		return $persisted;
	}

	/**
	 * @return string Option Name, includes slashes
	 */
	static private function optionName(){
		return 'vs-'.get_called_class();
	}

	/**
	 * @return string Meta name without slahes because that caused lots of problems
	 */
	static private function userMetaName(){
		return stripslashes(self::optionName());
	}
}