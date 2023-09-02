<?php

require_once(dirname(__FILE__) . "/config.php");
require_once(dirname(__FILE__) . "/logs.php");
require_once(dirname(__FILE__) . "/db.php");
include_once(dirname(__FILE__) . "/api.php");
include_once(dirname(__FILE__) . "/logs.php");

require_once(dirname(__FILE__) . "/vendor/autoload.php");

include_once(dirname(__FILE__) . "/wikibot_config.php");
/*
The above file must contain the following settings:
$wikibot_user Account username for the wikibot.
$wikibot_pass Account password for the wikibot.
*/

function wikibot_writelog($str, $type, $reportdate)
{
	global $sitecfg_workdir;

	if($type==0)echo "Writing the following to the wikibot_error.log: $str\n";
	if($type==2)echo "$str\n";

	$path = "";
	if($type==0)$path = "$sitecfg_workdir/debuglogs/wikibot_error.log";
	if($type==1 || $type==2)$path = "$sitecfg_workdir/debuglogs/wikibot_status.log";

	$f = fopen($path, "a");
	if($f===FALSE)
	{
		echo "Failed to open: $path.\n";
		return 1;
	}

	fprintf($f, "%s reportdate=%s: %s\n", gmdate(DATE_ATOM), $reportdate, $str);
	fclose($f);

	return 0;
}

function wikibot_updatenewspages($api, $services, $updateversion, $reportdate, $timestamp, $newspage_text, $newsarchivepage_text, $newspage, $newsarchivepage, $insertstring)
{
	global $wikibot_loggedin, $wikibot_user, $wikibot_pass;

	wikibot_writelog("wikibot_updatenewspages(): insertstring: $insertstring", 1, $reportdate);

	$locatestr = "</noinclude>\n";

	$startpos_news = strpos($newspage_text, $locatestr);
	if($startpos_news===FALSE)
	{
		wikibot_writelog("Failed to locate the first news entry on the wiki news page.", 0, $reportdate);
		return 2;
	}

	$startpos_news+= strlen($locatestr);

	$newline_needed = 1;

	$archiveline_pos = strlen($newspage_text)-1;//Locate the last line in the news-page.
	if($newspage_text[$archiveline_pos]=="\n")
	{
		$newline_needed = 0;
		$pos--;
	}

	while($archiveline_pos>0)
	{
		if($newspage_text[$archiveline_pos]=="\n")
		{
			$archiveline_pos++;
			break;
		}

		$archiveline_pos--;
	}

	if($archiveline_pos==0)
	{
		wikibot_writelog("Failed to locate the last line in the news-page.", 0, $reportdate);
		return 3;
	}

	$newspage_new = substr($newspage_text, 0, $startpos_news) . $insertstring . substr($newspage_text, $startpos_news, $archiveline_pos-$startpos_news);

	$archiveline = substr($newspage_text, $archiveline_pos, strlen($newspage_text)-$archiveline_pos);//Extract the last line from the news-page.
	if($newline_needed)$archiveline.= "\n";

	$newsarchivepage_new = $archiveline . $newsarchivepage_text;

	wikibot_writelog("New news-page: $newspage_new", 1, $reportdate);
	wikibot_writelog("New news-archive-page: $newsarchivepage_new", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending news pages edit requests...", 2, $reportdate);

		$content = new \Mediawiki\DataModel\Content($newspage_new);
		$revision = new \Mediawiki\DataModel\Revision($content, $newspage->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);

		/*if($newspage->setText($newspage_new)===FALSE)
		{
			wikibot_writelog("The http request for editing the news-page failed.", 0, $reportdate);
			return 4;
		}*/

		$content = new \Mediawiki\DataModel\Content($newsarchivepage_new);
		$revision = new \Mediawiki\DataModel\Revision($content, $newsarchivepage->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);

		/*if($newsarchivepage->setText($newsarchivepage_new)===FALSE)
		{
			wikibot_writelog("The http request for editing the news-archive-page failed.", 0, $reportdate);
			return 4;
		}*/

		wikibot_writelog("The news pages edit requests were successful.", 2, $reportdate);
	}

	return 0;
}

function wikibot_updatepage_homemenu($api, $services, $updateversion, $reportdate, $timestamp, $page, $page_text)
{
	global $wikibot_loggedin;

	if(strstr($page_text, $updateversion)!==FALSE)
	{
		wikibot_writelog("This updateversion is already listed on the home-menu page.", 2, $reportdate);
		return 0;
	}

	//echo "Updatever not found on home-menu page, this should not happen.\n";
	//return 0;

	echo "Home Menu page:\n$page_text\n";

	echo "Updating home-menu page...\n";

	$str = "=== System Versions List ===\n{| class=\"wikitable\"\n|-\n";

	$pos = strpos($page_text, $str);
	if($pos===FALSE)
	{
		wikibot_writelog("Failed to locate the system-versions table.", 0, $reportdate);
		return 2;
	}
	$pos+= strlen($str);

	$tableposend = strpos($page_text, "|}", $pos);
	if($tableposend===FALSE)
	{
		wikibot_writelog("Failed to locate the system-versions table end.", 0, $reportdate);
		return 2;
	}

	$tabletext = substr($page_text, $pos, ($tableposend+2)-$pos);
	$tablelen = ($tableposend+2)-$pos;

	$table_entry = "|-\n";
	$pos = 0;
	$line_end = 0;

	$posend = strpos($tabletext, "|-\n");
	if($posend===FALSE)
	{
		wikibot_writelog("Failed to locate the system-versions table-headers end.", 0, $reportdate);
		return 2;
	}

	while($pos < $posend)
	{
		if(substr($tabletext, $pos, 2) !== "! ")
		{
			wikibot_writelog("Invalid system-version table-header line.", 0, $reportdate);
			return 2;
		}

		$pos+= 2;
		$line_end = strpos($tabletext, "\n", $pos);

		if($line_end===FALSE)
		{
			wikibot_writelog("Invalid system-version table-header line: missing newline.", 0, $reportdate);
			return 2;
		}

		$line = substr($tabletext, $pos, $line_end-$pos);
		$pos = $line_end+1;

		$table_entry.= "| ";

		$cmpstr = "System version";

		if(substr($line, 0, strlen($cmpstr)) === $cmpstr)
		{
			$table_entry.= "[[$updateversion|$updateversion]]";
		}
		else if(strpos($line, "Date")!==FALSE || strpos($line, "date")!==FALSE)
		{
			$table_entry.= gmdate("F j, Y", $timestamp);
		}
		else if($line === "CDN Availability")
		{
			$table_entry.= "Available";
		}
		else if($line === "CUP Released")
		{
			$table_entry.= "No";
		}
		else if(strpos($line, "Changelog")!==FALSE)
		{
			$table_entry.= "See [[$updateversion|this]].";
		}

		$table_entry.= "\n";
	}

	$new_page = substr($page_text, 0, $tableposend) . $table_entry . substr($page_text, $tableposend, strlen($page_text) - $tableposend);

	wikibot_writelog("New entry added to the Home Menu sysupdates table:\n$table_entry", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending home-menu page edit request...", 2, $reportdate);

		$content = new \Mediawiki\DataModel\Content($new_page);
		$revision = new \Mediawiki\DataModel\Revision($content, $page->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);

		/*if($page->setText($new_page)===FALSE)
		{
			wikibot_writelog("The http request for editing the Home Menu page failed.", 0, $reportdate);
			return 4;
		}*/

		wikibot_writelog("The home-menu page edit request was successful.", 2, $reportdate);
	}

	echo "Finished updating the Home Menu page.\n";

	return 0;
}

