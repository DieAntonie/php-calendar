<?php
/*
   Copyright 2002 Sean Proctor, Nathan Poiro

   This file is part of PHP-Calendar.

   PHP-Calendar is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   PHP-Calendar is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with PHP-Calendar; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

define('IN_PHPC', 1);

include('miniconfig.php');
include($phpc_root_path . 'includes/calendar.php');

session_start();

ini_set('arg_separator.output', "&amp;");

unset($user);
$user = $HTTP_SESSION_VARS['user'];

list($BName, $BVersion) = browser();

$db_events = new sql_db(SQL_HOSTNAME, SQL_USERNAME, SQL_PASSWORD, SQL_DATABASE);

/*
   echo "<pre>get vars:</pre>";
   foreach ($HTTP_GET_VARS as $key=>$val){
   echo "<pre>$key: $val</pre>";
   }
   echo "<pre>post vars:</pre>";
   foreach ($HTTP_POST_VARS as $key=>$val) {
   echo "<pre>$key: $val</pre>";
   }
 */

foreach($HTTP_GET_VARS as $key => $value) {
	$vars[$key] = $value;
}

foreach($HTTP_POST_VARS as $key => $value) {
	$vars[$key] = $value;
}

$currentday = date('j');
$currentmonth = date('n');
$currentyear = date('Y');

if (!isset($vars['month'])) {
	$month = $currentmonth;
} else {
	$month = $vars['month'];
}

if(!isset($vars['year'])) {
	$year = $currentyear;
} else {
	$year = date('Y', mktime(0,0,0,$month,1,$vars['year']));
}

if(!isset($vars['day'])) {
	if($month == $currentmonth) $day = $currentday;
	else $day = 1;
} else {
	$day = ($vars['day'] - 1) % date("t", mktime(0,0,0,$month,1,$year)) + 1;
}

while($month < 1) $month += 12;
$month = ($month - 1) % 12 + 1;

if(empty($vars['action'])) {
	$action = 'main';
} else {
	$action = $vars['action'];
}

switch($action) {
	case 'add':

		include($phpc_root_dir . 'includes/event_form.php');
		$output = event_form('add');
		break;

	case 'delete':

		include($phpc_root_dir . 'includes/event_delete.php');
		$output = delete();
		break;

	case 'display':

		include($phpc_root_dir . 'includes/display.php');
		$output = display();
		break;

	case 'submit':

		include($phpc_root_dir . 'includes/event_submit.php');
		$output = submit_event();
		break;

	case 'modify':

		include($phpc_root_dir . 'includes/event_form.php');
		$output = event_form('modify');
		break;

	case 'search':

		include($phpc_root_dir . 'includes/search.php');
		$output = search();
		break;

	case 'login':

		include($phpc_root_dir . 'includes/login.php');
		$output = login();
		break;

	case 'logout':

		include($phpc_root_dir . 'includes/logout.php');
		$output = logout();
		break;

	case 'main':

		include($phpc_root_dir . 'includes/main.php');
		$output = calendar();
		break;

	default:
		soft_error(_('Invalid action'));

}

echo top() . navbar() . $output . bottom();

?>
