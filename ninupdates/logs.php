<?php

include_once("config.php");

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

function parse_soapresp($buf)
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

		if($titleid_pos!==FALSE)$titleid = substr($title, $titleid_pos, 16);
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

	$sysupdate_systitlehashes[$region] = "";

	$titlehash_pos = strpos($buf, "<TitleHash>") + 11;
	$titlehash_posend = strpos($buf, "</TitleHash>");
	if($titlehash_pos!==FALSE && $titlehash_posend!==FALSE)
	{
		$titlehash = substr($buf, $titlehash_pos, $titlehash_posend - $titlehash_pos);
		$sysupdate_systitlehashes[$region] = mysqli_real_escape_string($mysqldb, $titlehash);
	}
}

function titlelist_dbupdate()
{
	global $mysqldb, $dbcurdate, $system, $region, $newtitles, $newtitlesversions, $newtitles_sizes, $newtitles_tiksizes, $newtitles_tmdsizes, $newtotal_titles;

	$titles_added = 0;

	$query="SELECT id FROM ninupdates_consoles WHERE system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$row = mysqli_fetch_row($result);
	$systemid = $row[0];

	for($titlei=0; $titlei<$newtotal_titles; $titlei++)
	{
		$query = "SELECT id FROM ninupdates_titleids WHERE titleid='".$newtitles[$titlei]."'";
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

		$query = "SELECT id FROM ninupdates_titles WHERE version=".$newtitlesversions[$titlei]." && region='".$region."' && tid=$tid && systemid=$systemid";
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

	$query="SELECT sysname FROM ninupdates_consoles WHERE system='".$sys."'";
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
