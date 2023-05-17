<?php

if (file_exists(dirname(__FILE__) . '/SSI.php') && !defined('SMF'))
	require_once(dirname(__FILE__) . '/SSI.php');
elseif(!defined('SMF'))
	die('<b>Error:</b> Cannot install - please verify that you put this file in the same place as SMF\'s index.php and SSI.php files.');

if ((SMF == 'SSI') && !$user_info['is_admin'])
	die('Admin privileges required.');

global $smcFunc;

db_extend('packages');
$smcFunc['db_create_table']('{db_prefix}telegram', 
	array(
		array(
			'name' => 'chatID',
			'type' => 'int',
			'unsigned' => true,
			'auto' => false,
		),
		array(
			'name' => 'memID',
			'type' => 'int',
			'unsigned' => true,
			'auto' => false,
		),
		array(
			'name' => 'data',
			'type' => 'tinytext',
		),
	),
	array(
		array('columns' => array('chatID'), 'type' => 'primary')
	),
	array(),
		'ignore');
		
$smcFunc['db_create_table']('{db_prefix}tg_notify_antibf', 
	array(
		array(
			'name' => 'chatID',
			'type' => 'int',
			'unsigned' => true,
			
		),
		array(
			'name' => 'date',
			'type' => 'int',
		),
		array(
			'name' => 'hit',
			'type' => 'int',
		),
	),
	array(
		array('columns' => array('chatID'), 'type' => 'primary')
	),
	array(),
		'ignore');

if (SMF == 'SSI')
	echo 'Database changes are complete! Please wait...';
?>