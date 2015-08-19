<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

dbconnection_start();

$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.reportdaterfc, ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_reports.id DESC LIMIT 1";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);

$reportdate=$row[0];
$reportdaterfc=$row[1];
$system=$row[2];
echo "The newest report from the previous scan(s) was $reportdate-$system, requested at \"$reportdaterfc\".\n";

$query="SELECT lastscan FROM ninupdates_management";
$result=mysqli_query($mysqldb, $query);
$row = mysqli_fetch_row($result);

$lastscan = $row[0];
echo "The last scan was finished at: $lastscan.\n";

echo "Running fresh ninsoap scan...\n";
system("php /home/yellows8/ninupdates/ninsoap.php > $sitecfg_workdir/ninsoap_ircbottmp");
echo "Total detected title-listing changes for each of the scanned platforms: ";
system("grep -c \"System update available for regions\" $sitecfg_workdir/ninsoap_ircbottmp");
echo "Scan finished.\n";

dbconnection_end();

?>
