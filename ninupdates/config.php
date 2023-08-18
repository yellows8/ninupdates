<?php

/*
site_cfg.php Should set the following config params:
$sitecfg_remotecmd Determines whether to use SSH for the IRC msgme(optional, default is 0 for disabled).
$sitecfg_target_email Optional email address to send the report url to. Email is disabled if this isn't specified.
$sitecfg_httpbase URL where the pub_html scripts are located.
$sitecfg_emailhost Optional email host portion of the sender email address. Email is disabled if this isn't specified.
$sitecfg_sshhost SSH host used for the SSH IRC msgme(only needed when $sitecfg_remotecmd is non-zero).
$sitecfg_workdir Absolute path to the location of these scripts.
$sitecfg_logplainhttp200 This is optional, default is zero. When non-zero, the following is enabled: writeNormalLog("RESULT: 200")
$sitecfg_mysqldb_username MySQL username.
$sitecfg_mysqldb_pwdpath Path to file containing MySQL password.
$sitecfg_mysqldb_database MySQL database.

$sitecfg_homepage_header Optional HTML to include near the beginning of the "reports.php"(homepage) <body>.
$sitecfg_homepage_footer Optional HTML to include at the very end of the "reports.php"(homepage) <body>.
$sitecfg_reportupdatepage_header Optional HTML to include near the beginning of the reports.php report update-pages <body>.
$sitecfg_reportupdatepage_footer Optional HTML to include at the very end of the reports.php report update-pages <body>.
$sitecfg_sitenav_header Optional HTML to include immediately before the site navigation-bar.

$sitecfg_irc_msg_dirpath Dirpath used when sending IRC messages with appendmsg_tofile, if not specified this functionality is disabled.
$sitecfg_irc_msgtargets["{system}"]= "{filename}"; IRC msgtarget filename to use with the specified system, if not specified IRC messages are disabled. This used for sysupdate-detected notifs.
$sitecfg_irc_msgtarget Similar to sitecfg_irc_msgtargets, except this is an optional string filename to use for all systems.
$sitecfg_irc_msgtargets_whitelist Array of strings for filenames allowed to be used by appendmsg_tofile. If not specified, this is loaded from sitecfg_irc_msgtarget(s). 'msgme' is hard-coded to be allowed regardless.

$sitecfg_postproc_cmd This is the command which will be executed by postproc.php, if this is set. The full command passed to system() is: "$sitecfg_postproc_cmd $reportdate $system".

$sitecfg_load_titlelist_cmd See ninsoap.php.

$sitecfg_consoles_deviceid["{system}"]["{regioncode}"] = "{id}"; DeviceId to use, overrides the value from the db.
*/

require_once(dirname(__FILE__) . "/site_cfg.php");

if(!isset($sitecfg_remotecmd))$sitecfg_remotecmd = 0;
if(!isset($sitecfg_target_email))$sitecfg_target_email = "";
if(!isset($sitecfg_emailhost))$sitecfg_emailhost = "";
if(!isset($sitecfg_sshhost))$sitecfg_sshhost = "";
if(!isset($sitecfg_logplainhttp200))$sitecfg_logplainhttp200 = 0;
if(!isset($sitecfg_homepage_header))$sitecfg_homepage_header = "";
if(!isset($sitecfg_homepage_footer))$sitecfg_homepage_footer = "";
if(!isset($sitecfg_reportupdatepage_header))$sitecfg_reportupdatepage_header = "";
if(!isset($sitecfg_reportupdatepage_footer))$sitecfg_reportupdatepage_footer = "";
if(!isset($sitecfg_sitenav_header))$sitecfg_sitenav_header = "";

if(!isset($sitecfg_irc_msg_dirpath))$sitecfg_irc_msg_dirpath = "";
if(!isset($sitecfg_irc_msgtargets))$sitecfg_irc_msgtargets = array();
if(!isset($sitecfg_irc_msgtarget))$sitecfg_irc_msgtarget = "";

function appendmsg_tofile($msg, $filename)
{
	global $sitecfg_remotecmd, $sitecfg_sshhost, $sitecfg_irc_msg_dirpath, $sitecfg_irc_msgtarget, $sitecfg_irc_msgtargets, $sitecfg_irc_msgtargets_whitelist;

	if($sitecfg_irc_msg_dirpath!="" && strlen($filename)>0)
	{
		if(!isset($sitecfg_irc_msgtargets_whitelist))
		{
			$sitecfg_irc_msgtargets_whitelist = array();
			if(strlen($sitecfg_irc_msgtarget)>0) $sitecfg_irc_msgtargets_whitelist[] = $sitecfg_irc_msgtarget;
			foreach($sitecfg_irc_msgtargets as $target)
			{
				if(strlen($target)>0)
				{
					$sitecfg_irc_msgtargets_whitelist[] = $target;
				}
			}
		}

		$found = False;
		if($filename == "msgme")
		{
			$found = True;
		}
		else
		{
			foreach($sitecfg_irc_msgtargets_whitelist as $target)
			{
				if(strlen($target)>0 && $target == $filename)
				{
					$found = True;
					break;
				}
			}
		}
		if($found===False)
		{
			echo "appendmsg_tofile(): The specified filename is not whitelisted, msg will not be sent: $filename\n";
			return;
		}

		$tmp_cmd = "echo " . escapeshellarg($msg) . " >> " . escapeshellarg("$sitecfg_irc_msg_dirpath/$filename");
		$irc_syscmd = $tmp_cmd;
		if($sitecfg_remotecmd==1)$irc_syscmd = "ssh yellows8@$sitecfg_sshhost \"".$tmp_cmd."\"";
		system($irc_syscmd);
	}
}

function send_notif($args)
{
	global $sitecfg_workdir;

	$cmd = "cd $sitecfg_workdir && ";
	$cmd .= $sitecfg_workdir . "/send_notif.py";
	foreach($args as $arg)
	{
		if(strlen($arg)>0)
		{
			$cmd.= " " . escapeshellarg($arg);
		}
	}
	$cmd.= " >> $sitecfg_workdir/sendnotif_log 2>&1 &";
	system($cmd);
}

?>
