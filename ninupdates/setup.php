<?php

require_once(dirname(__FILE__) . "/db.php");

// Run this to setup the database. This is used automatically from ninsoap.php.
// This does not handle altering existing tables with adjustments to the tables.

function ninupdates_setup()
{
	global $mysqldb;

	dbconnection_start();

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_consoles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `system` varchar(8) DEFAULT NULL,
  `sysname` varchar(16) DEFAULT NULL,
  `deviceid` varchar(32) DEFAULT NULL,
  `clientcertfn` varchar(32) DEFAULT NULL,
  `clientprivfn` varchar(32) DEFAULT NULL,
  `regions` varchar(16) DEFAULT NULL,
  `nushttpsurl` varchar(50) DEFAULT NULL,
  `platformid` int DEFAULT NULL,
  `subplatformid` int DEFAULT NULL,
  `enabled` int DEFAULT NULL,
  `wikicfgid` int DEFAULT NULL,
  `lastreqstatus` varchar(256) DEFAULT NULL,
  `useragent_fw` varchar(32) DEFAULT NULL,
  `eid` varchar(8) DEFAULT NULL,
  `generation` int DEFAULT '0',
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_management` (
  `id` int NOT NULL AUTO_INCREMENT,
  `maintenanceflag` int DEFAULT NULL,
  `lastscan` varchar(128) DEFAULT NULL,
  `lastreqstatus` varchar(256) DEFAULT NULL,
  `prevreqstatus` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_officialchangelog_pages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regionid` int DEFAULT NULL,
  `url` varchar(256) DEFAULT NULL,
  `systemid` int DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_officialchangelogs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pageid` int DEFAULT NULL,
  `reportid` int DEFAULT NULL,
  `ninsite_html` varchar(10240) DEFAULT NULL,
  `display_html` varchar(10240) DEFAULT NULL,
  `wiki_text` varchar(10240) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_regions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `regioncode` varchar(1) DEFAULT NULL,
  `regionid` varchar(3) DEFAULT NULL,
  `countrycode` varchar(2) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_reports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reportdate` varchar(32) DEFAULT NULL,
  `curdate` datetime DEFAULT NULL,
  `systemid` int DEFAULT NULL,
  `log` varchar(8) DEFAULT NULL,
  `regions` varchar(32) DEFAULT NULL,
  `updateversion` varchar(32) DEFAULT NULL,
  `reportdaterfc` varchar(40) DEFAULT NULL,
  `initialscan` int DEFAULT NULL,
  `updatever_autoset` int DEFAULT NULL,
  `wikibot_runfinished` int DEFAULT NULL,
  `wikipage_exists` int DEFAULT NULL,
  `postproc_runfinished` int DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_systitlehashes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reportid` int DEFAULT NULL,
  `region` varchar(4) DEFAULT NULL,
  `titlehash` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_titleids` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titleid` varchar(16) DEFAULT NULL,
  `description` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_titles` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tid` int DEFAULT NULL,
  `version` int DEFAULT NULL,
  `fssize` int DEFAULT NULL,
  `tmdsize` int DEFAULT NULL,
  `tiksize` int DEFAULT NULL,
  `systemid` int DEFAULT NULL,
  `region` varchar(4) DEFAULT NULL,
  `curdate` datetime DEFAULT NULL,
  `reportid` int DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	$query = "CREATE TABLE IF NOT EXISTS `ninupdates_wikiconfig` (
  `id` int NOT NULL AUTO_INCREMENT,
  `systemid` int DEFAULT NULL,
  `wikibot_enabled` int DEFAULT NULL,
  `serverbaseurl` varchar(64) DEFAULT NULL,
  `apiprefixuri` varchar(16) DEFAULT NULL,
  `news_pagetitle` varchar(32) DEFAULT NULL,
  `newsarchive_pagetitle` varchar(32) DEFAULT NULL,
  `homemenu_pagetitle` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`)
)";

	$result=mysqli_query($mysqldb, $query);

	dbconnection_end();
}

if($_SERVER['SCRIPT_NAME'] === "setup.php")
{
	ninupdates_setup();
	echo "Setup finished.\n";
}

?>
