<?php
require_once('PHP/Fork.php');

require_once('abraham-twitteroauth-7f0962f/twitteroauth/twitteroauth.php');

class addonTwitter extends botController
{
	protected $channel;
	
	protected $friends;
	
	protected $auth_file;
	
	private $consumer_key;
	private $consumer_secret;
	private $auth_token;
	private $token_secret;
	
	private $displayTweetId;
	
	var $forked_process;
	
	public function setChannel($channel) { $this->channel = $channel; }
	public function getChannel() { return $this->channel; }
	
	
	public function setFriends($friends) { $this->friends = $friends; }
	public function getFriends() { return $this->friends; }
	
	
	public function setAuthFile($twitterAuthFile) { $this->auth_file = $twitterAuthFile; }
	public function getAuthFile() { return $this->auth_file; }
	
	
	private function setConsumerKey($consumer_key) { $this->consumer_key = $consumer_key; }
	private function getConsumerKey() { return $this->consumer_key; }
	
	
	private function setConsumerSecret($consumer_secret) { $this->consumer_secret = $consumer_secret; }
	private function getConsumerSecret() { return $this->consumer_secret; }
	
	
	private function setAuthToken($auth_token) { $this->auth_token = $auth_token; }
	private function getAuthToken() { return $this->auth_token; }
	
	
	private function setTokenSecret($token_secret) { $this->token_secret = $token_secret; }
	private function getTokenSecret() { return $this->token_secret; }
	
	
	private function setForkedProcess($forked_process) { $this->forked_process = $forked_process; }
	private function getForkedProcess() { return $this->forked_process; }
	
	
	function __construct()
	{
		include_once 'twitter_config.php';
		if (file_exists($this->getAuthFile()))
		{
			$oauthFileData = unserialize(file_get_contents($this->getAuthFile()));
			
			$this->setConsumerKey($oauthFileData['consumer_key']);
			$this->setConsumerSecret($oauthFileData['consumer_secret']);
			$this->setAuthToken($oauthFileData['auth_token']);
			$this->setTokenSecret($oauthFileData['token_secret']);
		}
	}
	
	function __destruct()
	{
		// kill any forked processes before the bot is killed
		
		if (is_object($this->getForkedProcess()))
		{
			$twitterThread = $this->getForkedProcess();
			$twitterThread->stop();
		}
	}
	
	// -- bot commands --
	function twitterOauthRegister(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		/*
		// abraham-twitteroauth-7f0962f
		*/
		$connection = new TwitterOAuth($this->getConsumerKey(), $this->getConsumerSecret());
		$request_token = $connection->getRequestToken();
		$token = $request_token['oauth_token'];
		$authUrl = $connection->getAuthorizeURL($token);
		
		$this->setAuthToken($request_token['oauth_token']);
		$this->setTokenSecret($request_token['oauth_token_secret']);
		
		$irc->message($data->type, $data->nick, "Please follow this URL to begin the authentication process: " . $authUrl);
		$irc->message($data->type, $data->nick, "Once you have logged in and authorised the twitter application, please tell me the PIN you were given: twitter validate #######");
	}
	
	
	function twitterOauthValidate(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (!isset($data->messageex[2]))
		{
			$irc->message($data->type, $data->nick, "You need to give me a PIN to be able to complete this registration process. Please follow the details given by: twitter oauth register");
			return true;
		}
		
		$verifierPin = $data->messageex[2];
		
		/*
		// abraham-twitteroauth-7f0962f
		*/
		$tempConnection = new TwitterOAuth($this->getConsumerKey(), $this->getConsumerSecret(), $this->getAuthToken(), $this->getTokenSecret());
		$access_token = $tempConnection->getAccessToken(NULL, $verifierPin);
		
		$this->setAuthToken($access_token['oauth_token']);
		$this->setTokenSecret($access_token['oauth_token_secret']);
		
		$connection = new TwitterOAuth($this->getConsumerKey(), $this->getConsumerSecret(), $this->getAuthToken(), $this->getTokenSecret());
		$content = $connection->get('account/verify_credentials');
		
		if (isset($content->error) && $content->error != '')
		{
			$irc->message($data->type, $data->nick, "Sorry, but I was not able to register with Twitter. Please check that you have correctly entered the PIN provided by Twitter.");
			return true;
		}
		
		$fileData = array(
			'consumer_key' => $this->getConsumerKey(),
			'consumer_secret' => $this->getConsumerSecret(),
			'auth_token' => $this->getAuthToken(),
			'token_secret' => $this->getTokenSecret()
		);
		file_put_contents($this->getAuthFile(), serialize($fileData));
		
		$irc->message($data->type, $data->nick, "Authentication with Twitter was successful. You can now begin streaming tweets with: twitter stream start");
	}
	
	
	function twitterStreamStart(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		
		if ($this->getAuthToken() == '' || $this->getTokenSecret() == '')
		{
			$irc->message($data->type, $data->nick, "I do not yet have the correct Twitter OAuth details to be able to stream tweets.  Please run: twitter oauth register");
			return true;
		}
		
		
		$tigBase->setVar('auth_file', $this->getAuthFile());
		$tigBase->setVar('friends', $this->getFriends());
		
		if (is_object($this->getForkedProcess()))
		{
			$irc->message($data->type, $data->nick, "I am already gating tweets. Use 'twitter stop' to stop.");
			return true;
		}
		
		$this->displayTweetId = $irc->registerTimehandler(2000, $this, 'displayTweet');
		
		$tigBase->setVar('last_message_displayed', TRUE);
		$tigBase->setVar('last_message', 'start');
		
		$irc->message($data->type, $this->getChannel(), "Now gating tweets to IRC for these users: " . join (', ' , $this->getFriends()));
		
		$twitterThread = new executeThread('twitterThread');
		$twitterThread->start();
		
		$this->setForkedProcess($twitterThread);
	}
	
	
	function twitterStreamStop(&$irc, &$data)
	{
		global $tigBase;
		
		if (!$tigBase->isTrustedUser($data->nick))
			return $tigBase->restrictedError($irc, $data);
		
		if (!is_object($this->getForkedProcess()))
		{
			$irc->message($data->type, $data->nick, "I am not currently gating tweets. Use 'twitter start' to start.");
			return true;
		}
		
		$irc->unregisterTimeid($this->displayTweetId);
		
		$twitterThread = $this->getForkedProcess();
		$twitterThread->stop();
		
		$this->setForkedProcess(FALSE);
		$irc->message($data->type, $this->getChannel(), "Gating tweets to IRC has been disabled.");
	}
	
	
	function displayTweet(&$irc)
	{
		if (is_object($this->getForkedProcess()))
		{
			global $tigBase;
			
			$twitterThread = $this->getForkedProcess();
			
			if (DEBUG)
				//echo "\n \n" . 'last_message: ' . $twitterThread->getVariable('last_message') . "\n \n";
			
			if ($twitterThread->getVariable('last_message_displayed') == FALSE)
			{
				$twitterThread->setVariable('last_message_displayed', TRUE);
				
				$tweet = $twitterThread->getVariable('last_message');
				
				$irc->message(SMARTIRC_TYPE_CHANNEL, $this->getChannel(), $tweet);
			}
		}
	}
	
	
	function twitterInfo(&$irc)
	{
		echo "\n --- Debug info ---\n";
		print_r($this);
		$connection = new TwitterOAuth($this->getConsumerKey(), $this->getConsumerSecret(), $this->getAuthToken(), $this->getTokenSecret());
		$content = $connection->get('account/verify_credentials');
		print_r($content);
		echo "\n --- Debug info ---\n";
	}
}

