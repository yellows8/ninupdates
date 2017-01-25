<?php

//This is based on the example code from here: https://twitteroauth.com/

require "vendor/abraham/twitteroauth/autoload.php";

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

	if(!isset($twittercfg_consumer_key) || !isset($twittercfg_consumer_secret) || !isset($twittercfg_access_token) || !isset($twittercfg_access_token_secret))
	{
		echo "Twitter config is missing.\n";
		return 1;
	}

	$connection = new TwitterOAuth($twittercfg_consumer_key, $twittercfg_consumer_secret, $twittercfg_access_token, $twittercfg_access_token_secret);
	$content = $connection->get("account/verify_credentials");
	$statuscode = $connection->getLastHttpCode();

	if($statuscode != 200)
	{
		echo "Auth failed, got HTTP status-code: $statuscode.\n";
		return 2;
	}

	$statues = $connection->post("statuses/update", ["status" => $msg]);
	$statuscode = $connection->getLastHttpCode();

	if($statuscode != 200)
	{
		echo "statuses/update request failed, got HTTP status-code: $statuscode.\n";
		return 3;
	}

	return 0;
}

?>
