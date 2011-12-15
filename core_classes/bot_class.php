<?php
class tigBase
{
	protected $trustedUsers = array();
	var $variables;
	
	// -- Object setting functions --
	public function setTrustedUsers($trustedUsers)
	{
		$this->trustedUsers = $trustedUsers;
	}
	public function getTrustedUsers()
	{
		return $this->trustedUsers;
	}
	
	
	public function setVar($key, $value)
	{
		$this->variables[$key] = $value;
	}
	public function getVar($key)
	{
		return $this->variables[$key];
	}
	public function delVar($key)
	{
		unset($this->variables[$key]);
	}
	
	
	public function isTrustedUser($nick)
	{
		return in_array(strtolower($nick), $this->trustedUsers);
	}
	
	
	public function restrictedError(&$irc, &$data)
	{
		$irc->message(SMARTIRC_TYPE_NOTICE, $data->nick, "I'm very sorry, but I cannot do that as you are not in my list of trusted users.");
		return false;
	}
	
	
	public function sec2hms ($sec, $padHours = false) 
	{
	
		// start with a blank string
		$hms = "";
		
		// do the hours first: there are 3600 seconds in an hour, so if we divide
		// the total number of seconds by 3600 and throw away the remainder, we're
		// left with the number of hours in those seconds
		$hours = intval(intval($sec) / 3600); 
		
		// add hours to $hms (with a leading 0 if asked for)
		$hms .= ($padHours) 
			? str_pad($hours, 2, "0", STR_PAD_LEFT). ":"
			: $hours. ":";
		
		// dividing the total seconds by 60 will give us the number of minutes
		// in total, but we're interested in *minutes past the hour* and to get
		// this, we have to divide by 60 again and then use the remainder
		$minutes = intval(($sec / 60) % 60); 
		
		// add minutes to $hms (with a leading 0 if needed)
		$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ":";
		
		// seconds past the minute are found by dividing the total number of seconds
		// by 60 and using the remainder
		$seconds = intval($sec % 60); 
		
		// add seconds to $hms (with a leading 0 if needed)
		$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT);
		
		// done!
		return $hms;
		
	}

}

class botController extends tigBase
{
	public function quit(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		/*
		// kill any existing threads before quitting IRC and shutting down the bot
		if (is_object($this->getForkedProcess()))
		{
			$twitterThread = $this->getForkedProcess();
			$twitterThread->stop();
		}
		*/
		
		$irc->quit($data->nick . " has asked me to quit IRC. Good bye everybody :)");
	}
	
	
	public function changeNick(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if(isset($data->messageex[1]))
		{
			$newNick = $data->messageex[1];
		}
		else
		{
			$newNick = $tigBase->getVar('nickname');
		}

		$irc->changeNick($newNick, SMARTIRC_CRITICAL);
	}
	
	
	public function channelAdd(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (count($data->messageex) < 3)
		{
			$irc->message($data->type, $data->nick, "Missing arguments. Correct format is: channel add <#channel>");
			return true;
		}
		
		$newChannel = $data->messageex[2];
		
		$channels = $tigBase->getVar('channels');
		if (!in_array($newChannel, $channels))
		{
			$channels[] = $newChannel;
			$tigBase->setVar('channels', $channels);
			$irc->message($data->type, $data->nick, $newChannel . " has been added to the list of accessable channels.");
		}
		else
		{
			$irc->message($data->type, $data->nick, $newChannel . " is already in the list of accessable channels.");
		}
	}
	
	
	public function channelRemove(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (count($data->messageex) < 3)
		{
			$irc->message($data->type, $data->nick, "Missing arguments. Correct format is: channel remove <#channel>");
			return true;
		}
		
		$removeChannel = $data->messageex[2];
		
		$channels = $tigBase->getVar('channels');
		if (in_array($removeChannel, $channels))
		{
			for ($i = 0; $i < count($channels); $i++)
			{
				if ($channels[$i] == $removeChannel)
				{
					unset($channels[$i]);
				}
			}
			$tigBase->setVar('channels', $channels);
			$irc->message($data->type, $data->nick, $removeChannel . " has been removed from the list of accessable channels.");
		}
		else
		{
			$irc->message($data->type, $data->nick, $removeChannel . " is not in the list of accessable channels.");
		}
		
	}
	
	
	public function channelList(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		$channels = $tigBase->getVar('channels');
		$irc->message($data->type, $data->nick, "Accessable channels: " . implode(', ', $channels));
	}
	
	
	public function joinChannel(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (count($data->messageex) < 2)
		{
			$irc->message($data->type, $data->nick, "Missing arguments. Correct format is: channel join <#channel>");
			return true;
		}
		
		$channel = $data->messageex[1];
		$channels = $tigBase->getVar('channels');
		
		if (in_array($channel, $channels))
		{
			$irc->join($channel);
			$irc->message($data->type, $data->nick, "Joining channel " . $channel);
		}
		else
		{
			$irc->message($data->type, $data->nick, $channel . " is not in the list of accessable channels.");
		}
	}
	
	
	public function leaveChannel(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (count($data->messageex) < 2)
		{
			$irc->message($data->type, $data->nick, "Missing arguments. Correct format is: channel leave <#channel>");
			return true;
		}
		
		$channel = $data->messageex[1];
		$channels = $tigBase->getVar('channels');
		
		if (in_array($channel, $channels))
		{
			$irc->part($channel);
			$irc->message($data->type, $data->nick, "Leaving channel " . $channel);
		}
		else
		{
			$irc->message($data->type, $data->nick, $channel . " is not in the list of accessable channels.");
		}
	}
}


$tigBase = new tigBase();

$tigBase->setTrustedUsers($trustedUsers);
$tigBase->setVar('channels', $ircDetails['channels']);
$tigBase->setVar('nickname', $ircDetails['nickname']);


$bot = new botController();

$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^quit', $bot, 'quit');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^changenick', $bot, 'changeNick');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^channel add', $bot, 'channelAdd');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^channel remove', $bot, 'channelRemove');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^channel list', $bot, 'channelList');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^join', $bot, 'joinChannel');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^leave', $bot, 'leaveChannel');