class executeThread extends PHP_Fork {
	
	var $last_message;
	
	function executeThread($name)
	{
		$this->PHP_Fork($name);
		$this->counter = 0;
	}
	
	function run()
	{
		global $tigBase;
		
		$oauth = false;
		if (file_exists($tigBase->getVar('auth_file')))
		{
			$oauthData = unserialize(file_get_contents($tigBase->getVar('auth_file')));
			$oauth = true;
		}
		
		if ($oauth)
		{
			$method = 'POST';
			$url = 'https://userstream.twitter.com/2/user.json';
			$parameters = array();
			
			$connection = new TwitterOAuth($oauthData['consumer_key'], $oauthData['consumer_secret'], $oauthData['auth_token'], $oauthData['token_secret']);
			
			$oauth_request = OAuthRequest::from_consumer_and_token($connection->consumer, $connection->token, $method, $url, $parameters);
			$oauth_request->sign_request($connection->sha1_method, $connection->consumer, $connection->token);
			
			$oauthParameters = $oauth_request->get_parameters();
			
			
			$fp = fsockopen("ssl://userstream.twitter.com", 443, $errno, $errstr, 30);
			if(!$fp){
				print "$errstr ($errno)\n";
			} else {
				$request = "POST /2/user.json HTTP/1.1\r\n";
				$request .= "Host: userstream.twitter.com\r\n";
				$request .= "Authorization: OAuth";
				
				$first = true;
				foreach ($oauthParameters as $key => $value)
				{
					$request .= ($fist) ? ' ' : ', ';
					$request .= OAuthUtil::urlencode_rfc3986($key) . '="' . OAuthUtil::urlencode_rfc3986($value) . '"';
				}
				
				$request .= "\r\n\r\n";
				
				if (DEBUG)
					echo "--debug--\n{$request}\n--debug--\n";
				
				fwrite($fp, $request);
				while(!feof($fp)){
					$json = fgets($fp);
					$data = json_decode($json, true);
					if($data){
						if (in_array($data['user']['screen_name'], $tigBase->getVar('friends')))
						{
							$new_message = '[' . $data['user']['name'] . '/@' . $data['user']['screen_name'] . '] ' . $data['text'];
							
							if ($new_message != $this->last_message)
							{
								$this->last_message = $new_message;
								$this->setVariable('last_message', $new_message);
								$this->setVariable('last_message_displayed', FALSE);
							}
						}
					}
				}
				fclose($fp);
			}
			
		}
	}
}

$addonTwitter = new addonTwitter();

$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^twitter oauth register', $addonTwitter, 'twitterOauthRegister');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^twitter oauth validate', $addonTwitter, 'twitterOauthValidate');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^twitter stream start', $addonTwitter, 'twitterStreamStart');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^twitter stream stop', $addonTwitter, 'twitterStreamStop');
$irc->registerActionhandler(SMARTIRC_TYPE_QUERY|SMARTIRC_TYPE_NOTICE, '^twitter info', $addonTwitter, 'twitterInfo');
