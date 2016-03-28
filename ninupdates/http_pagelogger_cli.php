<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

include_once("http_pagelogger.php");

if($argc<5)
{
	die("Usage:\nphp http_pagelogger_cli.php <url> <datadir> <msgprefix> <msgurl> <enable_notification>\n");
}

process_pagelogger($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);

?>
