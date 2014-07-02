<?php

/*
site_cfg.php Should set the following config params:
$remotecmd Determines whether to use SSH for the IRC msgme(optional, default is 0 for disabled).
$target_email Email address to send the report url to.
$httpbase Optional override for the URL where the pub_html scripts are located.
$emailhost Email host portion of the sender email address.
$sshhost SSH host used for the SSH IRC msgme(only needed when $remotecmd is non-zero).
$workdir Absolute path to the location of these scripts.
$mysqldb_username MySQL username.
$mysqldb_pwdpath Path to file containing MySQL password.
$mysqldb_database MySQL database.
*/

include_once("site_cfg.php");

if(!isset($remotecmd))$remotecmd = 0;
if(!isset($httpbase))$httpbase = "http://" . $_SERVER["SERVER_NAME"];
if(!isset($sshhost))$sshhost = "";

function appendmsg_tofile($msg, $filename)
{
	global $remotecmd, $sshhost;
	$tmp_cmd = "echo '" . $msg . "' >> /home/yellows8/.irssi/$filename";
	$irc_syscmd = $tmp_cmd;
	if($remotecmd==1)$irc_syscmd = "ssh yellows8@$sshhost \"".$tmp_cmd."\"";
	system($irc_syscmd);
}

function sendircmsg($msg)
{
	global $system;

	appendmsg_tofile($msg, "msgme");
	if($system=="ctr")appendmsg_tofile($msg, "msgchan");
}

?>
