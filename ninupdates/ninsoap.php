<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");
require_once(dirname(__FILE__) . "/setup.php");
require_once(dirname(__FILE__) . "/get_officialchangelog.php");

$curtime_override = 0;

if($argc>=2)
{
	//Hour is 24h.
	$tmp_month = 0;
	$tmp_day = 0;
	$tmp_year = 0;
	$tmp_hour = 0;
	$tmp_min = 0;
	$tmp_sec = 0;
	if(sscanf($argv[1], "%04u-%02u-%02u_%02u-%02u-%02u", $tmp_year, $tmp_month, $tmp_day, $tmp_hour, $tmp_min, $tmp_sec) == 6)
	{
		echo "Using the specified curtime_override.\n";
		$curtime_override = gmmktime($tmp_hour, $tmp_min, $tmp_sec, $tmp_month, $tmp_day, $tmp_year);
	}
	else
	{
		echo "The specified curtime_override is invalid, ignoring it.\n";
	}

	if($argc>=4)
	{
		$overridden_initial_titleid = $argv[2];
		$overridden_initial_titleversion = $argv[3];
	}
}

do_systems_soap();

function do_systems_soap()
{
	global $mysqldb, $sitecfg_workdir, $sitecfg_httpbase, $reqstatus_notif;

	dbconnection_start();
	ninupdates_setup();

	$query="SELECT COUNT(*) FROM ninupdates_management";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);
		$count = $row[0];

		if($count==0)
		{
			$query = "INSERT INTO ninupdates_management (maintenanceflag, lastscan) VALUES (0,'')";
			$result=mysqli_query($mysqldb, $query);
		}
	}

	if(!db_checkmaintenance(0))
	{
		init_curl();

		$query="SELECT ninupdates_consoles.system FROM ninupdates_consoles WHERE enabled!=0 OR enabled IS NULL";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		$reqstatus_notif="";

		for($i=0; $i<$numrows; $i++)
		{
			$row = mysqli_fetch_row($result);
			dosystem($row[0]);
		}

		$query="UPDATE ninupdates_management SET lastscan='" . gmdate(DATE_RFC822, time()) . "'";
		$result=mysqli_query($mysqldb, $query);

		close_curl();

		if(strlen($reqstatus_notif)>0)
		{
			echo "Sending reqstatus notif...\n";

			$reqstatus_timestamp = gmdate("Y-m-d_H-i-s");

			$dirpath = "$sitecfg_workdir/reqstatus";
			if(!is_dir($dirpath)) mkdir($dirpath, 0750);

			$ftmp = fopen("$dirpath/$reqstatus_timestamp", "w");
			fwrite($ftmp, $reqstatus_notif);
			fclose($ftmp);

			$msg = "Last request status changed for system(s): $sitecfg_httpbase/reqstatus/".$reqstatus_timestamp . " https://www.nintendo.co.jp/netinfo/en_US/index.html";

			send_notif([$msg, "--webhook", "--social", "--fedivisibility=unlisted"]);
			$reqstatus_notif="";
		}

		$query="SELECT COUNT(*) FROM ninupdates_reports, ninupdates_consoles, ninupdates_officialchangelog_pages WHERE ninupdates_reports.updatever_autoset=0 AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$count = $row[0];

			if($count>0)
			{
				echo "Starting a get_officialchangelog task for processing $count report(s)...\n";

				$get_officialchangelog_timestamp = gmdate("Y-m-d_H-i-s");

				$dirpath = "$sitecfg_workdir/get_officialchangelog_scheduled_out";
				if(!is_dir($dirpath)) mkdir($dirpath, 0700);

				system("php ".escapeshellarg("$sitecfg_workdir/get_officialchangelog_cli.php")." > ".escapeshellarg("$dirpath/$get_officialchangelog_timestamp")." 2>&1 &");
			}
		}

		$query="SELECT COUNT(*) FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updatever_autoset=1 && ninupdates_reports.wikibot_runfinished=0 AND ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$count = $row[0];

			if($count>0)
			{
				echo "Starting a wikibot task for processing $count report(s)...\n";

				$wikibot_timestamp = gmdate("Y-m-d_H-i-s");

				$dirpath = "$sitecfg_workdir/wikibot_out";
				if(!is_dir($dirpath)) mkdir($dirpath, 0700);

				system("php ".escapeshellarg("$sitecfg_workdir/wikibot.php")." scheduled > ".escapeshellarg("$dirpath/$wikibot_timestamp")." 2>&1 &");
			}
		}

		// In case the changelog still isn't available 20mins after the report curdate, run wikibot without waiting on changelog. This assumes the updatever was updated via postproc by now, hence generation!=0.
		$query="SELECT COUNT(*) FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updatever_autoset=0 && ninupdates_reports.wikibot_runfinished=0 AND ninupdates_reports.curdate < FROM_UNIXTIME(".(time() - 20*60).") AND ninupdates_consoles.generation!=0 AND ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$count = $row[0];

			if($count>0)
			{
				echo "Starting a wikibot updatever_autoset0 task for processing $count report(s)...\n";

				$wikibot_timestamp = gmdate("Y-m-d_H-i-s");

				$dirpath = "$sitecfg_workdir/wikibot_out_updatever_autoset0";
				if(!is_dir($dirpath)) mkdir($dirpath, 0700);

				system("php ".escapeshellarg("$sitecfg_workdir/wikibot.php")." scheduled_updatever_autoset0 > ".escapeshellarg("$dirpath/$wikibot_timestamp")." 2>&1 &");
			}
		}

		$query="SELECT ninupdates_reports.reportdate, ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.postproc_runfinished=0 AND ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows>0)
		{
			echo "Starting post-processing tasks for processing $numrows report(s)...\n";

			$postproc_timestamp = gmdate("Y-m-d_H-i-s");

			$dirpath = "$sitecfg_workdir/postproc_out";
			if(!is_dir($dirpath)) mkdir($dirpath, 0700);

			for($i=0; $i<$numrows; $i++)
			{
				$row = mysqli_fetch_row($result);
				$reportdate = $row[0];
				$sys = $row[1];

				$maincmd_str = "php $sitecfg_workdir/postproc.php $reportdate $sys";

				$cmdout = system("ps aux | grep -c \"$maincmd_str\"");
				$proc_running = 0;
				if(is_numeric($cmdout))
				{
					if($cmdout > 2)$proc_running = 1;
				}

				if($proc_running==0)
				{
					echo "Starting postproc task for $reportdate-$sys...\n";
					system("$maincmd_str > $dirpath/$postproc_timestamp 2>&1 &");
				}
				else
				{
					echo "The postproc task for $reportdate-$sys wasn't finished but the process is still running, skipping process creation for it.\n";
				}
			}
		}

		dbconnection_end();
	}
}

