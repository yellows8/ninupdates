<?php

//This is based on the example code from here: https://twitteroauth.com/

require "vendor/autoload.php";

use Abraham\TwitterOAuth\TwitterOAuth;

include_once(dirname(__FILE__) . "/twitter_config.php");
/*
The above file must contain the following settings:
$twittercfg_consumer_key
$twittercfg_consumer_secret
$twittercfg_access_token
$twittercfg_access_token_secret
*/

function sendtweet($msg)
{
	global $twittercfg_consumer_key, $twittercfg_consumer_secret, $twittercfg_access_token, $twittercfg_access_token_secret;

	$ret = 0;

	if(!isset($twittercfg_consumer_key) || !isset($twittercfg_consumer_secret) || !isset($twittercfg_access_token) || !isset($twittercfg_access_token_secret))
	{
		echo "Twitter config is missing.\n";
		$ret = 1;
	}

	if($ret == 0)
	{
		$connection = new TwitterOAuth($twittercfg_consumer_key, $twittercfg_consumer_secret, $twittercfg_access_token, $twittercfg_access_token_secret);
		$connection->setApiVersion('2');
	}

	if($ret == 0)
	{
		$out = $connection->post("tweets", ["text" => $msg], $json = true);
		$statuscode = $connection->getLastHttpCode();

		if($statuscode != 200 && $statuscode != 201)
		{
			echo "tweet request failed, got HTTP status-code: $statuscode.\n";
			var_dump($out);
			$ret = 3;
		}
	}

	$tmp_cmd = dirname(__FILE__) .  "/send_mastodon.py " . escapeshellarg($msg);
	system($tmp_cmd);

	return $ret;
}

?>
