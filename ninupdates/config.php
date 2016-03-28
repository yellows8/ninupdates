<?php

/*
site_cfg.php Should set the following config params:
$sitecfg_remotecmd Determines whether to use SSH for the IRC msgme(optional, default is 0 for disabled).
$sitecfg_target_email Email address to send the report url to.
$sitecfg_httpbase URL where the pub_html scripts are located.
$sitecfg_emailhost Email host portion of the sender email address.
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

$sitecfg_postproc_cmd This is the command which will be executed by postproc.php, if this is set. The full command passed to system() is: "$sitecfg_postproc_cmd $reportdate $system".
*/

require_once(dirname(__FILE__) . "/site_cfg.php");

if(!isset($sitecfg_remotecmd))$sitecfg_remotecmd = 0;
if(!isset($sitecfg_sshhost))$sitecfg_sshhost = "";
if(!isset($sitecfg_logplainhttp200))$sitecfg_logplainhttp200 = 0;
if(!isset($sitecfg_homepage_header))$sitecfg_homepage_header = "";
if(!isset($sitecfg_homepage_footer))$sitecfg_homepage_footer = "";
if(!isset($sitecfg_reportupdatepage_header))$sitecfg_reportupdatepage_header = "";
if(!isset($sitecfg_reportupdatepage_footer))$sitecfg_reportupdatepage_footer = "";
if(!isset($sitecfg_sitenav_header))$sitecfg_sitenav_header = "";

function appendmsg_tofile($msg, $filename)
{
	global $sitecfg_remotecmd, $sitecfg_sshhost;
	$tmp_cmd = "echo '" . $msg . "' >> /home/yellows8/.irssi/$filename";
	$irc_syscmd = $tmp_cmd;
	if($sitecfg_remotecmd==1)$irc_syscmd = "ssh yellows8@$sitecfg_sshhost \"".$tmp_cmd."\"";
	system($irc_syscmd);
}

function sendircmsg($msg)
{
	global $system;

	appendmsg_tofile($msg, "msg_yls8ninupdateschan");
	if($system=="ctr" || $system=="ktr")appendmsg_tofile($msg, "msg3dsdev");
	if($system=="wup" || $system=="wupv")appendmsg_tofile($msg, "msgwiiudev");
}

?>
