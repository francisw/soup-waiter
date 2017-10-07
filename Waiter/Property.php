<?php
/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 07/10/2017
 * Time: 15:34
 */

namespace Waiter;


class Property extends SitePersistedMultiton
{
    protected $title;
    protected $join;
    protected $destination;

    public function __construct()
    {
        $this->join = 'in';
    }

    public function onCreate($key){
        SoupWaiter::single()->property_count++;
    }
}