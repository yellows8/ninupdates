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

	wikibot_writelog("The news-pages were updated.", 2, $reportdate);
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

	wikibot_writelog("Home Menu page updated.", 2, $reportdate);
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

	wikibot_writelog("NavboxVersions page updated.", 2, $reportdate);
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

function wikibot_strip_titledesc($desc)
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
		else
		{
			$end_pos = strpos($desc, " (");
			if($end_pos!==FALSE) $desc = substr($desc, 0, $end_pos);
		}
	}
	return $desc;
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
					$desc = wikibot_strip_titledesc($desc);
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

function wikibot_edit_titlelist($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $updateversion_norebootless)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase;

	wikibot_writelog("wikibot_edit_titlelist(): Loading report titlelisting, then generating the titlelist wikigen...", 2, $reportdate);

	$wikigen_data = array();

	$report_titlelist = report_get_titlelist($system, $reportdate);
	if(count($report_titlelist)==0)
	{
		wikibot_writelog("wikibot_edit_titlelist(): report_titlelist is empty.", 0, $reportdate);
		return 1;
	}

	$wikigen_page = array();
	$wikigen_page["page_title"] = "Title_list";

	$targets = array();

	for($i=0; $i<4; $i++)
	{
		$search_section = "= System Modules =";
		if($i==1) $search_section = "= System Data Archives =";
		else if($i==2) $search_section = "= System Applets =";
		else if($i==3) $search_section = "= System Applications =";

		$target["search_section"] = $search_section;

		$target["insert_row_tables"] = array();
		$target["table_lists"] = array();

		$targets[] = $target;
	}

	foreach($report_titlelist as &$title)
	{
		$title_type=NULL;
		// Ignore the platform byte.
		if(substr($title["titleid"], 2, 12)==="000000000000")
		{
			$title_type=0;
			$target = &$targets[0];
		}
		else if(substr($title["titleid"], 2, 12)==="000000000008")
		{
			$title_type=1;
			$target = &$targets[1];
		}
		else if(substr($title["titleid"], 2, 11)==="00000000001")
		{
			$title_type=2;
			$target = &$targets[2];
		}
		else
		{
			$title_type=3;
			$target = &$targets[3];
		}

		$tmpdata = array();
		$ver = "v" . $title["version"];
		$intver = intval($title["version"]);
		$verparse = (($intver>>26)&0x3F) . "." . (($intver>>20)&0x3F) . "." . (($intver>>16)&0xF) . "." . ($intver&0xFFFF);
		$ver_entry = "[[".$updateversion_norebootless."|$ver]] ($verparse)";

		$desc = $title["description"];
		if($desc==="N/A")
		{
			$desc = "";
		}
		else
		{
			$desc = wikibot_strip_titledesc($desc);
		}

		if($title["status"]==="New")
		{
			$desc_prefix = "[".$updateversion."+]";
			$desc = $desc_prefix." ".$desc;

			$tmpdata["search_text"] = $title["titleid"];
			$tmpdata["search_column"] = 0;
			$tmpdata["sort"] = 0;
			$tmpdata["sort_columnlen"] = 16;
			$tmpdata["columns"] = [$title["titleid"], $ver_entry, $desc];
			if($title_type!=0) $tmpdata["columns"][] = "";

			$table_lists = array();

			// Version column.
			$table_list = array();
			$table_list["target_text_prefix"] = $title["titleid"];
			$table_list["delimiter"] = "<br/> ";
			$table_list["target_column"] = 1;
			$table_list["search_text"] = $ver;
			$table_list["insert_text"] = $ver_entry;
			$table_lists[] = $table_list;

			// Description/Name column.
			$table_list = array();
			$table_list["target_text_prefix"] = $title["titleid"];
			$table_list["delimiter"] = "<br/> ";
			$table_list["target_column"] = 2;
			$table_list["search_text"] = $desc_prefix;
			$table_list["insert_text"] = $desc;

			$findreplace_list = array();
			$findreplace_entry = array();

			$findreplace_entry["find_text"] = "(currently not present on retail devices)";
			$findreplace_entry["replace_text"] = "";

			$findreplace_list[] = $findreplace_entry;

			$table_list["findreplace_list"] = $findreplace_list;
			$table_lists[] = $table_list;

			$tmpdata["table_lists"] = $table_lists;

			$target["insert_row_tables"][] = $tmpdata;
		}
		else if($title["status"]==="Changed")
		{
			$tmpdata["target_text_prefix"] = $title["titleid"];
			$tmpdata["delimiter"] = "<br/> ";
			$tmpdata["target_column"] = 1;
			$tmpdata["search_text"] = $ver;
			$tmpdata["insert_text"] = $ver_entry;
			$target["table_lists"][] = $tmpdata;
		}
		else
		{
			wikibot_writelog("wikibot_edit_titlelist(): Invalid status for titleid $titleid: $status", 0, $reportdate);
		}
	}

	$wikigen_page["targets"] = $targets;
	$wikigen_data[] = $wikigen_page;

	return wikibot_process_wikigen($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, wikigen_data: $wikigen_data);
}

function wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $rebootless_flag, $updateversion_norebootless, $system_generation, $postproc_runfinished, $report_latest_flag)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_httpbase, $wikicfgid;

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

	$query="SELECT ninupdates_reports.regions, ninupdates_reports.id, ninupdates_consoles.sysname, ninupdates_consoles.system, ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE ninupdates_reports.updateversion='".$updateversion."' && ninupdates_reports.systemid=ninupdates_consoles.id && wikibot_runfinished=0 && ninupdates_consoles.wikicfgid=$wikicfgid";
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
		wikibot_writelog("Sysupdate page updated.", 2, $reportdate);
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
		$title = new \Mediawiki\DataModel\Title($updateversion_norebootless);
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
		wikibot_writelog($text, 2, $reportdate);

		if($report_latest_flag===True)
		{
			$text = "Sending notif...";
			wikibot_writelog($text, 2, $reportdate);

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
		else
		{
			$text = "Skipping sending notif since this isn't the latest report.";
			wikibot_writelog($text, 2, $reportdate);
		}
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

	wikibot_writelog("wikibot_edit_firmwarenews(): curupdatetext: $curupdatetext, reportupdatetext: $reportupdatetext", 2, $reportdate);

	if($curupdatetext === $reportupdatetext)
	{
		wikibot_writelog("curupdatetext and reportupdatetext already match.", 2, $reportdate);
		return 0;
	}

	wikibot_writelog("wikibot_edit_firmwarenews(): curupdatetext and reportupdatetext don't match, generating new page-text...", 2, $reportdate);

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

function wikibot_parse_table($section_text, $section_pos_table, &$table_columns, &$table_columns_pos, &$use_single_line)
{
	$lines = explode("\n", $section_text);
	$found = False;
	$num_columns=0;

	$tmpdata = array();
	$linepos = $section_pos_table;
	$tmpdata_pos = array();
	$table_columns = array();
	$table_columns_pos = array();
	$use_single_line = False;
	$lines_count = count($lines);

	for($i=0; $i<$lines_count; $i++)
	{
		$line_prefix = substr($lines[$i], 0, 2);
		if($line_prefix==="{|" || $line_prefix==="! ")
		{
			$linepos+= strlen($lines[$i])+1;
			continue;
		}
		if($line_prefix==="|-" || $line_prefix==="|}" || ($i==$lines_count-1 && strlen($lines[$i])==0))
		{
			if(count($tmpdata)>0)
			{
				$table_columns[] = $tmpdata;
				$table_columns_pos[] = $tmpdata_pos;
				$tmpdata = array();
				$tmpdata_pos = array();
				$tmpdata_pos["columns"] = array();
			}
			if($line_prefix==="|}") break;
		}
		else
		{
			$curline = substr($lines[$i], 2);
			$curpos = strpos($curline, " ||");
			$colpos = 0;
			if($curpos!==FALSE)
			{
				if($use_single_line===False) $use_single_line = True;
				while($curpos!==FALSE)
				{
					$tmpdata[] = substr($curline, $colpos, $curpos-$colpos);
					$tmpdata_pos["columns"][] = $linepos+2+$colpos;
					$colpos = $curpos+3;
					$curpos = strpos($curline, " ||", $colpos);
					$colpos++;
				}
				$tmp = substr($curline, $colpos);
				if(strlen($tmp)>0)
				{
					$tmpdata[] = $tmp;
					$tmpdata_pos["columns"][] = $linepos+2+$colpos;
				}
			}
			else
			{
				$tmp = substr($curline, $colpos);
				$tmpdata[] = $tmp;
				$tmpdata_pos["columns"][] = $linepos+2+$colpos;
			}

			if(!isset($tmpdata_pos["row"]))
			{
				$tmpdata_pos["row"] = $linepos;
			}
		}
		$linepos+= strlen($lines[$i])+1;
	}

	if(count($tmpdata)>0)
	{
		$table_columns[] = $tmpdata;
		$table_columns_pos[] = $tmpdata_pos;
		$tmpdata = array();
	}
}

function wikibot_parse_rowspan(&$tmp_column, &$rowspan, &$updated_column=NULL)
{
	$rowspan=0;
	$tmp_pos = strpos($tmp_column, "rowspan=\"");
	if($tmp_pos!==FALSE)
	{
		$tmp_pos_end = strpos($tmp_column, "\" |", $tmp_pos);
		if($tmp_pos_end!==FALSE)
		{
			$rowspan = intval(substr($tmp_column, $tmp_pos+9, $tmp_pos_end-($tmp_pos+9)));
			if($updated_column!==NULL)
			{
				$rowspan+=1;
				$updated_column = substr($tmp_column, 0, $tmp_pos+9) . $rowspan . substr($tmp_column, $tmp_pos_end);
			}
			$tmp_column = substr($tmp_column, $tmp_pos_end+3);
		}
	}
}

function wikibot_check_search_text($search_type, $search_text, $tmp_column)
{
	if(($search_type==0 && strpos($tmp_column, $search_text)!==FALSE) || ($search_type==1 && $tmp_column === $search_text))
	{
		return True;
	}
	else if($search_type==2)
	{
		foreach($search_text as $cur_search)
		{
			if(strpos($tmp_column, $cur_search)!==FALSE)
			{
				return True;
			}
		}
	}
	return False;
}

function wikibot_print_search_text($search_type, $search_text)
{
	if($search_type==2)
	{
		$tmpstr = "[";
		foreach($search_text as $cur_search)
		{
			if(strlen($tmpstr)!=1) $tmpstr.= ", ";
			$tmpstr.= "\"$cur_search\"";
		}
		$tmpstr.= "]";
		return $tmpstr;
	}
	else
	{
		return "\"$search_text\"";
	}
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
				"search_section_end": "{search end text}", # Defaults to "|}" (wikitable end) if not specified. The default is "" if full_page is true. This is the text to search for relative to search_section which is used as the end-pos for the section to edit (that is, the edit-section will be the text immediately before this). If "", then the page-end is used as the section-end instead.
				"full_page": true, # If specified, the above search_section fields can be optional with the section starting at the page start. See also search_section_end above.

				"parse_tables": [ # Optional array of parse_table. Parse tables for use with text_sections below.
					"page_title": "{wiki page title}", # The specified page must exist on the wiki, otherwise this entry is skipped. If there's multiple entries with the same page_title, the entries following the first one are skipped.
					"search_section": "{search text}", # Optional. The located text is used as the starting pos for locating the table (with page-start being the default).
				],

				"text_sections": [ # Optional array of text_section. Insert raw text.
					{
						"search_text": "{text}", # Text to search for within the edit-section to determine whether editing is needed. If found, editing this text_section is skipped.
						"insert_text": "{text}", # Text to insert. If insert_before_text is not specified, "\n" is added prior to the insert_text. If present, there's special handling for each instance of "!TABLE[{args}]", which uses the data from parse_tables above. Each arg is seperated by ",". There's at least 4 args: target_page (must match parse_table page_title), target_column (which column to use for target_text), target_text (if column data contains this then the target_load_column is loaded), target_load_column (which column to load when a match is found). Optional additional args can be specified: if "NOLINK" is specified, then links are stripped from the column data. Arg "DEFAULT={column_data_default}" sets the column data to use when the target row isn't found, instead of target_text. Arg "MATCH={EXACT|STRPOS}" controls how to compare the target_column data with target_text: EXACT indicates the column must be an exact match, while STRPOS indicates strpos() is used (which is the default). The "!TABLE[{args}]" text is replaced with the column data, if a matching row isn't found column_data_default is used as the column data.
						"insert_before_text": "{text}" # Optional. By default insert_text is inserted at the end of the edit-section. If this is specified, the text is inserted at the pos of the specified text.
					},
				],

				"insert_row_tables": [ # Optional array of insert_row_table. Insert a row at the end of a table.
					"search_text": "{text}", # Raw text to search for within the edit-section to determine whether editing is needed (text for rowspan is skipped if needed). If found, editing this insert_row_table is skipped.
					"search_text_rowspan": "{text}", # Same as search_text except for rowspan.
					"search_column": {integer}, # Optional. Normally search_text is used raw for the text-search. If this is specified, the search is instead done on each table row with the specified column (0 is the first column).
					"search_column_rowspan": {integer}, # Optional. Only used if 'rowspan' is present in the column data specified by search_column. Used with search_text_rowspan to determine whether editing is needed. Must be >=1 since this is decreased by 1 after the first entry.
					"search_type": {integer}, # Optional, defaults to 0. Only used if search_column is specified, same as val0 otherwise. Controls the method to use for doing the search with search_text. 0 = strpos, 1 = exact match, 2 = strpos with search_text as an array of strings (only 1 matching entry needs to be found).
					"search_type_rowspan": {integer}, # Same as search_type except for rowspan.
					"sort": {integer}, # If specified, search_text is used for comparing against the first column in each table row. This is used for determining where to insert the row, with fallback to table-end if not found. 0 = regular compare, 1 = {same as 0 except intval is used}. search_type=2 is not supported when sort is specified.
					"sort_columnlen": {integer}, # If specified, the column value used by sort must match the specified length otherwise an error is thrown.
					"table_lists": [], # Optional array of table_list to append to the below table_lists array when search_text is found successfully.
					"rowspan_edit_prev": [ # Optional array, when rowspan is present this edits the previous row which is within the same rowspan.
						{
							"column": {integer}, # Which column to edit. This must be >=1, when the edited row is not the first row within the rowspan this is decreased by 1.
							"findreplace_list": [], # Same as findreplace_list in table_lists below.
						},
					],
					"columns": [ # Array of text strings for each column. The inserted text is "| " followed by the string then newline. If the string is "!LAST", the value from the table row prior to the inserted row is used for this column. If the string is "!TIMESTAMP", an UTC date is used as the column string (report timestamp, otherwise for wikigen-argv it's from time()).
					],
				],

				"table_lists": [ # Optional array of table_list. Insert text into a table column.
					{
						"target_text_prefix": "{text}", # Line to update is located by searching for "| " followed by the specified target_text_prefix.
						"delimiter": "{text}", # Optional delimiter to use with insert_text, defaults to ", ".
						"insert_before_text": "{text}", # Optional. By default insert_text is inserted at the end of the table line. If this is specified, the text is inserted at the pos of the specified text.
						"target_column": {integer}, # Optional column to edit within the same line, seperated by " ||". If specified, insert_before_text is ignored and the text will be inserted at the end of the specified column. The first column (before the first " ||") is value 0, which is the default. This is only enabled when >0.
						"search_text": "{text}", # Text to search for within the table line to determine whether editing is needed. If found, editing this table_list is skipped.
						"findreplace_list": [ # Optional array containing the following entry data. The entry data is used with the raw line text and str_replace, prior to handling insert_text.
							{
								"find_text": {str}, # Text string to find with str_replace.
								"replace_text": {str}, # Text string to replace with str_replace.
							},
						],
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

function wikibot_process_wikigen($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, &$in_page_text=NULL, &$out_page_updated=NULL, $wikigen_path="", $wikigen_data=NULL)
{
	global $mysqldb, $system, $wikibot_loggedin, $sitecfg_workdir;

	if($wikigen_data===NULL)
	{
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
	}
	else
	{
		$wikigen = $wikigen_data;
	}

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
				wikibot_writelog("wikibot_process_wikigen($pagetitle): Page doesn't exist, skipping this page entry.", 2, $reportdate);

				continue;
			}

			wikibot_writelog("wikibot_process_wikigen($pagetitle): Page exists, generating new page text if needed...", 2, $reportdate);

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
			$search_section = "";
			$full_page = False;
			if(isset($wikigen_target["full_page"]))
			{
				$full_page = $wikigen_target["full_page"];
			}
			if(isset($wikigen_target["search_section"]))
			{
				$search_section = $wikigen_target["search_section"];
			}
			else if($page_search_section=="" && $full_page==False)
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): json search_section field(s) aren't set, skipping this entry.", 2, $reportdate);

				continue;
			}

			$page_section_pos = 0;
			if($page_search_section!="")
			{
				$page_section_pos = strpos($page_text, $page_search_section);
				if($page_section_pos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the text for page search_section, search_section: \"$page_search_section\"", 0, $reportdate);
					if($ret==0) $ret=1;
					continue;
				}
			}

			if($search_section!="")
			{
				$section_pos = strpos($page_text, $search_section, $page_section_pos);
				if($section_pos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the text for target search_section, search_section: \"$search_section\"", 0, $reportdate);
					if($ret==0) $ret=1;
					continue;
				}
			}
			else
			{
				$section_pos = $page_section_pos;
			}

			if($full_page==False)
			{
				$search_section_end = "|}";
			}
			else
			{
				$search_section_end = "";
			}
			if(isset($wikigen_target["search_section_end"]))
			{
				$search_section_end = $wikigen_target["search_section_end"];
			}

			$array_set_count = 0;
			$array_counts = 0;
			$parse_tables = array();
			$text_sections = array();
			$insert_row_tables = array();
			$table_lists = array();
			$tables_updatever_range = array();

			$parse_tables_data = array();

			if(isset($wikigen_target["parse_tables"]))
			{
				$parse_tables = $wikigen_target["parse_tables"];
				$array_set_count++;
				$array_counts+= count($parse_tables);
			}

			if(isset($wikigen_target["text_sections"]))
			{
				$text_sections = $wikigen_target["text_sections"];
				$array_set_count++;
				$array_counts+= count($text_sections);
			}

			if(isset($wikigen_target["insert_row_tables"]))
			{
				$insert_row_tables = $wikigen_target["insert_row_tables"];
				$array_set_count++;
				$array_counts+= count($insert_row_tables);
			}

			if(isset($wikigen_target["table_lists"]))
			{
				$table_lists = $wikigen_target["table_lists"];
				$array_set_count++;
				$array_counts+= count($table_lists);
			}

			if(isset($wikigen_target["tables_updatever_range"]))
			{
				$tables_updatever_range = $wikigen_target["tables_updatever_range"];
				$array_set_count++;
				$array_counts+= count($tables_updatever_range);
			}

			if($array_set_count==0)
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): None of the json arrays are set, skipping this target entry.", 2, $reportdate);

				continue;
			}

			if($array_counts==0)
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): The json array(s) are empty, skipping this target entry.", 2, $reportdate);

				continue;
			}

			foreach ($parse_tables as $parse_table)
			{
				if(isset($parse_table["page_title"]))
				{
					$parsetable_pagetitle = $parse_table["page_title"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json fields used by parse_table isn't set, skipping this entry.", 2, $reportdate);

					continue;
				}

				if(isset($parse_tables_data[$parsetable_pagetitle]))
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The specified parse_table($parsetable_pagetitle) already had a matching page previously loaded, ignoring this entry.", 0, $reportdate);
					continue;
				}

				$tmp_page = $services->newPageGetter()->getFromTitle($parsetable_pagetitle);
				$tmp_revision = $tmp_page->getRevisions()->getLatest();

				if($tmp_revision===NULL)
				{
					wikibot_writelog("wikibot_process_wikigen($parsetable_pagetitle): Page doesn't exist, skipping this parse_table entry.", 2, $reportdate);

					continue;
				}

				$parsetable_page_text = $tmp_revision->getContent()->getData();

				$parsetable_search_section = "";
				if(isset($parse_table["search_section"]))
				{
					$parsetable_search_section = $parse_table["search_section"];
					$parsetable_section_pos = strpos($parsetable_page_text, $parsetable_search_section);
					if($parsetable_section_pos===FALSE)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the text for parse_table($parsetable_pagetitle) search_section, search_section: \"$parsetable_search_section\"", 0, $reportdate);
						if($ret==0) $ret=1;
						continue;
					}
				}
				else
				{
					$parsetable_section_pos = 0;
				}

				$section_pos_table = strpos($parsetable_page_text, "{|", $parsetable_section_pos);
				if($section_pos_table===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): parse_table($parsetable_pagetitle): Failed to find the table start, search_section: \"$parsetable_search_section\"", 0, $reportdate);
					if($ret==0) $ret=1;
					continue;
				}

				$table_endpos = strpos($parsetable_page_text, "|}", $section_pos_table);
				if($table_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the table end for parse_table($parsetable_pagetitle).", 2, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$section_text = substr($parsetable_page_text, $section_pos_table, $table_endpos-$section_pos_table);

				$table_columns = array();
				$table_columns_pos = array();
				$use_single_line = False;
				wikibot_parse_table($section_text, $section_pos_table, $table_columns, $table_columns_pos, $use_single_line);

				$table_columns_count = count($table_columns);
				if($table_columns_count==0)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The table for parse_table is empty.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$parse_tables_data[$parsetable_pagetitle] = $table_columns;
			}

			foreach($text_sections as $text_section)
			{
				if($search_section_end!=="")
				{
					$section_endpos = strpos($page_text, $search_section_end, $section_pos);
				}
				else
				{
					$section_endpos = strlen($page_text);
				}
				if($section_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): text_section: Failed to find the section end, search_section_end: \"$search_section_end\"", 0, $reportdate);
					if($ret==0) $ret=2;
					break;
				}

				$section_text = substr($page_text, $section_pos, $section_endpos-$section_pos);

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
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This text already exists in the section for text_section, search_text: \"$search_text\"", 2, $reportdate);
					continue;
				}

				$errorflag = False;
				while(($curpos = strpos($insert_text, "!TABLE["))!==FALSE)
				{
					$endpos = strpos($insert_text, "]", $curpos);
					if($endpos===FALSE)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find ']' following '!TABLE[' for text_section. search_text: \"$search_text\"", 0, $reportdate);
						if($ret==0) $ret=2;
						$errorflag = True;
						break;
					}

					$text_args = explode(",", substr($insert_text, $curpos+7, $endpos-$curpos-7));
					$text_args_count = count($text_args);
					if($text_args_count<4)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): Invalid number of text_args for '!TABLE[' for text_section. search_text: \"$search_text\"", 0, $reportdate);
						if($ret==0) $ret=2;
						$errorflag = True;
						break;
					}

					$target_page = $text_args[0];
					$target_column = $text_args[1];
					$target_text = $text_args[2];
					$target_load_column = $text_args[3];

					$strip_link = False;
					$column_data_default = $target_text;
					$match_type = 1;

					if($text_args_count>=5)
					{
						for($argi=4; $argi<$text_args_count; $argi++)
						{
							if($text_args[$argi]==="NOLINK")
							{
								$strip_link = True;
							}
							else if(substr($text_args[$argi], 0, 8)==="DEFAULT=")
							{
								$column_data_default = substr($text_args[$argi], 8);
							}
							else if(substr($text_args[$argi], 0, 6)==="MATCH=")
							{
								$match_str = substr($text_args[$argi], 6);
								if($match_str==="EXACT")
								{
									$match_type = 0;
								}
								else if($match_str==="STRPOS")
								{
									$match_type = 1;
								}
							}
						}
					}

					if(!isset($parse_tables_data[$target_page]))
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): The page($target_page) required by '!TABLE[' for text_section was not loaded. search_text: \"$search_text\"", 0, $reportdate);
						if($ret==0) $ret=2;
						$errorflag = True;
						break;
					}

					$table_columns_count = count($parse_tables_data[$target_page]);
					$column_data = NULL;
					for($i=0; $i<$table_columns_count; $i++)
					{
						$tmpcount = count($parse_tables_data[$target_page][$i]);
						if($target_column>=$tmpcount || $target_load_column>=$tmpcount)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): The target columns specified for '!TABLE[' for text_section are not valid for this row. tmpcount=$tmpcount, target_column=$target_column, target_load_column=$target_load_column. search_text: \"$search_text\"", 0, $reportdate);
							if($ret==0) $ret=2;
							$errorflag = True;
							break;
						}

						if(($match_type==0 && $parse_tables_data[$target_page][$i][$target_column]===$target_text) || ($match_type==1 && strpos($parse_tables_data[$target_page][$i][$target_column], $target_text)!==FALSE))
						{
							$column_data = $parse_tables_data[$target_page][$i][$target_load_column];
							break;
						}
					}
					if($errorflag) break;

					if($column_data===NULL)
					{
						$column_data = $column_data_default;
						wikibot_writelog("wikibot_process_wikigen($pagetitle): The target_text for '!TABLE[' for text_section was not found in the table, using column_data_default ($column_data_default) as the column_data. search_text: \"$search_text\"", 2, $reportdate);
						if($ret==0) $ret=2;
					}
					else if($strip_link===True)
					{
						$link_startpos = strpos($column_data, "[[");
						if($link_startpos!==FALSE)
						{
							$link_startpos = strpos($column_data, "|", $link_startpos);
							if($link_startpos!==FALSE)
							{
								$link_startpos+=1;
								$link_endpos = strpos($column_data, "]]", $link_startpos);
								if($link_endpos!==FALSE)
								{
									$column_data = substr($column_data, $link_startpos, $link_endpos-$link_startpos);
								}
							}
						}
					}

					$insert_text = substr($insert_text, 0, $curpos) . $column_data . substr($insert_text, $endpos+1);
				}
				if($errorflag) continue;

				$insert_pos = strlen($section_text);

				if(isset($text_section["insert_before_text"]))
				{
					$insert_before_text = $text_section["insert_before_text"];
					$insert_pos = strpos($section_text, $insert_before_text);
					if($insert_pos===FALSE)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the insert_before_text for text_section. search_text: \"$search_text\"", 0, $reportdate);
						if($ret==0) $ret=2;
						continue;
					}
				}
				else
				{
					$insert_text = "\n" . $insert_text;
				}

				wikibot_writelog("wikibot_process_wikigen($pagetitle): Successfully edited the page for text_section. search_text: \"$search_text\"", 2, $reportdate);

				$section_text = substr($section_text, 0, $insert_pos) . $insert_text . substr($section_text, $insert_pos);
				$page_text = substr($page_text, 0, $section_pos) . $section_text . substr($page_text, $section_endpos);
				$page_updated = True;
			}

			foreach ($insert_row_tables as $insert_row_table)
			{
				$section_pos_table = strpos($page_text, "{|", $section_pos);
				if($section_pos_table===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): insert_row_table: Failed to find the table start, search_section: \"$search_section\"", 0, $reportdate);
					if($ret==0) $ret=1;
					continue;
				}

				if($search_section_end!=="")
				{
					$section_endpos = strpos($page_text, $search_section_end, $section_pos_table);
				}
				else
				{
					$section_endpos = strlen($page_text);
				}
				if($section_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): insert_row_table: Failed to find the section end, search_section_end: \"$search_section_end\"", 0, $reportdate);
					if($ret==0) $ret=2;
					break;
				}

				$section_text = substr($page_text, $section_pos_table, $section_endpos-$section_pos_table);

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

				$search_column = -1;
				if(isset($insert_row_table["search_column"]))
				{
					$search_column = $insert_row_table["search_column"];
				}

				$search_column_rowspan = -1;
				if(isset($insert_row_table["search_column_rowspan"]))
				{
					$search_column_rowspan = $insert_row_table["search_column_rowspan"];
				}

				$search_type = 0;
				if(isset($insert_row_table["search_type"]))
				{
					$search_type = $insert_row_table["search_type"];
				}

				$search_type_rowspan = 0;
				if(isset($insert_row_table["search_type_rowspan"]))
				{
					$search_type_rowspan = $insert_row_table["search_type_rowspan"];
				}

				$search_text_rowspan = "";
				if(isset($insert_row_table["search_text_rowspan"]))
				{
					$search_text_rowspan = $insert_row_table["search_text_rowspan"];
				}

				$search_text_print = wikibot_print_search_text($search_type, $search_text);
				$search_text_rowspan_print = wikibot_print_search_text($search_type_rowspan, $search_text_rowspan);

				$enable_sort = FALSE;
				$sort_type=0;
				if(isset($insert_row_table["sort"]))
				{
					$enable_sort = TRUE;
					$sort_type = $insert_row_table["sort"];
				}

				$sort_columnlen = 0;
				if(isset($insert_row_table["sort_columnlen"]))
				{
					$sort_columnlen = $insert_row_table["sort_columnlen"];
				}

				$columns_count = count($columns);

				$extra_table_lists = NULL;
				if(isset($insert_row_table["table_lists"]))
				{
					$extra_table_lists = $insert_row_table["table_lists"];
				}

				$rowspan_edit_prev = array();
				if(isset($insert_row_table["rowspan_edit_prev"]))
				{
					$rowspan_edit_prev = $insert_row_table["rowspan_edit_prev"];
				}

				if($search_column==-1 && strpos($section_text, $search_text)!==FALSE)
				{
					$tmpstr = "";
					if($extra_table_lists!==NULL)
					{
						$tmpstr = " The input table_lists from this insert_row_table will be processed later.";
						foreach($extra_table_lists as $tmp_extra) $table_lists[] = $tmp_extra;
					}
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This text already exists in the section for insert_row_table, search_text: $search_text_print.$tmpstr", 2, $reportdate);
					continue;
				}

				$table_endpos = strpos($page_text, "|}", $section_pos);
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

				$table_columns = array();
				$table_columns_pos = array();
				$use_single_line = False;
				wikibot_parse_table($section_text, $section_pos_table, $table_columns, $table_columns_pos, $use_single_line);

				$table_columns_count = count($table_columns);
				if($table_columns_count==0)
				{
					$last_table_column = NULL;
					$num_columns = 0;
				}
				else
				{
					$last_table_column = $table_columns[$table_columns_count-1];
					$num_columns = count($last_table_column);
				}

				if($sort_columnlen>0)
				{
					$errorflag=False;
					for($i=0; $i<$table_columns_count; $i++)
					{
						$tmpcount = count($table_columns[$i]);
						if($tmpcount==0)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): insert_row_table: the count for this table row is $tmpcount, search_text: $search_text_print", 0, $reportdate);
							if($ret==0) $ret=2;
							$errorflag=True;
							break;
						}

						$tmpdata_len = strlen($table_columns[$i][0]);
						if($tmpdata_len!=$sort_columnlen)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): The column length is invalid for insert_row_table: sort_columnlen=$sort_columnlen, but actual column len = $tmpdata_len. column data: \"".$table_columns[$i][0]."\"", 0, $reportdate);
							if($ret==0) $ret=2;
							$errorflag = True;
							break;
						}
					}
					if($errorflag) continue;
				}

				$rowspan=0;
				$rowspan_column_index=NULL;
				$target_column_index=NULL;
				if($search_column!=-1)
				{
					$errorflag=False;
					for($i=0; $i<$table_columns_count; $i++)
					{
						$tmpcount = count($table_columns[$i]);
						if($search_column >= $tmpcount)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): insert_row_table: search_column = $search_column but the count for this table row is $tmpcount, search_text: $search_text_print", 0, $reportdate);
							if($ret==0) $ret=2;
							$errorflag=True;
							break;
						}

						$tmp_column = $table_columns[$i][$search_column];

						$rowspan=0;
						wikibot_parse_rowspan($tmp_column, $rowspan);

						if(wikibot_check_search_text($search_type, $search_text, $tmp_column))
						{
							$foundflag = True;
							if($rowspan>=1 && $search_text_rowspan!=="")
							{
								$rowspan_pos=0;
								$foundflag = False;
								for($i2=0; $i2<$rowspan; $i2++)
								{
									if($i2>0) $rowspan_pos = 1;
									$tmp_column = $table_columns[$i+$i2][$search_column_rowspan-$rowspan_pos];

									if(wikibot_check_search_text($search_type_rowspan, $search_text_rowspan, $tmp_column))
									{
										$foundflag = True;
										break;
									}
								}
							}

							if($foundflag===True)
							{
								$tmpstr = "";
								if($rowspan>=1 && $search_text_rowspan!=="")
								{
									$tmpstr.= " search_text_rowspan: $search_text_rowspan_print.";
								}
								if($extra_table_lists!==NULL)
								{
									$tmpstr.= " The input table_lists from this insert_row_table will be processed later.";
									foreach($extra_table_lists as $tmp_extra) $table_lists[] = $tmp_extra;
								}
								wikibot_writelog("wikibot_process_wikigen($pagetitle): This text already exists in the column for search_column=$search_column insert_row_table, search_text: $search_text_print.$tmpstr", 2, $reportdate);
								$errorflag = True;
								break;
							}

							$next_row = $i+1;
							if($rowspan>1)
							{
								$next_row+=$rowspan-1;
								if($next_row<$table_columns_count)
								{
									$table_endpos = $table_columns_pos[$next_row]["row"];
								}
							}
							if($next_row-1<$table_columns_count)
							{
								$target_column_index = $next_row-1;
							}
							$rowspan_column_index = $i;
							break;
						}
						if($rowspan>1) $i+= $rowspan-1;
					}
					if($errorflag) continue;
				}

				if($search_column_rowspan==-1 && $num_columns>0)
				{
					if($columns_count != $num_columns)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): The number of json columns for insert_row_table ($columns_count) doesn't match table num_columns ($num_columns). Whichever value is smaller will be used for the entrycount.", 2, $reportdate);
					}
					$entrycount = min($columns_count, $num_columns);
				}
				else
				{
					$entrycount = $columns_count;
				}

				if($search_text_rowspan!=="" && $enable_sort===FALSE && ($rowspan_column_index===NULL || $target_column_index===NULL))
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the target rows for insert_row_table with rowspan. search_text: $search_text_print, search_text_rowspan = $search_text_rowspan_print.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				if($enable_sort===TRUE)
				{
					if($search_type==2)
					{
						$tmpstr = "";
						if($search_text_rowspan!=="")
						{
							$tmpstr.= ", search_text_rowspan: $search_text_rowspan_print";
						}
						wikibot_writelog("wikibot_process_wikigen($pagetitle): search_type=2 is not available when sort is enabled with insert_row_table. search_text: $search_text_print$tmpstr.", 0, $reportdate);
						if($ret==0) $ret=2;
						continue;
					}

					$target_pos = NULL;
					if($sort_type==1)
					{
						if($search_text!=="0")
						{
							$search_text_org = $search_text;
							$search_text = intval($search_text, 0);
							if($search_text===0)
							{
								wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to convert search_text with intval for insert_row_table, search_text: \"$search_text_org\"", 0, $reportdate);
								if($ret==0) $ret=3;
								continue;
							}
						}
						else
						{
							$search_text = 0;
						}
					}

					$errorflag = False;
					for($i=0; $i<$table_columns_count; $i++)
					{
						$tmpent = $table_columns[$i][0];
						$rowspan=0;
						wikibot_parse_rowspan($tmpent, $rowspan);

						$next_row = $i+1;
						if($rowspan>1)
						{
							$next_row+=$rowspan-1;
						}

						if($sort_type==1)
						{
							if($tmpent!=="0")
							{
								$tmpent_org = $tmpent;
								$tmpent = intval($tmpent, 0);
								if($tmpent===0)
								{
									wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to convert table column with intval for insert_row_table, column data: \"$tmpent_org\"", 0, $reportdate);
									if($ret==0) $ret=3;
									$errorflag = True;
									break;
								}
							}
							else
							{
								$tmpent = 0;
							}
						}

						if($next_row<$table_columns_count)
						{
							$tmpent2 = $table_columns[$next_row][0];
							$rowspan2=0;
							wikibot_parse_rowspan($tmpent2, $rowspan2);
							if($sort_type==1)
							{
								if($tmpent2!=="0")
								{
									$tmpent2_org = $tmpent2;
									$tmpent2 = intval($tmpent2, 0);
									if($tmpent2===0)
									{
										wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to convert table column with intval for insert_row_table, column data: \"$tmpent2_org\"", 0, $reportdate);
										if($ret==0) $ret=3;
										$errorflag = True;
										break;
									}
								}
								else
								{
									$tmpent2 = 0;
								}
							}

							if($tmpent<=$search_text && $tmpent2>=$search_text)
							{
								$target_pos = $table_columns_pos[$next_row]["row"];
								$last_table_column = $table_columns[$i];
							}
							else if($i==0 && $search_text < $tmpent)
							{
								$target_pos = $table_columns_pos[$i]["row"];
								$last_table_column = NULL;
							}
						}
						else
						{
							if($tmpent<=$search_text)
							{
								$target_pos = NULL;
								$last_table_column = $table_columns[$table_columns_count-1];
							}
							else if($target_pos===NULL)
							{
								$target_pos = $table_columns_pos[$i]["row"];
								if($table_columns_count>1)
								{
									$last_table_column = $table_columns[$i-1];
								}
								else
								{
									$last_table_column = NULL;
								}
							}
						}
						$i+=$next_row-1;
					}

					if($target_pos!==NULL)
					{
						$table_endpos = $target_pos;
						$new_text = "";
					}
					if($last_table_column===NULL)
					{
						wikibot_writelog("wikibot_process_wikigen($pagetitle): !LAST will output '' for this insert_row_table entry since the inserted row is at the start of the table.", 2, $reportdate);
					}
					if($errorflag) continue;
				}

				for($i=0; $i<$entrycount; $i++)
				{
					$linetext = $columns[$i];

					if($i==0 || $use_single_line===False)
					{
						$column_prefix = "| ";
						if($use_single_line===False)
						{
							$column_append = "\n";
						}
						else
						{
							$column_append = "";
						}
					}
					else if($i>0 && $use_single_line===True)
					{
						$column_prefix = " || ";
						$column_append = "";
					}
					if($i==$entrycount-1 && $use_single_line===True)
					{
						$column_append = "\n";
					}

					if($linetext === "!LAST")
					{
						$tmp = "";
						if($last_table_column!==NULL) $tmp = $last_table_column[$i];
						$new_text.= $column_prefix . $tmp . $column_append;
					}
					else if($linetext === "!TIMESTAMP")
					{
						$new_text.= $column_prefix . gmdate("F j, Y", $timestamp)." (UTC)" . $column_append;
					}
					else
					{
						$new_text.= $column_prefix . $linetext . $column_append;
					}
				}

				$tmp = substr($page_text, $table_endpos, 2);
				if($tmp!="|-" && $tmp!="|}")
				{
					$new_text.= "|-\n";
				}

				$tmp_page_updated = False;

				$page_text_prev = substr($page_text, 0, $table_endpos);
				if($rowspan_column_index!==NULL && $target_column_index!==NULL)
				{
					// Use wikibot_parse_rowspan to increase the value of each 'rowspan=' field by 1, for $rowspan_column_index.
					$pos_delta=0;
					if($entrycount>0 && $enable_sort===FALSE)
					{
						$tmpcount = count($table_columns[$rowspan_column_index]);
						for($i=0; $i<$tmpcount; $i++)
						{
							$tmpent = $table_columns[$rowspan_column_index][$i];
							$rowspan=0;
							$updated_column="";
							$tmpent_len = strlen($tmpent);
							wikibot_parse_rowspan($tmpent, $rowspan, $updated_column);
							$updated_column_len = strlen($updated_column);

							if($rowspan>0 && $updated_column!="")
							{
								$tmp_pos = $table_columns_pos[$rowspan_column_index]["columns"][$i] + $pos_delta;
								$page_text_prev = substr($page_text_prev, 0, $tmp_pos) . $updated_column . substr($page_text_prev, $tmp_pos+$tmpent_len);
								$pos_delta += $updated_column_len - $tmpent_len;
							}
						}
					}

					// Find-replace the previous row within the rowspan if needed.
					$errorflag = False;
					foreach($rowspan_edit_prev as $edit_prev)
					{
						if(isset($edit_prev["column"]) && isset($edit_prev["findreplace_list"]))
						{
							$target_column = $edit_prev["column"];
							$findreplace_list = $edit_prev["findreplace_list"];
						}
						else
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): The required fields for rowspan_edit_prev are missing, skipping this rowspan_edit_prev entry. search_text: $search_text_print", 2, $reportdate);
							continue;
						}

						$tmpcount = count($table_columns[$target_column_index]);
						if($target_column >= $tmpcount)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): insert_row_table: rowspan_edit_prev column = $target_column but the count for this table row is $tmpcount, search_text: $search_text_print", 0, $reportdate);
							if($ret==0) $ret=2;
							$errorflag=True;
							break;
						}

						if($target_column < 1)
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): insert_row_table: rowspan_edit_prev column = $target_column, this must be >=1. search_text: $search_text_print", 0, $reportdate);
							if($ret==0) $ret=2;
							$errorflag=True;
							break;
						}

						if($rowspan_column_index != $target_column_index)
						{
							$target_column--;
						}

						$tmpent = $table_columns[$target_column_index][$target_column];
						$tmpent_org = $tmpent;
						$tmpent_len = strlen($tmpent);

						foreach($findreplace_list as $findreplace_entry)
						{
							if(isset($findreplace_entry["find_text"]) && isset($findreplace_entry["replace_text"]))
							{
								$tmpent = str_replace($findreplace_entry["find_text"], $findreplace_entry["replace_text"], $tmpent);
							}
							else
							{
								wikibot_writelog("wikibot_process_wikigen($pagetitle): This findreplace_list is missing the required fields with insert_row_table rowspan_edit_prev, search_text: $search_text_print", 2, $reportdate);
								continue;
							}
						}

						if($tmpent!==$tmpent_org)
						{
							$updated_column_len = strlen($tmpent);

							$tmp_pos = $table_columns_pos[$target_column_index]["columns"][$target_column] + $pos_delta;
							$page_text_prev = substr($page_text_prev, 0, $tmp_pos) . $tmpent . substr($page_text_prev, $tmp_pos+$tmpent_len);
							$pos_delta += $updated_column_len - $tmpent_len;
							$tmp_page_updated = True;
						}
					}
					if($errorflag) continue;
				}

				if($entrycount>0)
				{
					$tmp_page_updated = True;
				}

				if($tmp_page_updated===True)
				{
					$page_updated = True;
					$page_text = $page_text_prev . $new_text . substr($page_text, $table_endpos);
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Successfully edited the page for insert_row_table. search_text: $search_text_print", 2, $reportdate);
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Page editing for insert_row_table was not needed. search_text: $search_text_print", 2, $reportdate);
				}
			}

			foreach($table_lists as $table_list)
			{
				if($search_section_end!=="")
				{
					$section_endpos = strpos($page_text, $search_section_end, $section_pos);
				}
				else
				{
					$section_endpos = strlen($page_text);
				}
				if($section_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): table_list: Failed to find the section end, search_section_end: \"$search_section_end\"", 0, $reportdate);
					if($ret==0) $ret=2;
					break;
				}

				$section_text = substr($page_text, $section_pos, $section_endpos-$section_pos);

				if(isset($table_list["target_text_prefix"]))
				{
					$target_text_prefix = $table_list["target_text_prefix"];
				}
				else
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json target_text_prefix isn't set, skipping this table_list entry.", 2, $reportdate);

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

				$findreplace_list = NULL;
				if(isset($table_list["findreplace_list"]))
				{
					$findreplace_list = $table_list["findreplace_list"];
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
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the table text for table_list target_text_prefix: \"$target_text_prefix\"", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$line_endpos = strpos($section_text, "\n", $prefix_pos);
				if($line_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the target line end for table_list.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$line = substr($section_text, $prefix_pos, $line_endpos-$prefix_pos);

				if(strpos($line, $search_text)!==FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): This entry already exists in the table for table_list, target_text_prefix=\"$target_text_prefix\" with search_text: \"$search_text\"", 2, $reportdate);
					continue;
				}

				if($findreplace_list!==NULL)
				{
					foreach($findreplace_list as $findreplace_entry)
					{
						if(isset($findreplace_entry["find_text"]) && isset($findreplace_entry["replace_text"]))
						{
							$line = str_replace($findreplace_entry["find_text"], $findreplace_entry["replace_text"], $line);
						}
						else
						{
							wikibot_writelog("wikibot_process_wikigen($pagetitle): This findreplace_list is missing the required fields with table_list, target_text_prefix=\"$target_text_prefix\" with search_text: \"$search_text\"", 2, $reportdate);
							continue;
						}
					}
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
								wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find ' ||' for target_column=$target_column with table_list, target_text_prefix: \"$target_text_prefix\"", 0, $reportdate);
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

					$tmp = substr($line, $curpos-3, 3);
					if($tmp!==" ||" && $tmp!=="|| ") // If the column isn't empty, add delimiter.
					{
						$insert_text = $delimiter . $insert_text;
					}
					else if($tmp[2]!==" ")
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
							wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the insert_before_text for table_list, search_text: \"$search_text\"", 0, $reportdate);
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

				wikibot_writelog("wikibot_process_wikigen($pagetitle): Successfully edited the page for table_list. target_text_prefix=\"$target_text_prefix\" with search_text: \"$search_text\"", 2, $reportdate);

				$new_line = substr($line, 0, $insert_pos) . $insert_text . substr($line, $insert_pos);
				$section_text = substr($section_text, 0, $prefix_pos) . $new_line . substr($section_text, $line_endpos);
				$page_text = substr($page_text, 0, $section_pos) . $section_text . substr($page_text, $section_endpos);
				$page_updated = True;
			}

			foreach($tables_updatever_range as $table_updatever_range)
			{
				if($search_section_end!=="")
				{
					$section_endpos = strpos($page_text, $search_section_end, $section_pos);
				}
				else
				{
					$section_endpos = strlen($page_text);
				}
				if($section_endpos===FALSE)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): Failed to find the section end.", 0, $reportdate);
					if($ret==0) $ret=2;
					break;
				}

				$section_text = substr($page_text, $section_pos, $section_endpos-$section_pos);

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
					wikibot_writelog("wikibot_process_wikigen($pagetitle): json table_updatever_range columns isn't set, skipping this tables_updatever_range entry.", 2, $reportdate);

					continue;
				}
				$columns_count = count($columns);

				$table_columns = array();
				$table_columns_pos = array();
				$use_single_line = False;
				wikibot_parse_table($section_text, $section_pos, $table_columns, $table_columns_pos, $use_single_line);

				$new_text = "";
				if(substr($page_text, $section_endpos-3, 3)!="|-\n")
				{
					$new_text.= "|-\n";
				}

				$num_rows=count($table_columns);

				if($num_rows == 0)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The table for table_updatever_range is empty.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				$target_row_index = $num_rows-1;
				$last_table_row = $table_columns[$target_row_index];
				$num_columns = count($last_table_row);

				if($num_columns==0)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The last table row for table_updatever_range is empty.", 0, $reportdate);
					if($ret==0) $ret=2;
					continue;
				}

				if(strlen($last_table_row[0])==0)
				{
					wikibot_writelog("wikibot_process_wikigen($pagetitle): The updatever-column in the last table row for table_updatever_range is empty.", 0, $reportdate);
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
				if(substr($last_table_row[0], 0, 1)==="[")
				{
					$updatever_prefix = "[";
					$updatever_append = "]";
				}

				// Check whether the json data and the table doesn't match, skipping the updatever in the table.
				$entrycount = min($columns_count, $num_columns);
				$found = False;
				for($i=0; $i<$entrycount; $i++)
				{
					if($last_table_row[$i+1]!==$columns[$i])
					{
						$found = True;
						break;
					}
				}

				if($found)
				{
					$new_text.= "| ".$updatever_prefix.$updateversion.$updatever_append;
					if($use_single_line===False) $new_text.="\n";
					for($i=0; $i<$entrycount; $i++)
					{
						if($use_single_line===False)
						{
							$new_text.= "| ";
						}
						else
						{
							$new_text.= " || ";
						}
						$new_text.= $columns[$i];
						if($use_single_line===False || $i==$entrycount-1) $new_text.="\n";
					}

					$page_text = substr($page_text, 0, $section_endpos) . $new_text . substr($page_text, $section_endpos);
				}
				else // When data matches, just update the updatever in the table. This will also be reached when the json columns is empty.
				{
					$tmpent = $last_table_row[0];
					$tmpent_len = strlen($tmpent);

					$tmp = strpos($tmpent, "-");
					if($tmp===FALSE)
					{
						$insert_pos = strpos($tmpent, "]");
						if($insert_pos===FALSE)
						{
							$insert_pos = strlen($tmpent);
						}
						$tmpent = substr($tmpent, 0, $insert_pos) . "-" . $updateversion . substr($tmpent, $insert_pos);
					}
					else
					{
						$tmpent = substr($tmpent, 0, $tmp+1) . $updateversion.$updatever_append;
					}

					$tmp_pos = $table_columns_pos[$target_row_index]["columns"][0];
					$page_text = substr($page_text, 0, $tmp_pos) . $tmpent . substr($page_text, $tmp_pos+$tmpent_len);
				}

				wikibot_writelog("wikibot_process_wikigen($pagetitle): Successfully edited the page for table_updatever_range.", 2, $reportdate);

				$page_updated = True;
			}
		}

		if($in_page_text!==NULL && $out_page_updated!==NULL) $out_page_updated = $page_updated;

		if($page_updated && $in_page_text===NULL)
		{
			wikibot_writelog("wikibot_process_wikigen($pagetitle): Page updated.", 2, $reportdate);
			wikibot_writelog("wikibot_process_wikigen($pagetitle): New page:\n$page_text", 1, $reportdate);

			if($wikibot_loggedin == 1)
			{
				wikibot_writelog("wikibot_process_wikigen($pagetitle): Sending page edit request...", 2, $reportdate);

				$newContent = new \Mediawiki\DataModel\Content($page_text);
				$title = new \Mediawiki\DataModel\Title($pagetitle);
				$identifier = new \Mediawiki\DataModel\PageIdentifier($title);
				$revision = new \Mediawiki\DataModel\Revision($newContent, $identifier);
				$services->newRevisionSaver()->save($revision);

				$text = "wikibot_process_wikigen($pagetitle): Page edit request was successful.";
				echo "$text\n";
				wikibot_writelog($text, 1, $reportdate);
			}
		}
		else if($page_updated===False)
		{
			wikibot_writelog("wikibot_process_wikigen($pagetitle): Page was not updated.", 2, $reportdate);
		}
	}

	return $ret;
}