function dosystem($console)
{
	global $mysqldb, $region, $system, $sitecfg_irc_msg_dirpath, $sitecfg_irc_msgtarget, $sitecfg_irc_msgtargets, $sitecfg_emailhost, $sitecfg_target_email, $sitecfg_httpbase, $sitecfg_workdir, $sitecfg_notif_fedi_append, $sitecfg_notif_fedi_append_system, $sysupdate_available, $soap_timestamp, $dbcurdate, $sysupdate_regions, $sysupdate_timestamp, $sysupdate_systitlehashes, $curtime_override, $reqstatus_notif;

	$system = $console;
	$msgme_message = "";
	$html_message = "";
	$sysupdate_available = 0;
	$sysupdate_timestamp = "";
	$soap_timestamp = "";
	$sysupdate_regions = "";
	$dbcurdate = "";
	$sysupdate_systitlehashes = array();

	echo "System $system\n";

	$query="SELECT ninupdates_consoles.regions FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);
	$regions = $row[0];

	$query="SELECT ninupdates_consoles.lastreqstatus FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	$lastreqstatus = "";
	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);
		$lastreqstatus = $row[0];
	}

	// When the last report is <1h ago and the report regions don't match the system regions, reuse the report in case the remaining regions are detected.
	$reuse_report = False;
	if($curtime_override===0)
	{
		$query="SELECT ninupdates_reports.reportdaterfc, ninupdates_reports.regions, ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' AND ninupdates_consoles.system='".$system."' AND ninupdates_reports.systemid=ninupdates_consoles.id ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		if($numrows>0)
		{
			$row = mysqli_fetch_row($result);
			$last_report_timestamp = date_timestamp_get(date_create_from_format(DateTimeInterface::RFC822, $row[0]));
			$last_report_regions = $row[1];
			$last_reportdate = $row[2];
			$last_report_regions_cmp = str_replace(",", "", $last_report_regions);
			$check_time = time();
			if(strlen($last_report_regions_cmp) !== strlen($regions) && $check_time > $last_report_timestamp && $check_time - $last_report_timestamp < 60*60)
			{
				echo "Regions mismatch compared against system/last-report. Reusing the existing report, last_reportdate = $last_reportdate.\n";
				$sysupdate_timestamp = $last_reportdate;
				$reuse_report = True;
			}
		}
	}

	for($i=0; $i<strlen($regions); $i++)
	{
		main(substr($regions, $i, 1));
	}

	$query="SELECT ninupdates_consoles.lastreqstatus FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);
		$lastreqstatus_new = $row[0];

		if($lastreqstatus==="" || $lastreqstatus===NULL)$lastreqstatus = "OK";
		if($lastreqstatus_new==="" || $lastreqstatus_new===NULL)$lastreqstatus_new = "OK";

		if($lastreqstatus !== $lastreqstatus_new)
		{
			echo "Req status changed since last scan.\n";
			$msg = "Last " . getsystem_sysname($system) . " request status changed. Previous: \"$lastreqstatus\". Current: \"$lastreqstatus_new\".";
			echo "msg: $msg\n";

			// Batch these notifs for sending later.
			if(strlen($reqstatus_notif)>0) $reqstatus_notif.= "\n";
			$reqstatus_notif.= $msg;
		}
	}

	if($sysupdate_available==0)
	{
		echo "System $system: No updated titles available.\n";
	}
	else
	{
		$msgme_message = "$sitecfg_httpbase/reports.php?date=".$sysupdate_timestamp."&sys=".$system;
		$email_message = "$msgme_message";
		
		$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_consoles.system='".$system."' AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_reports.log='report'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		$initialscan = 0;
		if($numrows==0)$initialscan = 1;

		$updateversion = "N/A";
		if($initialscan)$updateversion = "Initial_scan";

		$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$sysupdate_timestamp."' AND ninupdates_consoles.system='".$system."' AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_reports.log='report'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		$report_exists = 0;
		$old_sysupdate_regions = "";
		$new_sysupdate_regions = "";
		if($numrows==0)
		{
			$query="SELECT ninupdates_consoles.id FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
			$result=mysqli_query($mysqldb, $query);
			$row = mysqli_fetch_row($result);
			$systemid = $row[0];

			$query = "INSERT INTO ninupdates_reports (reportdate, curdate, systemid, log, regions, updateversion, reportdaterfc, initialscan, updatever_autoset, wikibot_runfinished, postproc_runfinished) VALUES ('".$sysupdate_timestamp."','".$dbcurdate."',$systemid,'report','".$sysupdate_regions."','".$updateversion."','".$soap_timestamp."',$initialscan,0,0,0)";
			$result=mysqli_query($mysqldb, $query);
			$reportid = mysqli_insert_id($mysqldb);
		}
		else
		{
			//this will only happen when the report row already exists.
			$report_exists = 1;
			$query = "SELECT ninupdates_reports.id, ninupdates_reports.regions FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.reportdate='".$sysupdate_timestamp."' AND ninupdates_consoles.system='".$system."' AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_reports.log='report'";
			$result=mysqli_query($mysqldb, $query);
			$row = mysqli_fetch_row($result);
			$reportid = $row[0];
			$old_sysupdate_regions = $row[1];
			$new_sysupdate_regions = $old_sysupdate_regions;
		}

		$region = strtok($sysupdate_regions, ",");
		while($region!==FALSE)
		{
			$query="SELECT COUNT(*) FROM ninupdates_systitlehashes WHERE ninupdates_systitlehashes.reportid='".$reportid."' AND ninupdates_systitlehashes.region='".$region."'";
			$result=mysqli_query($mysqldb, $query);
			$numrows=mysqli_num_rows($result);
			$count=0;

			if($numrows>0)
			{
				$row = mysqli_fetch_row($result);
				$count = $row[0];
			}

			if($count==0)
			{
				$query = "INSERT INTO ninupdates_systitlehashes (reportid, region, titlehash) VALUES ('".$reportid."','".$region."','".$sysupdate_systitlehashes[$region]."')";
				$result=mysqli_query($mysqldb, $query);
			}

			if($report_exists == 1)
			{
				if(strstr($old_sysupdate_regions, $region)===FALSE)
				{
					if(strlen($new_sysupdate_regions)>0) $new_sysupdate_regions.= ",";
					$new_sysupdate_regions.= $region;
				}
			}

			$region = strtok(",");
		}

		if($report_exists == 1)
		{
			$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.regions='".$new_sysupdate_regions."', ninupdates_reports.updatever_autoset=0 WHERE ninupdates_reports.reportdate='".$sysupdate_timestamp."' AND ninupdates_consoles.system='".$system."' AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_reports.log='report'";
			$result=mysqli_query($mysqldb, $query);
		}

		$query="UPDATE ninupdates_titles SET reportid=$reportid WHERE curdate='".$dbcurdate."' AND reportid=0";
		$result=mysqli_query($mysqldb, $query);

		if($initialscan==0)echo "System $system: System update available for regions $sysupdate_regions.\n";
		if($initialscan)echo "System $system: Initial scan successful for regions $sysupdate_regions.\n";

		echo "\nSending notifications...\n";

		$notif_msg = "Sysupdate detected for " . getsystem_sysname($system) . ": $msgme_message";

		if($reuse_report === True)
		{
			$notif_msg = "Sysupdate detected for " . getsystem_sysname($system) . " for an existing report with additional region(s): $msgme_message";
		}

		$args = [$notif_msg, "--social", "--webhook"];
		if($reuse_report === True)
		{
			$args[] = "--fedivisibility=unlisted";
		}

		if($sitecfg_irc_msg_dirpath!="")
		{
			$targets = array();
			if($sitecfg_irc_msgtarget!="") $targets[] = $sitecfg_irc_msgtarget;
			if(isset($sitecfg_irc_msgtargets))
			{
				if(isset($sitecfg_irc_msgtargets["$system"]))
				{
					$targets[] = $sitecfg_irc_msgtargets["$system"];
				}
			}
			if(count($targets)>0)
			{
				$args[] = "--irc";
				$args[] = "--irctarget";
				foreach($targets as $target)
				{
					if(strlen($target)>0)
					{
						$args[] = $target;
					}
				}
			}
		}
		if($initialscan==0 && $reuse_report===False)
		{
			$notif_fedi = "";
			if(isset($sitecfg_notif_fedi_append)) $notif_fedi.= " ".$sitecfg_notif_fedi_append;
			if(isset($sitecfg_notif_fedi_append_system))
			{
				if(isset($sitecfg_notif_fedi_append_system["$system"]))
				{
					$notif_fedi.= " ".$sitecfg_notif_fedi_append_system["$system"];
				}
			}
			if($notif_fedi!="")
			{
				$args[] = "--fedi";
				$args[] = $notif_msg.$notif_fedi;
			}
		}
		send_notif($args);
		if($sitecfg_target_email!="" && $sitecfg_emailhost!="")
		{
			echo "Sending email...\n";
			if(!mail($sitecfg_target_email, "$system sysupdates", $email_message, "From: ninsoap@$sitecfg_emailhost"))echo "Failed to send mail.\n";
		}

		/*echo "Writing to the lastupdates_csvurls file...\n";
		$msg = "$sitecfg_httpbase/titlelist.php?date=".$sysupdate_timestamp."&sys=".$system."&csv=1";
		$tmp_cmd = "echo '" . $msg . "' >> $sitecfg_workdir/lastupdates_csvurls";
		system($tmp_cmd);*/
	}
}

