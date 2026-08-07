<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");

function init_curl_pagelogger()
{
	global $curl_handle_pagelogger, $sitecfg_workdir, $error_FH_pagelogger;

	$error_FH_pagelogger = fopen("$sitecfg_workdir/debuglogs/pagelogger_curlerror.log","w");
	$curl_handle_pagelogger = curl_init();
}

function close_curl_pagelogger()
{
	global $curl_handle_pagelogger, $error_FH_pagelogger;

	curl_close($curl_handle_pagelogger);
	fclose($error_FH_pagelogger);
}

function pagelogger_handle_version($buf, $insystem, $system_base)
{
	global $mysqldb;

	$str = $buf;
	if($str!==FALSE) $str = strstr($str, "<h3><a name=\"");
	if($str!==FALSE) $str = strstr($str, "\">");
	if($str!==FALSE) $str = substr($str, 2);

	if($str!==FALSE)
	{
		$strdata = strtok($str, " ");
		$strdata = strtok(" ");
		if(ctype_alpha($strdata[strlen($strdata)-1]) === TRUE)$strdata = substr($strdata, 0, strlen($strdata)-1);
		$strdata = mysqli_real_escape_string($mysqldb, $strdata);
		echo "Version from site: $strdata\n";

		$system = $insystem;
		$query="SELECT id, regions FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
		$result=mysqli_query($mysqldb, $query);

		$numrows=mysqli_num_rows($result);
		if($numrows==0)
		{
			echo "The specified system is invalid, with filterjs=2.\n";
			return;
		}

		$row = mysqli_fetch_row($result);
		$systemid = $row[0];
		$regions = $row[1];

		$sysupdate_regions = "";
		for($i=0; $i<strlen($regions); $i++)
		{
			$region = substr($regions, $i, 1);
			if($sysupdate_regions!="")$sysupdate_regions.= ",";
			$sysupdate_regions.= $region;
		}

		$system = $system_base;
		$query="SELECT id FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
		$result=mysqli_query($mysqldb, $query);

		$numrows=mysqli_num_rows($result);
		if($numrows==0)
		{
			echo "The specified base system is invalid, with filterjs=2.\n";
			return;
		}

		$row = mysqli_fetch_row($result);
		$systemid_base = $row[0];

		$query="SELECT reportdate, curdate, reportdaterfc FROM ninupdates_reports WHERE ninupdates_reports.updateversion='".$strdata."' && ninupdates_reports.systemid=$systemid_base";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		if($numrows>0) // If a matching report with the base system is found (which should be the case normally), use timestamps from that. Otherwise generate the timestamps.
		{
			$row = mysqli_fetch_row($result);
			$sysupdate_timestamp = $row[0];
			$dbcurdate = $row[1];
			$soap_timestamp = $row[2];

			echo "Using timestamps from existing report: reportdate = " . $sysupdate_timestamp . "\n";
		}
		else
		{
			$curtime = time();
			$soap_timestamp = gmdate(DATE_RFC822, $curtime);
			$curdate = gmdate("Y-m-d_H-i-s", $curtime);
			$sysupdate_timestamp = $curdate;

			$query = "SELECT FROM_UNIXTIME($curtime)";
			$result=mysqli_query($mysqldb, $query);
			$row = mysqli_fetch_row($result);
			$dbcurdate = $row[0];

			echo "Using newly generated timestamps: reportdate = " . $sysupdate_timestamp . "\n";
		}

		$query="SELECT updateversion FROM ninupdates_reports WHERE (ninupdates_reports.updateversion='".$strdata."' || ninupdates_reports.updateversion='N/A') && ninupdates_reports.systemid=$systemid";
		$result=mysqli_query($mysqldb, $query);

		$numrows=mysqli_num_rows($result);
		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$updateversion = $row[0];

			echo "Found a report with updateversion '".$updateversion."', skipping report creation.\n";
		}
		else
		{
			$initialscan = 0;
			$query = "INSERT INTO ninupdates_reports (reportdate, curdate, systemid, log, regions, updateversion, reportdaterfc, initialscan, updatever_autoset, wikibot_runfinished, postproc_runfinished) VALUES ('".$sysupdate_timestamp."','".$dbcurdate."',$systemid,'report','".$sysupdate_regions."','".$strdata."','".$soap_timestamp."',$initialscan,0,0,0)";
			$result=mysqli_query($mysqldb, $query);
			$reportid = mysqli_insert_id($mysqldb);
			echo "Report created.\n";
		}
	}
}

