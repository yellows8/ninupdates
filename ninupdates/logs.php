<?php

require_once(dirname(__FILE__) . "/config.php");

require_once(dirname(__FILE__) . "/api.php");

function getlogcontents($filename)
{
	$content = "";
	$line = "";

	if(!file_exists($filename))return "";
	$flog = fopen($filename, "r");
	if($flog===FALSE)return "";

	while(!feof($flog))
	{
		$line = fgets($flog, 256);
		if(!feof($flog))$content .= $line;//Remove the last line of the file in $content.
	}

	fclose($flog);
	return $content;
}

function init_titlelistarray()
{
	global $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles;

	$newtitles = array();
	$newtitlesversions = array();
	$newtitles_sizes = array();
	$newtitles_tiksizes = array();
	$newtitles_tmdsizes = array();
	$newtotal_titles = 0;

	$oldtitles = array();
	$oldtitlesversions = array();
	$oldtitles_sizes = array();
	$oldtitles_tiksizes = array();
	$oldtitles_tmdsizes = array();
	$oldtotal_titles = 0;
}

function load_oldtitlelist($oldlog)
{
	global $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles;

	load_titlelist($oldlog, $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles);
}

function load_newtitlelist($newlog)
{
	global $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles;

	load_titlelist($newlog, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles);
}

function load_titlelist($soaplog, &$titleids, &$titleversions, &$titles_sizes, &$titles_tiksizes, &$titles_tmdsizes, &$total_titles)
{
	$text = $soaplog;
	$titleid = "";
	$titlever = "";
	$titlesize = 0;
	$titlesizetik = "0";
	$titlesizetmd = "0";

	while(($text = strstr($text, "titleid")))
	{
		$titlever_pos = strpos($text, "ver ") + 4;
		$titlever_posend = strpos($text, "size");
		$newline_pos = strpos($text, "<br>");
		if($titlever_posend===FALSE)
		{
			$titlever_posend = $newline_pos;
		}
		else
		{
			$titlever_posend -= 1;
		}

		$titlesize_pos = 0;
		$titlesize_posend = 0;
		$titletiksize_pos = 0;
		$titletiksize_posend = 0;
		$titletmdsize_pos = 0;
		$titletmdsize_posend = 0;

		if(strstr($text, "size"))
		{
			$titlesize_pos = strpos($text, "size ") + 5;
			$titletiksize_pos = strpos($text, "tiksize");
			$titletmdsize_pos = strpos($text, "tmdsize");

			$titlesize_posend = $titletiksize_pos;
			$titletiksize_posend = $titletmdsize_pos;
			$titletmdsize_posend = $newline_pos;

			if($titlesize_posend===FALSE)
			{
				$titlesize_posend = $newline_pos;
			}
			else
			{
				$titlesize_posend -= 1;
			}

			if($titletiksize_posend===FALSE)
			{
				$titletiksize_posend = $newline_pos;
			}
			else
			{
				$titletiksize_posend -= 1;
			}

			if($titletmdsize_posend===FALSE)
			{
				$titletmdsize_posend = $newline_pos;
			}
			else
			{
				$titletmdsize_posend -= 1;
			}
		}

		$titleid = substr($text, 8, 16);
		$titlever = substr($text, $titlever_pos, $titlever_posend - $titlever_pos);
		if($titlesize_pos!==FALSE)$titlesize = substr($text, $titlesize_pos, $titlesize_posend - $titlesize_pos);
		if($titletiksize_pos!==FALSE)$titlesizetik = substr($text, $titletiksize_pos, $titletiksize_posend - $titletiksize_pos);
		if($titletmdsize_pos!==FALSE)$titlesizetmd = substr($text, $titletmdsize_pos, $titletmdsize_posend - $titletmdsize_pos);

		if($titlesizetik=="tagsmissing")$titlesizetik = "0";
		if($titlesizetmd=="tagsmissing")$titlesizetmd = "0";

		$titlesizetik = intval($titlesizetik);
		$titlesizetmd = intval($titlesizetmd);

		$titleids[] = $titleid;
		$titleversions[] = intval($titlever);
		$titles_sizes[] = intval($titlesize);
		$titles_tiksizes[] = $titlesizetik;
		$titles_tmdsizes[] = $titlesizetmd;

		$total_titles++;

		$text = strstr($text, "<br>");
	}
}

