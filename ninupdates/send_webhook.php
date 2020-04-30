<?php

function send_webhook($msg, $target_hook = 0)
{
	$tmp_cmd = dirname(__FILE__) .  "/webhook.py " . escapeshellarg($msg) . " " . escapeshellarg($target_hook);
	return system($tmp_cmd);
}

?>
