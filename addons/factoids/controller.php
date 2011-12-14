<?php

require_once('MDB2.php');

class addonFactoid extends botController
{
	private $mdb2_dsn;
	private $mdb2_options;
	private $mdb2;
	
	public function setMdb2Dsn($mdb2_dsn) { $this->mdb2_dsn = $mdb2_dsn; }
	public function getMdb2Dsn() { return $this->mdb2_dsn; }
	
	
	public function setMdb2Options($mdb2_options) { $this->mdb2_options = $mdb2_options; }
	public function getMdb2Options() { return $this->mdb2_options; }
	
	
	function __construct()
	{
		include_once 'factoids_config.php';
		
		$this->mdb2 =& MDB2::connect($this->mdb2_dsn, $this->mdb2_options);
		if (PEAR::isError($this->mdb2)) {
			die('Factoids Addon error: ' . $this->mdb2->getMessage() . "\n");
		}
	}
	
	
	function __destruct()
	{
		
	}
	
	
	function learn(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (count($data->messageex) < 4 || !strpos($data->message, '='))
		{
			$irc->message($data->type, $data->nick, "Missing arguments. Correct format is: !learn <trigger> = <fact>");
			return true;
		}
		
		
		$message = preg_replace("/^!learn /", '', $data->message);
		$firstEqualPos = strpos($message, "=");
		$trigger = trim(substr($message, 0, $firstEqualPos));
		$fact = trim(substr($message, $firstEqualPos + 1));
		
		$replaceSql = 'INSERT INTO factoids SET `trigger` = "' . $this->mdb2->escape($trigger) . '", `content` = "' . $this->mdb2->escape($fact) . '", `created_by` = "' . $this->mdb2->escape($data->nick) . '"';
		$queryResult = $this->mdb2->query($replaceSql);
		
		if ($data->channel != '')
			$sendTo = $data->channel;
		else
			$sendTo = $data->nick;
		
		$irc->message($data->type, $sendTo, "I now know that " . $trigger . " = " . $fact);
		$irc->message($data->type, $sendTo, "This information can be retrieved with: !info " . $trigger);
		$irc->message($data->type, $sendTo, "If you need to remove it, use: !forget " . $trigger);
	}
	
	
	function forget(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		$message = preg_replace("/^!forget /", '', $data->message);
		$trigger = trim($message);
		
		$deleteSql = 'UPDATE factoids SET `is_active` = 0 WHERE `trigger` = "' . $this->mdb2->escape($trigger) . '"';
		$queryResult = $this->mdb2->query($deleteSql);
		
		if ($data->channel != '')
			$sendTo = $data->channel;
		else
			$sendTo = $data->nick;
		
		
	}
	
	
	function info(&$irc, &$data)
	{
		global $tigBase;
		
		$message = preg_replace("/^!info /", '', $data->message);
		$trigger = trim($message);
		
		$selectSql = 'SELECT * FROM factoids WHERE `is_active` = 1 AND `trigger` = "' . $this->mdb2->escape($trigger) . '" ORDER BY `created_at` DESC LIMIT 1';
		$queryResult = $this->mdb2->query($selectSql);
		
		if ($data->channel != '')
			$sendTo = $data->channel;
		else
			$sendTo = $data->nick;
		
		while ($queryResult && $factRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			$irc->message($data->type, $sendTo, "Info about " . $trigger . ": " . $factRow['content']);
		}
	}
	
	
	function random(&$irc, &$data)
	{
		global $tigBase;
		
		$triggerSelectSql = 'SELECT * FROM factoids WHERE is_active = 1 GROUP BY `trigger` ORDER BY RAND() LIMIT 0,1;';
		$queryResult = $this->mdb2->query($triggerSelectSql);
		
		if ($data->channel != '')
			$sendTo = $data->channel;
		else
			$sendTo = $data->nick;
		
		while ($queryResult && $factRow = $queryResult->fetchRow(MDB2_FETCHMODE_ASSOC))
		{
			$irc->message($data->type, $sendTo, "Info about " . $factRow['trigger'] . ": " . $factRow['content']);
		}
	}
}

$addonFactoid = new addonFactoid();

$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE|SMARTIRC_TYPE_CHANNEL,'^!learn', $addonFactoid, 'learn');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE|SMARTIRC_TYPE_CHANNEL,'^!forget', $addonFactoid, 'forget');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE|SMARTIRC_TYPE_CHANNEL,'^!info', $addonFactoid, 'info');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE|SMARTIRC_TYPE_CHANNEL,'^!random fact', $addonFactoid, 'random');

