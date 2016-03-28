<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/general";

function ninupdates_api($ninupdatesapi_in_command, $ninupdatesapi_in_sys, $ninupdatesapi_in_region, $ninupdatesapi_in_titleid, $ninupdatesapi_in_filterent)
{
	global $mysqldb, $ninupdatesapi_out_total_entries, $ninupdatesapi_out_version_array, $ninupdatesapi_out_reportdate_array, $ninupdatesapi_out_updateversion_array;

	dbconnection_start();

	db_checkmaintenance(1);

	$ninupdatesapi_in_command = mysqli_real_escape_string($mysqldb, $ninupdatesapi_in_command);
	$ninupdatesapi_in_sys = mysqli_real_escape_string($mysqldb, $ninupdatesapi_in_sys);
	$ninupdatesapi_in_region = mysqli_real_escape_string($mysqldb, $ninupdatesapi_in_region);
	$ninupdatesapi_in_titleid = mysqli_real_escape_string($mysqldb, $ninupdatesapi_in_titleid);

	if($ninupdatesapi_in_command==="" || $ninupdatesapi_in_sys==="" || $ninupdatesapi_in_region==="" || $ninupdatesapi_in_titleid==="" || $ninupdatesapi_in_filterent<0 || $ninupdatesapi_in_filterent>2)
	{
		dbconnection_end();
		return 1;
	}

	if($ninupdatesapi_in_command!=="gettitleversions")
	{
		dbconnection_end();
		return 2;
	}

	$query="SELECT id FROM ninupdates_consoles WHERE system='".$ninupdatesapi_in_sys."'";
	$result=mysqli_query($mysqldb, $query);

	$numrows=mysqli_num_rows($result);
	if($numrows==0)
	{
		dbconnection_end();
		return 3;
	}

	$row = mysqli_fetch_row($result);
	$system = $row[0];

	$query = "SELECT id FROM ninupdates_titleids WHERE titleid='".$ninupdatesapi_in_titleid."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
		
	if($numrows==0)
	{
		dbconnection_end();
		return 4;
	}

	$row = mysqli_fetch_row($result);
	$rowid = $row[0];

	$versionquery = "GROUP_CONCAT(DISTINCT ninupdates_titles.version ORDER BY ninupdates_titles.version SEPARATOR ','),";
	$reportdatequery = "GROUP_CONCAT(DISTINCT ninupdates_reports.reportdate ORDER BY ninupdates_reports.curdate SEPARATOR ','),";
	$updateverquery = "GROUP_CONCAT(DISTINCT ninupdates_reports.updateversion ORDER BY ninupdates_reports.curdate SEPARATOR ',')";

	$query = "SELECT $versionquery $reportdatequery $updateverquery FROM ninupdates_titles, ninupdates_reports WHERE ninupdates_titles.tid=$rowid && ninupdates_titles.region='".$ninupdatesapi_in_region."' && ninupdates_titles.systemid=$system && ninupdates_reports.id=ninupdates_titles.reportid";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		dbconnection_end();
		return 5;
	}

	$row = mysqli_fetch_row($result);
	$versions = $row[0];
	$reportdates = $row[1];
	$updateversions = $row[2];

	dbconnection_end();

	$ninupdatesapi_out_total_entries = 0;
	$ninupdatesapi_out_version_array = array();
	$ninupdatesapi_out_reportdate_array = array();
	$ninupdatesapi_out_updateversion_array = array();

	$ver = strtok($versions, ",");
	while($ver!==FALSE)
	{
		$ninupdatesapi_out_version_array[] = "v$ver";
		$ninupdatesapi_out_total_entries++;
		$ver = strtok(",");
	}

	$cur_reportdate = strtok($reportdates, ",");
	while($cur_reportdate!==FALSE)
	{
		$ninupdatesapi_out_reportdate_array[] = $cur_reportdate;
		$cur_reportdate = strtok(",");
	}

	$updatever = strtok($updateversions, ",");
	while($updatever!==FALSE)
	{
		$ninupdatesapi_out_updateversion_array[] = $updatever;
		$updatever = strtok(",");
	}

	if($ninupdatesapi_in_filterent!=0)
	{
		if($ninupdatesapi_in_filterent==1)$index = 0;
		if($ninupdatesapi_in_filterent==2)$index = $ninupdatesapi_out_total_entries-1;

		$tmp = $ninupdatesapi_out_version_array[$index];
		$ninupdatesapi_out_version_array = array();
		$ninupdatesapi_out_version_array[] = $tmp;

		$tmp = $ninupdatesapi_out_reportdate_array[$index];
		$ninupdatesapi_out_reportdate_array = array();
		$ninupdatesapi_out_reportdate_array[] = $tmp;

		$tmp = $ninupdatesapi_out_updateversion_array[$index];
		$ninupdatesapi_out_updateversion_array = array();
		$ninupdatesapi_out_updateversion_array[] = $tmp;

		$ninupdatesapi_out_total_entries = 1;
	}

	return 0;
}

?>