function diff_titlelists($oldlog, $curdatefn)
{
	global $oldtitles, $oldtitlesversions, $oldtitles_sizes, $oldtitles_tiksizes, $oldtitles_tmdsizes, $oldtotal_titles, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $difflogbuf, $system, $region, $sitecfg_workdir, $soap_timestamp, $dbcurdate;

	$difflogbuf = "<html><head></head><body>\n";
	$text = "";
	$found = 0;
	$oldtitleid="";
	$titleid="";
	$oldtitlever="";
	$titlever="";
	$updatedtitles = 0;
	$update_size = 0;

	load_oldtitlelist($oldlog);

	for($newi=0; $newi<$newtotal_titles; $newi++)
	{
		$found = 0;

		$titleid = $newtitles[$newi];
		$titlever = $newtitlesversions[$newi];
		$titlesize = $newtitles_sizes[$newi];

		for($oldi=0; $oldi<$oldtotal_titles; $oldi++)
		{
			$oldtitleid = $oldtitles[$oldi];
			$oldtitlever = $oldtitlesversions[$oldi];

			if($titleid==$oldtitleid)
			{
				if($titlever<=$oldtitlever)
				{
					$found = 2;
					break;//skip titles which weren't updated
				}
				$found = 1;
			}
		}

		if($found!=2)
		{
			$updatedtitles++;
			$update_size+= $titlesize;
			$status="";
			if($found==0)$status = "new";
			if($found==1)$status = "updated";

			$text = "titleid $titleid ver $titlever titlesize $titlesize status $status<br>\n";
			$difflogbuf.= $text;
			echo $text;
		}
	}

	if($updatedtitles==0)return 0;

	$difflogbuf .= "Update content size: $update_size<br>\n";
	$difflogbuf .= "</body></html>";

	$freport = fopen("$sitecfg_workdir/reports$system/$region/$curdatefn", "w");
	fwrite($freport, $difflogbuf);
	fclose($freport);

	return 1;
}

function parse_soapresp($buf, $disable_titlehash_init)
{
	global $mysqldb, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $system, $region, $sysupdate_systitlehashes;
	$title = $buf;
	$titleid_pos = 0;
	$titlever_pos = 0;
	$titlesize_pos = 0;
	$titleid = "";
	$titlever = "";
	$titlesize = 0;
	$titlesizetik = "";
	$titlesizetmd = "";
	$logbuf="";

	while(($title = strstr($title, "<TitleVersion>")))
	{
		$titleid_pos = strpos($title,  "<TitleId>") + 9;
		$titleid_posend = strpos($title, "</TitleId>");
		$titlever_pos = strpos($title, "<Version>") + 9;
		$titlever_posend = strpos($title, "</Version>");
		$titlesize_pos = strpos($title, "<FsSize>") + 8;
		$titlesize_posend = strpos($title, "</FsSize>");
		$titlesizetik_pos = strpos($title, "<TicketSize>") + 12;
		$titlesizetik_posend = strpos($title, "</TicketSize>");
		$titlesizetmd_pos = strpos($title, "<TMDSize>") + 9;
		$titlesizetmd_posend = strpos($title, "</TMDSize>");

		if($titlesize_posend===FALSE)
		{
			$titlesize_pos = strpos($title, "<RawSize>") + 9;
			$titlesize_posend = strpos($title, "</RawSize>");
		}

		if($titleid_posend!==FALSE)$titleid = substr($title, $titleid_pos, $titleid_posend - $titleid_pos);
		if($titlever_posend!==FALSE)$titlever = substr($title, $titlever_pos, $titlever_posend - $titlever_pos);
		if($titlesize_posend!==FALSE)$titlesize = substr($title, $titlesize_pos, $titlesize_posend - $titlesize_pos);
		if($titlesizetik_posend!==FALSE)$titlesizetik = substr($title, $titlesizetik_pos, $titlesizetik_posend - $titlesizetik_pos);
		if($titlesizetmd_posend!==FALSE)$titlesizetmd = substr($title, $titlesizetmd_pos, $titlesizetmd_posend - $titlesizetmd_pos);

		if($titlever_posend!==FALSE)$titlever = intval($titlever);
		if($titlesize_posend!==FALSE)$titlesize = intval($titlesize);

		if($titleid_pos===FALSE)$titleid="tagsmissing";
		if($titlever_pos===FALSE || $titlever_posend===FALSE)$titlever = 0;
		if($titlesize_pos===FALSE || $titlesize_posend===FALSE)$titlesize = 0;
		if($titlesizetik_pos===FALSE || $titlesizetik_posend===FALSE)$titlesizetik = 0;
		if($titlesizetmd_pos===FALSE || $titlesizetmd_posend===FALSE)$titlesizetmd = 0;

		$newtitles[] = mysqli_real_escape_string($mysqldb, $titleid);
		$newtitlesversions[] = mysqli_real_escape_string($mysqldb, $titlever);
		$newtitles_sizes[] = mysqli_real_escape_string($mysqldb, $titlesize);
		$newtitles_tiksizes[] = mysqli_real_escape_string($mysqldb, $titlesizetik);
		$newtitles_tmdsizes[] = mysqli_real_escape_string($mysqldb, $titlesizetmd);

		$newtotal_titles++;

		$title = strstr($title, "</TitleVersion>");
	}

	if($disable_titlehash_init==0)
	{
		$sysupdate_systitlehashes[$region] = "";

		$titlehash_pos = strpos($buf, "<TitleHash>") + 11;
		$titlehash_posend = strpos($buf, "</TitleHash>");
		if($titlehash_pos!==FALSE && $titlehash_posend!==FALSE)
		{
			$titlehash = substr($buf, $titlehash_pos, $titlehash_posend - $titlehash_pos);
			$sysupdate_systitlehashes[$region] = mysqli_real_escape_string($mysqldb, $titlehash);
		}
	}
}

