<?php

require_once(dirname(__FILE__) . "/tweet.php");

if($argc<2)
{
	echo("Usage:\nphp tweet_cli.php <msg>\n");
	exit(1);
}

sendtweet($argv[1]);

?>
