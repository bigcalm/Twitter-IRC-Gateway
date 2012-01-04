<?php
class Minecraft {
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


	public function setMdb2Options($mdb2_options) {
		$this->mdb2_options = $mdb2_options;
	}
	public function getMdb2Options() {
		return $this->mdb2_options;
	}


	public function setTablePrefix($table_prefix) {
		$this->table_prefix = $table_prefix;
	}
	public function getTablePrefix() {
		return $this->table_prefix;
	}


	public function setFriends($friends) {
		$this->friends = $friends;
	}
	public function getFriends() {
		return $this->friends;
	}


	function __construct($minecraftObject = null)
	{
		require_once('MDB2.php');

		if (!is_object($minecraftObject))
		{
			include_once '../mcstats_config.php';
				
			$this->mdb2 =& MDB2::connect($this->mdb2_dsn, $this->mdb2_options);
			if (PEAR::isError($this->mdb2)) {
				die('Miecraft Stats db error: ' . $this->mdb2->getMessage() . "\n");
			}

		}
		else
		{
			$this->mdb2 = $minecraftObject;
		}
	}

	public static function getMinecraftUsers($options = array(), $minecraftObject = null)
	{
		if (!is_object($minecraftObject))
		{
			$minecraftObject = new Minecraft();
		}
		$userSql = 'SELECT * FROM ' . $minecraftObject->getTablePrefix() . 'minecraft_stats WHERE 1 = 1';

		if (isset($options['bot_name']))
		{
			if (!is_array($options['bot_name']))
			$options['bot_name'] = array($options['bot_name']);
				
			$userSql .= ' AND bot_name IN ("' . implode('", "', $minecraftObject->mdb2->escape($options['bot_name'])) . '")';
		}

		if (isset($options['action']))
		{
			if (!is_array($options['action']))
			$options['action'] = array($options['action']);

			$userSql .= ' AND action IN ("' . implode('", "', $minecraftObject->mdb2->escape($options['action'])) . '")';
		}

		if (isset($options['notes']))
		{
			if (!is_array($options['notes']))
			$options['notes'] = array($options['notes']);

			$userSql .= ' AND notes IN ("' . implode('", "', $minecraftObject->mdb2->escape($options['notes'])) . '")';
		}

		if (isset($options['second_entity']))
		{
			if (!is_array($options['second_entity']))
			$options['second_entity'] = array($options['second_entity']);

			$userSql .= ' AND second_entity IN ("' . implode('", "', $minecraftObject->mdb2->escape($options['second_entity'])) . '")';
		}

		if (isset($options['created_at']))
		{
			// something
		}

		if (isset($options['order_by']) && preg_match("/^user_nick/", $options['order_by']))
		$userSql .= ' ORDER BY ' . $options['order_by'];
		else
		$userSql .= ' ORDER BY user_nick ASC';


		// $userSql .= ' GROUP BY user_nick';
		// $userSql .= ' GROUP BY bot_name';

		$queryResult = $minecraftObject->mdb2->query($userSql);

		if (PEAR::isError($queryResult)) {
			die('sql error');
		}

		$results = array();
		while ($queryResult && $row = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			$rows[] = $row;
		}

		$minecraftUsers = array();
		foreach ($rows as $row)
		{
			if (!isset($minecraftUsers[$row['user_nick']]['user_nick']))
			$minecraftUsers[$row['user_nick']]['user_nick'] = $row['user_nick'];
				
			if (!isset($minecraftUsers[$row['user_nick']]['bot_name']))
			$minecraftUsers[$row['user_nick']]['bot_name'] = $row['bot_name'];
				
			if (!isset($minecraftUsers[$row['user_nick']]))
			$minecraftUsers[$row['user_nick']] = array();
				
			if (!isset($minecraftUsers[$row['user_nick']]['connections']))
			$minecraftUsers[$row['user_nick']]['connections'] = 0;
				
			if (!isset($minecraftUsers[$row['user_nick']]['disconnections']))
			$minecraftUsers[$row['user_nick']]['disconnections'] = 0;
				
			if (!isset($minecraftUsers[$row['user_nick']]['deaths']))
			$minecraftUsers[$row['user_nick']]['deaths'] = 0;
				
			if ($row['action'] == 'connection')
			{
				$minecraftUsers[$row['user_nick']]['connections']++;

				if (!isset($minecraftUsers[$row['user_nick']]['first_connected']))
				$minecraftUsers[$row['user_nick']]['first_connected'] = strtotime($row['created_at']);
				if (strtotime($row['created_at']) < $minecraftUsers[$row['user_nick']]['first_connected'])
				$minecraftUsers[$row['user_nick']]['first_connected'] = strtotime($row['created_at']);


				if (!isset($minecraftUsers[$row['user_nick']]['last_connected']))
				$minecraftUsers[$row['user_nick']]['last_connected'] = strtotime($row['created_at']);
					
				if (strtotime($row['created_at']) > $minecraftUsers[$row['user_nick']]['last_connected'])
				$minecraftUsers[$row['user_nick']]['last_connected'] = strtotime($row['created_at']);
			}
				
			if ($row['action'] == 'disconnection')
			{
				$minecraftUsers[$row['user_nick']]['disconnections']++;

				if (!isset($minecraftUsers[$row['user_nick']]['last_disconnected']))
				$minecraftUsers[$row['user_nick']]['last_disconnected'] = strtotime($row['created_at']);
					
				if (strtotime($row['created_at']) > $minecraftUsers[$row['user_nick']]['last_disconnected'])
				$minecraftUsers[$row['user_nick']]['last_disconnected'] = strtotime($row['created_at']);
			}
				
			if ($row['action'] == 'death')
			$minecraftUsers[$row['user_nick']]['deaths']++;
				
				
			$results[$row['user_nick']] =  $minecraftUsers[$row['user_nick']];
		}

		$resultSet = array();
		foreach ($minecraftUsers as $userNick => $result)
		{
			$minecraftUser = new MinecraftUser($minecraftObject);
			$minecraftUser->setUserNick($result['user_nick']);
			$minecraftUser->setBotName($result['bot_name']);
			$minecraftUser->setConnections($result['connections']);
			$minecraftUser->setDisconnections($result['disconnections']);
			$minecraftUser->setFirstConnected($result['first_connected']);
			$minecraftUser->setLastConnected($result['last_connected']);
			$minecraftUser->setLastDisconnected($result['last_disconnected']);
			$minecraftUser->setDeaths($result['deaths']);
				
			$resultSet[] = $minecraftUser;
		}

		return $resultSet;
	}


}