function send_httprequest_pagelogger($url, $filterjs)
{
	global $httpstat_pagelogger, $sitecfg_workdir, $curl_handle_pagelogger, $error_FH_pagelogger, $lastmod_dateid, $lastmod;

	curl_setopt($curl_handle_pagelogger, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle_pagelogger, CURLOPT_STDERR, $error_FH_pagelogger);

	curl_setopt($curl_handle_pagelogger, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle_pagelogger, CURLOPT_URL, $url);

	curl_setopt($curl_handle_pagelogger, CURLOPT_FILETIME, true);
	if(isset($lastdate))curl_setopt($ch, CURLOPT_HTTPHEADER, array("If-Modified-Since: " . gmdate('D, d M Y H:i:s \G\M\T', $lastdate)));

	curl_setopt($curl_handle_pagelogger, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($curl_handle_pagelogger, CURLOPT_SSL_VERIFYHOST, 0);

	$buf = curl_exec($curl_handle_pagelogger);

	$errorstr = "";

	$httpstat_pagelogger = curl_getinfo($curl_handle_pagelogger, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$errorstr = "HTTP request failed: " . curl_error ($curl_handle_pagelogger);
		$httpstat_pagelogger = "0";
	} else if($httpstat_pagelogger!="200")$errorstr = "HTTP error $httpstat_pagelogger: " . curl_error ($curl_handle_pagelogger);

	if($errorstr!="")$buf = $errorstr;

	$lastmod = curl_getinfo ($curl_handle_pagelogger, CURLINFO_FILETIME);
	if($lastmod!==-1)
	{
		echo "lastmod:".gmdate(DATE_RFC822, $lastmod)."\n";
		$lastmod_dateid = gmdate("Y-m-d_H-i-s", $lastmod);
		echo "lastmod_dateid: $lastmod_dateid\n";
	}
	else if($httpstat_pagelogger=="200")
	{
		if($filterjs=="1" || $filterjs=="2")
		{
			while(1)
			{
				$pos = strpos($buf, "<script");
				$posend = strpos($buf, "</script>");
				if($pos!==FALSE && $posend!==FALSE)
				{
					$buf = substr($buf, 0, $pos) . substr($buf, $posend+9);
				}
				else
				{
					break;
				}
			}

                        $pos = strpos($buf, '<section class="update-versions">');
                        if($pos!==FALSE) $posend = strpos($buf,  "</section>", $pos);
                        if($pos!==FALSE && $posend!==FALSE)
                        {
                                $buf = substr($buf, $pos, $posend+10-$pos);
                        }
		}

		$lastmod_dateid = hash('sha256', $buf);
		echo "Last-Modified not available, using hash: $lastmod_dateid\n";
	}

	return $buf;
}

function sendnotif_pagelogger($msg, $enable_notification, $msgtarget)
{
	if($enable_notification>=1)
	{
		$args = [$msg, "--social"];
		if($enable_notification & (1<<2)) // Previously val1. Use bit2 to avoid collisions with old values.
		{
			$args[] = "--irc";
			$args[] = "--irctarget=$msgtarget";
		}
		if($enable_notification & (1<<1)) $args[] = "--webhook"; // Previously val3.
		if($enable_notification & (1<<3)) $args[] = "--fedivisibility=unlisted";
		send_notif($args);
	}
}

function process_pagelogger($url, $datadir, $msgprefix, $msgurl, $enable_notification, $msgtarget = "msg3dsdev", $filterjs = "0")
{
	global $httpstat_pagelogger, $lastmod_dateid, $lastmod;

	$enable_notification = intval($enable_notification);

	if(!is_dir($datadir)) mkdir($datadir, 0775);

	$curtime = time();
	init_curl_pagelogger();
	$buf = send_httprequest_pagelogger($url, $filterjs);
	close_curl_pagelogger();
	if($httpstat_pagelogger == "0")return 5;//Return immediately when the HTTP request failed.

	$httpstat_file = "$datadir/httpstat";

	$httpstat_prev = FALSE;
	if(file_exists($httpstat_file)===TRUE)$httpstat_prev = file_get_contents($httpstat_file);

	if($httpstat_pagelogger == 200 || $httpstat_pagelogger == 403 || $httpstat_pagelogger == 404)//Only keep track of status-code changes between these (otherwise this might catch server-side issues).
	{
		$f = fopen($httpstat_file, "w");
		fwrite($f, $httpstat_pagelogger);
		fclose($f);

		if($httpstat_prev!==FALSE && $httpstat_prev!=$httpstat_pagelogger)
		{
			$msg = "The HTTP response status-code changed from $httpstat_prev to $httpstat_pagelogger, with the following URL: $url";
			echo "$msg\n";

			sendnotif_pagelogger($msg, $enable_notification, $msgtarget);
		}
	}

	if($httpstat_pagelogger!="200")
	{
		echo "Request for the pagelogger with url \"$url\" failed: HTTP $httpstat_pagelogger.\n";
		return 4;
	}

	$path = "$datadir/$lastmod_dateid";

	if(file_exists($path)===TRUE)return 0;//Already have this page revision.

	echo "This revision doesn't exist locally, saving it + sending notification...\n";

	$f = fopen($path, "w");
	fwrite($f, $buf);
	fclose($f);

	if($enable_notification>=1)
	{
		if($lastmod!==-1)
		{
			$msg_text = "Last-Modified: " . gmdate(DATE_RFC822, $lastmod);
		}
		else
		{
			$msg_text = "Request timestamp: " . gmdate(DATE_RFC822, $curtime);
		}
		$msg = "$msgprefix " . $msg_text . ". $msgurl";

		sendnotif_pagelogger($msg, $enable_notification, $msgtarget);
	}
	else
	{
		echo "Notification sending is disabled.\n";
	}

	if($filterjs=="2") // Get the system-version and create a report, if needed.
	{
		pagelogger_handle_version($buf, "bee", "hac");
	}

	return 0;
}

?>