function initialize($generation)
{
	global $mysqldb, $hdrs, $soapreq, $fp, $system, $region, $sitecfg_workdir, $soapreq_data, $httpreq_useragent, $console_deviceid, $sitecfg_consoles_deviceid;
	
	error_reporting(E_ALL);

	$regionid = "";
	$countrycode = "";

	$query="SELECT deviceid, platformid, subplatformid, useragent_fw, eid FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows)
	{
		$row = mysqli_fetch_row($result);
		$deviceid = $row[0];
		$platformid = $row[1];
		$subplatformid = $row[2];

		$useragent_fw = $row[3];
		$eid = $row[4];

		if(isset($sitecfg_consoles_deviceid))
		{
			if(isset($sitecfg_consoles_deviceid["$system"]["$region"])) $deviceid = $sitecfg_consoles_deviceid["$system"]["$region"];
		}

		$console_deviceid = $deviceid;

		if($generation==0)
		{
			$platformid = ($platformid << 32);
			if($subplatformid != NULL && $subplatformid != "")$platformid |= ($subplatformid << 31);

			if($platformid != NULL && $platformid != "")
			{
				$deviceid = $platformid | rand(0, 0x7fffffff);
			}
		}
	}
	else
	{
		echo("Row doesn't exist in the db for system $system.\n");
		exit(1);
	}

	$query="SELECT regionid, countrycode FROM ninupdates_regions WHERE ninupdates_regions.regioncode='".$region."'";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);

	if($numrows)
	{
		$regionid = $row[0];
		$countrycode = $row[1];
	}
	else
	{
		echo("Row doesn't exist in the db for region $region.\n");
		exit(2);
	}

	if($generation==0)
	{
		$httpreq_useragent = "ds libnup/2.0";
	}
	else
	{
		if($useragent_fw==="" || $useragent_fw===NULL)
		{
			echo("useragent_fw field isn't set for system $system.\n");
			exit(3);
		}
		if($eid==="" || $eid===NULL)
		{
			echo("eid field isn't set for system $system.\n");
			exit(4);
		}

		$httpreq_useragent = "NintendoSDK Firmware/" . $useragent_fw . " (platform:NX; did:" . $deviceid . "; eid:" . $eid . ")";
	}

	if($generation==0)
	{
		$soapreq_data = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
  <soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\"
                    xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\"
                    xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\">
    <soapenv:Body>
      <GetSystemUpdateRequest xmlns=\"urn:nus.wsapi.broadon.com\">
        <Version>1.0</Version>
        <MessageId>1</MessageId>
        <DeviceId>$deviceid</DeviceId>
        <RegionId>$regionid</RegionId>
        <CountryCode>$countrycode</CountryCode>
        <Attribute>2</Attribute>
      </GetSystemUpdateRequest>
    </soapenv:Body>
  </soapenv:Envelope>";
	}

	if($generation==0)
	{
		$hdrs = array('SOAPAction: "urn:nus.wsapi.broadon.com/GetSystemUpdate"', 'Content-Type: application/xml', 'Content-Size: '.strlen($soapreq_data), 'Connection: Keep-Alive', 'Keep-Alive: 30');
	}
	else
	{
		$hdrs = array('Accept:application/json');
	}

	init_titlelistarray();
}

