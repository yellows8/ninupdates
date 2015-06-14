<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

function getsysupdatever_writelog($str, $type)
{
	global $sitecfg_workdir;

	if($type==0)echo "Writing the following to the getsysupdatever_error.log: $str\n";

	$path = "";
	if($type==0)$path = "$sitecfg_workdir/debuglogs/getsysupdatever_error.log";
	if($type==1)$path = "$sitecfg_workdir/debuglogs/getsysupdatever_status.log";

	$f = fopen($path, "a");
	if($f===FALSE)
	{
		echo "Failed to open: $path.\n";
		return 1;
	}

	fprintf($f, "%s: %s\n", date("m-d-y_h-i-s"), $str);
	fclose($f);

	return 0;
}

function init_curl_getver()
{
	global $curl_handle_getver, $sitecfg_workdir, $error_FH_getver;

	$error_FH_getver = fopen("$sitecfg_workdir/debuglogs/getsysupdatever_curlerror.log","w");
	$curl_handle_getver = curl_init();
}

function close_curl_getver()
{
	global $curl_handle_getver, $error_FH_getver;

	curl_close($curl_handle_getver);
	fclose($error_FH_getver);
}

function send_httprequest_getver($url)
{
	global $httpstat_getver, $sitecfg_workdir, $curl_handle_getver, $error_FH_getver;

	curl_setopt($curl_handle_getver, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle_getver, CURLOPT_STDERR, $error_FH_getver);

	curl_setopt($curl_handle_getver, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle_getver, CURLOPT_URL, $url);

	$buf = curl_exec($curl_handle_getver);

	$errorstr = "";

	$httpstat_getver = curl_getinfo($curl_handle_getver, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$errorstr = "HTTP request failed: " . curl_error ($curl_handle);
		$httpstat_getver = "0";
	} else if($httpstat_getver!="200")$errorstr = "HTTP error $httpstat_getver: " . curl_error ($curl_handle_getver);

	if($errorstr!="")$buf = $errorstr;

	return $buf;
}

function get_ninsite_latest_sysupdatever($reportdate, $system, $pageurl)
{
	global $httpstat_getver;

	$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);

	$numrows=mysql_num_rows($result);
	if($numrows==0)
	{
		echo getsysupdatever_writelog("The specified system is invalid.", 0);
		return 1;
	}

	$row = mysql_fetch_row($result);
	$systemid = $row[0];

	$query="SELECT updateversion FROM ninupdates_reports WHERE reportdate='".$reportdate."' && systemid=$systemid";
	$result=mysql_query($query);

	$numrows=mysql_num_rows($result);
	if($numrows==0)
	{
		getsysupdatever_writelog("The specified report was not found.", 0);
		return 2;
	}

	$row = mysql_fetch_row($result);
	$updateversion = $row[0];

	if($updateversion != "N/A" && $updateversion != "Initial scan")
	{
		getsysupdatever_writelog("The updateversion for the specified report is already set.", 0);
		return 3;
	}

	if(ctype_alpha($updateversion[strlen($updateversion)-1]) === TRUE)$updateversion = substr($updateversion, 0, strlen($updateversion)-1);

	init_curl_getver();
	$replydata = send_httprequest_getver($pageurl);
	close_curl_getver();

	if($httpstat_getver!="200")
	{
		getsysupdatever_writelog("Request to the sysupdate list page failed.", 0);
		return 4;
	}
	else
	{
		echo "Successfully received the sysupdate list page.\n";

		$str = strstr($replydata, ">Version");//The Nintendo .uk and .jp sites are not supported with this.
		if($str===FALSE)
		{
			getsysupdatever_writelog("Failed to find version string.", 0);
			return 5;
		}
		else
		{
			$strdata = strtok($str, " ");
			$strdata = strtok(" ");

			if(ctype_alpha($strdata[strlen($strdata)-1]) === TRUE)$strdata = substr($strdata, 0, strlen($strdata)-1);
			mysql_real_escape_string($strdata);
			echo "Version from site: $strdata\n";

			if($updateversion != $strdata)
			{
				$query="SELECT id FROM ninupdates_reports WHERE systemid=$systemid && updateversion='".$strdata."'";
				$result=mysql_query($query);

				$numrows=mysql_num_rows($result);

				if($numrows==0)
				{
					echo "Updating report updateversion...\n";
					$query="UPDATE ninupdates_reports SET updateversion='".$strdata."' WHERE reportdate='".$reportdate."' && systemid=$systemid";
					$result=mysql_query($query);

					getsysupdatever_writelog("Set the updateversion for report=$reportdate and system=$system to: $strdata.", 1);
				}
				else
				{
					getsysupdatever_writelog("The version from the site is already used with one of the reports.", 0);
					return 6;
				}
			}
			else
			{
				getsysupdatever_writelog("The report updateversion already matches the one from the site.", 0);
				return 7;
			}
		}
	}

	return 0;
}

?>
