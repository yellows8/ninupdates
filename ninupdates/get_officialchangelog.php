<?php

include_once("config.php");
include_once("logs.php");
include_once("db.php");

function getofficalchangelog_writelog($str, $type, $reportdate)
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

	fprintf($f, "%s reportdate=%s: %s\n", date("m-d-y_h-i-s"), $reportdate, $str);
	fclose($f);

	return 0;
}

function init_curl_getchangelog()
{
	global $curl_handle_getchangelog, $sitecfg_workdir, $error_FH_getchangelog;

	$error_FH_getchangelog = fopen("$sitecfg_workdir/debuglogs/getsysupdatever_curlerror.log","w");
	$curl_handle_getchangelog = curl_init();
}

function close_curl_getchangelog()
{
	global $curl_handle_getchangelog, $error_FH_getchangelog;

	curl_close($curl_handle_getchangelog);
	fclose($error_FH_getchangelog);
}

function send_httprequest_getchangelog($url)
{
	global $httpstat_getchangelog, $sitecfg_workdir, $curl_handle_getchangelog, $error_FH_getchangelog;

	curl_setopt($curl_handle_getchangelog, CURLOPT_VERBOSE, true);
	curl_setopt ($curl_handle_getchangelog, CURLOPT_STDERR, $error_FH_getchangelog);

	curl_setopt($curl_handle_getchangelog, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle_getchangelog, CURLOPT_URL, $url);

	$buf = curl_exec($curl_handle_getchangelog);

	$errorstr = "";

	$httpstat_getchangelog = curl_getinfo($curl_handle_getchangelog, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$errorstr = "HTTP request failed: " . curl_error ($curl_handle);
		$httpstat_getchangelog = "0";
	} else if($httpstat_getchangelog!="200")$errorstr = "HTTP error $httpstat_getchangelog: " . curl_error ($curl_handle_getchangelog);

	if($errorstr!="")$buf = $errorstr;

	return $buf;
}

function getofficalchangelog_writechangelog($reportdate, $pageid, $reportid, $changelog)
{
	getofficalchangelog_writelog("Changelog from site, which will be written into a mysql row, for pageid=$pageid and reportid=$reportid: $changelog", 1, $reportdate);

	$display_html = "";
	$wiki_text = "";

	$display_html = str_replace("<br/>", "", $changelog);
	$display_html = strip_tags($display_html);
	while(strpos($display_html, "\\n\\n")!==FALSE)$display_html = str_replace("\\n\\n", "\\n", $display_html);//Don't allow multiple consecutive newlines.
	$display_html = str_replace("\\n", "<br/>\\n", $display_html);

	if(substr($display_html, 0, 7) == "<br/>\\n")$display_html = substr($display_html, 7, strlen($display_html)-7);//Remove any newline that occurs at the very beginning.

	$wiki_text = "";
	$wiki_text_tmp = strip_tags($display_html);

	$basepos = 0;
	$findpos = 0;

	$findpos = strpos($wiki_text_tmp, "\\n", $basepos);
	while($findpos!==FALSE)
	{
		$wiki_text.= "* ".substr($wiki_text_tmp, $basepos, $findpos-$basepos)."\n";
		$basepos = 2+$findpos;
		$findpos = strpos($wiki_text_tmp, "\\n", $basepos);
	}

	$query="SELECT id FROM ninupdates_officialchangelogs WHERE pageid=$pageid && reportid=$reportid";
	$result=mysql_query($query);

	$numrows=mysql_num_rows($result);
	if($numrows>0)
	{
		$query="UPDATE ninupdates_officialchangelogs SET ninsite_html='".$changelog."', display_html='".$display_html."', wiki_text='".$wiki_text."' WHERE pageid=$pageid && reportid=$reportid";
		$result=mysql_query($query);
	}
	else
	{
		$query = "INSERT INTO ninupdates_officialchangelogs (pageid, reportid, ninsite_html, display_html, wiki_text) VALUES ($pageid, $reportid, '".$changelog."', '".$display_html."', '".$wiki_text."')";
		$result=mysql_query($query);
	}
}