function init_curl()
{
	global $curl_handle, $sitecfg_workdir, $error_FH;

	$dirpath = "$sitecfg_workdir/debuglogs";
	if(!is_dir($dirpath)) mkdir($dirpath, 0770);

	$path = "$dirpath/error.log";
	$error_FH = fopen($path, "w"); // truncate
	fclose($error_FH);
	$error_FH = fopen($path, "a"); // Use append-mode so that each curl request logs properly (otherwise only the last request is logged).
	$curl_handle = curl_init();
}

function close_curl()
{
	global $curl_handle, $error_FH;

	curl_close($curl_handle);
	fclose($error_FH);
}

function send_httprequest($url, $generation)
{
	global $mysqldb, $hdrs, $soapreq, $httpstat, $sitecfg_workdir, $soapreq_data, $httpreq_useragent, $curl_handle, $system, $region, $error_FH;

	$query="SELECT clientcertfn, clientprivfn FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);
	$clientcertfn = $row[0];
	$clientprivfn = $row[1];

	curl_setopt($curl_handle, CURLOPT_VERBOSE, true);
	curl_setopt($curl_handle, CURLOPT_STDERR, $error_FH);

	curl_setopt($curl_handle, CURLOPT_USERAGENT, $httpreq_useragent);

	curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);

	curl_setopt($curl_handle, CURLOPT_HTTPHEADER, $hdrs);

	if($generation==0)
	{
		curl_setopt($curl_handle, CURLOPT_POST, 1);
		curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $soapreq_data);
	}
	else
	{
		curl_setopt($curl_handle, CURLOPT_POST, 0);
	}

	curl_setopt($curl_handle, CURLOPT_URL, $url);

	if(strstr($url, "https") && $clientcertfn!="" && $clientprivfn!="")
	{
		$certbasepath = "$sitecfg_workdir/sslcerts";
		$certbasepath_region = "$certbasepath/$region";
		if(file_exists("$certbasepath_region/$clientcertfn")===TRUE && file_exists("$certbasepath_region/$clientprivfn")===TRUE) $certbasepath = $certbasepath_region;

		curl_setopt($curl_handle, CURLOPT_SSLCERTTYPE, "PEM");
		curl_setopt($curl_handle, CURLOPT_SSLCERT, "$certbasepath/$clientcertfn");
		curl_setopt($curl_handle, CURLOPT_SSLKEY, "$certbasepath/$clientprivfn");

		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, 0);
	}

	$buf = curl_exec($curl_handle);

	$errorstr = "";

	$httpstat = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
	if($buf===FALSE)
	{
		$errorstr = "HTTP request failed: " . curl_error ($curl_handle);
		$httpstat = "0";
	} else if($httpstat!="200")$errorstr = "HTTP error $httpstat: " . curl_error ($curl_handle);

	if($errorstr!="")$buf = $errorstr;

	$query="UPDATE ninupdates_consoles SET lastreqstatus='" . mysqli_real_escape_string($mysqldb, $errorstr) . "' WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);

	fflush($error_FH);

	return $buf;
}

