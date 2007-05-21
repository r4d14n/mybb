<?php
/**
 * MyBB 1.2
 * Copyright � 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->version_check, "index.php?".SID."&amp;module=home/version_check");

if(!$mybb->input['action'])
{	
	$page->output_header($lang->version_check);
	
	$sub_tabs['version_check'] = array(
		'title' => $lang->version_check,
		'link' => "index.php?".SID."&amp;module=home/version_check",
		'description' => $lang->version_check_description
	);
	
	$sub_tabs['download_mybb'] = array(
		'title' => $lang->dl_the_latest_mybb,
		'link' => "http://mybboard.net/downloads",
		'link_target' => '_blank'
	);
	
	$sub_tabs['check_plugins'] = array(
		'title' => $lang->check_plugin_versions,
		'link' => "index.php?".SID."&amp;module=config/plugins&amp;action=check",
	);
	
	$page->output_nav_tabs($sub_tabs, 'version_check');	
	
	$current_version = rawurlencode($mybb->version_code);

	$updated_cache = array(
		"last_check" => time()
	);

	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = fetch_remote_file("http://mybboard.net/version_check.php");
	if(!$contents)
	{
		$page->output_inline_error($lang->error_communication);
		$page->output_footer();
		exit;
	}

	$parser = new XMLParser($contents);
	$tree = $parser->get_tree();

	$latest_code = $tree['mybb']['version_code']['value'];
	$latest_version = "<strong>".$tree['mybb']['latest_version']['value']."</strong> (".$latest_code.")";
	if($latest_code > $mybb->version_code)
	{
		$latest_version = "<span style=\"color: #C00;\">".$latest_version."</span>";
		$version_warn = 1;
		$updated_cache['latest_version'] = $latest_version;
		$updated_cache['latest_version_code'] = $latest_code;
	}
	else
	{
		$latest_version = "<span style=\"color: green;\">".$latest_version."</span>";
	}
	
	$cache->update("update_check", $updated_cache);

	require_once MYBB_ROOT."inc/class_feedparser.php";
	$feed_parser = new FeedParser();
	$feed_parser->parse_feed("http://www.mybboard.net/latest_news.php");
	echo $feed_parser->error;
	
	$table = new Table;
	$table->construct_header($lang->your_version);
	$table->construct_header($lang->latest_version);
	
	$table->construct_cell("<strong>".$mybb->version."</strong> (".$mybb->version_code.")");
	$table->construct_cell($latest_version);
	$table->construct_row();
	
	$table->output($lang->version_check);
	
	if($version_warn)
	{
		$page->output_error("<p><em>{$lang->error_out_of_date}</em> {$lang->update_forum}</p>");
	}
	else
	{
		$page->output_success("<p><em>{$lang->success_up_to_date}</em></p>");
	}
	
	if($feed_parser->error == '')
	{
		foreach($feed_parser->items as $item)
		{
			if($item['date_timestamp'])
			{
				$stamp = my_date($mybb->settings['dateformat'], $item['date_timestamp']).", ".my_date($mybb->settings['timeformat'], $item['date_timestamp']);
			}
			else
			{
				$stamp = '';
			}
			if($item['content'])
			{
				$content = $item['content'];
			}
			else
			{
				$content = $item['description'];
			}
			$table->construct_cell("<span style=\"font-size: 16px;\"><strong>".$item['title']."</strong></span>{$content}<strong><span style=\"float: right;\">{$stamp}</span><a href=\"{$item['link']}\" target=\"_blank\">&raquo; {$lang->read_more}</a></strong>");
			$table->construct_row();
		}
	}
	else
	{
		$table->construct_cell("{$lang->error_fetch_news} <!-- error code: {$feed_parser->error} -->");
		$table->construct_row();
	}
	
	$table->output($lang->latest_mybb_announcements);
	
	$page->output_footer();
}

?>