function wikibot_edit_navboxversions($api, $services, $updateversion, $reportdate, $pagename, $page_text)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	if(strstr($page_text, $updateversion)!==FALSE)
	{
		wikibot_writelog("This updateversion is already listed on the NavboxVersions page.", 2, $reportdate);
		return 0;
	}

	$tmp_pos = strpos($updateversion, ".");
	if($tmp_pos===FALSE)
	{
		wikibot_writelog("Failed to locate '.' in the updateversion.", 0, $reportdate);
		return 2;
	}

	$major_version = "[[" . substr($updateversion, 0, $tmp_pos+1);

	$new_page = "";
	$new_text = "";

	$version_pos = strpos($page_text, $major_version);
	$posend = 0;
	if($version_pos===FALSE)
	{
		wikibot_writelog("Major version text '$major_version' not found on the NavboxVersions page, adding a new entry...", 2, $reportdate);

		$posend = strpos($page_text, "|}");
		if($posend===FALSE)
		{
			wikibot_writelog("Failed to locate the NavboxVersions table end.", 0, $reportdate);
			return 2;
		}

		$new_text = "| align=\"center\" | [[$updateversion]]\n|-\n";

		if(substr($page_text, $posend-1, 1) !== "\n") $new_text = "\n" . $new_text;
	}
	else
	{
		$posend = strpos($page_text, "|-", $version_pos);
		if($posend===FALSE)
		{
			wikibot_writelog("Failed to locate the NavboxVersions table entry end.", 0, $reportdate);
			return 2;
		}
		if(substr($page_text, $posend-1, 1) === "\n") $posend--;

		$new_text = " • [[$updateversion]]";
	}

	$new_page = substr($page_text, 0, $posend) . $new_text . substr($page_text, $posend);

	wikibot_writelog("Updated NavboxVersions page:\n$new_page", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending NavboxVersions page edit request...", 2, $reportdate);

		$newContent = new \Mediawiki\DataModel\Content($new_page);
		$title = new \Mediawiki\DataModel\Title($pagename);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		$text = "NavboxVersions page edit request was successful.";
		wikibot_writelog($text, 1, $reportdate);
	}

	return 0;
}

function wikibot_generate_titlelist_text(&$titles, &$new_text, $prefix, $print_titleid, $strip_desc)
{
	if(count($titles)>0)
	{
		$new_text.= $prefix;
		$cnt=0;
		foreach($titles as $title)
		{
			$desc = $title["titleid"];
			if($title["description"]!="" && $title["description"]!="N/A")
			{
				$desc = $title["description"];
				if($strip_desc)
				{
					$tmp_pos = strpos($desc, "-sysmodule");
					if($tmp_pos!==FALSE)
					{
						$desc = substr($desc, 0, $tmp_pos);
					}
					else
					{
						$start_pos = strpos($desc, '"');
						$end_pos = strpos($desc, '" applet');
						if($start_pos!==FALSE && $end_pos!==FALSE)
						{
							$desc = substr($desc, $start_pos+1, $end_pos-$start_pos-1);
						}
					}
				}
				if($print_titleid) $desc.= " (".$title["titleid"].")";
			}
			if($cnt>0)$new_text.= ", ";
			$new_text.= "$desc";
			$cnt++;
		}
		$new_text.= ".";
	}
}

function wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $rebootless_flag, $updateversion_norebootless, $system_generation, $postproc_runfinished)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	$page_text = "";

	$page_exists = False;
	$page_updated = False;

	$out_titlestatus_new = array();
	$out_titlestatus_changed = array();
	$ignore_titles = array();

	if($system_generation!=0) // Ignore the SystemUpdate/sysver titles.
	{
		$ignore_titles[] = "0100000000000816";
		$ignore_titles[] = "0100000000000809";
		$ignore_titles[] = "0100000000000826";
	}

	$report_titlelist = report_get_titlelist($system, $reportdate, out_titlestatus_new: $out_titlestatus_new, out_titlestatus_changed: $out_titlestatus_changed, ignore_titles: $ignore_titles);

	if($rebootless_flag===False)
	{
		$navbox_pagename = "Template:NavboxVersions";

		$tmp_page = $services->newPageGetter()->getFromTitle($navbox_pagename);
		$navbox_revision = $tmp_page->getRevisions()->getLatest();

		if($navbox_revision!==NULL)
		{
			wikibot_writelog("Updating NavboxVersions if needed...", 2, $reportdate);
			$navbox_text = $navbox_revision->getContent()->getData();
			wikibot_edit_navboxversions($api, $services, $updateversion_norebootless, $reportdate, $navbox_pagename, $navbox_text);
		}
	}

	$titlelist_text = "";
	if($system_generation!=0 && $rebootless_flag===False)
	{
		$out_titlestatus_changed_other = array();
		$out_titlestatus_changed_sysmodules = array();
		$out_titlestatus_changed_systemdata = array();
		$out_titlestatus_changed_applets = array();

		foreach($out_titlestatus_changed as &$title)
		{
			if(substr($title["titleid"], 0, 14)==="01000000000000")
			{
				$out_titlestatus_changed_sysmodules[] = $title;
			}
			else if(substr($title["titleid"], 0, 14)==="01000000000008")
			{
				$out_titlestatus_changed_systemdata[] = $title;
			}
			else if(substr($title["titleid"], 0, 13)==="0100000000001")
			{
				$out_titlestatus_changed_applets[] = $title;
			}
			else
			{
				$out_titlestatus_changed_other[] = $title;
			}
		}
		if(count($out_titlestatus_new)>0)
		{
			wikibot_generate_titlelist_text($out_titlestatus_new, $titlelist_text, "* The following new titles were added: ", True, False);
			$titlelist_text.= "\n";
		}
		if(count($out_titlestatus_changed)>0)
		{
			$titlelist_text.= "* The following titles were updated:\n";
			if(count($out_titlestatus_changed_sysmodules)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_sysmodules, $titlelist_text, "** Sysmodules: ", False, True);
				$titlelist_text.= "\n";
			}
			if(count($out_titlestatus_changed_systemdata)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_systemdata, $titlelist_text, "** SystemData (non-sysver): ", False, True);
				$titlelist_text.= "\n";
			}
			if(count($out_titlestatus_changed_applets)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_applets, $titlelist_text, "** Applets: ", False, True);
				$titlelist_text.= "\n";
			}
			if(count($out_titlestatus_changed_other)>0)
			{
				wikibot_generate_titlelist_text($out_titlestatus_changed_other, $titlelist_text, "** ", False, True);
				$titlelist_text.= "\n";
			}
		}
	}

	$tmp_page = $services->newPageGetter()->getFromTitle($updateversion_norebootless);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision!==NULL)
	{
		wikibot_writelog("Sysupdate page already exists, updating the page if needed...", 2, $reportdate);
		$page_exists = True;
		$page_text = $tmp_revision->getContent()->getData();
	}

	if(!$page_exists)
	{
		wikibot_writelog("Sysupdate page doesn't exist, generating a page...", 2, $reportdate);

		if($rebootless_flag)
		{
			wikibot_writelog("Sysupdate page doesn't exist but this report is rebootless, skipping page creation.", 0, $reportdate);
			return 0;
		}
	}

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.id, ninupdates_consoles.sysname, ninupdates_consoles.system, ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updateversion='".$updateversion."' && ninupdates_reports.systemid=ninupdates_consoles.id && wikibot_runfinished=0";
	$result_systems=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result_systems);

	if($numrows==0)
	{
		wikibot_writelog("wikibot_edit_updatepage(): Failed to load the report info where wikibot_runfinished=0.", 0, $reportdate);
		return 1;
	}

	$regions_list = "";
	$changelog_count = 0;

	$changelog_text = "";

	$sysnames_list = "";
	$total_systems = $numrows;

	$reportlinks_list = "";

	for($system_index=0; $system_index<$total_systems; $system_index++)
	{
		$row = mysqli_fetch_row($result_systems);
		$regions = $row[0];
		$reportid = $row[1];
		$cursysname = $row[2];
		$cursystem = $row[3];
		$curreportdate = $row[4];

		if($system_index>0)$sysnames_list.= "+";
		$sysnames_list.= $cursysname;

		if($system_index>0)$regions_list.=" ";
		$regions_list.= "This $cursysname update was released for the following regions: ";

		$searchtext = "/reports.php?date=$curreportdate&sys=$cursystem]";
		$text = "[$sitecfg_httpbase$searchtext";
		if(!$page_exists || strpos($page_text, $searchtext)===FALSE)
		{
			$reportlinks_list.= "* $text\n";
		}

		$regions_count = 0;
		$region = strtok($regions, ",");
		while($region!==FALSE)
		{
			if(strlen($region)>1)break;

			$region_next = strtok(",");

			$query="SELECT regionid FROM ninupdates_regions WHERE regioncode='".$region."'";
			$result=mysqli_query($mysqldb, $query);
			$numrows=mysqli_num_rows($result);

			if($numrows==0)
			{
				wikibot_writelog("wikibot_edit_updatepage(): Failed to load the regionid.", 0, $reportdate);
				return 2;
			}

			$row = mysqli_fetch_row($result);
			$regionid = $row[0];

			if($regions_count==0)
			{
				$regions_list.= $regionid;
			}
			else
			{
				if($region_next!==FALSE)
				{
					$regions_list.= ", " . $regionid;
				}
				else
				{
					$regions_list.= ", and " . $regionid;
				}
			}

			if($system_index==0)//Normally when there's multiple systems' reports with the same updateversion, the changelog is loaded from the same page anyway.
			{
				$query="SELECT ninupdates_officialchangelog_pages.url, ninupdates_officialchangelog_pages.id FROM ninupdates_officialchangelog_pages, ninupdates_consoles, ninupdates_regions WHERE ninupdates_consoles.system='".$cursystem."' && ninupdates_officialchangelog_pages.systemid=ninupdates_consoles.id && ninupdates_officialchangelog_pages.regionid=ninupdates_regions.id && ninupdates_regions.regioncode='".$region."'";
				$result=mysqli_query($mysqldb, $query);
				$numrows=mysqli_num_rows($result);
				if($numrows>0)
				{
					$changelog_count++;

					$row = mysqli_fetch_row($result);
					$pageurl = $row[0];
					$pageid = $row[1];

					$wiki_text = "";

					$query = "SELECT wiki_text FROM ninupdates_officialchangelogs WHERE pageid=$pageid && reportid=$reportid";
					$result=mysqli_query($mysqldb, $query);
					$numrows=mysqli_num_rows($result);
					if($numrows>0)
					{
						$row = mysqli_fetch_row($result);
						$wiki_text = $row[0];
					}

					if($wiki_text===FALSE)$wiki_text = "";
					if($wiki_text=="")$wiki_text = "* N/A, see the official page for the actual changelog.\n";

					$changelog_text.= "[$pageurl Official] $regionid change-log:\n$wiki_text";
				}
			}

			$regions_count++;
			$region = $region_next;
		}

		$regions_list.= ".";
	}

	$timestamp_str = gmdate("F j, Y", $timestamp);
	$changelog_hdr = "==Change-log==";

	if(!$page_exists)
	{
		$page_updated = True;

		$page_text.= "The $sysnames_list ".$updateversion_norebootless." system update was released on ".$timestamp_str." (UTC). $regions_list\n\n";
		$page_text.= "Security flaws fixed: <fill this in manually later, see the updatedetails page from the ninupdates-report page(s) once available for now>.\n\n";

		$page_text.= $changelog_hdr."\n";
		$page_text.= "$changelog_text\n";

		$page_text.= "==System Titles==\n";
		if($titlelist_text==="")
		{
			$page_text.= "<fill this in (manually) later>\n";
		}
		else
		{
			$page_text.= $titlelist_text;
		}
		$page_text.= "\n";

		$page_text.= "==See Also==\n";
		$page_text.= "System update report(s):\n";
		$page_text.= $reportlinks_list;
		$page_text.= "\n";

		if($navbox_revision!==NULL)
		{
			$page_text.= "\n{{NavboxVersions}}\n\n";
			$page_text.= "[[Category:System versions]]\n";
		}
	}
	else
	{
		if($rebootless_flag===True)
		{
			if(strpos($page_text, $timestamp_str)===FALSE)
			{
				$insert_pos = strpos($page_text, $changelog_hdr);
				if($insert_pos===FALSE)
				{
					wikibot_writelog("wikibot_edit_updatepage(): Failed to locate the changelog_hdr, skipping adding the rebootless text.", 0, $reportdate);
				}
				else
				{
					$new_text = "Additionally, a rebootless $sysnames_list system update was released for ".$updateversion_norebootless." on ".$timestamp_str." (UTC). $regions_list";

					if(count($report_titlelist)>0)
					{
						wikibot_generate_titlelist_text($out_titlestatus_new, $new_text, " The following new titles were added: ", True, False);
						wikibot_generate_titlelist_text($out_titlestatus_changed, $new_text, " The following (non-sysver) titles were updated: ", False, False);
					}

					$new_text.= "\n\n";

					$page_updated = True;
					$page_text = substr($page_text, 0, $insert_pos) . $new_text . substr($page_text, $insert_pos);
				}
			}
		}

		if(strlen($reportlinks_list)>0)
		{
			$insert_pos = strpos($page_text, "System update report(s)");
			if($insert_pos!==FALSE)
			{
				$tmp_pos = $insert_pos;
				while($tmp_pos!==FALSE)
				{
					$tmp_pos = strpos($page_text, "\n", $tmp_pos);
					if($tmp_pos!==FALSE && $page_text[$tmp_pos+1]=="\n")
					{
						$tmp_pos++;
						break;
					}
					if($tmp_pos!==FALSE)
					{
						$tmp_pos++;
					}
				}
				if($tmp_pos!==FALSE)
				{
					$new_text = $reportlinks_list;

					$insert_pos = $tmp_pos;
					$page_text = substr($page_text, 0, $insert_pos) . $new_text . substr($page_text, $insert_pos);

					$page_updated = True;
				}
			}
		}
	}

	if($postproc_runfinished!=0)
	{
		$out_page_updated = False;
		wikibot_process_wikigen($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $page_text, $out_page_updated);
		if($out_page_updated) $page_updated = $out_page_updated;
	}

	if($page_updated)
	{
		wikibot_writelog("New sysupdate page:\n$page_text", 1, $reportdate);
	}
	else
	{
		wikibot_writelog("Sysupdate page edit isn't needed, skipping sending the edit request.", 2, $reportdate);
	}

	if($wikibot_loggedin == 1 && $page_updated)
	{
		wikibot_writelog("Sending sysupdate page edit request...", 2, $reportdate);

		/*$content = new \Mediawiki\DataModel\Content($page_text);
		$revision = new \Mediawiki\DataModel\Revision($content, $page->getPageIdentifier());
		$services->newRevisionSaver()->save($revision);*/

		$newContent = new \Mediawiki\DataModel\Content($page_text);
		$title = new \Mediawiki\DataModel\Title($updateversion);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		/*if($page->setText($page_text)===FALSE)
		{
			wikibot_writelog("The http request for editing the sysupdate page failed.", 0, $reportdate);
			return 4;
		}*/
		
		$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikipage_exists=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
		$result=mysqli_query($mysqldb, $query);		

		$text = "Sysupdate page edit request was successful.";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);
		$text = "Sending notif...";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);

		$msgtext = "created";
		$msgtextnew = "new";
		if($page_exists==1)
		{
			$msgtext = "updated";
			$msgtextnew = "";
		}

		$wiki_uribase = "wiki/";
		if($apiprefixuri == "")$wiki_uribase = "index.php?title=";

		$notif_msg = "The wiki page for the $msgtextnew $sysnames_list $updateversion_norebootless sysupdate has been $msgtext: $serverbaseurl$wiki_uribase$updateversion_norebootless";
		if($rebootless_flag===False) $notif_msg.= " See also: ".$serverbaseurl.$wiki_uribase."Special:RecentChanges";
		send_notif([$notif_msg, "--social"]);
	}

	return 0;
}