function load_titlelist_withcmd($reportdate)
{
	global $sitecfg_workdir, $sitecfg_load_titlelist_cmd, $system, $region, $newtitles, $newtitlesversions, $newtotal_titles;

	if($newtotal_titles < 1)
	{
		echo "newtotal_titles is <1.\n";
		return -2;
	}

	$titleid = $newtitles[0];
	$titlever = $newtitlesversions[0];

	$dirpath = "$sitecfg_workdir/load_titlelist_data";
	if(!is_dir($dirpath)) mkdir($dirpath, 0700);

	$filepath = "$dirpath/titlelist";
	if(!is_dir($filepath)) mkdir($filepath, 0700);

	$filepath = "$filepath/$reportdate-$system";
	$maincmd_str = "$sitecfg_load_titlelist_cmd ".escapeshellarg($reportdate)." ".escapeshellarg($system)." ".escapeshellarg($region)." ".escapeshellarg($filepath)." ".escapeshellarg($dirpath)." ".escapeshellarg("$titleid,$titlever");

	echo "Running load_titlelist cmd...\n";
	$retval = 0;
	$ret = system("$maincmd_str > ".escapeshellarg("$sitecfg_workdir/load_titlelist_out/$reportdate-$system")." 2>&1", $retval);
	if($ret===FALSE || $retval!=0)
	{
		echo "cmd failed.\n";
		if($retval>0)$retval = -$retval;
		if($retval==0)$retval = -3;
		return $retval;
	}

	if(file_exists($filepath)===FALSE)
	{
		echo "The titlelist file doesn't exist.\n";
		return -1;
	}

	$buf = file_get_contents($filepath);
	if($buf===FALSE)
	{
		echo "Failed to load the titlelist file.\n";
		return -1;
	}

	parse_soapresp($buf, 1);//Reuse the SOAP format.

	return 0;
}

