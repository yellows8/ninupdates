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

function tweet_init(&$connection)
{
	global $twittercfg_consumer_key, $twittercfg_consumer_secret, $twittercfg_access_token, $twittercfg_access_token_secret;
	if(!isset($twittercfg_consumer_key) || !isset($twittercfg_consumer_secret) || !isset($twittercfg_access_token) || !isset($twittercfg_access_token_secret))
	{
		echo "Twitter config is missing.\n";
		return 1;
	}

	$connection = new TwitterOAuth($twittercfg_consumer_key, $twittercfg_consumer_secret, $twittercfg_access_token, $twittercfg_access_token_secret);
	$connection->setApiVersion('2');

	return 0;
}

function sendtweet($msg)
{
	$ret = tweet_init($connection);

	if($ret == 0)
	{
		$out = $connection->post("tweets", ["text" => $msg], $json = true);
		$statuscode = $connection->getLastHttpCode();

		if($statuscode != 200 && $statuscode != 201)
		{
			echo "sendtweet(): request failed, got HTTP status-code: $statuscode.\n";
			var_dump($out);
			$ret = 3;
		}
	}

	return $ret;
}

function tweet_delete($in_id)
{
	$ret = tweet_init($connection);

	if($ret == 0)
	{
		$out = $connection->delete("tweets/$in_id");
		$statuscode = $connection->getLastHttpCode();

		if($statuscode != 200 && $statuscode != 201)
		{
			echo "tweet_delete(): request failed, got HTTP status-code: $statuscode.\n";
			var_dump($out);
			$ret = 3;
		}
	}

	return $ret;
}

if($_SERVER['SCRIPT_NAME'] === "tweet.php")
{
	if($argc<2)
	{
		echo("Usage:\nphp tweet.php <msg> [if specified, delete this id instead]\n");
		exit(1);
	}

	if($argc==2)
	{
		sendtweet($argv[1]);
	}
	else
	{
		tweet_delete($argv[2]);
	}
}

?>
