<?php

require_once(dirname(__FILE__) . "/config.php");

if($argc<3)
{
	die("Usage:\nphp send_irc.php <msg> <msgtarget>\n");
}

appendmsg_tofile($argv[1], $argv[2]);

?>
