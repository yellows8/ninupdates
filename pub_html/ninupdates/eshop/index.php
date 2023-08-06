<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/eshopindexphp";

dbconnection_start();

db_checkmaintenance(1);

$con = "<!doctype html>\n<html lang=\"en\">";

$con .= "<head><meta charset=\"UTF-8\" /><title>eShop Scanning</title></head>\n<body>";

$con.= "$sitecfg_sitenav_header<a href=\"$sitecfg_httpbase/reports.php\">Homepage</a> -> eShop Scanning<hr><br/><br/>\n";

$con.= "See <a href=\"$sitecfg_httpbase/eshop/versionlist_data/ctr/\">here</a> for 3DS eShop <a href=\"http://3dbrew.org/wiki/Home_Menu#VersionList\">VersionList</a> scanning. See <a href=\"verlist_parser.php\">here</a> for VersionList parsing.<br/><br/>\n";

$con.= "</body></html>";

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");

echo $con;

?>
