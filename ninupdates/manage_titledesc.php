<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/titlesetdesc";

if($argc<2)
{
	die("Usage:\nphp manage_titledesc.php <titleid> <description text when setting the desc>\n");
}

dbconnection_start();

$titleid = "";
$desc = "";
$titleid = mysqli_real_escape_string($mysqldb, $argv[1]);
if($argc > 2)$desc = mysqli_real_escape_string($mysqldb, $argv[2]);

$query = "SELECT id, description FROM ninupdates_titleids WHERE titleid='" . $titleid . "'";
$result=mysqli_query($mysqldb, $query);
$numrows=mysqli_num_rows($result);

if($numrows==0)
{
	dbconnection_end();

	echo "Row for titleid not found.\n";

	return 1;
}

$row = mysqli_fetch_row($result);
$rowid = $row[0];
$curdesc = $row[1];

if($curdesc==="" || $curdesc===NULL)$curdesc = "N/A";

if($desc==="")echo "$curdesc";

if($desc!=="")
{
	$query = "UPDATE ninupdates_titleids SET description='".$desc."' WHERE id=$rowid";
	$result=mysqli_query($mysqldb, $query);

	writeNormalLog("manage_titledesc: CHANGED TID $titleid DESC TO $desc.");
}

dbconnection_end();

?>
