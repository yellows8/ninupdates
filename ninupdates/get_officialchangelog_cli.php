<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");
include_once("get_officialchangelog.php");

dbconnection_start();

$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.regions, ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updatever_autoset=0 && ninupdates_reports.systemid=ninupdates_consoles.id";
$result=mysqli_query($mysqldb, $query);
$numrows_reports=mysqli_num_rows($result);

for($i=0; $i<$numrows_reports; $i++)
{
	$row = mysqli_fetch_row($result);
	$sysupdate_timestamp = $row[0];
	$sysupdate_regions = $row[1];
	$system = $row[2];

	$pos = 0;
	$len = strlen($sysupdate_regions);

	while($pos < $len)
	{
		$region = substr($sysupdate_regions, $pos, 1);

		$query="SELECT ninupdates_officialchangelog_pages.url, ninupdates_officialchangelog_pages.id FROM ninupdates_officialchangelog_pages, ninupdates_consoles, ninupdates_regions WHERE ninupdates_consoles.system='".$system."' && ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id && ninupdates_officialchangelog_pages.regionid=ninupdates_regions.id && ninupdates_regions.regioncode='".$region."'";
		$result_other=mysqli_query($mysqldb, $query);
		$numrows_pages=mysqli_num_rows($result_other);
		if($numrows_pages!=0)
		{
			$row = mysqli_fetch_row($result_other);
			$pageurl = $row[0];
			$pageid = $row[1];

			echo "Running get_ninsite_latest_sysupdatever() with system=$system and region=$region...\n";

			get_ninsite_changelog($sysupdate_timestamp, $system, $pageurl, $pageid);
		}

		$pos++;
		if($pos >= $len)break;

		if($sysupdate_regions[$pos]==',')$pos++;
	}
}

$query="SELECT COUNT(*) FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updatever_autoset=1 && ninupdates_reports.wikibot_runfinished=0 && ninupdates_reports.systemid=ninupdates_consoles.id";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows>0)
{
	$row = mysqli_fetch_row($result);
	$count = $row[0];

	if($count>0)
	{
		echo "Starting a wikibot task for processing $count report(s)...\n";

		$wikibot_timestamp = date("m-d-y_h-i-s");

		system("php $sitecfg_workdir/wikibot.php scheduled > $sitecfg_workdir/wikibot_out/$wikibot_timestamp 2>&1 &");
	}
}

dbconnection_end();

?>
