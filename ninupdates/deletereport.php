<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

if($argc<3)
{
	die("This deletes the specified report and all data in the mysql database linked to it.\nUsage:\nphp deletereport.php <reportdate> <system(internal name)>\n");
}

dbconnection_start();

$reportdate = mysqli_real_escape_string($mysqldb, $argv[1]);
$system = mysqli_real_escape_string($mysqldb, $argv[2]);

$query="SELECT ninupdates_reports.id FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to find the specified report with the input system.\n");
}

$row = mysqli_fetch_row($result);
$reportid = $row[0];

$query="DELETE FROM ninupdates_titles WHERE reportid=$reportid";
$result=mysqli_query($mysqldb, $query);

$query="DELETE FROM ninupdates_systitlehashes WHERE reportid=$reportid";
$result=mysqli_query($mysqldb, $query);

$query="DELETE FROM ninupdates_officialchangelogs WHERE reportid=$reportid";
$result=mysqli_query($mysqldb, $query);

$query="DELETE FROM ninupdates_reports WHERE id=$reportid";
$result=mysqli_query($mysqldb, $query);

dbconnection_end();

?>