function get_ninsite_changelog($reportdate, $system, $pageurl, $pageid)
{
	global $httpstat_getchangelog;

	$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysql_query($query);

	$numrows=mysql_num_rows($result);
	if($numrows==0)
	{
		echo getofficalchangelog_writelog("The specified system is invalid.", 0, $reportdate);
		return 1;
	}

	$row = mysql_fetch_row($result);
	$systemid = $row[0];

	$query="SELECT updateversion, id FROM ninupdates_reports WHERE reportdate='".$reportdate."' && systemid=$systemid";
	$result=mysql_query($query);

	$numrows=mysql_num_rows($result);
	if($numrows==0)
	{
		getofficalchangelog_writelog("The specified report was not found.", 0, $reportdate);
		return 2;
	}

	$row = mysql_fetch_row($result);
	$updateversion = $row[0];
	$reportid = $row[1];

	if($updateversion != "N/A" && $updateversion != "Initial scan")
	{
		getofficalchangelog_writelog("The updateversion for the specified report is already set.", 0, $reportdate);
		return 3;
	}

	if(ctype_alpha($updateversion[strlen($updateversion)-1]) === TRUE)$updateversion = substr($updateversion, 0, strlen($updateversion)-1);

	init_curl_getchangelog();
	$replydata = send_httprequest_getchangelog($pageurl);
	close_curl_getchangelog();

	if($httpstat_getchangelog!="200")
	{
		getofficalchangelog_writelog("Request to the sysupdate list page failed.", 0, $reportdate);
		return 4;
	}
	else
	{
		echo "Successfully received the sysupdate list page.\n";

		$str = strstr($replydata, ">Version");//The Nintendo .uk and .jp sites are not supported with this.
		if($str===FALSE)
		{
			getofficalchangelog_writelog("Failed to find version string.", 0, $reportdate);
			return 5;
		}
		else
		{
			$len = 	10;
			$changelog = strstr($str, "</h3></p>");//3DS
			if($changelog!==FALSE)
			{
				$posend = strpos($changelog, "<p><h3>");
				if($posend!==FALSE)
				{
					$changelog = substr($changelog, $len, $posend-$len);
				}
				else
				{
					$changelog = FALSE;
				}
			}
			else//WiiU
			{
				$changelog = strstr($str, "</b>");

				if($changelog!==FALSE)
				{
					$len = 5;
					if(strpos($changelog, "<p>Released")!==FALSE)
					{
						$val = strpos($changelog, "</p>");
						if($val===FALSE)
						{
							$changelog = FALSE;
						}
						else
						{
							$len = 5 + $val;
						}
					}

					$posend = FALSE;
					if($changelog!==FALSE)$posend = strpos($changelog, "<b>Version");
					if($posend!==FALSE)
					{
						$changelog = substr($changelog, $len, $posend-$len);
					}
					else
					{
						$changelog = FALSE;
					}
				}
			}
			if($changelog!==FALSE)$changelog = mysql_real_escape_string($changelog);

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

					getofficalchangelog_writelog("Set the updateversion for report=$reportdate and system=$system to: $strdata.", 1, $reportdate);

					if($changelog!==FALSE)
					{
						echo "Writing changelog into mysql...\n";
						getofficalchangelog_writechangelog($reportdate, $pageid, $reportid, $changelog);
					}
					else
					{
						getofficalchangelog_writelog("Failed to extract changelog.", 0, $reportdate);
					}
				}
				else
				{
					getofficalchangelog_writelog("The version from the site is already used with one of the reports.", 0, $reportdate);
					return 6;
				}
			}
			else
			{
				getofficalchangelog_writelog("The report updateversion already matches the one from the site.", 0, $reportdate);
				return 7;
			}
		}
	}

	return 0;
}

?>
