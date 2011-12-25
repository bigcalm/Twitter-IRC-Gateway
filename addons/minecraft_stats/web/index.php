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
	
	
	public function setMdb2Options($mdb2_options) { $this->mdb2_options = $mdb2_options; }
	public function getMdb2Options() { return $this->mdb2_options; }
	
	
	public function setTablePrefix($table_prefix) { $this->table_prefix = $table_prefix; }
	public function getTablePrefix() { return $this->table_prefix; }
	
	
	public function setFriends($friends) { $this->friends = $friends; }
	public function getFriends() { return $this->friends; }
	
	
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
	
	public function getUserNick() { return $this->user_nick; }
	public function setUserNick($user_nick) { $this->user_nick = $user_nick; }
	
	public function getAction() { return $this->action; }
	public function setAction($action) { $this->action = $action; }
	
	public function getNotes() { return $this->notes; }
	public function setNotes($notes) { $this->notes = $notes; }
	
	public function getSecondEntity() { return $this->second_entity; }
	public function setSecondEntity($second_entity) { $this->second_entity = $second_entity; }
	
	public function getCreatedAt() { return $this->created_at; }
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
	
	public function getBotName() { return $this->bot_name; }
	public function setBotName($bot_name) { $this->bot_name = $bot_name; }
	
	public function getUserNick() { return $this->user_nick; }
	public function setUserNick($user_nick) { $this->user_nick = $user_nick; }
	
	public function getConnections() { return $this->connections; }
	public function setConnections($connections) { $this->connections = $connections; }
	
	public function getDisconnections() { return $this->disconnections; }
	public function setDisconnections($disconnections) { $this->disconnections = $disconnections; }
	
	public function getDeaths() { return $this->deaths; }
	public function setDeaths($deaths) { $this->deaths = $deaths; }
	
	public function getFirstConnected() { return $this->first_connected; }
	public function setFirstConnected($first_connected) { $this->first_connected = $first_connected; }
	
	public function getLastConnected() { return $this->last_connected; }
	public function setLastConnected($last_connected) { $this->last_connected = $last_connected; }
	
	public function getLastDisconnected() { return $this->last_disconnected; }
	public function setLastDisconnected($last_disconnected) { $this->last_disconnected = $last_disconnected; }
	
	public function getTimeConnected() { return $this->time_connected; }
	public function setTimeConnected($time_connected) { $this->time_connected = $time_connected; }
	
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
}

function cmpUserNick($a, $b)
{
	if(  $a->getUserNick() ==  $b->getUserNick() ){
		return 0 ;
	}
	return ($a->getUserNick() < $b->getUserNick()) ? -1 : 1;
}

function cmpConnections($a, $b)
{
	if(  $a->getConnections() ==  $b->getConnections() ){
		return 0 ;
	}
	return ($a->getConnections() < $b->getConnections()) ? -1 : 1;
}

function cmpFirstConnected($a, $b)
{
	if(  $a->getFirstConnected() ==  $b->getFirstConnected() ){
		return 0 ;
	}
	return ($a->getFirstConnected() < $b->getFirstConnected()) ? -1 : 1;
}

function cmpLastConnected($a, $b)
{
	if(  $a->getLastConnected() ==  $b->getLastConnected() ){
		return 0 ;
	}
	return ($a->getLastConnected() < $b->getLastConnected()) ? -1 : 1;
}

function cmpLastDisconnected($a, $b)
{
	if(  $a->getLastDisconnected() ==  $b->getLastDisconnected() ){
		return 0 ;
	}
	return ($a->getLastDisconnected() < $b->getLastDisconnected()) ? -1 : 1;
}

function cmpTimeConnected($a, $b)
{
	if(  $a->getTimeConnected() ==  $b->getTimeConnected() ){
		return 0 ;
	}
	return ($a->getTimeConnected() < $b->getTimeConnected()) ? -1 : 1;
}

function cmpDeaths($a, $b)
{
	if(  $a->getDeaths() ==  $b->getDeaths() ){
		return 0 ;
	}
	return ($a->getDeaths() < $b->getDeaths()) ? -1 : 1;
}

