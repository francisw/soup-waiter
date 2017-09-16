<?php
require_once "SoupWaiter.php";

/*
Plugin Name: VacationSoup Owner plugin
Plugin URI: https://vacationsoup.com/
Description: Syndicate and Automate Vacation Rental Posting with Vacation Soup
Version: 0.0
Author: Francis Wallinger
Author URI: https://vacationsoup.com
License: GPL2
*/

add_action ('init',array(SoupWaiter::single(),'init'));

