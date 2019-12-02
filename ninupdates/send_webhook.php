<?php

function send_webhook($msg)
{
	$tmp_cmd = dirname(__FILE__) .  "/webhook.py " . escapeshellarg($msg);
	return system($tmp_cmd);
}

?>