function parse_json_resp($buf)
{
	global $mysqldb, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles, $system, $ninupdatesapi_out_version_array, $region, $sysupdate_systitlehashes, $overridden_initial_titleid, $overridden_initial_titleversion;

	$titleid = "";
	$titlever = "";
	$titlesize = 0;
	$titlesizetik = 0;
	$titlesizetmd = 0;

	$jsonobj = json_decode($buf);
	if($jsonobj === NULL)
	{
		"json_decode() failed.\n";
		return 1;
	}

	if(!isset($jsonobj->system_update_metas))
	{
		echo "json is missing 'system_update_metas'.\n";
		return 2;
	}
	
	$titlelist = $jsonobj->system_update_metas;

	if(count($titlelist) < 1)
	{
		echo "json titlelist is empty.\n";
		return 3;
	}

	$title_entry = $titlelist[0];

	if(!isset($title_entry->title_id) || !isset($title_entry->title_version))
	{
		echo "json title_entry titleid/titleversion isn't set.\n";
		return 4;
	}

	$titleid = mysqli_real_escape_string($mysqldb, $title_entry->title_id);
	$titlever = mysqli_real_escape_string($mysqldb, $title_entry->title_version);

	if($overridden_initial_titleid!="")$titleid = mysqli_real_escape_string($mysqldb, $overridden_initial_titleid);
	if($overridden_initial_titleversion!="")$titlever = mysqli_real_escape_string($mysqldb, $overridden_initial_titleversion);

	$sysupdate_systitlehashes[$region] = "$titleid,$titlever";

	$newtitles[] = $titleid;
	$newtitlesversions[] = $titlever;
	$newtitles_sizes[] = $titlesize;
	$newtitles_tiksizes[] = $titlesizetik;
	$newtitles_tmdsizes[] = $titlesizetmd;

	$newtotal_titles++;

	return 0;
}

