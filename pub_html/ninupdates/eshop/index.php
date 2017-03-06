<?php

include_once("/home/yellows8/ninupdates/config.php");
include_once("/home/yellows8/ninupdates/db.php");
include_once("/home/yellows8/ninupdates/logs.php");
include_once("/home/yellows8/ninupdates/weblogging.php");

$logging_dir = "$sitecfg_workdir/weblogs/eshopindexphp";

dbconnection_start();

db_checkmaintenance(1);

$con = "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\" dir=\"ltr\">\n";

$con .= "<head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\" /><title>eShop Scanning</title></head>\n<body>";

$con.= "$sitecfg_sitenav_header<a href=\"$sitecfg_httpbase/reports.php\">Homepage</a> -> eShop Scanning<hr><br/><br/>\n";

$con.= "See <a href=\"$sitecfg_httpbase/eshop/versionlist_data/ctr/\">here</a> for 3DS eShop <a href=\"http://3dbrew.org/wiki/Home_Menu#VersionList\">VersionList</a> scanning. See <a href=\"verlist_parser.php\">here</a> for VersionList parsing.<br/><br/>\n";

$con.= "</body></html>";

dbconnection_end();

if($sitecfg_logplainhttp200!=0)writeNormalLog("RESULT: 200");

echo $con;

?>
