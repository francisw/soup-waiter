<?php

/**
 * Created by PhpStorm.
 * User: francisw
 * Date: 01/09/2017
 * Time: 15:10
 */
class Topic {
	/**
	 * @var string $content The unique topic, e.g. My favourite café
	 */
	private $content;
	/**
	 * @var string joining word, e.g. in
	 */
	private $join;
	public function __construct() {
		$this->join = 'in';
	}
}