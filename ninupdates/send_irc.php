<?php

require_once(dirname(__FILE__) . "/config.php");

if($argc<3)
{
	echo("Usage:\nphp send_irc.php <msg> <msgtarget(s)>\n");
	exit(1);
}

for($i=2; $i<$argc; $i++)
{
	appendmsg_tofile($argv[1], $argv[$i]);
}

?>
