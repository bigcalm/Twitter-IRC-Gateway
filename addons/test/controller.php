<?php
class addonExample extends botController
{
	function channel_test(&$irc, &$data)
	{
		global $tigBase;
		$irc->message(SMARTIRC_TYPE_CHANNEL, $data->channel, $data->nick . ": no I don't like tests!");
	}
	
	function query_test(&$irc, &$data)
	{
		global $tigBase;
		
		$channels = $tigBase->getVar('channels');
		$channel = $channels[0];
		
		$irc->message(SMARTIRC_TYPE_CHANNEL, $channel, $data->nick.' said "'.$data->message.'" to me!');
		$irc->message($data->type, $data->nick, 'I told everyone on ' . $channel . ' what you said!');
	}
}

$addonExample = new addonExample();

$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE,'^test', $addonExample, 'query_test');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL, '^test', $addonExample, 'channel_test');
