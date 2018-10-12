<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class PersistedMultiton extends Multiton  {

    /**
     * Persist and recall this object in options
     *
     * @param string|null $id
     *
     * @return PersistedMultiton The object calling this method
     */
	protected function persist($id=null){
	    if (null==$id){
	        $id = $this->id;
        }

		if ($this->created){
	        unset($this->created);
	        $this->onCreate($id);
        }

		if (SoupWaiter::is_multiuser()) {
			update_user_meta( get_current_user_id(), self::userMetaName($id), $this );
		} else {
			update_option( self::optionName($id), $this, 'yes' );
		}
		return $this;
	}

    /**
     * @param string|null $id
     *
     * @return PersistedMultiton
     * @throws \Exception nIf no key is passed
     */
	static protected function getPersisted($id=null){
        if (null===$id){
            throw new \Exception("Can not retrieve ".get_called_class()." without a key");
        }

		$user = get_current_user_id();

		if (SoupWaiter::is_multiuser()) {
			$persisted = get_user_meta($user,self::userMetaName($id),true);
			if (!$persisted){
				// If no user level record, check for option record and import it if found
				$persisted = get_option(self::optionName($id));
				if ($persisted){
					update_user_meta($user,self::userMetaName($id),$persisted);
					delete_option(self::optionName($id));
				}
			}
		} else {
			$persisted = get_option(self::optionName($id));
			if (!$persisted){
				// If no option record, check for first user record and import it if found
				$persisted = get_user_meta($user,self::userMetaName($id),true);
				if ($persisted){
					update_option(self::optionName($id),$persisted,'yes');
					delete_user_meta($user,self::userMetaName($id));
				}
			}
		}

		return $persisted;
	}

	/**
	 * @param int $key
	 * @return string Option Name, includes slashes
	 */
	static private function optionName($key){
		return 'vs-'.get_called_class()."-{$key}";
	}

	/**
	 * @return string Meta name without slahes because that caused lots of problems
	 */
	static private function userMetaName($key){
		return stripslashes(self::optionName($key));
	}

	public function onCreate($key){
	    // do nothing, and get overloaded if needed
    }
}