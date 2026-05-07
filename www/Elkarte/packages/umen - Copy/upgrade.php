<?php

/**
 * @package   Ultimate Menu mod
 * @version   1.1.1
 * @author    John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2014, John Rayes
 * @license   http://opensource.org/licenses/MIT MIT
 */

// If we have found SSI.php and we are outside of ElkArte, then we are running standalone.
if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('ELK'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif (!defined('ELK')) // If we are outside ElkArte and can't find SSI.php, then throw an error
	die('<b>Error:</b> Cannot install - please verify you put this file in the same place as ElkArte\'s SSI.php.');

$dbtbl = db_table();
$dbtbl->db_add_column(
	'{db_prefix}um_menu',
	array(
		'name' => 'icon',
		'type' => 'varchar',
		'size' => 40,
	),
	array(),
	'ignore'
);
$dbtbl->db_remove_column(
	'{db_prefix}um_menu',
	'slug'
);