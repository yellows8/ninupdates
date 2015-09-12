<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");
include_once("/home/yellows8/ninupdates/nzone/nzone.php");

$logging_dir = "$sitecfg_workdir/weblogs/general";

dbconnection_start();

db_checkmaintenance(1);

$reqversion = "";
if(isset($_REQUEST['version']))$reqversion = mysqli_real_escape_string($mysqldb, $_REQUEST['version']);

$query="SELECT id FROM ninupdates_consoles WHERE system='ctr'";
$result=mysqli_query($mysqldb, $query);

$numrows=mysqli_num_rows($result);
if($numrows==0)
{
	dbconnection_end();
	writeNormalLog("NZONE: FAILED TO FIND CTR SYSTEM. RESULT: 200");
	exit;
}

$row = mysqli_fetch_row($result);
$system = $row[0];

$query = "SELECT id FROM ninupdates_titleids WHERE titleid='000400DB00010502'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);
		
if($numrows==0)
{
	dbconnection_end();

	header("Location: reports.php");
	writeNormalLog("ROW FOR NZONE-HOTSPOTS TITLEID NOT FOUND. RESULT: 302");

	return;
}

$row = mysqli_fetch_row($result);
$rowid = $row[0];

$versionquery = "GROUP_CONCAT(DISTINCT ninupdates_titles.version ORDER BY ninupdates_titles.version SEPARATOR ','),";
$reportdatequery = "GROUP_CONCAT(DISTINCT ninupdates_reports.reportdate ORDER BY ninupdates_reports.curdate SEPARATOR ','),";
$updateverquery = "GROUP_CONCAT(DISTINCT ninupdates_reports.updateversion ORDER BY ninupdates_reports.curdate SEPARATOR ',')";

$query = "SELECT $versionquery $reportdatequery $updateverquery FROM ninupdates_titles, ninupdates_reports WHERE ninupdates_titles.tid=$rowid && ninupdates_titles.region='E' && ninupdates_titles.systemid=$system && ninupdates_reports.id=ninupdates_titles.reportid";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();

	header("Location: reports.php");
	writeNormalLog("ROW FOR NZONE-HOTSPOTS TITLE NOT FOUND. RESULT: 302");

	return;
}

$row = mysqli_fetch_row($result);
$versions = $row[0];
$reportdates = $row[1];
$updateversions = $row[2];

dbconnection_end();

$total_entries = 0;
$version_array = array();
$reportdate_array = array();
$updateversion_array = array();

$ver = strtok($versions, ",");
while($ver!==FALSE)
{
	$version_array[] = "v$ver";
	$total_entries++;
	$ver = strtok(",");
}

$cur_reportdate = strtok($reportdates, ",");
while($cur_reportdate!==FALSE)
{
	$reportdate_array[] = $cur_reportdate;
	$cur_reportdate = strtok(",");
}

$updatever = strtok($updateversions, ",");
while($updatever!==FALSE)
{
	$updateversion_array[] = $updatever;
	$updatever = strtok(",");
}

if($reqversion=="")
{
	$con = "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo Zone Hotspots</title></head><body>\n";
	$con.= "<table border=\"1\">
<tr>
  <th>Title version</th>
  <th>Update version + report</th>
  <th>Parsed data</th>
</tr>\n";
	$con .= "</body></html>";

	for($i=0; $i<$total_entries; $i++)
	{
		$url = "reports.php?date=".$reportdate_array[$i]."&amp;sys=ctr";
		$path = "$sitecfg_workdir/nzone/hotspotconf/" . $version_array[$i] . "_hotspot.conf";
		$parsetext = "N/A";

		if(file_exists($path))
		{
			$urlparse = "3ds_nzonehotspots.php?version=" . $version_array[$i];
			$parsetext = "<a href =\"$urlparse\">Available</a>";
		}

		$con.= "<tr>\n";
		$con.= "<td>".$version_array[$i]."</td>\n";
		$con.= "<td><a href =\"$url\">".$updateversion_array[$i]."</a></td>\n";
		$con.= "<td>$parsetext</td>\n";
		$con.= "</tr>\n";
	}

	echo $con;

	return;
}
else
{
	$found = 0;
	for($i=0; $i<$total_entries; $i++)
	{
		if($version_array[$i] == $reqversion)
		{
			$found = 1;
			break;
		}
	}

	if(!$found)
	{
		header("Location: 3ds_nzonehotspots.php");
		writeNormalLog("THE INPUT NZONE HOTSPOT VERSION DOES NOT EXIST IN THE DB. RESULT: 302");

		return;
	}

	$path = "$sitecfg_workdir/nzone/hotspotconf/" . $reqversion . "_hotspot.conf";
	if(!file_exists($path))
	{
		header("Location: 3ds_nzonehotspots.php");
		writeNormalLog("THE INPUT NZONE HOTSPOT VERSION DOES NOT HAVE A _hotspot.conf WHICH EXISTS. RESULT: 302");

		return;
	}

	parse_hotspotconf($path);
}

?>
