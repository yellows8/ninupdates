<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

if($argc<4)
{
	die("This merges the specified reports and all related data in the mysql database.\nUsage:\nphp merge_reports.php <system(internal name)> <dst_reportdate> <src_reportdate>\n");
}

dbconnection_start();

$system = mysqli_real_escape_string($mysqldb, $argv[1]);
$dst_reportdate = mysqli_real_escape_string($mysqldb, $argv[2]);
$src_reportdate = mysqli_real_escape_string($mysqldb, $argv[3]);

$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to find the specified system.\n");
}

$row = mysqli_fetch_row($result);
$systemid = $row[0];

$query="SELECT ninupdates_reports.id, ninupdates_reports.curdate, ninupdates_reports.regions FROM ninupdates_reports WHERE ninupdates_reports.reportdate='".$dst_reportdate."' && ninupdates_reports.systemid=$systemid";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to find the specified dst-report with the input system.\n");
}

$row = mysqli_fetch_row($result);
$dst_reportid = $row[0];
$dst_reportcurdate = $row[1];
$dst_regions = $row[2];

$query="SELECT ninupdates_reports.id, ninupdates_reports.curdate, ninupdates_reports.regions FROM ninupdates_reports WHERE ninupdates_reports.reportdate='".$src_reportdate."' && ninupdates_reports.systemid=$systemid";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to find the specified src-report with the input system.\n");
}

$row = mysqli_fetch_row($result);
$src_reportid = $row[0];
$src_reportcurdate = $row[1];
$src_regions = $row[2];

$query="SELECT regions FROM ninupdates_consoles WHERE id='".$systemid."'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();
	die("Failed to load the regions field for this system.\n");
}

$row = mysqli_fetch_row($result);
$regions = $row[0];

$query = "UPDATE ninupdates_titles SET reportid='".$dst_reportid."', curdate='".$dst_reportcurdate."' WHERE curdate='".$src_reportcurdate."' && reportid='".$src_reportid."' && systemid=$systemid";
$result=mysqli_query($mysqldb, $query);
if($result===FALSE)
{
	dbconnection_end();
	die("Failed to update ninupdates_titles.");
}

$query = "UPDATE ninupdates_systitlehashes SET reportid='".$dst_reportid."' WHERE reportid='".$src_reportid."'";
$result=mysqli_query($mysqldb, $query);
if($result===FALSE)
{
	dbconnection_end();
	die("Failed to update ninupdates_systitlehashes.");
}

$query = "UPDATE ninupdates_officialchangelogs SET reportid='".$dst_reportid."' WHERE reportid='".$src_reportid."'";
$result=mysqli_query($mysqldb, $query);
if($result===FALSE)
{
	dbconnection_end();
	die("Failed to update ninupdates_officialchangelogs.");
}

$new_regions = "";

for($i=0; $i<strlen($regions); $i++)
{
	$cur_region = substr($regions, $i, 1);

	$found = 0;

	$dst_region = strtok($dst_regions, ",");
	while($dst_region!==FALSE)
	{
		if($dst_region === $cur_region)
		{
			$found = 1;
			break;
		}
		$dst_region = strtok(",");
	}

	if($found == 0)
	{
		$src_region = strtok($src_regions, ",");
		while($src_region!==FALSE)
		{
			if($src_region === $cur_region)
			{
				$found = 1;
				break;
			}

			$src_region = strtok(",");
		}
	}

	if($found == 1)
	{
		if($new_regions!="")$new_regions.= ",";
		$new_regions.= $cur_region;
	}
}

$query="DELETE FROM ninupdates_reports WHERE id=$src_reportid && ninupdates_reports.systemid=$systemid";
$result=mysqli_query($mysqldb, $query);
if($result===FALSE)
{
	dbconnection_end();
	die("Failed to delete the report.");
}

$query = "UPDATE ninupdates_reports SET regions='".$new_regions."' WHERE id='".$dst_reportid."' && ninupdates_reports.systemid=$systemid";
$result=mysqli_query($mysqldb, $query);
if($result===FALSE)
{
	dbconnection_end();
	die("Failed to update ninupdates_reports regions.");
}

dbconnection_end();

?>