function wikibot_edit_firmwarenews($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	$page_text = "";

	$page_exists = 1;

	$pagetitle = "FirmwareNews";

	$tmp_page = $services->newPageGetter()->getFromTitle($pagetitle);
	$tmp_revision = $tmp_page->getRevisions()->getLatest();

	if($tmp_revision===NULL)
	{
		wikibot_writelog("FirmwareNews page doesn't exist.", 2, $reportdate);

		$page_exists = 0;

		return 0;
	}

	wikibot_writelog("FirmwareNews page exists, generating new page text if needed...", 2, $reportdate);

	$page_text = $tmp_revision->getContent()->getData();

	$strstart = strstr($page_text, "'''");
	if($strstart===FALSE)
	{
		wikibot_writelog("wikibot_edit_firmwarenews(): Failed to find the curupdatetext start.", 0, $reportdate);
		return 1;
	}

	$strendpos = strpos($strstart, "'''", 3);
	if($strendpos===FALSE)
	{
		wikibot_writelog("wikibot_edit_firmwarenews(): Failed to find the curupdatetext end.", 0, $reportdate);
		return 2;
	}

	$curupdatetext = substr($strstart, 3, $strendpos-3);
	$reportupdatetext = "[[$updateversion]]";

	wikibot_writelog("curupdatetext: $curupdatetext, reportupdatetext: $reportupdatetext", 2, $reportdate);

	if($curupdatetext === $reportupdatetext)
	{
		wikibot_writelog("curupdatetext and reportupdatetext already match.", 2, $reportdate);
		return 0;
	}

	wikibot_writelog("curupdatetext and reportupdatetext don't match, generating new page-text...", 2, $reportdate);

	$new_page_text = str_replace("'''$curupdatetext'''", "'''$reportupdatetext'''", $page_text);

	wikibot_writelog("New FirmwareNews page:\n$new_page_text", 1, $reportdate);

	if($wikibot_loggedin == 1)
	{
		wikibot_writelog("Sending FirmwareNews page edit request...", 2, $reportdate);

		$newContent = new \Mediawiki\DataModel\Content($new_page_text);
		$title = new \Mediawiki\DataModel\Title($pagetitle);
		$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
		$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
		$services->newRevisionSaver()->save($revision);

		$text = "FirmwareNews page edit request was successful.";
		echo "$text\n";
		wikibot_writelog($text, 1, $reportdate);
	}

	return 0;
}

/*
This parses a json file for editing wiki pages, with the below format.
When any errors occur, it will skip processing the current array-entry, with the error only being returned after processing the rest of the json. This includes fields not being found which are not listed as optional, and when most text-search functionality (excluding search_text) fails.

[ # Array of page
	{
		"page_title": "{wiki page title}", # The specified page must exist on the wiki, otherwise this page is skipped. If value is "!UPDATEVER", this page is used during editing the updatepage (wikibot_edit_updatepage), otherwise this page is skipped.
		"search_section": "{search text}", # Must be specified either here or in the below target entry. If both are specified, the search starting pos for the target search_section is the pos of the page search_section. The located text is used as the starting pos for the section to edit.

		"targets": [ # Array of target
			{
				"search_section": {see above}
				"search_section_end": "{search end text}", # Defaults to "|}" if not specified (wikitable end). This is the text to search for relative to search_section which is used as the end-pos for the section to edit (that is, the edit-section will be the text immediately before this).

				"text_sections": [ # Optional array of text_section. Insert raw text.
					{
						"search_text": "{text}", # Text to search for within the edit-section to determine whether editing is needed. If found, editing this text_section is skipped.
						"insert_text": "{text}", # Text to insert. If insert_before_text is not specified, "\n" is added prior to the insert_text.
						"insert_before_text": "{text}" # Optional. By default insert_text is inserted at the end of the edit-section. If this is specified, the text is inserted at the pos of the specified text.
					},
				],

				"insert_row_tables": [ # Optional array of insert_row_table. Insert a row at the end of a table.
					"search_text": "{text}", # Row text to search for within the edit-section to determine whether editing is needed. If found, editing this insert_row_table is skipped. The text searched for is "| " followed by the search_text value, then newline.
					"columns": [ # Array of text strings for each column. The inserted text is "| " followed by the string then newline. If the string is "!LAST", the value from the last table row is used for this column. If the string is "!TIMESTAMP", an UTC date is used as the column string (report timestamp, otherwise for wikigen-argv it's from time()).
					],
				],

				"table_lists": [ # Optional array of table_list. Insert text into a table column.
					{
						"target_text_prefix": "{text}", # Line to update is located by searching for "| " followed by the specified target_text_prefix.
						"delimiter": "{text}", # Optional delimiter to use with insert_text, defaults to ", ".
						"insert_before_text": "{text}", # Optional. By default insert_text is inserted at the end of the table line. If this is specified, the text is inserted at the pos of the specified text.
						"target_column": {integer}, # Optional column to edit within the same line, seperated by " ||". If specified, insert_before_text is ignored and the text will be inserted at the end of the specified column. The first column (before the first " ||") is value 0, which is the default. This is only enabled when >0.
						"search_text": "{text}", # Text to search for within the table line to determine whether editing is needed. If found, editing this table_list is skipped.
						"insert_text": "{text}", # Text to insert. If insert_before_text is not specified, delimiter is added before insert_text, otherwise it's appended afterwards. With target_column, delimiter is only used for adding before insert_text when the column isn't empty.
					},
				],

				"tables_updatever_range": [ # Optional array of table_updatever_range. Not available when the updateversion isn't set ('N/A'), hence this is also disabled when running wikibot with a wikigen file from argv. Updates a table where the first column has the form "[updateversion]" or "[updateversion_min-updateversion_max]", with the input updateversion being used as the search_text to determine whether editing is required. "[]" around updateversion is only used if it's present in the table.
					{
						"columns": [ # Array of text strings for each column (following "| "), skipping the updateversion column. When the last row in the table matches the data specified here (ignoring updateversion), only the table updateversion is updated (also happens when the columns array is empty). Otherwise, a new row is inserted at the end of the table with the updateversion and the specified data.
						],
					},
				],
			},
		],
	},
]
*/

