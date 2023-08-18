<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

require_once(dirname(__FILE__) . "/http_pagelogger.php");

if($argc<2)
{
	echo("Usage:\nphp versionlist.php <enable_notification>\n");
	exit(1);
}

process_pagelogger("https://tagaya-ctr.cdn.nintendo.net/tagaya/versionlist", "$sitecfg_workdir/versionlist/ctr", "A new 3DS eShop VersionList was downloaded.", "$sitecfg_httpbase/eshop/", $argv[1]);

?>
