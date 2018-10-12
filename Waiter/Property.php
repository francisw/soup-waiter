<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 07/10/2017
 * Time: 15:34
 */

namespace Waiter;


class Property extends PersistedMultiton
{
    protected $title;
	protected $join;
	protected $destination;
	protected $join2;
	protected $destination2;
	protected $join3;
	protected $destination3;
	protected $latitude;
	protected $longitude;

    public function __construct()
    {
        $this->join = 'in';
    }

    public function __set($name,$value){
    	parent::__set($name,$value);
    	SoupWaiterAdmin::single()->get_properties(); // This re-loads property summaries into Waiter
    }

    public function onCreate($key){

    }
}