function wikibot_process_wikigen($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, &$in_page_text=NULL, &$out_page_updated=NULL, $wikigen_path="")
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_workdir;

	$path = $wikigen_path;
	if($wikigen_path=="")
	{
		$updatedir = "$sitecfg_workdir/sysupdatedl/autodl_sysupdates/$reportdate-$system";
		$path = "$updatedir/wikigen.json";
	}
	else
	{
		wikibot_writelog("wikibot_process_wikigen(): Processing wikigen with path: $wikigen_path", 2, $reportdate);
	}

	if(file_exists($path)===FALSE)
	{
		$msg = "wikibot_process_wikigen(): The wikigen file doesn't exist.";
		if($wikigen_path=="") // Only throw an error when processing an input file.
		{
			wikibot_writelog($msg, 2, $reportdate);
			return 0;
		}
		else
		{
			wikibot_writelog($msg, 0, $reportdate);
			return 3;
		}
	}

	$wikigen = file_get_contents($path);

	if($wikigen===FALSE)
	{
		wikibot_writelog("wikibot_process_wikigen(): Failed to load the wikigen file.", 0, $reportdate);
		return 3;
	}

	$wikigen = json_decode($wikigen, true);

	if($wikigen===NULL)
	{
		wikibot_writelog("wikibot_process_wikigen(): Failed to decode the wikigen file.", 0, $reportdate);
		return 4;
	}

	$ret=0;

	foreach($wikigen as $wikigen_page)
	{
		if(isset($wikigen_page["page_title"]))
		{
			$pagetitle = $wikigen_page["page_title"];
		}
		else
		{
			wikibot_writelog("wikibot_process_wikigen(): json page_title field isn't set, skipping this page entry.", 2, $reportdate);
			continue;
		}

		if($pagetitle=="!UPDATEVER")
		{
			if($in_page_text===NULL)
			{
				continue;
			}
			else
			{
				$page_text = &$in_page_text;
			}
		}
		else
		{
			if($in_page_text!==NULL)
			{
				continue;
			}

			$tmp_page = $services->newPageGetter()->getFromTitle($pagetitle);
			$tmp_revision = $tmp_page->getRevisions()->getLatest();

			if($tmp_revision===NULL)
			{
				wikibot_writelog("wikibot_process_wikigen(): $pagetitle page doesn't exist, skipping this page entry.", 2, $reportdate);

				continue;
			}

			wikibot_writelog("wikibot_process_wikigen(): $pagetitle page exists, generating new page text if needed...", 2, $reportdate);

			$page_text = $tmp_revision->getContent()->getData();
		}

		if(!isset($wikigen_page["targets"]))
		{
			wikibot_writelog("wikibot_process_wikigen($pagetitle): json targets isn't set, skipping this page entry.", 2, $reportdate);

			continue;
		}

		$wikigen_targets = &$wikigen_page["targets"];
		$page_updated = False;
		$page_search_section = "";

		if(isset($wikigen_page["search_section"]))
		{
			$page_search_section = $wikigen_page["search_section"];
		}

		foreach($wikigen_targets as $wikigen_target)
		{
			$page_text_org = $page_text;

			$search_section = "";
			if(isset($wikigen_target["search_section"]))
			{
				$search_section = $wikigen_target["search_section"];
			}
			else if($page_search_section=="")
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): json search_section field(s) aren't set, skipping this entry.", 2, $reportdate);

				continue;
			}

			$page_section_pos = 0;
			if($page_search_section!="")
			{
				$page_section_pos = strpos($page_text_org, $page_search_section);
				if($page_section_pos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the text for page search_section.", 0, $reportdate);
					if($ret==0) $ret=1;
					continue;
				}
			}

			if($search_section!="")
			{
				$section_pos = strpos($page_text_org, $search_section, $page_section_pos);
				if($section_pos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the text for target search_section.", 0, $reportdate);
					if($ret==0) $ret=1;
					continue;
				}
			}
			else
			{
				$section_pos = $page_section_pos;
			}

			$search_section_end = "|}";
			if(isset($wikigen_target["search_section_end"]))
			{
				$search_section_end = $wikigen_target["search_section_end"];
			}

			$section_endpos = strpos($page_text_org, $search_section_end, $section_pos);
			if($section_endpos===FALSE)
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the section end.", 0, $reportdate);
				if($ret==0) $ret=2;
				continue;
			}

			$section_text = substr($page_text_org, $section_pos, $section_endpos-$section_pos);

			$text_sections = array();
			$insert_row_tables = array();
			$table_lists = array();
			$tables_updatever_range = array();

			if(isset($wikigen_target["text_sections"]))
			{
				$text_sections = $wikigen_target["text_sections"];
			}

			if(isset($wikigen_target["insert_row_tables"]))
			{
				$insert_row_tables = $wikigen_target["insert_row_tables"];
			}

			if(isset($wikigen_target["table_lists"]))
			{
				$table_lists = $wikigen_target["table_lists"];
			}

			if(isset($wikigen_target["tables_updatever_range"]))
			{
				$tables_updatever_range = $wikigen_target["tables_updatever_range"];
			}

			if(!isset($wikigen_target["text_sections"]) && !isset($wikigen_target["insert_row_tables"]) && !isset($wikigen_target["table_lists"]) && !isset($wikigen_target["tables_updatever_range"]))
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): json text_sections/insert_row_tables/table_lists/tables_updatever_range isn't set, skipping this entry.", 2, $reportdate);

				continue;
			}

			foreach($text_sections as $text_section)
			{
				if(isset($text_section["search_text"]) && isset($text_section["insert_text"]))
				{
					$search_text = $text_section["search_text"];
					$insert_text = $text_section["insert_text"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json fields used by text_section aren't set, skipping this entry.", 2, $reportdate);

					continue;
				}

				if(strpos($section_text, $search_text)!==FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This text already exists in the section, search_text: $search_text", 2, $reportdate);
					continue;
				}

				$insert_pos = strlen($section_text);

				if(isset($text_section["insert_before_text"]))
				{
					$insert_before_text = $text_section["insert_before_text"];
					$insert_pos = strpos($section_text, $insert_before_text);
					if($insert_pos===FALSE)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the insert_before_text. search_text: $search_text", 0, $reportdate);
						if($ret==0) $ret=2;
						continue;
					}
				}
				else
				{
					$insert_text = "\n" . $insert_text;
				}

				$section_text = substr($section_text, 0, $insert_pos) . $insert_text . substr($section_text, $insert_pos);
				$page_text = substr($page_text_org, 0, $section_pos) . $section_text . substr($page_text_org, $section_endpos);
				$page_updated = True;
			}

			foreach ($insert_row_tables as $insert_row_table)
			{
				if(isset($insert_row_table["search_text"]) && isset($insert_row_table["columns"]))
				{
					$search_text = $insert_row_table["search_text"];
					$columns = $insert_row_table["columns"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json fields used by insert_row_table isn't set, skipping this entry.", 2, $reportdate);

					continue;
				}

				$columns_count = count($columns);

				$search_text = "| $search_text\n";
				if(strpos($section_text, $search_text)!==FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This text already exists in the section for insert_row_table, search_text: $search_text", 2, $reportdate);
					continue;
				}

				$table_endpos = strpos($page_text_org, "|}", $section_pos);
				if($table_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the table end for insert_row_table.", 2, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$new_text = "";
				if(substr($page_text, $section_endpos-3, 3)!="|-\n")
				{
					$new_text.= "|-\n";
				}

				$lines = explode("\n", $section_text);
				$found = False;
				$num_columns=0;
				for($i=count($lines)-1; $i>=0; $i--)
				{
					if(($i == count($lines)-1 && substr($lines[$i], 0, 2)==="|-") || strlen($lines[$i])==0)
					{
						continue;
					}
					if(substr($lines[$i], 0, 2)==="|-")
					{
						$linei = $i+1;
						$found = True;
						break;
					}
					$num_columns++;
				}

				if($found === False)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the last table entry for insert_row_table.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				if($columns_count != $num_columns)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The number of json columns for insert_row_table ($columns_count) doesn't match table num_columns ($num_columns). Whichever value is smaller will be used for the entrycount.", 2, $reportdate);
				}
				$entrycount = min($columns_count, $num_columns);

				$i=0;
				for($i=0; $i<$entrycount; $i++)
				{
					$linetext = $columns[$i];
					if($linetext === "!LAST")
					{
						$new_text.= $lines[$linei+$i] . "\n";
					}
					else if($linetext === "!TIMESTAMP")
					{
						$new_text.= "| " . gmdate("F j, Y", $timestamp)." (UTC)" . "\n";
					}
					else
					{
						$new_text.= "| $linetext\n";
					}
				}

				$page_text = substr($page_text_org, 0, $table_endpos) . $new_text . substr($page_text_org, $table_endpos);
				$page_updated = True;
			}

			foreach($table_lists as $table_list)
			{
				if(isset($table_list["target_text_prefix"]))
				{
					$target_text_prefix = $table_list["target_text_prefix"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json target_text_prefix isn't set, skipping this entry.", 2, $reportdate);

					continue;
				}

				$delimiter = ", ";
				if(isset($table_list["delimiter"]))
				{
					$delimiter = $table_list["delimiter"];
				}

				$insert_before_text = "";
				if(isset($table_list["insert_before_text"]))
				{
					$insert_before_text = $table_list["insert_before_text"];
				}

				$target_column = 0;
				if(isset($table_list["target_column"]))
				{
					$target_column = $table_list["target_column"];
				}

				if(isset($table_list["search_text"]) && isset($table_list["insert_text"]))
				{
					$search_text = $table_list["search_text"];
					$insert_text = $table_list["insert_text"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json fields used by table_list aren't set, skipping this entry.", 2, $reportdate);

					continue;
				}

				$prefix_pos = strpos($section_text, "| $target_text_prefix");
				if($prefix_pos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the table text for target_text_prefix: $target_text_prefix", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$line_endpos = strpos($section_text, "\n", $prefix_pos);
				if($line_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the target line end.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$line = substr($section_text, $prefix_pos, $line_endpos-$prefix_pos);

				if(strpos($line, $search_text)!==FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This entry already exists in the table, search_text: $search_text", 2, $reportdate);
					continue;
				}

				if($target_column>0)
				{
					$curpos=0;
					$errorflag = False;
					for($i=0; $i<=$target_column; $i++)
					{
						$curpos = strpos($line, " ||", $curpos);
						if($curpos===FALSE)
						{
							if($i==$target_column)
							{
								$curpos = strlen($line);
							}
							else
							{
								wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find ' ||' for target_column=$target_column with table_list, target_text_prefix: $target_text_prefix", 0, $reportdate);
								if($ret==0) $ret=2;
								$errorflag = True;
								break;
							}
						}
						else if($i<$target_column)
						{
							$curpos+=3;
						}
					}
					if($errorflag) continue;
					$insert_pos = $curpos;

					if(substr($line, $curpos-3, 3)!==" ||") // If the column isn't empty, add delimiter.
					{
						$insert_text = $delimiter . $insert_text;
					}
					else
					{
						$insert_text = " " . $insert_text;
					}
				}
				else
				{
					if($insert_before_text!="")
					{
						$insert_pos = strpos($line, $insert_before_text);
						if($insert_pos===FALSE)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the insert_before_text, search_text: $search_text", 0, $reportdate);
							if($ret==0) $ret=2;
							continue;
						}
						$insert_text.= $delimiter;
					}
					else
					{
						$insert_text = $delimiter . $insert_text;
						$insert_pos = strlen($line);
					}
				}

				$new_line = substr($line, 0, $insert_pos) . $insert_text . substr($line, $insert_pos);
				$section_text = substr($section_text, 0, $prefix_pos) . $new_line . substr($section_text, $line_endpos);
				$page_text = substr($page_text_org, 0, $section_pos) . $section_text . substr($page_text_org, $section_endpos);
				$page_updated = True;
			}

			foreach($tables_updatever_range as $table_updatever_range)
			{
				if($updateversion==="N/A")
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json tables_updatever_range is not available when the updateversion isn't set ('N/A').", 2, $reportdate);
					continue;
				}

				if(strpos($section_text, $updateversion)!==FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This entry already exists in the table for tables_updatever_range.", 2, $reportdate);
					continue;
				}

				if(isset($table_updatever_range["columns"]))
				{
					$columns = $table_updatever_range["columns"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json table_updatever_range columns isn't set, skipping this entry.", 2, $reportdate);

					continue;
				}
				$columns_count = count($columns);

				$new_text = "";
				if(substr($page_text, $section_endpos-3, 3)!="|-\n")
				{
					$new_text.= "|-\n";
				}

				$lines = explode("\n", $section_text);
				$found = False;
				$num_columns=0;
				for($i=count($lines)-1; $i>=0; $i--)
				{
					if(($i == count($lines)-1 && substr($lines[$i], 0, 2)==="|-") || strlen($lines[$i])==0)
					{
						continue;
					}
					if(substr($lines[$i], 0, 2)==="|-")
					{
						$linei = $i+1;
						$found = True;
						break;
					}
					$num_columns++;
				}

				if($found === False)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the last table entry for table_updatever_range.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				if($num_columns>0) $num_columns--; // Don't include updatever in this value.
				if($columns_count != $num_columns)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The number of json columns for table_updatever_range ($columns_count) doesn't match table num_columns ($num_columns). Whichever value is smaller will be used for the entrycount.", 2, $reportdate);
				}

				$updatever_prefix = "";
				$updatever_append = "";
				if(substr($lines[$linei], 2, 1)==="[")
				{
					$updatever_prefix = "[";
					$updatever_append = "]";
				}

				// Check whether the json data and the table doesn't match, skipping the updatever in the table.
				$entrycount = min($columns_count, $num_columns);
				$found = False;
				for($i=0; $i<$entrycount; $i++)
				{
					$tmp_line = substr($lines[$linei+1+$i], 2);
					if($tmp_line!==$columns[$i])
					{
						$found = True;
						break;
					}
				}

				if($found)
				{
					$new_text.= "| ".$updatever_prefix.$updateversion.$updatever_append."\n";
					for($i=0; $i<$entrycount; $i++)
					{
						$new_text.= "| ".$columns[$i]."\n";
					}

					$page_text = substr($page_text_org, 0, $section_endpos) . $new_text . substr($page_text_org, $section_endpos);
				}
				else // When data matches, just update the updatever in the table. This will also be reached when the json columns is empty.
				{
					$new_text = "";
					for($i=0; $i<count($lines); $i++)
					{
						$linetext = $lines[$i];
						if($i==count($lines)-1 && strlen($linetext)==0) continue;
						if($linei == $i)
						{
							$tmp = strpos($linetext, "-");
							if($tmp===FALSE)
							{
								$linetext = "| ".$updatever_prefix.$updateversion.$updatever_append;
							}
							else
							{
								$linetext = substr($linetext, 0, $tmp+1) . $updateversion.$updatever_append;
							}
						}
						$new_text.= $linetext . "\n";
					}
					$page_text = substr($page_text_org, 0, $section_pos) . $new_text . substr($page_text_org, $section_endpos);
				}
				$page_updated = True;
			}
		}

		if($in_page_text!==NULL && $out_page_updated!==NULL) $out_page_updated = $page_updated;

		if($page_updated && $in_page_text===NULL)
		{
			wikibot_writelog("New $pagetitle page:\n$page_text", 1, $reportdate);

			if($wikibot_loggedin == 1)
			{
				wikibot_writelog("Sending $pagetitle page edit request...", 2, $reportdate);

				$newContent = new \Mediawiki\DataModel\Content($new_page_text);
				$title = new \Mediawiki\DataModel\Title($pagetitle);
				$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
				$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
				$services->newRevisionSaver()->save($revision);

				$text = "$pagetitle page edit request was successful.";
				echo "$text\n";
				wikibot_writelog($text, 1, $reportdate);
			}
		}
	}

	return $ret;
}

