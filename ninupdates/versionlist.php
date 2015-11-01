<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

include_once("http_pagelogger.php");

if($argc<2)
{
	die("Usage:\nphp versionlist.php <enable_notification>\n");
}

process_pagelogger("https://tagaya-ctr.cdn.nintendo.net/tagaya/versionlist", "$sitecfg_workdir/versionlist/ctr", "A new 3DS eShop VersionList was downloaded.", "$sitecfg_httpbase/eshop/", $argv[1]);

?>
