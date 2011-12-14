<?php

require_once('MDB2.php');

class addonMinecraftStats extends botController
{
	private $mdb2_dsn;
	private $mdb2_options;
	private $mdb2;
	private $table_prefix;
	
	private $friends;
	
	public function setMdb2Dsn($mdb2_dsn) {
		$this->mdb2_dsn = $mdb2_dsn;
	}
	public function getMdb2Dsn() {
		return $this->mdb2_dsn;
	}
	
	
	public function setMdb2Options($mdb2_options) { $this->mdb2_options = $mdb2_options; }
	public function getMdb2Options() { return $this->mdb2_options; }
	
	
	public function setTablePrefix($table_prefix) { $this->table_prefix = $table_prefix; }
	public function getTablePrefix() { return $this->table_prefix; }
	
	
	public function setFriends($friends) { $this->friends = $friends; }
	public function getFriends() { return $this->friends; }
	
	
	function __construct()
	{
		include_once 'mcstats_config.php';
		
		$this->mdb2 =& MDB2::connect($this->mdb2_dsn, $this->mdb2_options);
		if (PEAR::isError($this->mdb2)) {
			die('Factoids Addon error: ' . $this->mdb2->getMessage() . "\n");
		}
	}
	
	
	function __destruct()
	{
		
	}
	
	
	public function manageLine(&$irc, &$data)
	{
		if (in_array($data->nick, $this->friends))
		{
			// check that the line wasn't user chatter
			if (!preg_match("/^</", $this->messageex[0]))
			{
				if (count($data->messageex) == 3 && preg_match("/has connected$/", $data->message))
				{
					// new user connection
					$botNick = $data->nick;
					$mcUser = $data->messageex[0];
					
					$this->recordConnection($botNick, $mcUser);
				}
				
				if (count($data->messageex) > 3 && preg_match("/has disconnected:/", $data->message))
				{
					// user disconnection
					$botNick = $data->nick;
					$mcUser = $data->messageex[0];
					
					$userString = $data->messageex[0] . ' ' . $data->messageex[1] . ' ' . $data->messageex[2] . ' ';
					$reason = preg_replace("/^" . $userString . "/", "", $data->message);
					$this->recordDisconnection($botNick, $mcUser, $reason);
				}
			}
		}
	}
	
	
	private function recordConnection($botNick, $mcUser)
	{
		$addConnectionSql = 'INSERT INTO ' . $this->getTablePrefix() . 'minecraft_stats (bot_name, user_nick, action) VALUES ("' . $this->mdb2->escape($botNick) . '", "' . $this->mdb2->escape($mcUser) . '", "connection");';
		$queryResult = $this->mdb2->query($addConnectionSql);
		
		if (DEBUG)
			echo "[" . date("Y-M-D H:i:s") . "] Minecraft Addon: " . $mcUser . " connected to " . $botNick . "\n";
	}
	
	
	private function recordDisconnection($botNick, $mcUser, $reason)
	{
		$addDisconnectionSql = 'INSERT INTO ' . $this->getTablePrefix() . 'minecraft_stats (bot_name, user_nick, action, notes) VALUES ("' . $this->mdb2->escape($botNick) . '", "' . $this->mdb2->escape($mcUser) . '", "disconnection", "' . $this->mdb2->escape($reason) . '");';
		$queryResult = $this->mdb2->query($addDisconnectionSql);
		
		if (DEBUG)
			echo "[" . date("Y-M-D H:i:s") . "] Minecraft Addon: " . $mcUser . " disconnected from " . $botNick . "\n";
	}
	
	
	public function displayStats(&$irc, &$data)
	{
		if (count($data->messageex) == 2)
		{
			// display stats for a user
			$displayResults = array();
			
			if ($data->channel != '')
				$sendTo = $data->channel;
			else
				$sendTo = $data->nick;
			
			$connectionStatsSql = 'SELECT COUNT(*) AS connections FROM ' . $this->getTablePrefix() . 'minecraft_stats WHERE user_nick = "' . $this->mdb2->escape($data->messageex[1]) . '" AND `action` = "connection";';
			$queryResult = $this->mdb2->query($connectionStatsSql);
			
			if (PEAR::isError($queryResult)) {
				$irc->message($data->type, $sendTo, "There was an error while fetching connection data on " . $$data->messageex[1]);
				return true;
			}
			
			while ($queryResult && $statsRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
			{
				$displayResults[] .= 'Connections: ' . $statsRow['connections'];
			}
			
			
			$disconnectionStatsSql = 'SELECT COUNT(*) AS disconnections FROM ' . $this->getTablePrefix() . 'minecraft_stats WHERE user_nick = "' . $this->mdb2->escape($data->messageex[1]) . '" AND `action` = "disconnection";';
			$queryResult = $this->mdb2->query($disconnectionStatsSql);
			
			if (PEAR::isError($queryResult)) {
				$irc->message($data->type, $sendTo, "There was an error while fetching disconnection data on " . $$data->messageex[1]);
				return true;
			}
			
			while ($queryResult && $statsRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
			{
				$displayResults[] .= 'Disconnections: ' . $statsRow['disconnections'];
			}
			
			
			if (count($displayResults) > 0)
			{
				$irc->message($data->type, $sendTo, "Minecraft stats for " . $data->messageex[1] . " -- " . implode(', ', $displayResults));
			}
		}
	}
}

$addonMinecraftStats = new addonMinecraftStats();

$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'has connected$', $addonMinecraftStats, 'manageLine');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'has disconnected:', $addonMinecraftStats, 'manageLine');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^!mcstats', $addonMinecraftStats, 'displayStats');