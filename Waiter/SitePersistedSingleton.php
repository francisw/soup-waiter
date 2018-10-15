<?php
namespace Waiter;

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 12/09/2017
 * Time: 11:30
 */
class SitePersistedSingleton extends Singleton  {
    /**
     * Persist this object in options
     * @param null $key
     * @return Singleton The object calling this method
     */
	protected function persist($key=null){
		$class = get_called_class();
		update_option("vs-{$class}",$this,false);
		return $this;
	}

	/**
	 * Return persisted object
	 * @param null $key
	 *
	 * @return Singleton|null
	 */
	static protected function getPersisted($key=null){
		$class = get_called_class();
		return get_option("vs-{$class}");
	}
	/**
	 * Delete persisted object
	 * @param null $key
	 *
	 * @return void
	 */
	static public function deletePersisted($key=null){
		$class = get_called_class();
		delete_option("vs-{$class}");
	}
}