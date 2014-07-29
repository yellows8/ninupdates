<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$workdir/weblogs/titlesetdesc";

dbconnection_start();

db_checkmaintenance(1);

$titleid = "";
$desc = "";
if(isset($_REQUEST['titleid']))$titleid = mysql_real_escape_string($_REQUEST['titleid']);
if(isset($_REQUEST['desc']))$desc = mysql_real_escape_string($_REQUEST['desc']);

$query = "SELECT id, description FROM ninupdates_titleids WHERE titleid='" . $titleid . "'";
$result=mysql_query($query);
$numrows=mysql_numrows($result);
		
if($numrows==0)
{
	dbconnection_end();

	header("Location: reports.php");
	writeNormalLog("ROW FOR TITLEID NOT FOUND. RESULT: 302");

	return;
}

$row = mysql_fetch_row($result);
$rowid = $row[0];
$curdesc = $row[1];

if($curdesc == NULL)
{
	$curdesc = "";
}
else
{
	$curdesc = "  Current description: $curdesc</br></br>";
}

if($desc=="")
{
	$con = "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>Nintendo System Update Set Title Description</title></head><body>
<form method=\"post\" action=\"title_setdesc.php?titleid=$titleid\" enctype=\"multipart/form-data\">
$curdesc
  Description: <input type=\"text\" value=\"\" name=\"desc\"/><input type=\"submit\" value=\"Submit\"/></form></body></html>";

	dbconnection_end();
	writeNormalLog("RESULT: 200");
	echo $con;

	return;
}
else
{
	$desc = strip_tags($desc);

	$query = "UPDATE ninupdates_titleids SET description='".$desc."' WHERE id=$rowid";
	$result=mysql_query($query);
	dbconnection_end();

	header("Location: reports.php");
	writeNormalLog("CHANGED DESC TO $desc. RESULT: 302");

	return;
}

?>
