<?php
$this->setMdb2Dsn(array(
	'phptype'  => 'mysql',
	'username' => 'factoids',
	'password' => 'f4ct01d5',
	'hostspec' => 'localhost',
	'database' => 'tig_factoids'
));
$this->setMdb2Options(array(
	'persistent' => true
));

$this->setTablePrefix('');

$this->setFriends(array(
	'clampsbot',
));