?>
<?php
	$minecraftUsers = Minecraft::getMinecraftUsers();
	
	$options = array();
	$direction = array(
		'username' => 'asc',
		'connections' => 'desc',
		'first_connected' => 'desc',
		'last_connected' => 'desc',
		'last_discconnected' => 'desc',
		'time_connected' => 'desc',
		'deaths' => 'desc'
	);
	
	if (!isset($_GET['order_by']))
		$_GET['order_by'] = 'username';
	
	if (!in_array($_GET['order_by'], array_keys($direction)))
		$_GET['order_by'] = 'username';
	
	if (!isset($_GET['direction']))
		$_GET['direction'] = 'asc';
	
	if (!in_array($_GET['direction'], array('asc', 'desc')))
		$_GET['direction'] = 'asc';
	
	switch ($_GET['order_by'])
	{
		case "username":
			$options['order_by'] = 'user_nick';
			$direction['username'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpUserNick');
			
			if ($direction['username'] == 'desc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
		
		case "connections":
			$options['order_by'] = 'connections';
			$direction['connections'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpConnections');
			
			if ($direction['connections'] == 'desc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
		
		case "first_connected":
			$options['order_by'] = 'first_connected';
			$direction['first_connected'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpFirstConnected');
			
			if ($direction['first_connected'] == 'desc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
		
		case "last_connected":
			$options['order_by'] = 'last_connected';
			$direction['last_connected'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpLastConnected');
			
			if ($direction['last_connected'] == 'desc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
				
		case "last_disconnected":
			$options['order_by'] = 'last_disconnected';
			$direction['last_disconnected'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpLastDisconnected');
			
			if ($direction['last_disconnected'] == 'desc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
			
		case "time_connected":
			$options['order_by'] = 'time_connected';
			$direction['time_connected'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpTimeConnected');
			
			if ($direction['time_connected'] == 'desc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
			
		case "deaths":
			$options['order_by'] = 'deaths';
			$direction['deaths'] = ($_GET['direction'] == 'asc') ? 'desc' : 'asc';
			usort($minecraftUsers, 'cmpDeaths');
			
			if ($direction['deaths'] == 'asc')
				$minecraftUsers = array_reverse($minecraftUsers);
			
			break;
	}
	
	$options['order_by'] .= ' ' . strtoupper($_GET['direction']);
	
	
?>
<html>
	<head>
		<title>Minecraft Stats for ##bitfolk-minecraft on freenode</title>
	</head>
	<style type="text/css">
		body { font-family: Ubuntu, sans-serif; }
		td { border: solid 1px #ccc; padding: 1em; margin: 1em; }
		th { border: solid 2px #aaa; padding: 1em; margin: 1em; }
	</style>
	<body>
		<h3>Over all stats by user</h3>
		<table>
			<thead>
				<tr>
					<th><a href="?order_by=username&amp;direction=<?php echo $direction['username']; ?>">Username</a></th>
					<th><a href="?order_by=connections&amp;direction=<?php echo $direction['connections']; ?>">Connections</a></th>
					<th><a href="?order_by=first_connected&amp;direction=<?php echo $direction['first_connected']; ?>">First connected</a></th>
					<th><a href="?order_by=last_connected&amp;direction=<?php echo $direction['last_connected']; ?>">Last connected</a></th>
					<th><a href="?order_by=last_disconnected&amp;direction=<?php echo $direction['last_disconnected']; ?>">Last disconnected</a></th>
					<th><a href="?order_by=time_connected&amp;direction=<?php echo $direction['time_connected']; ?>">Time connected</a></th>
					<th><a href="?order_by=deaths&amp;direction=<?php echo $direction['deaths']; ?>">Deaths</a></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($minecraftUsers as $minecraftUser): ?>
				<tr>
					<td><?php echo $minecraftUser->getUserNick(); ?></td>
					<td><?php echo $minecraftUser->getConnections(); ?></td>
					<td><?php echo date("jS M Y, H:i:s", $minecraftUser->getFirstConnected()); ?></td>
					<td><?php echo date("jS M Y, H:i:s", $minecraftUser->getLastConnected()); ?></td>
					<td><?php echo date("jS M Y, H:i:s", $minecraftUser->getLastDisconnected()); ?></td>
					<td><?php //echo $minecraftUser->; ?></td>
					<td><?php echo $minecraftUser->getDeaths(); if ($minecraftUser->getDeaths() > 0): ?> [details]<?php endif; ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</body>
</html>
