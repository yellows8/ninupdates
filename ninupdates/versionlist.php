<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

function init_curl_versionlist()
{
	global $curl_handle_versionlist, $sitecfg_workdir, $error_FH_versionlist;

	$error_FH_versionlist = fopen("$sitecfg_workdir/debuglogs/versionlist_curlerror.log","w");
	$curl_handle_versionlist = curl_init();
}

function close_curl_versionlist()
{
	global $curl_handle_versionlist, $error_FH_versionlist;

	curl_close($curl_handle_versionlist);
	fclose($error_FH_versionlist);
}

function send_httprequest_versionlist($url)
{
	global $httpstat_versionlist, $sitecfg_workdir, $curl_handle_versionlist, $error_FH_versionlist, $lastmod_dateid, $lastmod;

	curl_setopt($curl_handle_versionlist, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle_versionlist, CURLOPT_STDERR, $error_FH_versionlist);

	curl_setopt($curl_handle_versionlist, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle_versionlist, CURLOPT_URL, $url);

	curl_setopt($curl_handle_versionlist, CURLOPT_FILETIME, true);
	if(isset($lastdate))curl_setopt($ch, CURLOPT_HTTPHEADER, array("If-Modified-Since: " . gmdate('D, d M Y H:i:s \G\M\T', $lastdate)));

	curl_setopt($curl_handle_versionlist, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_handle_versionlist, CURLOPT_SSL_VERIFYHOST, 0);

	$buf = curl_exec($curl_handle_versionlist);

	$errorstr = "";

	$httpstat_versionlist = curl_getinfo($curl_handle_versionlist, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$errorstr = "HTTP request failed: " . curl_error ($curl_handle_versionlist);
		$httpstat_versionlist = "0";
	} else if($httpstat_versionlist!="200")$errorstr = "HTTP error $httpstat_versionlist: " . curl_error ($curl_handle_versionlist);

	if($errorstr!="")$buf = $errorstr;

	$lastmod = curl_getinfo ($curl_handle_versionlist, CURLINFO_FILETIME);
	echo "lastmod:".date(DATE_RFC822, $lastmod)."\n";
	$lastmod_dateid = date("m-d-y_H-i-s", $lastmod);
	echo "lastmod_dateid: $lastmod_dateid\n";

	return $buf;
}

function download_versionlist($verlist_system)
{
	return send_httprequest_versionlist("https://tagaya-ctr.cdn.nintendo.net/tagaya/versionlist");
}

function process_versionlist($verlist_system)
{
	global $sitecfg_workdir, $httpstat_versionlist, $lastmod_dateid, $lastmod;

	init_curl_versionlist();
	$buf = download_versionlist($verlist_system);
	close_curl_versionlist();

	if($httpstat_versionlist!="200")
	{
		echo "Request for the versionlist with sys=$verlist_system failed: HTTP $httpstat_versionlist.\n";
		return 4;
	}

	$path = "$sitecfg_workdir/versionlist/$verlist_system/$lastmod_dateid";

	if(file_exists($path)===TRUE)return 0;//Already have this versionlist revision.

	echo "This versionlist doesn't exist locally, saving it + sending notification...\n";

	$f = fopen($path, "w");
	fwrite($f, $buf);
	fclose($f);

	$msg = "A new 3DS eShop VersionList was downloaded, converted Last-Modified datetime: " . date(DATE_RFC822, $lastmod) . ". $sitecfg_httpbase/eshop/";

	appendmsg_tofile($msg, "msg3dsdev");

	return 0;
}

process_versionlist("ctr");

?>
