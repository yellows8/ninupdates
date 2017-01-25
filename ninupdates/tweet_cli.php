<?php

require_once(dirname(__FILE__) . "/tweet.php");

if($argc<2)
{
	die("Usage:\nphp tweet_cli.php <msg>\n");
}

sendtweet($argv[1]);

?>