// Get a title-listing for the specified report (changed/added titles). Duplicate titles in different regions are grouped together.
function report_get_titlelist($system, $reportdate, &$out_titlestatus_new = NULL, &$out_titlestatus_changed = NULL, $ignore_titles = NULL)
{
	global $mysqldb;

	$query="SELECT ninupdates_titles.tid, ninupdates_titleids.titleid, ninupdates_titleids.description, ninupdates_titles.version, GROUP_CONCAT(DISTINCT ninupdates_regions.regionid SEPARATOR ','), GROUP_CONCAT(DISTINCT ninupdates_titles.region ORDER BY ninupdates_regions.regionid SEPARATOR ',') FROM ninupdates_titles, ninupdates_titleids, ninupdates_regions, ninupdates_consoles, ninupdates_reports WHERE ninupdates_consoles.system='".$system."' AND ninupdates_titles.systemid=ninupdates_consoles.id AND ninupdates_titles.tid=ninupdates_titleids.id AND ninupdates_titles.reportid=ninupdates_reports.id AND ninupdates_reports.reportdate='".$reportdate."' AND ninupdates_regions.regioncode=ninupdates_titles.region GROUP BY ninupdates_titles.tid, ninupdates_titles.version";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	$out = array();

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysqli_fetch_row($result);

		$tmp = array();
		$tmp["tid"] = $row[0];
		$tmp["titleid"] = $row[1];
		$tmp["description"] = $row[2];
		$tmp["version"] = $row[3];
		$tmp["regions"] = $row[4];
		$tmp["regionids"] = $row[5];
		$tmp["status"] = "N/A";

		$found = False;
		if($ignore_titles!==NULL)
		{
			foreach($ignore_titles as $ignore_titleid)
			{
				if($tmp["titleid"] == $ignore_titleid)
				{
					$found = True;
					break;
				}
			}
		}
		if(!$found)
		{
			$out[] = $tmp;
		}
	}

	$query = "SELECT ninupdates_titles.tid, ninupdates_titles.region, MIN(ninupdates_titles.version) FROM ninupdates_titles, ninupdates_consoles WHERE ninupdates_consoles.system='".$system."' AND ninupdates_titles.systemid=ninupdates_consoles.id GROUP BY ninupdates_titles.tid, ninupdates_titles.region";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	for($rowi=0; $rowi<$numrows; $rowi++)
	{
		$row = mysqli_fetch_row($result);
		$min_tid = $row[0];
		$min_region = $row[1];
		$min_version = $row[2];

		foreach($out as &$title)
		{
			$tid = $title["tid"];
			$versions = $title["version"];
			$regionids = $title["regionids"];

			if($tid == $min_tid && strpos($regionids, $min_region)!==FALSE && $title["status"]==="N/A")
			{
				$titlestatus = "Changed";
				if($versions==$min_version)
				{
					$titlestatus = "New";
					if($out_titlestatus_new!==NULL)
					{
						$out_titlestatus_new[] = $title;
					}
				}
				else
				{
					if($out_titlestatus_changed!==NULL)
					{
						$out_titlestatus_changed[] = $title;
					}
				}

				$title["status"] = $titlestatus;

				break;
			}
		}
	}

	return $out;
}

function titlelist_dbupdate()
{
	global $mysqldb, $dbcurdate, $system, $region, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles;

	$titles_added = 0;

	$query="SELECT id FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);
	$systemid = $row[0];

	for($titlei=0; $titlei<$newtotal_titles; $titlei++)
	{
		$query = "SELECT id FROM ninupdates_titleids WHERE ninupdates_titleids.titleid='".$newtitles[$titlei]."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);
		if($numrows==0)
		{
			$query = "INSERT INTO ninupdates_titleids (titleid) VALUES ('".$newtitles[$titlei]."')";
			$result=mysqli_query($mysqldb, $query);
			$tid = mysqli_insert_id($mysqldb);
		}
		else
		{
			$row = mysqli_fetch_row($result);
			$tid = $row[0];
		}

		$query = "SELECT id FROM ninupdates_titles WHERE ninupdates_titles.version=".$newtitlesversions[$titlei]." AND ninupdates_titles.region='".$region."' AND ninupdates_titles.tid=$tid AND ninupdates_titles.systemid=$systemid";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows==0)
		{
			$query = "INSERT INTO ninupdates_titles (tid, version, fssize, tmdsize, tiksize, systemid, region, curdate, reportid) VALUES ('".$tid."','".$newtitlesversions[$titlei]."','".$newtitles_sizes[$titlei]."','".$newtitles_tmdsizes[$titlei]."','".$newtitles_tiksizes[$titlei]."',$systemid,'".$region."','".$dbcurdate."',0)";
			$result=mysqli_query($mysqldb, $query);

			$titles_added++;
		}
		else
		{
			$row = mysqli_fetch_row($result);
			$id = $row[0];
			$query="UPDATE ninupdates_titles SET fssize='".$newtitles_sizes[$titlei]."', tmdsize='".$newtitles_tmdsizes[$titlei]."', tiksize='".$newtitles_tiksizes[$titlei]."' WHERE id=$id";
			$result=mysqli_query($mysqldb, $query);
		}
	}

	return $titles_added;
}

function getsystem_sysname($sys)
{
	global $mysqldb;

	$query="SELECT ninupdates_consoles.sysname FROM ninupdates_consoles WHERE ninupdates_consoles.system='".$sys."'";
	$result=mysqli_query($mysqldb, $query);

	$numrows=mysqli_num_rows($result);
	if($numrows==0)
	{
		dbconnection_end();
		writeNormalLog("THE SPECIFIED SYSTEM DOES NOT EXIST IN THE TABLE. RESULT: 200");
		echo "The specified system is invalid.\n";
		exit;
	}

	$row = mysqli_fetch_row($result);
	return $row[0];
}

?>
