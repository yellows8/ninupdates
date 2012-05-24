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
$twldeviceid / $ctrdeviceid The SOAP DSi/3DS DeviceId. This is a u64 decimal string, the lower hex word can be random. The upper hex word is the consoletype: 3=DSi, 4=3DS.
*/

include_once("site_cfg.php");

$httpbase = "http://$host";

function sendircmsg($msg)
{
	global $remotecmd, $sshhost;
	$tmp_cmd = "echo '" . $msg . "' >> /home/yellows8/.irssi/msgme";
	$irc_syscmd = $tmp_cmd;
	if($remotecmd==1)$irc_syscmd = "ssh yellows8@$sshhost \"".$tmp_cmd."\"";
	system($irc_syscmd);
}

?>
