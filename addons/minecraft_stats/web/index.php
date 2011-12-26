<?php
include_once('classes.php');


function cmpUserNick($a, $b)
{
	if(  strtolower($a->getUserNick()) ==  strtolower($b->getUserNick()) ){
		return 0 ;
	}
	return (strtolower($a->getUserNick()) < strtolower($b->getUserNick())) ? -1 : 1;
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
		'last_disconnected' => 'desc',
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
