<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

include_once("http_pagelogger.php");

if($argc<6)
{
	echo("Usage:\nphp http_pagelogger_cli.php <url> <datadir> <msgprefix> <msgurl> <enable_notification> [optional msgtarget] [optional filterjs]\n");
	exit(1);
}

dbconnection_start();
if($argc<7)
{
	process_pagelogger($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
}
else if($argc<8)
{
	process_pagelogger($argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6]);
}
else
{
	process_pagelogger($argv[1], $argv[2], $argv[3], $argv[4], $argv[5], $argv[6], $argv[7]);
}
dbconnection_end();

?>