function runwikibot_newsysupdate($updateversion, $reportdate, $wikigen_path="")
{
	global $mysqldb, $wikibot_loggedin, $wikibot_user, $wikibot_pass, $system;

	$wikibot_loggedin = 0;
	$ret=0;

	$query="SELECT ninupdates_wikiconfig.serverbaseurl, ninupdates_wikiconfig.apiprefixuri, ninupdates_wikiconfig.news_pagetitle, ninupdates_wikiconfig.newsarchive_pagetitle, ninupdates_wikiconfig.homemenu_pagetitle FROM ninupdates_wikiconfig, ninupdates_consoles WHERE ninupdates_wikiconfig.wikibot_enabled=1 && ninupdates_wikiconfig.id=ninupdates_consoles.wikicfgid && ninupdates_consoles.system='".$system."'";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	if($numrows==0)
	{
		echo "Wiki config is not available for this system($system), or wikibot processing is disabled for this wiki, skipping wikibot processing for it.\n";

		if($wikigen_path=="")
		{
			$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikibot_runfinished=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
			$result=mysqli_query($mysqldb, $query);
		}

		return 0;
	}

	$row = mysqli_fetch_row($result);
	$serverbaseurl = $row[0];
	$apiprefixuri = $row[1];
	$wiki_newspagetitle = $row[2];
	$wiki_newsarchivepagetitle = $row[3];
	$wiki_homemenutitle = $row[4];

	if(!isset($wiki_homemenutitle))$wiki_homemenutitle = "";

	$wiki_apibaseurl = $serverbaseurl . $apiprefixuri;

	if($wikigen_path=="")
	{
		$query="SELECT ninupdates_reports.reportdaterfc, ninupdates_consoles.generation, ninupdates_reports.postproc_runfinished FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.log='report' && ninupdates_reports.reportdate='".$reportdate."' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."'";
		$result=mysqli_query($mysqldb, $query);
		$numrows=mysqli_num_rows($result);

		if($numrows==0)
		{
			wikibot_writelog("Failed to find the report with reportdate $reportdate.", 0, $reportdate);
			return 12;
		}

		$row = mysqli_fetch_row($result);
		$timestamp = date_timestamp_get(date_create_from_format(DateTimeInterface::RFC822, $row[0]));
		$system_generation = $row[1];
		$postproc_runfinished = $row[2];
	}
	else
	{
		$timestamp = time();
		$system_generation = 0;
		$postproc_runfinished = 0;
	}

	$updateversion_norebootless = $updateversion;
	$rebootless_flag = False;
	$rebootless_pos = strpos($updateversion, "_rebootless");
	if($rebootless_pos!==FALSE)
	{
		wikibot_writelog("This report updateversion is rebootless, skipping handling for the relevant pages.", 2, $reportdate);

		$rebootless_flag = True;
		$updateversion_norebootless = substr($updateversion, 0, $rebootless_pos);
	}

	//try
	//{
		$api = new \Mediawiki\Api\MediawikiApi("$wiki_apibaseurl/api.php");

		//$api = new Wikimate($wiki_apibaseurl . "/api.php");

		if(!isset($wikibot_user) || !isset($wikibot_pass))
		{
			wikibot_writelog("Wikibot account config isn't setup, skipping login(edited pages will not be uploaded).", 0, $reportdate);
		}
		else
		{
			//if($api->login($wikibot_user, $wikibot_pass)===TRUE)
			if($api->login(new \Mediawiki\Api\ApiUser($wikibot_user, $wikibot_pass))===TRUE)
			{
				echo "Successfully logged into the wiki.\n";
				wikibot_writelog("Successfully logged into the wiki.", 1, $reportdate);
				$wikibot_loggedin = 1;
			}
			else
			{
				//$error = $api->getError();
				wikibot_writelog("Wiki login failed.", 0, $reportdate);
				return 7;
			}
		}

		$services = new \Mediawiki\Api\MediawikiFactory($api);
	//}

	/*catch(UsageException $e)
	{
		echo "Exception caught.\n";
		wikibot_writelog("Wiki api error: " . $e->getMessage(), 0, $reportdate);
		return 7;
	}*/

	if($wikibot_loggedin == 1)
	{
		$text = "Remote page editing is enabled.";
	}
	else
	{
		$text = "Remote page editing is disabled.";
	}

	wikibot_writelog($text, 2, $reportdate);

	$page = NULL;
	if($wikigen_path!="")
	{
		$tmpret = wikibot_process_wikigen($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, wikigen_path: $wikigen_path);
		if($ret==0) $ret = $tmpret;

		if($wikibot_loggedin == 1)$api->logout();

		echo "Wikibot run finished.\n";

		return $ret;
	}

	$newspage = $services->newPageGetter()->getFromTitle($wiki_newspagetitle);
	$revision = $newspage->getRevisions()->getLatest();
	$newspage_text = $revision->getContent()->getData();

	$newsarchivepage = $services->newPageGetter()->getFromTitle($wiki_newsarchivepagetitle);
	$revision = $newsarchivepage->getRevisions()->getLatest();
	$newsarchivepage_text = $revision->getContent()->getData();

	$updatelisted = 0;

	$sysupdate_date = gmdate("j F y", $timestamp);

	$news_searchtext = "update [[$updateversion]]";
	$insertstring = "*'''$sysupdate_date''' Nintendo released system ".$news_searchtext.".";

	if($rebootless_flag===True)
	{
		$insertstring = "*'''$sysupdate_date''' Nintendo released a rebootless system update for [[".$updateversion_norebootless."]].";
		$news_searchtext = $insertstring;
	}
	if(strstr($newspage_text, $news_searchtext)!==FALSE)
	{
		$updatelisted = 1;
	}
	else if(strstr($newsarchivepage_text, $news_searchtext)!==FALSE)
	{
		$updatelisted = 2;
	}

	if($updatelisted)
	{
			wikibot_writelog("This updatever is already listed on the wiki news.", 2, $reportdate);
	}
	else
	{
		$tmpret = wikibot_updatenewspages($api, $services, $updateversion, $reportdate, $timestamp, $newspage_text, $newsarchivepage_text, $newspage, $newsarchivepage, $insertstring."\n");
		if($ret==0) $ret = $tmpret;
	}

	if($wiki_homemenutitle!="")
	{
		$page = $services->newPageGetter()->getFromTitle($wiki_homemenutitle);
		$revision = $page->getRevisions()->getLatest();
		$homemenu_page_text = $revision->getContent()->getData();
		/*if($homemenu_page===FALSE || $homemenu_page->exists()==FALSE)
		{
			wikibot_writelog("Failed to get the home-menu page.", 0, $reportdate);
		}
		else
		{*/
			$tmpret = wikibot_updatepage_homemenu($api, $services, $updateversion, $reportdate, $timestamp, $page, $homemenu_page_text);
			if($ret==0) $ret = $tmpret;
		//}
	}

	/*$page = $services->newPageGetter()->getFromTitle($updateversion);
	$revision = $page->getRevisions()->getLatest();
	$sysupdate_page = $revision->getContent()->getData();*/
	$page=0;

	/*if($sysupdate_page===FALSE || $sysupdate_page->exists()==FALSE)
	{
		wikibot_writelog("The sysupdate page doesn't exist.", 1, $reportdate);
	}
	else
	{*/
		//wikibot_writelog("Sysupdate page:\n".$sysupdate_page, 1, $reportdate);
	//}

	$tmpret = wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $rebootless_flag, $updateversion_norebootless, $system_generation, $postproc_runfinished);
	if($ret==0) $ret = $tmpret;

	$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);

		$tmp_reportdate = $row[0];

		if($tmp_reportdate === $reportdate)
		{
			if($rebootless_flag===False)
			{
				$tmpret = wikibot_edit_firmwarenews($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);
				if($ret==0) $ret = $tmpret;
			}

			if($postproc_runfinished!=0)
			{
				$tmpret = wikibot_process_wikigen($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri);
				if($ret==0) $ret = $tmpret;
			}
			else
			{
				wikibot_writelog("Skipping wikigen handling since the report post-processing isn't finished.", 2, $reportdate);
			}
		}
		else
		{
			wikibot_writelog("Skipping FirmwareNews and wikigen page handling since this report isn't the latest one.", 2, $reportdate);
		}
	}

	echo "Updating the report's wikibot_runfinished field...\n";
	$query="UPDATE ninupdates_reports, ninupdates_consoles SET ninupdates_reports.wikibot_runfinished=1 WHERE reportdate='".$reportdate."' && ninupdates_consoles.system='".$system."' && ninupdates_reports.systemid=ninupdates_consoles.id";
	$result=mysqli_query($mysqldb, $query);

	if($wikibot_loggedin == 1)$api->logout();

	echo "Wikibot run finished.\n";

	return $ret;
}

