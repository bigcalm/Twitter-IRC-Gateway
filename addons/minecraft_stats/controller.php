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
		
		$this->reconnect();
	}
	
	
	function __destruct()
	{
	}
	
	
	function reconnect()
	{
		$this->mdb2 =& MDB2::connect($this->mdb2_dsn, $this->mdb2_options);
		if (PEAR::isError($this->mdb2)) {
			echo 'Minecraft Stats Addon error: ' . $this->mdb2->getMessage() . "\n";
		}
	}
	
	function query($query)
	{
		$queryResult = $this->mdb2->query($query);
		if (PEAR::isError($queryResult)) {

			try {
				if (!$this->reconnect())
				{
					throw new Exception('DB connection issue');
//						echo $this->mdb2->getMessage();
					return false;
				}
			} catch (Exception $e) {
				die ('Oops 1: ' . $e->getMessage());
			}

			try {
				$queryResult = $this->mdb2->query($query);
				if (PEAR::isError($queryResult)) {
					throw new Exception('DB connection issue');
					return false;
				}
			} catch (Exception $e) {
				die ('Oops 2: ' . $e->getMessage());
			}
		}
		
		return $queryResult;
	}
	
	public function manageConnection($irc, $data)
	{
		global $tigBase;
		
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
		global $tigBase;
		
		$addConnectionSql = 'INSERT INTO ' . $this->getTablePrefix() . 'minecraft_stats (bot_name, user_nick, action) VALUES ("' . $this->mdb2->escape($botNick) . '", "' . $this->mdb2->escape($mcUser) . '", "connection");';
		$queryResult = $this->query($addConnectionSql);
		
		if (DEBUG)
			echo "[" . date("Y-M-D H:i:s") . "] Minecraft Addon: " . $mcUser . " connected to " . $botNick . "\n";
	}
	
	
	private function recordDisconnection($botNick, $mcUser, $reason)
	{
		global $tigBase;
		
		$addDisconnectionSql = 'INSERT INTO ' . $this->getTablePrefix() . 'minecraft_stats (bot_name, user_nick, action, notes) VALUES ("' . $this->mdb2->escape($botNick) . '", "' . $this->mdb2->escape($mcUser) . '", "disconnection", "' . $this->mdb2->escape($reason) . '");';
		$queryResult = $this->query($addDisconnectionSql);
		
		if (DEBUG)
			echo "[" . date("Y-M-D H:i:s") . "] Minecraft Addon: " . $mcUser . " disconnected from " . $botNick . "\n";
	}
	
	
	public function displayStats($irc, $data)
	{
		global $tigBase;
		
		if ($data->channel != '')
			$sendTo = $data->channel;
		else
			$sendTo = $data->nick;
		
		if (count($data->messageex) == 2)
		{
			// single user stats required
			$mcUser = $data->messageex[1];
			$displayResults = $this->getSingleUserStats($mcUser);
			$irc->message($data->type, $sendTo, "Minecraft stats for " . $mcUser . " -- " . implode(', ', $displayResults));
		}
		else
		{
			return true;
			// all user stats required
			$mcUsernamesSql = 'SELECT user_nick FROM ' . $this->getTablePrefix() . 'minecraft_stats GROUP BY user_nick ORDER BY user_nick ASC;';
			$queryResult = $this->query($mcUsernamesSql);
			$mcUsers = array();
			
			if (PEAR::isError($queryResult)) {
				$irc->message($data->type, $sendTo, "There was an error while fetching connection data for all users");
				return true;
			}
			
			while ($queryResult && $userRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
			{
				$mcUsers[] = $userRow['user_nick'];
			}
			
			foreach ($mcUsers as $mcUser)
			{
				$displayResults = $this->getSingleUserStats($mcUser);
				$irc->message($data->type, $sendTo, "Minecraft stats for " . $mcUser . " -- " . implode(', ', $displayResults));
			}
		}
	}
	
	
	private function getSingleUserStats($mcUser)
	{
		global $tigBase;
		
		// display stats for a user
		$displayResults = array();
		
	// -- calculate number of connections
		$connectionStatsSql = 'SELECT COUNT(*) AS connections FROM ' . $this->getTablePrefix() . 'minecraft_stats WHERE user_nick = "' . $this->mdb2->escape($mcUser) . '" AND `action` = "connection";';
		$queryResult = $this->query($connectionStatsSql);
		
		if (!$queryResult) {
			return array("There was an error while fetching connection data on " . $mcUser);
		}
		
		while ($queryResult && $statsRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			$displayResults[] = 'Connections: ' . $statsRow['connections'];
		}
		
		
		
	// -- calculate number of disconnections
		/*
		$disconnectionStatsSql = 'SELECT COUNT(*) AS disconnections FROM ' . $this->getTablePrefix() . 'minecraft_stats WHERE user_nick = "' . $this->mdb2->escape($mcUser) . '" AND `action` = "disconnection";';
		$queryResult = $this->query($disconnectionStatsSql);
		
		if (PEAR::isError($queryResult)) {
			$irc->message($data->type, $sendTo, "There was an error while fetching disconnection data on " . $mcUser);
			return true;
		}
		
		while ($queryResult && $statsRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			$displayResults[] = 'Disconnections: ' . $statsRow['disconnections'];
		}
		*/
		
		
		
	// -- calculate total deaths
		$deathStatsSql = 'SELECT COUNT(*) AS deaths FROM ' . $this->getTablePrefix() . 'minecraft_stats WHERE user_nick = "' . $this->mdb2->escape($mcUser) . '" AND `action` = "death";';
		$queryResult = $this->query($deathStatsSql);
		
		if (PEAR::isError($queryResult)) {
			$irc->message($data->type, $sendTo, "There was an error while fetching death data on " . $mcUser);
			return true;
		}
		
		while ($queryResult && $statsRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			$displayResults[] = 'Deaths: ' . $statsRow['deaths'];
		}
		
		
		
	// -- calculate total time connected
		$connectionTimesSql = 'SELECT * FROM ' . $this->getTablePrefix() . 'minecraft_stats WHERE user_nick = "' . $this->mdb2->escape($mcUser) . '" ORDER BY created_at ASC;';
		$queryResult = $this->query($connectionTimesSql);
		
		if (PEAR::isError($queryResult)) {
			$irc->message($data->type, $sendTo, "There was an error while fetching connection data on " . $mcUser);
			return true;
		}
		
		$connectionTimePairs = array();
		$counter = 0;
		while ($queryResult && $connectionTimeRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			if (!isset($connectionTimePairs[$counter]))
				$connectionTimePairs[$counter] = array();
			
			if ($connectionTimeRow['action'] == 'connection')
			{
				$connectionTimePairs[$counter]['connection'] = $connectionTimeRow['created_at'];
			}
			if ($connectionTimeRow['action'] == 'disconnection')
			{
				// skip disconnection without a connection
				if (!isset($connectionTimePairs[$counter]['connection']))
				{
					unset($connectionTimePairs[$counter]);
					continue;
				}
				
				$connectionTimePairs[$counter]['disconnection'] = $connectionTimeRow['created_at'];
			}
			
			if (count($connectionTimePairs[$counter]) == 2)
			{
				$counter++;
			}
		}
		
		if (count($connectionTimePairs) > 0)
		{
			if (!isset($connectionTimePairs[count($connectionTimePairs) - 1]['disconnection']))
			$connectionTimePairs[count($connectionTimePairs) - 1]['disconnection'] = date('Y-m-d H:i:s');
		}
		
		$totalTimeConnected = 0;
		foreach ($connectionTimePairs as $connectionTimePair)
		{
			$connectTime = strtotime($connectionTimePair['connection']);
			$disconnectTime = strtotime($connectionTimePair['disconnection']);
			$totalTimeConnected += ($disconnectTime - $connectTime);
		}
		$displayResults[] = 'Total time connected: ' .  $tigBase->sec2hms($totalTimeConnected, false);
		
		return $displayResults;
	}
	
	
	public function death($irc, $data)
	{
		$botNick = $data->nick;
		$mcUser = $data->messageex[0];
		$meansOfDeath = trim(preg_replace("/^" . $mcUser . "/", "", $data->message));
		$this->recordDeath($botNick, $mcUser, $meansOfDeath);
	}
	
	
	public function deathMob($irc, $data)
	{
		$botNick = $data->nick;
		$mcUser = $data->messageex[0];
		$secondEntity = $data->messageex[count($data->messageex) - 1];
		$meansOfDeath = trim(preg_replace("/^" . $mcUser . "/", "", $data->message));
		$meansOfDeath = trim(preg_replace("/" . $secondEntity . "$/", "", $data->message));
		$this->recordDeath($botNick, $mcUser, $meansOfDeath, $secondEntity);
	}
	
	
	private function recordDeath($botNick, $mcUser, $meansOfDeath, $secondEntity = false)
	{
		$deathSql = 'INSERT INTO ' . $this->getTablePrefix() . 'minecraft_stats SET bot_name = "' . $this->mdb2->escape($botNick). '", user_nick = "' . $this->mdb2->escape($mcUser). '", `action` = "death", notes = "' . $this->mdb2->escape($meansOfDeath) . '"';
		
		if ($secondEntity !== false)
			$deathSql .= ', second_entity = "' . $this->mdb2->escape($secondEntity) . '"';
		
		$deathSql .= ';';
		
		$deathRes = $this->query($deathSql);
		if (DEBUG)
			echo "[" . date("Y-M-D H:i:s") . "] Minecraft Addon: Death: " . $mcUser . " " . $meansOfDeath . " " . $secondEntity . "\n";
		
	}
}

$addonMinecraftStats = new addonMinecraftStats();

$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'has connected$', $addonMinecraftStats, 'manageConnection');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'has disconnected:', $addonMinecraftStats, 'manageConnection');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^!mcstats', $addonMinecraftStats, 'displayStats');
// Death
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ went up in flames$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ burned to death$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ tried to swim in lava$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ suffocated in a wall$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ drowned$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ starved to death$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was pricked to death$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ hit the ground too hard$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ fell out of the world$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ died$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ blew up$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was killed by magic$', $addonMinecraftStats, 'death');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was slain by \w+$', $addonMinecraftStats, 'deathMob');
// $irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was slain by \w+$', $addonMinecraftStats, 'deathMob');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was shot by \w+$', $addonMinecraftStats, 'deathMob');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was fireballed by \w+$', $addonMinecraftStats, 'deathMob');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was pummeled by \w+$', $addonMinecraftStats, 'deathMob');
$irc->registerActionhandler(SMARTIRC_TYPE_CHANNEL,'^\w+ was killed by \w+$', $addonMinecraftStats, 'deathMob');






