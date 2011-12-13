<?php
// error_reporting(-1);
set_time_limit(0);

define('TIG_VERSION', 0.1);

$debugging = FALSE;

if (count($argv) > 1)
{
	if (in_array('--debug', $argv))
		$debugging = TRUE;
}

define('DEBUG', $debugging);

include_once('config/config.php');

require_once('Net/SmartIRC.php');

global $tigBase; // I did not want to do this

$irc = new Net_SmartIRC();

if ($debugging)
	$irc->setDebug(SMARTIRC_DEBUG_ALL);

$irc->setUseSockets(TRUE);

include_once('core_classes/bot_class.php');

foreach ($addons as $addon)
{
	include_once 'addons/' . $addon . '/controller.php';
}


$irc->connect($ircDetails['server hostname'], $ircDetails['server port']);
$irc->login($ircDetails['nickname'], $ircDetails['real name'], 0, $ircDetails['username'], $ircDetails['server password']);
$irc->join($ircDetails['channels']);
$irc->listen();
$irc->disconnect();
?>