class MinecraftStat extends Minecraft
{
	private $bot_name;
	private $user_nick;
	private $action;
	private $notes;
	private $second_entity;
	private $created_at;

	public function getUserNick() {
		return $this->user_nick;
	}
	public function setUserNick($user_nick) {
		$this->user_nick = $user_nick;
	}

	public function getAction() {
		return $this->action;
	}
	public function setAction($action) {
		$this->action = $action;
	}

	public function getNotes() {
		return $this->notes;
	}
	public function setNotes($notes) {
		$this->notes = $notes;
	}

	public function getSecondEntity() {
		return $this->second_entity;
	}
	public function setSecondEntity($second_entity) {
		$this->second_entity = $second_entity;
	}

	public function getCreatedAt() {
		return $this->created_at;
	}
}

class MinecraftUser extends Minecraft
{
	private $bot_name;
	private $user_nick;
	private $connections;
	private $disconnections;
	private $deaths;
	private $first_connected;
	private $last_connected;
	private $last_disconnected;
	private $time_connected;

	public function getBotName() {
		return $this->bot_name;
	}
	public function setBotName($bot_name) {
		$this->bot_name = $bot_name;
	}

	public function getUserNick() {
		return $this->user_nick;
	}
	public function setUserNick($user_nick) {
		$this->user_nick = $user_nick;
	}

	public function getConnections() {
		return $this->connections;
	}
	public function setConnections($connections) {
		$this->connections = $connections;
	}

	public function getDisconnections() {
		return $this->disconnections;
	}
	public function setDisconnections($disconnections) {
		$this->disconnections = $disconnections;
	}

	public function getDeaths() {
		return $this->deaths;
	}
	public function setDeaths($deaths) {
		$this->deaths = $deaths;
	}

	public function getFirstConnected() {
		return $this->first_connected;
	}
	public function setFirstConnected($first_connected) {
		$this->first_connected = $first_connected;
	}

	public function getLastConnected() {
		return $this->last_connected;
	}
	public function setLastConnected($last_connected) {
		$this->last_connected = $last_connected;
	}

	public function getLastDisconnected() {
		return $this->last_disconnected;
	}
	public function setLastDisconnected($last_disconnected) {
		$this->last_disconnected = $last_disconnected;
	}

	public function getTimeConnected() {
		return $this->time_connected;
	}
	public function setTimeConnected($time_connected) {
		$this->time_connected = $time_connected;
	}

	function __construct($minecraftObject = null)
	{
		parent::__construct($minecraftObject);

	}
}

class Tools
{
	static function debug($var)
	{
		echo '<pre>';
		print_r($var);
		echo '</pre>';
	}
	
/**
 * 
 * Format a length of time in a more human readable form
 * Copied from http://www.php.net/manual/en/ref.datetime.php#90989
 * @param int $date1
 * @param int $date2 (optional)
 */
	static function compare_dates($date1, $date2 = null) 
    {
    	$date2 = (isset($date2) ? $date2 : time());
    	
    $blocks = array( 
        array('name'=>'year','amount'    =>    60*60*24*365    ), 
        array('name'=>'month','amount'    =>    60*60*24*31    ), 
        array('name'=>'week','amount'    =>    60*60*24*7    ), 
        array('name'=>'day','amount'    =>    60*60*24    ), 
        array('name'=>'hour','amount'    =>    60*60        ), 
        array('name'=>'minute','amount'    =>    60        ), 
        array('name'=>'second','amount'    =>    1        ) 
        ); 
    
    $diff = abs($date1-$date2); 
    
    $levels = 2; 
    $current_level = 1; 
    $result = array(); 
    foreach($blocks as $block) 
        { 
        if ($current_level > $levels) {break;} 
        if ($diff/$block['amount'] >= 1) 
            { 
            $amount = floor($diff/$block['amount']); 
            if ($amount>1) {$plural='s';} else {$plural='';} 
            $result[] = $amount.' '.$block['name'].$plural; 
            $diff -= $amount*$block['amount']; 
            $current_level++; 
            } 
        } 
    return implode(' ',$result).' ago'; 
    } 
}