function titlelist_dbupdate_withcmd($curdate, $generation)
{
	global $system;

	if($generation!=0)
	{
		$retval = load_titlelist_withcmd($curdate);
		if($retval!=0)
		{
			$msg = "load_titlelist_withcmd() for $curdate-$system failed.";
			echo "Sending notif: $msg\n";
			send_notif([$msg, "--admin"]);
			return $retval;
		}
	}

	return titlelist_dbupdate();
}

function compare_titlelists($curdate, $generation)
{
	global $mysqldb, $system, $region, $sysupdate_systitlehashes;

	$query="SELECT ninupdates_systitlehashes.titlehash FROM ninupdates_reports, ninupdates_consoles, ninupdates_systitlehashes WHERE ninupdates_systitlehashes.reportid=ninupdates_reports.id AND ninupdates_reports.systemid=ninupdates_consoles.id AND ninupdates_consoles.system='".$system."' AND ninupdates_systitlehashes.region='".$region."' AND ninupdates_reports.log='report' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		//echo "System $system Region $region: Titlehash is missing.\n";
		return titlelist_dbupdate_withcmd($curdate, $generation);
	}
	else
	{
		$row = mysqli_fetch_row($result);
		$titlehashold = $row[0];

		if($sysupdate_systitlehashes[$region]!=$titlehashold)
		{
			if(($titlehashold!=NULL && $titlehashold!="") && ($sysupdate_systitlehashes[$region]!=NULL && $sysupdate_systitlehashes[$region]!=""))
			{
				$msg = "Potential sysupdate detected for $system region $region, checking titlelist...";
				echo "Sending notif: $msg\n";
				send_notif([$msg, "--webhook"]);
			}

			return titlelist_dbupdate_withcmd($curdate, $generation);
		}
		else
		{
			return 0;
		}
	}

	return 0;
}

