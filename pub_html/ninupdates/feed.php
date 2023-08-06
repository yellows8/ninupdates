<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/feedphp";

dbconnection_start();

db_checkmaintenance(1);

header("Content-Type: text/xml;charset=utf-8");

$query="SELECT ninupdates_reports.reportdaterfc FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);
$row = mysqli_fetch_row($result);
$curdate = gmdate(DATE_RSS, date_timestamp_get(date_create_from_format(DateTimeInterface::RFC822, $row[0])));

$con = "<?xml version=\"1.0\" encoding=\"UTF-8\" ?>
<rss xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:dc=\"http://purl.org/dc/elements/1.1/\" version=\"2.0\">
    <channel>
      <title>Sysupdate Reports</title>
      <atom:link href=\"$sitecfg_httpbase/feed.php\" rel=\"self\" type=\"application/rss+xml\" />
      <link>$sitecfg_httpbase/reports.php</link>
      <description>Nintendo System Update Reports</description>
      <lastBuildDate>$curdate</lastBuildDate>
      <language>en</language>
    ";

$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system, ninupdates_reports.reportdaterfc FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_reports.curdate DESC LIMIT 4";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

for($i=0; $i<$numrows; $i++)
{
	$row = mysqli_fetch_row($result);
	$reportdate = $row[0];
	$updateversion = $row[1];
	$system = $row[2];
	$curdate = gmdate(DATE_RSS, date_timestamp_get(date_create_from_format(DateTimeInterface::RFC822, $row[3])));

	$sys = getsystem_sysname($system);

	$item_title = "$sys $updateversion";
	if($updateversion=="N/A")$item_title = "$sys $reportdate";

	$url = "$sitecfg_httpbase/reports.php?date=$reportdate&sys=$system";
	$url = "<![CDATA[$url]]>";
	//$url = str_replace ("&","",htmlspecialchars(strip_tags($url))); 

	$con .= "	<item>
		<title>$item_title</title>
		<link>$url</link>
		<guid isPermaLink=\"true\">$url</guid>
		<description>$item_title</description>
		<pubDate>$curdate</pubDate>
	</item>\n";
}

$con.= " </channel>
</rss>";

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");
echo $con;

?>