function runwikibot_newsysupdate($updateversion, $reportdate, $wikigen_path="")
{
	global $mysqldb, $wikibot_loggedin, $wikibot_user, $wikibot_pass, $wikibot_ignore_latest_requirement, $system, $wikicfgid;

	$wikibot_loggedin = 0;
	$ret=0;

	$query="SELECT ninupdates_wikiconfig.id, ninupdates_wikiconfig.serverbaseurl, ninupdates_wikiconfig.apiprefixuri, ninupdates_wikiconfig.news_pagetitle, ninupdates_wikiconfig.newsarchive_pagetitle, ninupdates_wikiconfig.homemenu_pagetitle FROM ninupdates_wikiconfig, ninupdates_consoles WHERE ninupdates_wikiconfig.wikibot_enabled=1 && ninupdates_wikiconfig.id=ninupdates_consoles.wikicfgid && ninupdates_consoles.system='".$system."'";
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
	$wikicfgid = $row[0];
	$serverbaseurl = $row[1];
	$apiprefixuri = $row[2];
	$wiki_newspagetitle = $row[3];
	$wiki_newsarchivepagetitle = $row[4];
	$wiki_homemenutitle = $row[5];

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

	$report_latest_flag = False;
	if(isset($wikibot_ignore_latest_requirement) && $wikibot_ignore_latest_requirement===True)
	{
		$report_latest_flag = True;
	}

	$query="SELECT ninupdates_reports.reportdate FROM ninupdates_reports, ninupdates_consoles WHERE log='report' && ninupdates_reports.systemid=ninupdates_consoles.id && ninupdates_consoles.system='".$system."' ORDER BY ninupdates_reports.curdate DESC LIMIT 1";
	$result=mysqli_query($mysqldb, $query);
	$numrows=mysqli_num_rows($result);
	if($numrows>0)
	{
		$row = mysqli_fetch_row($result);

		$tmp_reportdate = $row[0];

		if($tmp_reportdate === $reportdate)
		{
			$report_latest_flag = True;
		}
	}

	$tmpret = wikibot_edit_updatepage($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $rebootless_flag, $updateversion_norebootless, $system_generation, $postproc_runfinished, $report_latest_flag);
	if($ret==0) $ret = $tmpret;

	if($report_latest_flag===True)
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

			if($system_generation!=0)
			{
				$tmpret = wikibot_edit_titlelist($api, $services, $updateversion, $reportdate, $timestamp, $page, $serverbaseurl, $apiprefixuri, $updateversion_norebootless);
				if($ret==0) $ret = $tmpret;
			}
		}
		else
		{
			wikibot_writelog("Skipping wikigen/titlelist handling since the report post-processing isn't finished.", 2, $reportdate);
		}
	}
	else
	{
		wikibot_writelog("Skipping FirmwareNews and wikigen/titlelist page handling since this report isn't the latest one.", 2, $reportdate);
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
	if($argc != 2 || ($argv[1]!=="scheduled" && $argv[1]!=="scheduled_updatever_autoset0"))
	{
		echo "Usage:\nphp wikibot.php <updateversion> <reportdate> <system>\n";
		echo "php wikibot.php scheduled\n";
		echo "php wikibot.php scheduled_updatever_autoset0\n";
		echo "php wikibot.php <system> <--wikigen=path>\n";
		return 0;
	}
	else
	{
		$wikibot_cmdtype = 1;
		if($argv[1]==="scheduled_updatever_autoset0") $wikibot_cmdtype = 3;
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
        $updatever_autoset = "1";
        if($wikibot_cmdtype == 3)  $updatever_autoset = "0";
	$query="SELECT ninupdates_reports.reportdate, ninupdates_reports.updateversion, ninupdates_consoles.system FROM ninupdates_reports, ninupdates_consoles WHERE updatever_autoset=".$updatever_autoset." && wikibot_runfinished=0 && ninupdates_reports.systemid=ninupdates_consoles.id";

	if($wikibot_cmdtype == 3) $query.= "AND ninupdates_reports.curdate < FROM_UNIXTIME(".(time() - 20*60).") AND ninupdates_consoles.generation!=0";

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
