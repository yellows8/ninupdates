<?

/*
site_cfg.php Should set the following config params:
$remotecmd flag, Determines whether to use SSH for the IRC msgme.
$target_email email Address to send the report url to.
$host Website host.
$emailhost Email host portion of the sender email address.
$sshhost SSH host used for the SSH IRC msgme.
$workdir Absolute path to the location of these scripts.
$mysqldb_username MySQL username.
$mysqldb_database MySQL database.
*/

include_once("site_cfg.php");

$httpbase = "http://$host";

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
