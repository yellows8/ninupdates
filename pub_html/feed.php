<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/feedphp";

dbconnection_start();

db_checkmaintenance(1);

header("Content-Type: text/xml;charset=iso-8859-1");

$query="SELECT ninupdates_reports.curdate FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY curdate DESC LIMIT 1";
$result=mysql_query($query);
$numrows=mysql_numrows($result);
$row = mysql_fetch_row($result);
$curdate = gmdate(DATE_RSS, strtotime($row[0]));

$con = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>
<rss version=\"2.0\" xmlns:atom=\"http://www.w3.org/2005/Atom\" xmlns:sy=\"http://purl.org/rss/1.0/modules/syndication/\">
    <channel>
      <title>Sysupdate Reports</title>
      <atom:link href=\"$sitecfg_httpbase/feed.php\" rel=\"self\" type=\"application/rss+xml\" />
      <link>$sitecfg_httpbase/reports.php</link>
      <description>Nintendo System Update Reports</description>
      <lastBuildDate>$curdate</lastBuildDate>
      <language>en</language>
      <sy:updatePeriod>hourly</sy:updatePeriod>
      <sy:updateFrequency>1</sy:updateFrequency>
    ";

$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system, ninupdates_reports.curdate FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY curdate DESC LIMIT 4";
$result=mysql_query($query);
$numrows=mysql_numrows($result);

for($i=0; $i<$numrows; $i++)
{
	$row = mysql_fetch_row($result);
	$reportdate = $row[0];
	$updateversion = $row[1];
	$system = $row[2];
	$curdate = gmdate(DATE_RSS, strtotime($row[3]));

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

writeNormalLog("RESULT: 200");
echo $con;

?>