$wikibot_cmdtype = 0;

if($argc<3)
{
	if($argc != 2 || $argv[1]!=="scheduled")
	{
		echo "Usage:\nphp wikibot.php <updateversion> <reportdate> <system>\n";
		echo "php wikibot.php scheduled\n";
		echo "php wikibot.php <system> <--wikigen=path>\n";
		return 0;
	}
	else
	{
		$wikibot_cmdtype = 1;
	}
}
else if($argc==3)
{
	if(substr($argv[2], 0, 10)=="--wikigen=")
	{
		$wikibot_cmdtype = 2;
		$wikibot_wikigen_path = substr($argv[2], 10);
	}
	else
	{
		echo "Usage:\nphp wikibot.php <system> <--wikigen=path>\n";
		return 0;
	}
}

dbconnection_start();

$lock_fp = fopen("$sitecfg_workdir/wikibot_lock", "w");
if($lock_fp===FALSE)
{
	echo "Failed to open lock file.\n";
	exit(1);
}

if(!flock($lock_fp, LOCK_EX))
{
	echo "Failed to obtain the lock.\n";
	exit(1);
}

if($wikibot_cmdtype == 0)
{
	$updateversion = mysqli_real_escape_string($mysqldb, $argv[1]);
	$reportdate = mysqli_real_escape_string($mysqldb, $argv[2]);
	$system = mysqli_real_escape_string($mysqldb, $argv[3]);

	runwikibot_newsysupdate($updateversion, $reportdate);
}
else if($wikibot_cmdtype == 2)
{
	$updateversion = "N/A";
	$reportdate = "wikigen";
	$system = mysqli_real_escape_string($mysqldb, $argv[1]);

	$ret = runwikibot_newsysupdate($updateversion, $reportdate, $wikibot_wikigen_path);
	if($ret!=0)
	{
		echo "Sending notif since an error occured...\n";
		$msg = "wikibot: An error occured while processing --wikigen for $system.";
		send_notif([$msg, "--admin"]);
	}
}
else
{
	$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE updatever_autoset=1 && wikibot_runfinished=0 && ninupdates_reports.systemid=ninupdates_consoles.id";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);

	echo "Doing a scheduled wikibot run for $numrows reports...\n";

	for($i=0; $i<$numrows; $i++)
	{
		$row = mysqli_fetch_row($result);
		$reportdate = $row[0];
		$updateversion = $row[1];
		$system = $row[2];

		echo "Starting wikibot processing with the following report: $reportdate-$system, updateversion=$updateversion.";
		echo "\n";

		$ret = runwikibot_newsysupdate($updateversion, $reportdate);
		if($ret!=0)
		{
			echo "Sending notif since an error occured...\n";
			$msg = "wikibot: An error occured while processing $reportdate-$system.";
			send_notif([$msg, "--admin"]);
		}

		echo "Wikibot processing for this report finished.\n";

		if($i != $numrows-1)echo "\n";
	}
}

fclose($lock_fp);

dbconnection_end();

?>
