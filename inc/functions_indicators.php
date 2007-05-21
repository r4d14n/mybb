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

/**
 * Mark a particular thread as read for the current user.
 *
 * @param int The thread ID
 * @param int The forum ID of the thread
 */
function mark_thread_read($tid, $fid)
{
	global $mybb, $db;

	// Can only do "true" tracking for registered users
	if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
	{
		// For registered users, store the information in the database.
		switch($db->type)
		{
			case "pgsql":
				$db->shutdown_query($db->build_replace_query("threadsread", array('tid' => $tid, 'uid' => $mybb->user['uid'], 'dateline' => time()), "tid"));
				break;
			default:
				$db->query("
					REPLACE INTO ".TABLE_PREFIX."threadsread (tid, uid, dateline)
					VALUES('$tid', '{$mybb->user['uid']}', '".time()."')
				");
		}

		// Fetch ALL of the child forums of this forum
		$forums = get_child_list($fid);
		$forums[] = $fid;
		$forums = implode(",", $forums);

		$unread_count = fetch_unread_count($forums);
		if($unread_count == 0)
		{
			mark_forum_read($fid);
		}
	}
	// Default back to cookie marking
	else
	{
		my_set_array_cookie("threadread", $tid, time());
	}
}

/**
 * Fetches the number of unread threads for the current user in a particular forum.
 *
 * @param string The forums (CSV list)
 * @return int The number of unread threads
 */
function fetch_unread_count($fid)
{
	global $db, $mybb;

	$cutoff = time()-$mybb->settings['threadreadcut']*60*60*24;

	if($mybb->user['uid'] == 0)
	{
		$comma = '';
		$tids = '';
		$threadsread = unserialize($_COOKIE['mybb']['threadread']);
		$forumsread = unserialize($_COOKIE['mybb']['forumread']);
		if(is_array($threadsread))
		{
			foreach($threadsread as $key => $value)
			{
				$tids .= $comma.intval($key);
				$comma = ',';
			}
		}
		
		if(!empty($tids))
		{
			$count = 0;
			
			// We set a limit to 100 otherwise it'll become too processor intensive, especially if we have many threads.
			$query = $db->query("
				SELECT lastpost, tid
				FROM ".TABLE_PREFIX."threads
				WHERE visible=1 AND closed NOT LIKE 'moved|%' AND fid IN ($fid) AND tid IN ($tids) AND lastpost > '{$cutoff}'
				LIMIT 100
			");
			while($thread = $db->fetch_array($query))
			{
				if($thread['lastpost'] > intval($threadsread[$thread['tid']]) && $thread['lastpost'] > intval($forumsread[$thread['tid']]))
				{
					++$count;
				}
			}
			return $count;
		}
	}
	else
	{
		switch($db->type)
		{
			case "pgsql":
				$query = $db->query("
					SELECT COUNT(t.tid) AS unread_count
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."threadsread tr ON (tr.tid=t.tid AND tr.uid='{$mybb->user['uid']}')
					LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=t.fid AND fr.uid='{$mybb->user['uid']}')
					WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' AND t.fid IN ($fid) AND t.lastpost > COALESCE(tr.dateline,$cutoff) AND t.lastpost > COALESCE(fr.dateline,$cutoff) AND t.lastpost>$cutoff
				");
				break;
			default:
				$query = $db->query("
					SELECT COUNT(t.tid) AS unread_count
					FROM ".TABLE_PREFIX."threads t
					LEFT JOIN ".TABLE_PREFIX."threadsread tr ON (tr.tid=t.tid AND tr.uid='{$mybb->user['uid']}')
					LEFT JOIN ".TABLE_PREFIX."forumsread fr ON (fr.fid=t.fid AND fr.uid='{$mybb->user['uid']}')
					WHERE t.visible=1 AND t.closed NOT LIKE 'moved|%' AND t.fid IN ($fid) AND t.lastpost > IFNULL(tr.dateline,$cutoff) AND t.lastpost > IFNULL(fr.dateline,$cutoff) AND t.lastpost>$cutoff
				");
		}
		return $db->fetch_field($query, "unread_count");
	}
}

/**
 * Mark a particular forum as read.
 *
 * @param int The forum ID
 */
function mark_forum_read($fid)
{
	global $mybb, $db;

	// Can only do "true" tracking for registered users
	if($mybb->settings['threadreadcut'] > 0 && $mybb->user['uid'])
	{
		switch($db->type)
		{
			case "pgsql":
				$db->shutdown_query($db->build_replace_query("forumsread", array('fid' => $fid, 'uid' => $mybb->user['uid'], 'dateline' => time()), "fid"));
				break;
			default:
				$db->query("
					REPLACE INTO ".TABLE_PREFIX."forumsread (fid, uid, dateline)
					VALUES('$fid', '{$mybb->user['uid']}', '".time()."')
				");
		}

		/**
		 * What needs to be done here is some sort of checking for:
		 *
		 *   Establish which sibling forums of each parent forum have
		 *   new posts or not to mark parent forums as read.
		 *
		 *   It's tricky and quite difficult to do especially
		 *   with performance/optimisation in mind.
		 */

		$db->shutdown_query("
			REPLACE INTO ".TABLE_PREFIX."forumsread (fid, uid, dateline)
			VALUES('{$fid}', '{$mybb->user['uid']}', '".time()."')
		");
	}
	// Mark in a cookie
	else
	{
		my_set_array_cookie("forumread", $fid, time());
	}
}
?>