function main($reg)
{
	global $mysqldb, $system, $log, $region, $httpstat, $syscmd, $sitecfg_httpbase, $sysupdate_available, $sysupdate_timestamp, $sitecfg_workdir, $curdate, $dbcurdate, $soap_timestamp, $sysupdate_regions, $arg_difflogold, $arg_difflognew, $newtotal_titles, $console_deviceid, $curtime_override;

	$region = $reg;

	$query="SELECT nushttpsurl, generation FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);
	$nushttpsurl = $row[0];
	$generation = $row[1];

	initialize($generation);

	if($generation==0)
	{
		$url = "$nushttpsurl/nus/services/NetUpdateSOAP";
	}
	else
	{
		$hostpos = strpos($nushttpsurl, ".nintendo.net");
		$chn_host = "n.nintendoswitch.cn";
		if($hostpos!==FALSE && $region=="C")
		{
			$nushttpsurl = substr($nushttpsurl, 0, $hostpos);
			$nushttpsurl = "$nushttpsurl.$chn_host";
		}
		else if($region=="C") $nushttpsurl = "$nushttpsurl.$chn_host";
		$url = "$nushttpsurl/v1/system_update_meta?device_id=" . $console_deviceid;
	}

	for($i=0; $i<5; $i++)
	{
		$ret = send_httprequest($url, $generation);
		if($httpstat!="0")break;
	}

	if($httpstat=="200")
	{
		if($generation==0)
		{
			parse_soapresp($ret, 0);
		}
		else
		{
			$retval = parse_json_resp($ret);
			if($retval!=0)return;
		}
	}
	else
	{
		echo $ret . "\n";
		return;
	}

	if($curtime_override==0)
	{
		$curtime = time();
	}
	else
	{
		$curtime = $curtime_override;
	}
	$soap_timestamp = gmdate(DATE_RFC822, $curtime);

	$curdate = gmdate("Y-m-d_H-i-s", $curtime);
	if($sysupdate_timestamp!="")$curdate = $sysupdate_timestamp;
	$curdatefn = $curdate . ".html";

	if($dbcurdate=="")
	{	
		$query = "SELECT FROM_UNIXTIME($curtime)";
		$result=mysqli_query($mysqldb, $query);
		$row = mysqli_fetch_row($result);
		$dbcurdate = $row[0];
	}

	if($curtime_override!=0)
	{
		echo "Using overridden timestamps: soap_timestamp = '".$soap_timestamp."', curdate='".$curdate."', dbcurdate='".$dbcurdate."'.\n";
	}

	$sendupdatelogs = 0;

	$query = "SELECT COUNT(*) FROM ninupdates_titles, ninupdates_consoles WHERE ninupdates_titles.region='".$region."' AND ninupdates_titles.systemid=ninupdates_consoles.id AND ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);
		$count = $row[0];
	}
	else
	{
		$count = 0;
	}

	if($count==0 && $newtotal_titles>0)
	{
		$retval = titlelist_dbupdate_withcmd($curdate, $generation);
		if($retval < 0)
		{
			echo "titlelist_dbupdate_withcmd() failed.\n";
			return;
		}

		$fsoap = fopen("$sitecfg_workdir/soap$system/$region/$curdatefn.soap", "w");
		fwrite($fsoap, $ret);
		fclose($fsoap);

		echo "System $system: This is first scan for region $region, unknown if any titles were recently updated.\n";

		$sendupdatelogs = 1;
	}
	else
	{
		$retval = compare_titlelists($curdate, $generation);
		if($retval < 0)
		{
			echo "compare_titlelists() failed.\n";
			return;
		}

		if($retval)
		{
			$sendupdatelogs = 1;

			$fsoap = fopen("$sitecfg_workdir/soap$system/$region/$curdatefn.soap", "w");
			fwrite($fsoap, $ret);
			fclose($fsoap);
		}
		else
		{
			$sendupdatelogs = 0;
		}
	}
	
	if($sendupdatelogs)
	{
		$sysupdate_available = 1;
		if($sysupdate_timestamp=="")$sysupdate_timestamp = $curdate;

		if($sysupdate_regions!="")$sysupdate_regions.= ",";
		$sysupdate_regions.= $region;
	}

}

?>
