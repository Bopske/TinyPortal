<?php
/**
 * @package TinyPortal
 * @version 3.0.3
 * @author IchBin - http://www.tinyportal.net
 * @founder Bloc
 * @license MPL 2.0
 *
 * The contents of this file are subject to the Mozilla Public License Version 2.0
 * (the "License"); you may not use this package except in compliance with
 * the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Copyright (C) - The TinyPortal Team
 *
 */
use TinyPortal\Util as TPUtil;

if (!defined('SMF')) {
	die('Hacking attempt...');
}

function TPDownloadActions(&$subActions)
{
	$subActions = array_merge(
		[
			'download' => ['TPdlmanager.php', 'TPdlmanager',   []],
			'rate_dlitem' => ['TPdlmanager.php', 'TPdlmanager',   []],
		],
		$subActions
	);
}

// TinyPortal downloads entrance
function TPdlmanager()
{
	global $settings, $context, $scripturl, $txt, $user_info, $sourcedir, $boarddir, $smcFunc;

	if (loadLanguage('TPmodules') == false) {
		loadLanguage('TPmodules', 'english');
	}

	if (loadLanguage('TPortalAdmin') == false) {
		loadLanguage('TPortalAdmin', 'english');
	}

	// get subaction
	$tpsub = '';
	if (isset($_GET['sub'])) {
		$context['TPortal']['subaction'] = $_GET['sub'];
		$tpsub = $_GET['sub'];
	}
	elseif (isset($_GET['sa'])) {
		$context['TPortal']['subaction'] = $_GET['sa'];
		$tpsub = $_GET['sa'];
	}

	// a switch to make it clear what is "forum" and not
	$context['TPortal']['not_forum'] = true;
	// call the editor setup
	require_once $sourcedir . '/TPcommon.php';
	// download manager?
	if (isset($_GET['dl'])) {
		$context['TPortal']['dlsub'] = $_GET['dl'] == '' ? '0' : $_GET['dl'];
	}

	// clear the linktree first
	TPstrip_linktree();

	// include source files in case of modules
	if (isset($context['TPortal']['dlsub'])) {
		TPdlmanager_init();
	}
	elseif ($tpsub == 'rate_dlitem' && isset($_POST['tp_dlitem_rating_submit']) && $_POST['tp_dlitem_type'] == 'dlitem_rating') {
		// check the session
		checkSession('post');
		$commenter = $context['user']['id'];
		$dl = $_POST['tp_dlitem_id'];
		// check if the download indeed exists
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT rating, voters FROM {db_prefix}tp_dlmanager
			WHERE id = {int:dlid}',
			['dlid' => $dl]
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			$row = $smcFunc['db_fetch_row']($request);
			$smcFunc['db_free_result']($request);
			$voters = [];
			$ratings = [];
			$voters = explode(',', $row[1]);
			$ratings = explode(',', $row[0]);
			// check if we haven't rated anyway
			if (!in_array($context['user']['id'], $voters)) {
				if ($row[0] != '') {
					$new_voters = $row[1] . ',' . $context['user']['id'];
					$new_ratings = $row[0] . ',' . $_POST['tp_dlitem_rating'];
				}
				else {
					$new_voters = $context['user']['id'];
					$new_ratings = $_POST['tp_dlitem_rating'];
				}
				// update ratings and raters
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET rating = {string:rate}
					WHERE id = {int:dlid}',
					['rate' => $new_ratings, 'dlid' => $dl]
				);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET voters = {string:vote}
					WHERE id = {int:dlid}',
					['vote' => $new_voters, 'dlid' => $dl]
				);
			}
			// go back to the download
			redirectexit('action=tportal;sa=download;dl=item' . $dl);
		}
	}
	elseif ($tpsub == 'dlsubmitsuccess') {
		$context['TPortal']['subaction'] = 'dlsubmitsuccess';
		loadtemplate('TPdlmanager');
		$context['sub_template'] = 'dlsubmitsuccess';
	}
	else {
		redirectexit('action=tportal;sa=download;dl');
	}
}

function TPdlmanager_init()
{
	global $context, $settings, $sourcedir;

	// load the needed strings
	if (loadLanguage('TPdlmanager') == false) {
		loadLanguage('TPdlmanager', 'english');
	}

	$context['can_tp_dlupload'] = allowedTo('tp_dlupload');

	require_once $sourcedir . '/TPcommon.php';
	// get subaction
	if (isset($context['TPortal']['dlsub'])) {
		// a switch to make it clear what is "forum" and not
		$context['TPortal']['not_forum'] = true;

		$context['html_headers'] .= '
			<script type="text/javascript" src="' . $settings['default_theme_url'] . '/scripts/editor.js?rc1"></script>';

		// see if admin section
		if (substr($context['TPortal']['dlsub'], 0, 5) == 'admin' || $context['TPortal']['dlsub'] == 'submission') {
			TPortalDLAdmin();
		}
		elseif (substr($context['TPortal']['dlsub'], 0, 8) == 'useredit') {
			TPortalDLUser(substr($context['TPortal']['dlsub'], 8));
		}
		else {
			TPortalDLManager();
		}
	}
}

// TinyPortal DLmanager
function TPortalDLManager()
{
	global $txt, $scripturl, $boarddir, $boardurl, $context, $settings, $smcFunc;

	// assume its the frontpage initially
	$context['TPortal']['dlaction'] = 'main';
	;

	// is the DLmanager even active?
	if (!$context['TPortal']['show_download']) {
		fatal_error($txt['tp-dlmanageroff'], false);
	}

	// add visual options to this section
	$context['TPortal']['dl_visual'] = [];
	$dl_visual = explode(',', $context['TPortal']['dl_visual_options']);
	$dv = ['left', 'right', 'center', 'lower', 'top', 'bottom'];
	foreach ($dv as $v => $val) {
		if ($context['TPortal'][$val . 'panel'] == 1) {
			if (in_array($val, $dl_visual)) {
				$context['TPortal'][$val . 'panel'] = '1';
			}
			else {
				$context['TPortal'][$val . 'panel'] = '0';
			}
		}
		$context['TPortal']['dl_visual'][$val] = true;
	}

	if (in_array('top', $dl_visual)) {
		$context['TPortal']['showtop'] = '1';
	}
	else {
		$context['TPortal']['showtop'] = '0';
	}

	// check that you can upload at all
	if (allowedTo('tp_dlupload')) {
		$context['TPortal']['can_upload'] = true;
	}
	else {
		$context['TPortal']['can_upload'] = false;
	}

	// fetch all files from tp-downloads
	if (isset($_GET['ftp']) && allowedTo('tp_dlmanager')) {
		TP_dlftpfiles();
	}

	// any uploads being sent?
	$context['TPortal']['uploads'] = [];
	if (isset($_FILES['tp-dluploadfile']['tmp_name']) || isset($_POST['tp-dluploadnot']) || isset($_POST['tp-dlexternalfile'])) {
		// skip the upload checks etc. if just an empty item
		if (!isset($_POST['tp-dluploadnot']) && empty($_POST['tp-dlexternalfile'])) {
			// check if uploaded quick-list picture
			if (isset($_FILES['qup_tp_dluploadtext']) && file_exists($_FILES['qup_tp_dluploadtext']['tmp_name'])) {
				$item_id = isset($_GET['dl']) ? $_GET['dl'] : 'upload';
				$name = TPuploadpicture('qup_tp_dluploadtext', $context['user']['id'] . 'uid');
				tp_createthumb($context['TPortal']['image_upload_path'] . $name, 50, 50, $context['TPortal']['image_upload_path'] . 'thumbs/thumb_' . $name);
				redirectexit('action=tportal;sa=download;dl=' . $item_id);
			}
			// check that nothing happended
			if (!file_exists($_FILES['tp-dluploadfile']['tmp_name']) || !is_uploaded_file($_FILES['tp-dluploadfile']['tmp_name'])) {
				fatal_error($txt['tp-dluploadfailure'], false);
			}

			// first, can we upload at all?
			if (!$context['TPortal']['can_upload']) {
				unlink($_FILES['tp-dluploadfile']['tmp_name']);
				fatal_error($txt['tp-dluploadnotallowed'], false);
			}
		}
		// a file it is
		$title = isset($_POST['tp-dluploadtitle']) ? strip_tags($_POST['tp-dluploadtitle']) : $txt['tp-no_title'];
		if ($title == '') {
			$title = $txt['tp-no_title'];
		}
		$text = isset($_POST['tp_dluploadtext']) ? $_POST['tp_dluploadtext'] : '';
		$category = isset($_POST['tp-dluploadcat']) ? (int) $_POST['tp-dluploadcat'] : 0;
		// a screenshot?
		if (file_exists($_FILES['tp_dluploadpic']['tmp_name']) || is_uploaded_file($_FILES['tp_dluploadpic']['tmp_name'])) {
			$shot = true;
		}
		else {
			$shot = false;
		}

		$icon = !empty($_POST['tp_dluploadicon']) ? $boardurl . '/tp-downloads/icons/' . $_POST['tp_dluploadicon'] : '';

		if (!isset($_POST['tp-dluploadnot']) && empty($_POST['tp-dlexternalfile'])) {
			// process the file
			$filename = $_FILES['tp-dluploadfile']['name'];
			$name = strtr($filename, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
			$name = strtr($name, ['Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u']);
			$name = preg_replace(['/\s/', '/[^\w_\.\-]/'], ['_', ''], $name);
		}
		elseif (!empty($_POST['tp-dlexternalfile'])) {
			$name = $_POST['tp-dlexternalfile'];
		}
		else {
			$name = '- empty item -';
		}

		if (isset($_POST['tp-dlupload_ftpstray'])) {
			$name = $_POST['tp-dlupload_ftpstray'];
		}

		$status = 'normal';

		if (!isset($_POST['tp-dluploadnot']) && empty($_POST['tp-dlexternalfile'])) {
			// check the size
			$dlfilesize = filesize($_FILES['tp-dluploadfile']['tmp_name']);
			if ($dlfilesize > (1024 * $context['TPortal']['dl_max_upload_size'])) {
				$status = 'maxsize';
				unlink($_FILES['tp-dluploadfile']['tmp_name']);
				$error = $txt['tp-dlmaxerror'] . ' ' . ($context['TPortal']['dl_max_upload_size']) . ' Kb<br /><br />' . $txt['tp-dlerrorfile'] . ': ' . ceil($dlfilesize / 1024) . $txt['tp-kb'];
				fatal_error($error, false);
			}
		}
		elseif (isset($_POST['tp-dluploadnot']) || !empty($_POST['tp-dlexternalfile'])) {
			$dlfilesize = 0;
		}
		else {
			$dlfilesize = filesize($context['TPortal']['download_upload_path'] . $name);
		}

		if (!isset($_POST['tp-dluploadnot']) && empty($_POST['tp-dlexternalfile'])) {
			// check the extension
			$allowed = explode(',', $context['TPortal']['dl_allowed_types']);
			$match = false;
			foreach ($allowed as $extension => $value) {
				$ext = '.' . $value;
				$extlen = strlen($ext);
				if (substr($name, strlen($name) - $extlen, $extlen) == $ext) {
					$match = true;
				}
			}
			if (!$match) {
				$status = 'wrongtype';
				unlink($_FILES['tp-dluploadfile']['tmp_name']);
				$error = $txt['tp-dlexterror'] . ':<b> <br />' . $context['TPortal']['dl_allowed_types'] . '</b><br /><br />' . $txt['tp-dlerrorfile'] . ': <b>' . $name . '</b>';
				fatal_error($error, false);
			}
		}

		// ok, go ahead
		if ($status == 'normal') {
			if (!isset($_POST['tp-dluploadnot']) && empty($_POST['tp-dlexternalfile'])) {
				// check that no other file exists with same name
				if (file_exists($context['TPortal']['download_upload_path'] . $name)) {
					$name = time() . $name;
				}

				$success = move_uploaded_file($_FILES['tp-dluploadfile']['tmp_name'], $context['TPortal']['download_upload_path'] . $name);
			}

			if ($shot) {
				$sfile = 'tp_dluploadpic';
				$uid = $context['user']['id'] . 'uid';
				$dim = '1800';
				$suf = 'jpg,gif,png';
				$dest = $context['TPortal']['image_upload_path'] . 'dlmanager';
				$sname = TPuploadpicture($sfile, $uid, $dim, $suf, $dest);
				$screenshot = $sname;
				tp_createthumb($dest . '/' . $sname, $context['TPortal']['dl_screenshotsize'][0], $context['TPortal']['dl_screenshotsize'][1], $dest . '/thumb/' . $sname);
				tp_createthumb($dest . '/' . $sname, $context['TPortal']['dl_screenshotsize'][2], $context['TPortal']['dl_screenshotsize'][3], $dest . '/listing/' . $sname);
			}
			else {
				if (isset($_POST['tp_dluploadpic_link'])) {
					$screenshot = $_POST['tp_dluploadpic_link'];
				}
				else {
					$screenshot = '';
				}
			}
			// insert it into the database
			$now = time();

			// if all uploads needs to be approved: set category to -category , but not for dl admins
			if ($context['TPortal']['dl_approve'] == '1' && !allowedTo('tp_dlmanager')) {
				$category = $category - $category - $category;
			}

			// get the category access
			$request = $smcFunc['db_query'](
				'',
				'
				SELECT access FROM {db_prefix}tp_dlmanager WHERE id = {int:cat}',
				['cat' => $category]
			);
			if ($smcFunc['db_num_rows']($request) > 0) {
				$row = $smcFunc['db_fetch_assoc']($request);
				$acc = $row['access'];
			}
			else {
				$acc = '';
			}

			$request = $smcFunc['db_insert'](
				'INSERT',
				'{db_prefix}tp_dlmanager',
				['name' => 'string', 'description' => 'string', 'icon' => 'string', 'category' => 'int', 'type' => 'string', 'downloads' => 'int', 'views' => 'int',
					'file' => 'string', 'created' => 'int', 'last_access' => 'int', 'filesize' => 'int', 'parent' => 'int', 'access' => 'string', 'link' => 'string',
					'author_id' => 'int', 'screenshot' => 'string', 'rating' => 'string', 'voters' => 'string', 'subitem' => 'int'],
				[$title, $text, $icon, $category, 'dlitem', 0, 1, $name, $now, $now, $dlfilesize, 0, '', '', $context['user']['id'], $screenshot, '', '', 0],
				['id']
			);

			$newitem = $smcFunc['db_insert_id']($request);

			// record the event
			if (($context['TPortal']['dl_approve'] == '1' && allowedTo('tp_dlmanager')) || $context['TPortal']['dl_approve'] == '0') {
				tp_recordevent($now, $context['user']['id'], 'tp-createdupload', 'action=tportal;sa=download;dl=item' . $newitem, 'Uploaded new file.', $acc, $newitem);
			}

			// should we create a topic?
			if (isset($_POST['create_topic']) && (allowedTo('admin_forum') || !empty($context['TPortal']['dl_create_topic']))) {
				$sticky = false;
				$announce = false;
				$icon = 'xx';
				// sticky and announce?
				if (isset($_POST['create_topic_sticky'])) {
					$sticky = true;
				}
				if (isset($_POST['create_topic_announce']) && allowedTo('admin_forum')) {
					$announce = true;
				}
				if (!empty($_POST['create_topic_board'])) {
					$brd = $_POST['create_topic_board'];
				}
				if (isset($_POST['create_topic_body'])) {
					$body = $_POST['create_topic_body'];
				}

				$body .= '[hr][b]' . $txt['tp-download'] . ':[/b][br]' . $scripturl . '?action=tportal;sa=download;dl=item' . $newitem;
				// ok, create the topic then
				$top = TP_createtopic($title, $body, $icon, $brd, $sticky ? 1 : 0, $context['user']['id']);
				// go to announce screen?
				if ($top > 0) {
					if ($announce) {
						redirectexit('action=announce;sa=selectgroup;topic=' . $top);
					}
					else {
						redirectexit('topic=' . $top);
					}
				}
			}
			// put this into submissions - id and type
			if ($category < 0) {
				$smcFunc['db_insert'](
					'INSERT',
					'{db_prefix}tp_variables',
					['value1' => 'string', 'value2' => 'string', 'value3' => 'string', 'type' => 'string', 'value4' => 'string', 'value5' => 'int'],
					[$title, $now, '', 'dl_not_approved', '', $newitem],
					['id']
				);
				redirectexit('action=tportal;sa=download;sub=dlsubmitsuccess');
			}
			else {
				if (!isset($_POST['tp-dluploadnot'])) {
					redirectexit('action=tportal;sa=download;dl=item' . $newitem);
				}
				elseif (isset($_POST['tp-dlupload_ftpstray'])) {
					redirectexit('action=tportal;sa=download;dl=adminftp;ftpitem=' . $newitem);
				}
				else {
					redirectexit('action=tportal;sa=download;dl=adminitem' . $newitem);
				}
			}
		}
	}

	// ok, on with the show :)
	TP_dluploadcats();
	TP_dlgeticons();
	// showing a category, or even a single item?
	$context['TPortal']['dlaction'] = '';
	if (isset($context['TPortal']['dlsub'])) {
		// a category?
		if (substr($context['TPortal']['dlsub'], 0, 3) == 'cat') {
			$context['TPortal']['dlcat'] = substr($context['TPortal']['dlsub'], 3);
			// check if its a number
			if (is_numeric($context['TPortal']['dlcat'])) {
				$context['TPortal']['dlaction'] = 'cat';
			}
			else {
				redirectexit('action=tportal;sa=download;dl');
			}
		}
		elseif (substr($context['TPortal']['dlsub'], 0, 4) == 'item') {
			$context['TPortal']['dlitem'] = substr($context['TPortal']['dlsub'], 4);
			if (is_numeric($context['TPortal']['dlitem'])) {
				$item = $context['TPortal']['dlitem'];
				$context['TPortal']['item'] = $item;
				$context['TPortal']['dlaction'] = 'item';
				$request = $smcFunc['db_query'](
					'',
					'
						SELECT category, subitem
						FROM {db_prefix}tp_dlmanager
						WHERE id = {int:dl} AND type = {string:type} LIMIT 1',
					['dl' => $item, 'type' => 'dlitem']
				);
				if ($smcFunc['db_num_rows']($request) > 0) {
					$row = $smcFunc['db_fetch_assoc']($request);
					$context['TPortal']['dlcat'] = $row['category'];
					$smcFunc['db_free_result']($request);
					// check that it is indeed a main item, if not: redirect to the main one.
					if ($row['subitem'] > 0) {
						redirectexit('action=tportal;sa=download;dl=item' . $row['subitem']);
					}
				}
				else {
					redirectexit('action=tportal;sa=download;dl');
				}
			}
			else {
				redirectexit('action=tportal;sa=download;dl');
			}
		}
		elseif ($context['TPortal']['dlsub'] == 'stats') {
			$context['TPortal']['dlaction'] = 'stats';
			$context['TPortal']['dlitem'] = '';
		}
		elseif ($context['TPortal']['dlsub'] == 'search') {
			$context['TPortal']['dlaction'] = 'search';
			$context['TPortal']['dlitem'] = '';
		}
		elseif ($context['TPortal']['dlsub'] == 'results') {
			$context['TPortal']['dlaction'] = 'results';
			$context['TPortal']['dlitem'] = '';
		}
		elseif ($context['TPortal']['dlsub'] == 'submission') {
			$context['TPortal']['dlaction'] = 'submission';
			$context['TPortal']['dlitem'] = '';
		}
		elseif (substr($context['TPortal']['dlsub'], 0, 3) == 'get') {
			$context['TPortal']['dlitem'] = substr($context['TPortal']['dlsub'], 3);
			if (is_numeric($context['TPortal']['dlitem'])) {
				$context['TPortal']['dlaction'] = 'get';
			}
			else {
				redirectexit('action=tportal;sa=download;dl');
			}
		}
		elseif (substr($context['TPortal']['dlsub'], 0, 6) == 'upload') {
			$context['TPortal']['dlitem'] = substr($context['TPortal']['dlsub'], 6);
			$context['TPortal']['dlaction'] = 'upload';

			// check your permission for uploading
			isAllowedTo('tp_dlupload');

			// Add in BBC editor before we call in template so the headers are there
			if ($context['TPortal']['dl_wysiwyg'] == 'bbc') {
				$context['TPortal']['editor_id'] = 'tp_dluploadtext';
				TP_prebbcbox($context['TPortal']['editor_id']);
			}
			elseif ($context['TPortal']['dl_wysiwyg'] == 'html') {
				TPwysiwyg_setup();
			}
			TP_dlgeticons();

			// allow to attach this to another item
			$context['TPortal']['attachitems'] = [];
			if (allowedTo('dlmanager')) {
				// get all items for a list
				$itemlist = $smcFunc['db_query'](
					'',
					'
					SELECT id, name FROM {db_prefix}tp_dlmanager
					WHERE type = {string:type} AND subitem = {int:sub} ORDER BY name ASC',
					['type' => 'dlitem', 'sub' => 0]
				);
				if ($smcFunc['db_num_rows']($itemlist) > 0) {
					while ($ilist = $smcFunc['db_fetch_assoc']($itemlist)) {
						$context['TPortal']['attachitems'][] = [
							'id' => $ilist['id'],
							'name' => $ilist['name'],
						];
					}
					$smcFunc['db_free_result']($itemlist);
				}
			}
			else {
				// how about attaching to one of your own?
				// get all items for a list
				$itemlist = $smcFunc['db_query'](
					'',
					'
					SELECT id,name FROM {db_prefix}tp_dlmanager
					WHERE category > {int:cat}
					AND type = {string:type}
					AND subitem = {int:sub}
					AND author_id = {int:auth}
					ORDER BY name ASC',
					['cat' => 0, 'type' => 'dlitem', 'sub' => 0, 'auth' => $context['user']['id']]
				);
				if (isset($itemlist) && $smcFunc['db_num_rows']($itemlist) > 0) {
					while ($ilist = $smcFunc['db_fetch_assoc']($itemlist)) {
						$context['TPortal']['attachitems'][] = [
							'id' => $ilist['id'],
							'name' => $ilist['name'],
						];
					}
					$smcFunc['db_free_result']($itemlist);
				}
			}

			$context['TPortal']['boards'] = [];
			// fetch all boards
			$request = $smcFunc['db_query']('', '
				SELECT b.id_board, b.name, b.board_order FROM {db_prefix}boards as b
				WHERE {query_see_board}
				ORDER BY b.board_order ASC');
			if ($smcFunc['db_num_rows']($request) > 0) {
				while ($row = $smcFunc['db_fetch_assoc']($request)) {
					$context['TPortal']['boards'][] = ['id' => $row['id_board'], 'name' => $row['name']];
				}

				$smcFunc['db_free_result']($request);
			}
		}

		// a category?
		else {
			// check its really exists
			$what = $context['TPortal']['dlsub'];
			$request = $smcFunc['db_query'](
				'',
				'
				SELECT id FROM {db_prefix}tp_dlmanager
				WHERE link = {string:link} LIMIT 1',
				['link' => $what]
			);
			if (isset($request) && $smcFunc['db_num_rows']($request) > 0) {
				$row = $smcFunc['db_fetch_assoc']($request);
				$context['TPortal']['dlcat'] = $row['id'];
				$context['TPortal']['dlsub'] = 'cat' . $row['id'];
				$context['TPortal']['dlaction'] = 'cat';
				$smcFunc['db_free_result']($request);
			}
		}
	}
	// add to the linktree
	TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=0', $txt['tp-downloads']);

	// set the title
	$context['page_title'] = $txt['tp-downloads'];
	$context['TPortal']['dl_title'] = $txt['tp-mainpage'];

	// DLmanager frontpage
	if ($context['TPortal']['dlaction'] == '') {
		$context['TPortal']['dlcats'] = [];
		$context['TPortal']['dlcatchilds'] = [];

		// add x most recent and feature the last one
		$context['TPortal']['dl_last_added'] = [];
		$context['TPortal']['dl_most_downloaded'] = [];
		$context['TPortal']['dl_week_downloaded'] = [];

		$mycats = [];
		dl_getcats();
		foreach ($context['TPortal']['dl_allowed_cats'] as $ca) {
			$mycats[] = $ca['id'];
		}

		// empty?
		if (sizeof($mycats) > 0) {
			$request = $smcFunc['db_query'](
				'',
				'
				SELECT dlm.id, dlm.name, dlm.icon, dlm.category, dlm.file, dlm.downloads, dlm.views,
					dlm.author_id AS author_id, dlm.created, dlm.screenshot, dlm.filesize,
					dlcat.name AS catname, mem.real_name AS real_name, LEFT(dlm.description,100) AS description
				FROM {db_prefix}tp_dlmanager AS dlm
				LEFT JOIN  {db_prefix}members AS mem
					ON dlm.author_id = mem.id_member
				LEFT JOIN {db_prefix}tp_dlmanager AS dlcat
					ON dlcat.id = dlm.category
				WHERE dlm.type = {string:type}
				AND dlm.category IN ({array_int:cat})
				ORDER BY dlm.created DESC LIMIT 6',
				['type' => 'dlitem', 'cat' => $mycats]
			);

			if ($smcFunc['db_num_rows']($request) > 0) {
				while ($row = $smcFunc['db_fetch_assoc']($request)) {
					$fs = '';
					if ($context['TPortal']['dl_fileprefix'] == 'K') {
						$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
						$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
						$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
					}

					$ico = '';
					$thumb = '';
					if ($context['TPortal']['dl_usescreenshot'] == 1) {
						if (!empty($row['screenshot'])) {
							if (file_exists($context['TPortal']['image_upload_path'] . 'dlmanager/thumb/' . $row['screenshot'])) {
								$ico = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/thumb/' . $row['screenshot'];
								$thumb = $row['icon'];
							}
						}
					}

					$context['TPortal']['dl_last_added'][] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'icon' => $row['icon'],
						'category' => $row['category'],
						'description' => $context['TPortal']['dl_wysiwyg'] == 'bbc' ? parse_bbc($row['description']) : $row['description'],
						'file' => $row['file'],
						'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'],
						'downloads' => $row['downloads'],
						'views' => $row['views'],
						'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
						'author_id' => $row['author_id'],
						'date' => timeformat($row['created']),
						'screenshot' => $ico,
						'catname' => $row['catname'],
						'cathref' => $scripturl . '?action=tportal;sa=download;dl=cat' . $row['category'],
						'filesize' => $fs,
					];
				}
				$smcFunc['db_free_result']($request);
			}
			$request = $smcFunc['db_query'](
				'',
				'
				SELECT dlm.id, dlm.name, dlm.icon, dlm.category, dlm.file, dlm.downloads, dlm.views,
					dlm.author_id as author_id, dlm.created, dlm.filesize, dlcat.name AS catname,
					mem.real_name as real_name
				FROM {db_prefix}tp_dlmanager AS dlm
				LEFT JOIN {db_prefix}members AS mem
					ON dlm.author_id = mem.id_member
				LEFT JOIN {db_prefix}tp_dlmanager AS dlcat
					ON dlcat.id = dlm.category
				WHERE dlm.type = {string:type}
				AND dlm.category IN ({array_string:cat})
				ORDER BY dlm.downloads DESC LIMIT 6',
				['type' => 'dlitem', 'cat' => $mycats]
			);

			if ($smcFunc['db_num_rows']($request) > 0) {
				while ($row = $smcFunc['db_fetch_assoc']($request)) {
					$fs = '';
					if ($context['TPortal']['dl_fileprefix'] == 'K') {
						$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
						$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
						$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
					}

					if ($context['TPortal']['dl_usescreenshot'] == 1) {
						if (!empty($row['screenshot'])) {
							$ico = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/thumb/' . $row['screenshot'];
						}
						else {
							$ico = '';
						}
					}
					else {
						$ico = '';
					}

					$context['TPortal']['dl_most_downloaded'][] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'icon' => $row['icon'],
						'category' => $row['category'],
						'file' => $row['file'],
						'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'],
						'downloads' => $row['downloads'],
						'views' => $row['views'],
						'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
						'author_id' => $row['author_id'],
						'date' => timeformat($row['created']),
						'screenshot' => $ico,
						'catname' => $row['catname'],
						'cathref' => $scripturl . '?action=tportal;sa=download;dl=cat' . $row['category'],
						'filesize' => $fs,
					];
				}
				$smcFunc['db_free_result']($request);
			}
			// fetch most downloaded this week
			$now = time();
			$week = (int) date('W', $now);
			$year = (int) date('Y', $now);
			$request = $smcFunc['db_query'](
				'',
				'
				SELECT dlm.id, dlm.name, dlm.icon, dlm.category, dlm.file, data.downloads, dlm.views,
					dlm.author_id as author_id, dlm.created, dlm.screenshot, dlm.filesize,
					dlcat.name AS catname, mem.real_name as real_name
				FROM {db_prefix}tp_dlmanager AS dlm
				LEFT JOIN {db_prefix}tp_dldata AS data
					ON data.item = dlm.id
				LEFT JOIN {db_prefix}members AS mem
					ON dlm.author_id = mem.id_member
				LEFT JOIN {db_prefix}tp_dlmanager AS dlcat
					ON dlcat.id = dlm.category
				WHERE dlm.type = {string:type}
				AND dlm.category IN ({array_string:cat})
				AND data.year = {int:yr}
				AND data.week = {int:week}
				AND dlm.author_id = mem.id_member
				ORDER BY data.downloads DESC LIMIT 6',
				['type' => 'dlitem', 'cat' => $mycats, 'yr' => $year, 'week' => $week]
			);

			if ($smcFunc['db_num_rows']($request) > 0) {
				while ($row = $smcFunc['db_fetch_assoc']($request)) {
					if ($context['TPortal']['dl_usescreenshot'] == 1) {
						if (!empty($row['screenshot'])) {
							$ico = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/thumb/' . $row['screenshot'];
						}
						else {
							$ico = '';
						}
					}
					else {
						$ico = '';
					}

					$fs = '';
					if ($context['TPortal']['dl_fileprefix'] == 'K') {
						$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
						$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
						$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
					}

					$context['TPortal']['dl_week_downloaded'][] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'icon' => $row['icon'],
						'category' => $row['category'],
						'file' => $row['file'],
						'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'],
						'downloads' => $row['downloads'],
						'views' => $row['views'],
						'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
						'author_id' => $row['author_id'],
						'date' => timeformat($row['created']),
						'screenshot' => $ico,
						'catname' => $row['catname'],
						'cathref' => $scripturl . '?action=tportal;sa=download;dl=cat' . $row['category'],
						'filesize' => $fs,
					];
				}
				$smcFunc['db_free_result']($request);
			}
		}
		// fetch the categories, the number of files
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT a.access AS access, a.icon AS icon, a.link AS shortname, a.description AS description,
				a.name AS name, a.id AS id, a.parent AS parent, a.downloads AS downloads,
	  			( CASE WHEN a.id = b.category THEN COUNT(a.id) ELSE 0 END ) AS files, b.category AS subchild
			FROM {db_prefix}tp_dlmanager AS a
			LEFT JOIN {db_prefix}tp_dlmanager AS b
				ON a.id = b.category
			WHERE a.type = {string:type}
		  	GROUP BY a.id, a.access, a.icon, a.link, a.description, a.name, a.parent, a.downloads, b.category
			ORDER BY a.downloads ASC',
			['type' => 'dlcat']
		);

		$fetched_cats = [];
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$show = get_perm($row['access'], 'tp_dlmanager');
				if ($show && $row['parent'] == 0) {
					$context['TPortal']['dlcats'][$row['id']] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'parent' => $row['parent'],
						'description' => $context['TPortal']['dl_wysiwyg'] == 'bbc' ? parse_bbc($row['description']) : $row['description'],
						'access' => $row['access'],
						'icon' => $row['icon'],
						'href' => !empty($row['shortname']) ? $scripturl . '?action=tportal;sa=download;dl=' . $row['shortname'] : $scripturl . '?action=tportal;sa=download;dl=cat' . $row['id'],
						'shortname' => !empty($row['shortname']) ? $row['shortname'] : $row['id'],
						'files' => $row['files'],
					];
					$fetched_cats[] = $row['id'];
				}
				elseif ($show && $row['parent'] > 0) {
					$context['TPortal']['dlcatchilds'][] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'parent' => $row['parent'],
						'icon' => $row['icon'],
						'href' => $scripturl . '?action=tportal;sa=download;dl=cat' . $row['id'],
						'files' => $row['files'],
					];
				}
			}
			$smcFunc['db_free_result']($request);
		}
		// add filecount to parent
		foreach ($context['TPortal']['dlcatchilds'] as $child) {
			if (isset($context['TPortal']['dlcats'][$child['parent']]) && $context['TPortal']['dlcats'][$child['parent']]['parent'] == 0) {
				$context['TPortal']['dlcats'][$child['parent']]['files'] = $context['TPortal']['dlcats'][$child['parent']]['files'] + $child['files'];
			}
		}
		// do we need the featured one?
		if (!empty($context['TPortal']['dl_featured'])) {
			// fetch the item data
			$item = $context['TPortal']['dl_featured'];
			$request = $smcFunc['db_query'](
				'',
				'
					SELECT dl.* , dl.author_id as author_id, m.real_name as real_name
					FROM {db_prefix}tp_dlmanager AS dl
					LEFT JOIN {db_prefix}members AS m
						ON dl.author_id = m.id_member
					WHERE dl.type = {string:type}
					AND dl.id = {int:item}
					LIMIT 1',
				['type' => 'dlitem', 'item' => $item]
			);
			if ($smcFunc['db_num_rows']($request) > 0) {
				$row = $smcFunc['db_fetch_assoc']($request);
				if ($context['TPortal']['dl_fileprefix'] == 'K') {
					$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
					$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
					$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
				}

				$rat = [];
				$rating_votes = 0;
				$rat = explode(',', $row['rating']);
				$rating_votes = count($rat);
				if ($row['rating'] == '') {
					$rating_votes = 0;
				}

				$total = 0;
				foreach ($rat as $mm => $mval) {
					if (is_numeric($mval)) {
						$total = $total + $mval;
					}
				}

				if ($rating_votes > 0 && $total > 0) {
					$rating_average = floor($total / $rating_votes);
				}
				else {
					$rating_average = 0;
				}

				// does it exist?
				if (file_exists($context['TPortal']['image_upload_path'] . 'dlmanager/listing/' . $row['screenshot']) && !empty($row['screenshot'])) {
					$decideshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/listing/' . $row['screenshot'];
				}
				else {
					$decideshot = '';
				}

				if ($context['user']['is_logged']) {
					$can_rate = in_array($context['user']['id'], explode(',', $row['voters'])) ? false : true;
				}
				else {
					$can_rate = false;
				}

				$context['TPortal']['featured'] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'description' => $context['TPortal']['dl_wysiwyg'] == 'bbc' ? parse_bbc($row['description']) : $row['description'],
					'category' => $row['category'],
					'file' => $row['file'],
					'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'],
					'downloads' => $row['downloads'],
					'views' => $row['views'],
					'link' => $row['link'],
					'date_last' => $row['last_access'],
					'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
					'author_id' => $row['author_id'],
					'screenshot' => $row['screenshot'],
					'sshot' => $decideshot,
					'icon' => $row['icon'],
					'created' => $row['created'],
					'filesize' => $fs,
					'subitem' => isset($fdata) ? $fdata : '',
					'rating_votes' => $rating_votes,
					'rating_average' => $rating_average,
					'can_rate' => $can_rate,
				];
			}
			$smcFunc['db_free_result']($request);
		}
		$context['TPortal']['dlheader'] = $txt['tp-downloads'];
	}
	// DLmanager category page
	elseif ($context['TPortal']['dlaction'] == 'cat') {
		// check if sorting is specified
		if (isset($_GET['dlsort']) && in_array($_GET['dlsort'], ['id', 'name', 'last_access', 'created', 'downloads', 'author_id'])) {
			$context['TPortal']['dlsort'] = $dlsort = $_GET['dlsort'];
		}
		else {
			$context['TPortal']['dlsort'] = $dlsort = 'id';
		}

		if (isset($_GET['asc'])) {
			$context['TPortal']['dlsort_way'] = $dlsort_way = 'asc';
		}
		else {
			$context['TPortal']['dlsort_way'] = $dlsort_way = 'desc';
		}

		$currentcat = $context['TPortal']['dlcat'];
		//fetch all  categories and its childs
		$context['TPortal']['dlcats'] = [];
		$context['TPortal']['dlcatchilds'] = [];
		$context['TPortal']['dl_week_downloaded'] = [];

		// fetch most downloaded this week
		$now = time();
		$week = (int) date('W', $now);
		$year = (int) date('Y', $now);
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT dlm.id, dlm.name, dlm.icon, dlm.category, dlm.file, dlm.downloads, dlm.views, dlm.author_id AS author_id, dlm.created, dlm.screenshot, dlm.filesize,
			dlcat.name AS catname, mem.real_name as real_name
			FROM {db_prefix}tp_dlmanager AS dlm
			LEFT JOIN {db_prefix}tp_dldata AS data
				ON data.item = dlm.id
			LEFT JOIN {db_prefix}members AS mem
				ON dlm.author_id = mem.id_member
			LEFT JOIN {db_prefix}tp_dlmanager AS dlcat
				ON dlcat.id = dlm.category
			WHERE dlm.type = {string:type}
			AND (dlm.category = {int:cat} OR dlm.parent = {int:cat})
			AND data.year = {int:year}
			AND data.week = {int:week}
			ORDER BY dlm.downloads DESC LIMIT 6',
			['type' => 'dlitem', 'cat' => $currentcat, 'year' => $year, 'week' => $week]
		);

		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				if ($context['TPortal']['dl_usescreenshot'] == 1) {
					if (!empty($row['screenshot'])) {
						$ico = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/thumb/' . $row['screenshot'];
					}
					else {
						$ico = '';
					}
				}
				else {
					$ico = '';
				}

				$fs = '';
				if ($context['TPortal']['dl_fileprefix'] == 'K') {
					$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
					$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
					$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
				}

				$context['TPortal']['dl_week_downloaded'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'icon' => $row['icon'],
					'category' => $row['category'],
					'file' => $row['file'],
					'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'],
					'downloads' => $row['downloads'],
					'views' => $row['views'],
					'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
					'author_id' => $row['author_id'],
					'date' => timeformat($row['created']),
					'screenshot' => $ico,
					'catname' => $row['catname'],
					'cathref' => $scripturl . '?action=tportal;sa=download;dl=cat' . $row['category'],
					'filesize' => $fs,
				];
			}
			$smcFunc['db_free_result']($request);
		}

		// add x most recent and feature the last one
		$context['TPortal']['dl_last_added'] = dl_recentitems(5, 'date', 'array', $context['TPortal']['dlcat']);
		$context['TPortal']['dl_most_downloaded'] = dl_recentitems(5, 'downloads', 'array', $context['TPortal']['dlcat']);

		// do we have access then?
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT parent, access, name
			FROM {db_prefix}tp_dlmanager
			WHERE id = {int:cat}',
			['cat' => $currentcat]
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$currentname = $row['name'];
				$context['page_title'] = $row['name'];
				$catparent = $row['parent'];
				if (!get_perm($row['access'], 'tp_dlmanager')) {
					// if a guest, make them login/register
					if ($context['user']['is_guest']) {
						redirectexit('action=login');
						;
					}
					else {
						redirectexit('action=tportal;sa=download;dl');
					}
				}
			}
			$smcFunc['db_free_result']($request);
		}
		// nothing there, let them know
		else {
			redirectexit('action=tportal;sa=download;dl');
		}

		$request = $smcFunc['db_query'](
			'',
			'
			SELECT a.access AS access, a.icon AS icon,	a.link AS shortname, a.description AS description,
				a.name AS name,	a.id AS id, a.parent AS parent, a.downloads AS downloads, ( CASE WHEN a.id = b.category THEN COUNT(a.id) ELSE 0 END ) AS files,
		  		b.category AS subchild
			FROM {db_prefix}tp_dlmanager AS a
			LEFT JOIN {db_prefix}tp_dlmanager AS b
		  		ON a.id = b.category
			WHERE a.type = {string:type}
			AND a.parent = {int:cat}
		  	GROUP BY a.id, a.access, a.icon, a.link, a.description,	a.name, a.parent, a.downloads, b.category
		  	ORDER BY a.downloads ASC',
			['type' => 'dlcat', 'cat' => $currentcat]
		);
		$context['TPortal']['dlchildren'] = [];
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$show = get_perm($row['access'], 'tp_dlmanager');
				if ($show && $row['parent'] == $currentcat) {
					$context['TPortal']['dlcats'][] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'parent' => $row['parent'],
						'description' => $context['TPortal']['dl_wysiwyg'] == 'bbc' ? parse_bbc($row['description']) : $row['description'],
						'access' => $row['access'],
						'icon' => $row['icon'],
						'href' => !empty($row['shortname']) ? $scripturl . '?action=tportal;sa=download;dl=' . $row['shortname'] : $scripturl . '?action=tportal;sa=download;dl=cat' . $row['id'],
						'shortname' => !empty($row['shortname']) ? $row['shortname'] : $row['id'],
						'files' => $row['files'],
					];
				}
				elseif ($show && $row['parent'] != $currentcat) {
					$context['TPortal']['dlchildren'][] = $row['id'];
					$context['TPortal']['dlcatchilds'][] = [
						'id' => $row['id'],
						'name' => $row['name'],
						'parent' => $row['parent'],
						'href' => !empty($row['shortname']) ? $scripturl . '?action=tportal;sa=download;dl=' . $row['shortname'] : $scripturl . '?action=tportal;sa=download;dl=cat' . $row['id'],
						'shortname' => !empty($row['shortname']) ? $row['shortname'] : $row['id'],
						'files' => $row['files'],
					];
				}
			}
			$smcFunc['db_free_result']($request);
		}

		// get any items in the category
		$context['TPortal']['dlitem'] = [];
		$start = 0;
		if (isset($_GET['p']) && !is_numeric($_GET['p'])) {
			fatal_error($txt['tp-dlnoninteger'],false);
		}
		elseif (isset($_GET['p']) && is_numeric($_GET['p'])) {
			$start = $_GET['p'];
		}
		$downloads_per_page = 10;

		// get total count
		$request = $smcFunc['db_query'](
			'',
			'
				SELECT COUNT(*) FROM {db_prefix}tp_dlmanager
				WHERE type = {string:type}
				AND category = {int:cat}
				AND subitem = {int:sub}',
			['type' => 'dlitem', 'cat' => $currentcat, 'sub' => 0]
		);
		$row = $smcFunc['db_fetch_row']($request);
		$rows2 = $row[0];

		$request = $smcFunc['db_query'](
			'',
			'
				SELECT dl.id, dl.description, dl.name, dl.category, dl.file,
					dl.downloads, dl.views, dl.link, dl.created, dl.last_access,
					dl.author_id as author_id, dl.icon, dl.screenshot, dl.filesize, mem.real_name as real_name
				FROM {db_prefix}tp_dlmanager as dl
				LEFT JOIN {db_prefix}members as mem ON (dl.author_id=mem.id_member)
				WHERE dl.type = {string:type}
				AND dl.category = {int:cat}
				AND dl.subitem = {int:sub}
				ORDER BY dl.' . $dlsort . ' ' . $dlsort_way . ' LIMIT {int:limit} OFFSET {int:start}',
			['type' => 'dlitem', 'cat' => $currentcat, 'sub' => 0, 'start' => $start, 'limit' => $downloads_per_page]
		);

		if ($smcFunc['db_num_rows']($request) > 0) {
			// set up the sorting links
			$context['TPortal']['sortlinks'] = '<span class="smalltext">' . $txt['tp-sortby'] . ': ';
			$what = ['id', 'name', 'downloads', 'last_access', 'created', 'author_id'];
			foreach ($what as $v) {
				if ($context['TPortal']['dlsort'] == $v) {
					$context['TPortal']['sortlinks'] .= '<a href="' . $scripturl . '?action=tportal;sa=download;dl=cat' . $currentcat . ';dlsort=' . $v . ';';
					if ($context['TPortal']['dlsort_way'] == 'asc') {
						$context['TPortal']['sortlinks'] .= 'desc;p=' . $start . '">' . $txt['tp-' . $v] . ' <img src="' . $settings['tp_images_url'] . '/TPsort_up.png" alt="" /></a> &nbsp;|&nbsp; ';
					}
					else {
						$context['TPortal']['sortlinks'] .= 'asc;p=' . $start . '">' . $txt['tp-' . $v] . ' <img src="' . $settings['tp_images_url'] . '/TPsort_down.png" alt="" /></a> &nbsp;|&nbsp; ';
					}
				}
				else {
					$context['TPortal']['sortlinks'] .= '<a href="' . $scripturl . '?action=tportal;sa=download;dl=cat' . $currentcat . ';dlsort=' . $v . ';desc;p=' . $start . '">' . $txt['tp-' . $v] . '</a> &nbsp;|&nbsp; ';
				}
			}
			$context['TPortal']['sortlinks'] = substr($context['TPortal']['sortlinks'], 0, strlen($context['TPortal']['sortlinks']) - 15);
			$context['TPortal']['sortlinks'] .= '</span>';

			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				if (TPUtil::shortenString($row['description'], $context['TPortal']['dl_limit_length'])) {
					$row['readmore'] = '...';
				}
				if (substr($row['screenshot'], 0, 16) == 'tp-images/Image/') {
					$decideshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . $row['screenshot'];
				}
				else {
					$decideshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/thumb/' . $row['screenshot'];
				}

				if ($context['TPortal']['dl_fileprefix'] == 'K') {
					$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
					$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
					$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
				}

				if ($context['TPortal']['dl_usescreenshot'] == 1) {
					if (!empty($row['screenshot'])) {
						$ico = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/thumb/' . $row['screenshot'];
					}
					else {
						$ico = $row['icon'];
					}
				}
				else {
					$ico = $row['icon'];
				}

				$context['TPortal']['dlitem'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'category' => $row['category'],
					'file' => $row['file'],
					'description' => $context['TPortal']['dl_wysiwyg'] == 'bbc' ? parse_bbc($row['description']) : $row['description'],
					'readmore' => isset($row['readmore']) ? $row['readmore'] : '',
					'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'],
					'dlhref' => $scripturl . '?action=tportal;sa=download;dl=get' . $row['id'],
					'downloads' => $row['downloads'],
					'views' => $row['views'],
					'link' => $row['link'],
					'created' => $row['created'],
					'date_last' => $row['last_access'],
					'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
					'author_id' => $row['author_id'],
					'screenshot' => $row['screenshot'],
					'sshot' => $decideshot,
					'icon' => $ico,
					'date' => $row['created'],
					'filesize' => $fs,
				];
			}
			$smcFunc['db_free_result']($request);
		}
		if (isset($context['TPortal']['mystart'])) {
			$mystart = $context['TPortal']['mystart'];
		}

		$currsorting = '';
		if (!empty($dlsort)) {
			$currsorting .= ';dlsort=' . $dlsort;
		}
		if (!empty($dlsort_way)) {
			$currsorting .= ';' . $dlsort_way;
		}

		// construct a pageindex
		$context['TPortal']['pageindex'] = TPageIndex($scripturl . '?action=tportal;sa=download;dl=cat' . $currentcat . $currsorting, $mystart, $rows2, $downloads_per_page);

		// check backwards for parents
		$done = 0;
		$context['TPortal']['parents'] = [];
		while ($catparent > 0 || $done < 2) {
			if (!empty($context['TPortal']['cats'][$catparent])) {
				$context['TPortal']['parents'][] = [
					'id' => $catparent,
					'name' => $context['TPortal']['cats'][$catparent]['name'],
					'parent' => $context['TPortal']['cats'][$catparent]['parent']
				];
				$catparent = $context['TPortal']['cats'][$catparent]['parent'];
			}
			else {
				$catparent = 0;
			}

			if ($catparent == 0) {
				$done++;
			}
		}

		// make the linktree
		if (sizeof($context['TPortal']['parents']) > 0) {
			$parts = array_reverse($context['TPortal']['parents']);
			// add to the linktree
			foreach ($parts as $par) {
				TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=cat' . $par['id'], $par['name']);
			}
		}
		// add to the linktree
		TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=cat' . $currentcat, $currentname);
		$context['TPortal']['dlheader'] = $currentname;
	}
	// DLmanager item page
	elseif ($context['TPortal']['dlaction'] == 'item') {
		//fetch the category
		$cat = $context['TPortal']['dlcat'];
		$context['TPortal']['dlcats'] = [];
		$catname = '';
		$catdesc = '';

		$request = $smcFunc['db_query'](
			'',
			'
			SELECT id, name, parent, icon, access, link
			FROM {db_prefix}tp_dlmanager
			WHERE id = {int:cat}
			AND type = {string:type} LIMIT 1',
			['cat' => $cat, 'type' => 'dlcat']
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$catshortname = $row['link'];
				$catname = $row['name'];
				$catparent = $row['parent'];
				$firstparent = $row['parent'];

				// check if you are allowed in here
				$show = get_perm($row['access'], 'tp_dlmanager');
				if (!$show) {
					redirectexit('action=tportal;sa=download;dl');
				}
			}
			$smcFunc['db_free_result']($request);
		}

		// set the title
		$context['TPortal']['dl_title'] = $catname;

		$context['TPortal']['parents'] = [];
		// check backwards for parents
		$done = 0;
		while ($catparent > 0 || $done < 2) {
			if (!empty($context['TPortal']['cats'][$catparent])) {
				$context['TPortal']['parents'][] = [
					'id' => $catparent,
					'shortname' => $catshortname,
					'name' => $context['TPortal']['cats'][$catparent]['name'],
					'parent' => $context['TPortal']['cats'][$catparent]['parent']
				];
				$catparent = $context['TPortal']['cats'][$catparent]['parent'];
			}
			else {
				$catparent = 0;
			}
			if ($catparent == 0) {
				$done++;
			}
		}

		// make the linktree
		if (sizeof($context['TPortal']['parents']) > 0) {
			$parts = array_reverse($context['TPortal']['parents'], true);
			// add to the linktree
			foreach ($parts as $parent) {
				if (!empty($parent['shortname'])) {
					TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=' . $parent['shortname'], $parent['name']);
				}
				else {
					TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=cat' . $parent['id'], $parent['name']);
				}
			}
		}

		// fetch the item data
		$item = $context['TPortal']['item'] = $item;
		$context['TPortal']['dlitem'] = [];
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT dl.*, dl.author_id as author_id, m.real_name as real_name
			FROM {db_prefix}tp_dlmanager AS dl
			LEFT JOIN {db_prefix}members AS m
			ON m.id_member = dl.author_id
			WHERE dl.type = {string:type}
			AND dl.id = {int:item}
			LIMIT 1',
			['type' => 'dlitem', 'item' => $item]
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			$rows = $smcFunc['db_num_rows']($request);
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$subitem = $row['id'];
				$fetch = $smcFunc['db_query'](
					'',
					'
					SELECT id, name, file, downloads, filesize, created, views
					FROM {db_prefix}tp_dlmanager
					WHERE type = {string:type}
					AND subitem = {int:sub}
					ORDER BY id DESC',
					['type' => 'dlitem', 'sub' => $subitem]
				);

				if ($smcFunc['db_num_rows']($fetch) > 0) {
					$fdata = [];
					while ($frow = $smcFunc['db_fetch_assoc']($fetch)) {
						if ($context['TPortal']['dl_fileprefix'] == 'K') {
							$ffs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
						}
						elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
							$ffs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
						}
						elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
							$ffs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
						}

						$fdata[] = [
							'id' => $frow['id'],
							'name' => $frow['name'],
							'file' => $frow['file'],
							'href' => $scripturl . '?action=tportal;sa=download;dl=get' . $frow['id'],
							'href2' => $scripturl . '?action=tportal;sa=download;dl=item' . $frow['id'],
							'downloads' => $frow['downloads'],
							'views' => $frow['views'],
							'created' => $frow['created'],
							'filesize' => $ffs,
						];
					}
					$smcFunc['db_free_result']($fetch);
				}

				if ($context['TPortal']['dl_fileprefix'] == 'K') {
					$fs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
					$fs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
					$fs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
				}

				$rat = [];
				$rating_votes = 0;
				$rat = explode(',', $row['rating']);
				$rating_votes = count($rat);
				if ($row['rating'] == '') {
					$rating_votes = 0;
				}

				$total = 0;
				foreach ($rat as $mm => $mval) {
					if (is_numeric($mval)) {
						$total = $total + $mval;
					}
				}

				if ($rating_votes > 0 && $total > 0) {
					$rating_average = floor($total / $rating_votes);
				}
				else {
					$rating_average = 0;
				}

				$bigshot = $decideshot = !empty($row['screenshot']) ? $boardurl . '/' . $row['screenshot'] : '';
				// does it exist?
				if (file_exists($context['TPortal']['image_upload_path'] . 'dlmanager/listing/' . $row['screenshot']) && !empty($row['screenshot'])) {
					$decideshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/listing/' . $row['screenshot'];
				}
				else {
					$decideshot = '';
				}
				if (file_exists($context['TPortal']['image_upload_path'] . 'dlmanager/' . $row['screenshot']) && !empty($row['screenshot'])) {
					$bigshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/' . $row['screenshot'];
				}
				else {
					$bigshot = '';
				}

				if ($context['user']['is_logged']) {
					$can_rate = in_array($context['user']['id'], explode(',', $row['voters'])) ? false : true;
				}
				else {
					$can_rate = false;
				}

				$context['TPortal']['dlitem'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'description' => $context['TPortal']['dl_wysiwyg'] == 'bbc' ? parse_bbc($row['description']) : $row['description'],
					'category' => $row['category'],
					'file' => $row['file'],
					'href' => $scripturl . '?action=tportal;sa=download;dl=get' . $row['id'],
					'downloads' => $row['downloads'],
					'views' => $row['views'],
					'link' => $row['link'],
					'date_last' => $row['last_access'],
					'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
					'author_id' => $row['author_id'],
					'screenshot' => $row['screenshot'],
					'sshot' => $decideshot,
					'bigshot' => $bigshot,
					'icon' => $row['icon'],
					'created' => $row['created'],
					'filesize' => $fs,
					'subitem' => isset($fdata) ? $fdata : '',
					'rating_votes' => $rating_votes,
					'rating_average' => $rating_average,
					'can_rate' => $can_rate,
				];
				$author = $row['author_id'];
				$parent_cat = $row['category'];
				$views = $row['views'];
				$itemname = $row['name'];
				$itemid = $row['id'];
				$context['page_title'] = $row['name'];
			}
			$smcFunc['db_free_result']($request);
			TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=cat' . $parent_cat, $catname);
			TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=item' . $itemid, $itemname);
			// update the views and last access!
			$views++;
			$now = time();
			$year = (int) date('Y', $now);
			$week = (int) date('W', $now);
			// update weekly views
			$req = $smcFunc['db_query'](
				'',
				'
				SELECT id FROM {db_prefix}tp_dldata
				WHERE year = {int:year}
				AND week = {int:week}
				AND item = {int:item}',
				['year' => $year, 'week' => $week, 'item' => $itemid]
			);
			if ($smcFunc['db_num_rows']($req) > 0) {
				$row = $smcFunc['db_fetch_assoc']($req);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dldata
					SET views = views + 1
					WHERE id = {int:item}',
					['item' => $row['id']]
				);
			}
			else {
				$smcFunc['db_insert'](
					'INSERT',
					'{db_prefix}tp_dldata',
					[
						'week' => 'int',
						'year' => 'int',
						'views' => 'int',
						'item' => 'int',
						'downloads' => 'int'
					],
					[$week, $year, 1, $itemid, 0],
					['id']
				);
			}

			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dlmanager
				SET views = {int:views}, last_access = {int:last}
				WHERE id = {int:item}',
				['views' => $views, 'last' => $now, 'item' => $itemid]
			);
			$context['TPortal']['dlheader'] = $itemname;
		}
	}
	elseif ($context['TPortal']['dlaction'] == 'get') {
		TPdownloadme();
	}
	elseif ($context['TPortal']['dlaction'] == 'stats') {
		TPdlstats();
	}
	elseif ($context['TPortal']['dlaction'] == 'results') {
		TPdlresults();
	}
	elseif ($context['TPortal']['dlaction'] == 'search') {
		TPdlsearch();
	}

	// For wireless, we use the Wireless template...
	if (defined('WIRELESS') && WIRELESS) {
		loadTemplate('TPwireless');
		if ($context['TPortal']['dlaction'] == 'item' || $context['TPortal']['dlaction'] == 'cat') {
			$what = $context['TPortal']['dlaction'];
		}
		else {
			$what = 'main';
		}

		$context['sub_template'] = WIRELESS_PROTOCOL . '_tp_dl_' . $what;
	}
	else {
		loadTemplate('TPdlmanager');
	}
}

// searched the files?
function TPdlresults()
{
	global $txt, $scripturl, $context, $user_info, $smcFunc;

	$start = 0;
	$max_results = 20;
	$usebody = false;
	$usetitle = false;
	
	if (isset($_GET['p']) && is_numeric($_GET['p'])) {
		$start = $_GET['p'];
	}

	checkSession('post');

	// nothing to search for?
	if (empty($_POST['dl_search'])) {
		fatal_error($txt['tp-nosearchentered'], false);
	}

	// clean the search
	$what2 = str_replace(' ', '%', strip_tags($_POST['dl_search']));
	$what = strip_tags($_POST['dl_search']);

	if (!empty($_POST['dl_searcharea_name'])) {
		$usetitle = true;
	}
	else {
		$usetitle = false;
	}
	if (!empty($_POST['dl_searcharea_desc'])) {
		$usebody = true;
	}
	else {
		$usebody = false;
	}

	if ($usetitle && !$usebody) {
		$query = 'd.name LIKE \'%{raw:what}%\'';
	}
	elseif (!$usetitle && $usebody) {
		$query = 'd.description LIKE \'%{raw:what}%\'';
	}
	elseif ($usetitle && $usebody) {
		$query = 'd.name LIKE \'%{raw:what}%\' OR d.description LIKE \'%{raw:what}%\'';
	}
	else {
		$query = 'd.name LIKE \'%{raw:what}%\'';
	}

	$dlquery = '(FIND_IN_SET(' . implode(', access) OR FIND_IN_SET(', $user_info['groups']) . ', access))';

	// find out which categories you have access to
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT id FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}
		AND ' . $dlquery,
		['type' => 'dlcat']
	);
	$allowedcats = [];
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			$allowedcats[] = $row['id'];
		}
		$smcFunc['db_free_result']($request);
	}
	else {
		$allowedcats[0] = -1;
	}

	$context['TPortal']['dlsearchresults'] = [];
	$context['TPortal']['dlsearchterm'] = $what;

	// find how many first
	$check = $smcFunc['db_query'](
		'',
		'
		SELECT COUNT(d.id)
		FROM {db_prefix}tp_dlmanager AS d
		WHERE ' . $query . '
		AND type = {string:type}',
		['type' => 'dlitem', 'what' => $what2]
	);
	$tt = $smcFunc['db_fetch_row']($check);
	$total = $tt[0];

	$request = $smcFunc['db_query'](
		'substring',
		'
		SELECT d.id, d.created, d.type, d.downloads, d.name, d.description as body, d.author_id as author_id, m.real_name as real_name
		FROM {db_prefix}tp_dlmanager AS d
		LEFT JOIN {db_prefix}members as m ON d.author_id = m.id_member
		WHERE ' . $query . '
		AND type = {string:type}
		ORDER BY d.created DESC LIMIT {int:start}, {int:limit}',
		[
			'type' => 'dlitem', 
			'what' => $what2, 
			'limit' => $max_results,
			'start' => $start
		]
	);
	// create pagelinks
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			TPUtil::shortenString($row['body'], 400);

			$row['body'] = strip_tags($row['body']);
			$row['name'] = preg_replace('/' . preg_quote($what, '/') . '/iu', '<mark class="highlight">$0</mark>', $row['name']);
			$row['body'] = preg_replace('/' . preg_quote($what, '/') . '/iu', '<mark class="highlight">$0</mark>', $row['body']);

			$context['TPortal']['dlsearchresults'][] = [
				'id' => $row['id'],
				'type' => $row['type'],
				'date' => $row['created'],
				'downloads' => $row['downloads'],
				'name' => $row['name'],
				'body' => $row['body'],
				'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
			];
		}
		$smcFunc['db_free_result']($request);
	}
	TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=search', $txt['tp-dlsearch']);

	$params = base64_encode(json_encode(['search' => $what, 'title' => $usetitle, 'body' => $usebody]));
	
	// Now that we know how many results to expect we can start calculating the page numbers.
	$context['page_index'] = constructPageIndex($scripturl . '?sa=download;dl=search;params=' . $params, $start, $total, $max_results, false);
//	$context['page_index'] = constructPageIndex($scripturl . '?action=tportal;sa=searcharticle2;params=' . $params, $start, $num_results, $max_results, false);

}
// searched the files?
function TPdlsearch()
{
	global $txt, $scripturl;

	TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=search', $txt['tp-dlsearch']);
}

// show some stats
function TPdlstats()
{
	global $scripturl, $smcFunc, $context;

	$context['TPortal']['dl_scats'] = [];
	$context['TPortal']['dl_sitems'] = [];
	$context['TPortal']['dl_scount'] = [];
	$context['TPortal']['topcats'] = [];
	// count items in each category
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT category FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}',
		['type' => 'dlitem']
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			if ($row['category'] > 0) {
				if (isset($context['TPortal']['dl_scount'][$row['category']])) {
					$context['TPortal']['dl_scount'][$row['category']]++;
				}
				else {
					$context['TPortal']['dl_scount'][$row['category']] = 1;
				}
			}
		}
		$smcFunc['db_free_result']($request);
	}

	// first: fetch all allowed categories
	$context['TPortal']['uploadcats'] = [];
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT id, parent, name, access
		FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}',
		['type' => 'dlcat']
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			$show = get_perm($row['access'], 'tp_dlmanager');
			if ($show) {
				$context['TPortal']['uploadcats'][$row['id']] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'parent' => $row['parent'],
				];
			}
		}
		$smcFunc['db_free_result']($request);
	}
	// no categories to select...
	else {
		return;
	}

	// fetch all categories with subcats
	$req = $smcFunc['db_query'](
		'',
		'
		SELECT * FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}',
		['type' => 'dlcat']
	);
	if ($smcFunc['db_num_rows']($req) > 0) {
		while ($brow = $smcFunc['db_fetch_assoc']($req)) {
			if (get_perm($brow['access'], 'tp_dlmanager')) {
				if (isset($context['TPortal']['dl_scount'][$brow['id']])) {
					$items = $context['TPortal']['dl_scount'][$brow['id']];
				}
				else {
					$items = 0;
				}

				$context['TPortal']['topcats'][] = [
					'items' => $items,
					'link' => '<a href="' . $scripturl . '?action=tportal;sa=download;dl=cat' . $brow['id'] . '">' . $brow['name'] . '</a>',
				];
				// add the category as viewable
				$context['TPortal']['viewcats'][] = $brow['id'];
			}
		}
		$smcFunc['db_free_result']($req);
		// sort it
		if (sizeof($context['TPortal']['topcats']) > 1) {
			usort($context['TPortal']['topcats'], 'dlsort');
		}
	}

	// fetch all items
	$context['TPortal']['topitems'] = [];

	$request = $smcFunc['db_query'](
		'',
		'
		SELECT category, filesize, views, downloads, id, name
		FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}',
		['type' => 'dlitem']
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			if (isset($context['TPortal']['viewcats']) && isset($row['category']) && is_array($context['TPortal']['viewcats']) && in_array($row['category'], $context['TPortal']['viewcats'])) {
				$context['TPortal']['topitems'][] = [
					'size' => $row['filesize'],
					'views' => $row['views'],
					'downloads' => $row['downloads'],
					'link' => '<a href="' . $scripturl . '?action=tportal;sa=download;dl=item' . $row['id'] . '">' . $row['name'] . '</a>',
				];
			}
		}
		$smcFunc['db_free_result']($request);
		// sort it by filesize, views and downloads
		$context['TPortal']['topsize'] = [];
		$context['TPortal']['topviews'] = [];
		$context['TPortal']['topsize'] = $context['TPortal']['topitems'];

		if (is_array($context['TPortal']['topsize'])) {
			usort($context['TPortal']['topsize'], 'dlsortsize');
		}

		$context['TPortal']['topviews'] = $context['TPortal']['topitems'];

		if (is_array($context['TPortal']['topviews'])) {
			usort($context['TPortal']['topviews'], 'dlsortviews');
		}

		if (is_array($context['TPortal']['topitems'])) {
			usort($context['TPortal']['topitems'], 'dlsortdownloads');
		}
	}
}

// download a file
function TPdownloadme()
{
	global $smcFunc, $modSettings, $context, $boarddir, $txt;

	$item = $context['TPortal']['dlitem'];
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT * FROM {db_prefix}tp_dlmanager
		WHERE id = {int:item} LIMIT 1',
		['item' => $item]
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		$row = $smcFunc['db_fetch_assoc']($request);
		$myfilename = $row['name'];
		$newname = TPDlgetname($row['file']);
		$real_filename = $row['file'];
		if ($row['subitem'] > 0) {
			$parent = $row['subitem'];
			$req3 = $smcFunc['db_query'](
				'',
				'
				SELECT category FROM {db_prefix}tp_dlmanager
				WHERE id = {int:parent} LIMIT 1',
				['parent' => $parent]
			);
			$what = $smcFunc['db_fetch_assoc']($req3);
			$cat = $what['category'];
			$request2 = $smcFunc['db_query'](
				'',
				'
				SELECT * FROM {db_prefix}tp_dlmanager
				WHERE id = {int:cat}',
				['cat' => $cat]
			);
			if ($smcFunc['db_num_rows']($request2) > 0) {
				$row2 = $smcFunc['db_fetch_assoc']($request2);
				$show = get_perm($row2['access'], 'tp_dlmanager');
				$smcFunc['db_free_result']($request2);
			}
		}
		else {
			$cat = $row['category'];
			$request2 = $smcFunc['db_query'](
				'',
				'
				SELECT * FROM {db_prefix}tp_dlmanager
				WHERE id = {int:cat}',
				['cat' => $cat]
			);
			if ($smcFunc['db_num_rows']($request2) > 0) {
				$row2 = $smcFunc['db_fetch_assoc']($request2);
				$show = get_perm($row2['access'], 'tp_dlmanager');
				$smcFunc['db_free_result']($request2);
			}
		}

		$external = false;
		if (TPUtil::hasLinks($real_filename)) {
			$filename = $real_filename;
			$external = true;
		}
		else {
			$filename = $context['TPortal']['download_upload_path'] . $real_filename;
		}
		$smcFunc['db_free_result']($request);
	}
	else {
		$show = false;
	}

	// can we actually download?
	if ($show == 1 || allowedTo('tp_dlmanager')) {
		$now = time();
		$year = (int) date('Y', $now);
		$week = (int) date('W', $now);

		// update weekly views
		$req = $smcFunc['db_query'](
			'',
			'
			SELECT id FROM {db_prefix}tp_dldata
			WHERE year = {int:year}
			AND week = {int:week}
			AND item = {int:item}',
			['year' => $year, 'week' => $week, 'item' => $item]
		);

		if ($smcFunc['db_num_rows']($req) > 0) {
			$row = $smcFunc['db_fetch_assoc']($req);
			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dldata
				SET downloads = downloads + 1
				WHERE id = {int:dlitem}',
				['dlitem' => $row['id']]
			);
		}
		else {
			$smcFunc['db_insert'](
				'INSERT',
				'{db_prefix}tp_dldata',
				['week' => 'int', 'year' => 'int', 'downloads' => 'int', 'item' => 'int'],
				[$week, $year, 1, $item],
				['id']
			);
		}

		$smcFunc['db_query'](
			'',
			'
			UPDATE {db_prefix}tp_dlmanager
			SET downloads = downloads + 1
			WHERE id = {int:item}',
			['item' => $item]
		);

		ob_end_clean();

		if ($external == true) {
			header('Location: ' . $filename);
		}
		else {
			//does it still exist?
			if (file_exists($filename)) {
				if (!empty($modSettings['enableCompressedOutput']) && @version_compare(PHP_VERSION, '4.2.0') >= 0 && @filesize($filename) <= 4194304) {
					@ob_start('ob_gzhandler');
				}
				else {
					ob_start();
					header('Content-Encoding: none');
				}

//				if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime(array_shift(explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']))) >= filemtime($filename)) {
//					ob_end_clean();
//					header('HTTP/1.1 304 Not Modified');
//					exit;
//				}

				// If it hasn't been modified since the last time this attachement was retrieved, there's no need to display it again.
				if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
					list($modified_since) = explode(';', $_SERVER['HTTP_IF_MODIFIED_SINCE']);
					if (strtotime($modified_since) >= filemtime($filename)) {
						ob_end_clean();
						// Answer the question - no, it hasn't been modified ;).
						header('HTTP/1.1 304 Not Modified');
						exit;
					}
				}

				// Send the attachment headers.
				header('Pragma: no-cache');
				header('Cache-Control: max-age=' . 10 . ', private');
				header('Cache-Control: no-store, no-cache, must-revalidate');
				header('Cache-Control: post-check=0, pre-check=0', false);
				if (!$context['browser']['is_gecko']) {
					header('Content-Transfer-Encoding: binary');
				}
				header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 525600 * 60) . ' GMT');
				header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filename)) . ' GMT');
				header('Accept-Ranges: bytes');
				header('Set-Cookie:');
				header('Connection: close');
				header('Content-Disposition: attachment; filename="' . $newname . '"');
				header('Content-Type: application/octet-stream');

				if (filesize($filename) != 0) {
					$size = @getimagesize($filename);
					if (!empty($size) && $size[2] > 0 && $size[2] < 4) {
						header('Content-Type: image/' . ($size[2] != 1 ? ($size[2] != 2 ? 'png' : 'jpeg') : 'gif'));
					}
				}

				if (empty($modSettings['enableCompressedOutput']) || filesize($filename) > 4194304) {
					header('Content-Length: ' . filesize($filename));
				}

				@set_time_limit(0);

				if (in_array(substr($real_filename, -4), ['.txt', '.css', '.htm', '.php', '.xml'])) {
					if (strpos($_SERVER['HTTP_USER_AGENT'], 'Windows') !== false) {
						$callback = function ($buffer) {
							return preg_replace('~[\r]?\n~', "\r\n", $buffer);
						};
					}
					elseif (strpos($_SERVER['HTTP_USER_AGENT'], 'Mac') !== false) {
						$callback = function ($buffer) {
							return preg_replace('~[\r]?\n~', "\r", $buffer);
						};
					}
					else {
						$callback = function ($buffer) {
							return preg_replace('~\r~', "\r\n", $buffer);
						};
					}
				}

				// Since we don't do output compression for files this large...
				if (filesize($filename) > 4194304) {
					// Forcibly end any output buffering going on.
					if (function_exists('ob_get_level')) {
						while (@ob_get_level() > 0) {
							@ob_end_clean();
						}
					}
					else {
						@ob_end_clean();
						@ob_end_clean();
						@ob_end_clean();
					}

					$fp = fopen($filename, 'rb');
					while (!feof($fp)) {
						if (isset($callback)) {
							echo $callback(fread($fp, 8192));
						}
						else {
							echo fread($fp, 8192);
						}
						flush();
					}
					fclose($fp);
				}
				// On some of the less-bright hosts, readfile() is disabled.  It's just a faster, more byte safe, version of what's in the if.
				elseif (isset($callback) || @readfile($filename) == null) {
					echo isset($callback) ? $callback(file_get_contents($filename)) : file_get_contents($filename);
				}
			}
			else {
				$error = '<div class="errorbox">' . $txt['tp-dlfileerror'] . '.</div><br>' . $txt['tp-dlerrorfile'] . ': <b>' . $real_filename . '</b>';
				fatal_error($error, false);
			}
		}
		obExit(false);
	}
	else {
		redirectexit('action=tportal;sa=download;dl');
	}
}

// DLmanager admin page
function TPortalDLAdmin()
{
	global $txt, $scripturl, $boarddir, $boardurl, $smcFunc, $context, $settings, $sourcedir;

	// check permissions
	if (isset($_POST['dl_useredit'])) {
		checkSession('post');
	}
	else {
		isAllowedTo('tp_dlmanager');
	}
	// set the linktree
	TPadd_linktree($scripturl . '?action=tpadmin', $txt['tp-admin']);
	TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=admin', 'TPdownloads');

	// add visual options to this section
	$dl_visual = explode(',', $context['TPortal']['dl_visual_options']);
	$dv = ['left', 'right', 'center', 'top', 'bottom', 'lower'];
	foreach ($dv as $v => $val) {
		if (in_array($val, $dl_visual)) {
			$context['TPortal'][$val . 'panel'] = '1';
			$context['TPortal']['dl_' . $val] = '1';
		}
		else {
			$context['TPortal'][$val . 'panel'] = '0';
		}
	}

	if (in_array('showtop', $dl_visual)) {
		$context['TPortal']['showtop'] = true;
		$context['TPortal']['dl_top'] = true;
	}
	else {
		$context['TPortal']['showtop'] = false;
	}

	if ($context['TPortal']['hidebars_admin_only'] == '1') {
		tp_hidebars();
	}
	/*if($context['TPortal']['hidebars_admin_only'] == '0') {
		tp_hidebars('left');
		tp_hidebars('right');
	}*/
	// fetch membergroups so we can quickly set permissions
	// dlmanager, dlupload, dlcreatetopic
	$context['TPortal']['perm_all_groups'] = get_grps();
	$context['TPortal']['perm_groups'] = tp_fetchpermissions(['tp_dlmanager', 'tp_dlupload', 'tp_dlcreatetopic']);
	$context['TPortal']['boards'] = tp_fetchboards();

	$context['TPortal']['all_dlitems'] = [];
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT id, name	FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}
		ORDER BY name ASC',
		['type' => 'dlitem']
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			$context['TPortal']['all_dlitems'][] = [
				'id' => $row['id'],
				'name' => $row['name'],
			];
		}
		$smcFunc['db_free_result']($request);
	}

	// add in BBC editor before we call in template so the headers are there
	if ($context['TPortal']['dl_wysiwyg'] == 'bbc') {
		if ($context['TPortal']['dlsub'] == 'adminaddcat') {
			$context['TPortal']['editor_id'] = 'newdladmin_text';
			TP_prebbcbox($context['TPortal']['editor_id']);
		}
		else {
			$context['TPortal']['editor_id'] = 'tp_dl_introtext';
			TP_prebbcbox($context['TPortal']['editor_id'], $context['TPortal']['dl_introtext']);
		}
	}
	elseif ($context['TPortal']['dl_wysiwyg'] == 'html') {
		TPwysiwyg_setup();
	}

	// any items from the ftp screen?
	if (!empty($_POST['ftpdlsend'])) {
		// new category?
		if (!empty($_POST['assign-ftp-newcat'])) {
			$newcat = true;
			$newcatname = $_POST['assign-ftp-newcat'];
			if (isset($_POST['assign-ftp-cat']) && $_POST['assign-ftp-cat'] > 0) {
				$newcatparent = $_POST['assign-ftp-cat'];
			}
			else {
				$newcatparent = 0;
			}
			if ($newcatname == '') {
				$newcatname = '-no name-';
			}
			$icon = $boardurl . '/tp-downloads/icons/' . $_POST['tp_newdladmin_icon'];
		}
		else {
			$newcat = false;
			$newcatname = '';
			$newcatnow = $_POST['assign-ftp-cat'];
			$newcatparent = 0;
		}

		//check if we have a category
		if (($newcat == false) && ($newcatnow == 0)) {
			redirectexit('action=tportal;sa=download;dl=adminftp;ftpcat=nocat');
		}
		// if new category create it first.
		if ($newcat) {
			$request = $smcFunc['db_insert'](
				'INSERT',
				'{db_prefix}tp_dlmanager',
				[
					'name' => 'string',
					'description' => 'string',
					'icon' => 'string',
					'category' => 'int',
					'type' => 'string',
					'downloads' => 'int',
					'views' => 'int',
					'file' => 'string',
					'created' => 'int',
					'last_access' => 'int',
					'filesize' => 'int',
					'parent' => 'int',
					'access' => 'string',
					'link' => 'string',
					'author_id' => 'int',
					'screenshot' => 'string',
					'rating' => 'string',
					'voters' => 'string',
					'subitem' => 'int'],
				[$newcatname, '', $icon, 0, 'dlcat', 0, 0, '', 0, 0, 0, $newcatparent, '', '', $context['user']['id'], '', '', '', 0],
				['id']
			);
			$newcatnow = $smcFunc['db_insert_id']($request);
		}
		// now go through each file and put it into the table.
		foreach ($_POST as $what => $value) {
			if (substr($what, 0, 19) == 'assign-ftp-checkbox') {
				$name = $value;
				$icon = $boardurl . '/tp-downloads/icons/' . $_POST['tp_newdladmin_icon'];
				$now = time();
				$fsize = filesize($context['TPortal']['download_upload_path'] . $value);
				$smcFunc['db_insert'](
					'INSERT',
					'{db_prefix}tp_dlmanager',
					[
						'name' => 'string',
						'description' => 'string',
						'icon' => 'string',
						'category' => 'int',
						'type' => 'string',
						'downloads' => 'int',
						'views' => 'int',
						'file' => 'string',
						'created' => 'int',
						'last_access' => 'int',
						'filesize' => 'int',
						'parent' => 'int',
						'access' => 'string',
						'link' => 'string',
						'author_id' => 'int',
						'screenshot' => 'string',
						'rating' => 'string',
						'voters' => 'string',
						'subitem' => 'int'],
					[$name, '', $icon, $newcatnow, 'dlitem', 1, 1, $value, $now, $now, $fsize, 0, '', '', $context['user']['id'], '', '', '', 0],
					['id']
				);
			}
		}
		// done, set a value to make member aware of assigned category
		redirectexit('action=tportal;sa=download;dl=adminftp;ftpcat=' . $newcatnow);
	}

	// check for new category
	if (!empty($_POST['newdlsend'])) {
		// get the items
		$name = strip_tags($_POST['newdladmin_name']);
		// no html here
		if (empty($name)) {
			$name = $txt['tp-dlnotitle'];
		}

		$link = $_POST['newdladmin_link'];
		$text = $_POST['newdladmin_text'];
		$parent = $_POST['newdladmin_parent'];
		$icon = $boardurl . '/tp-downloads/icons/' . $_POST['newdladmin_icon'];
		// special case, the access
		$dlgrp = [];
		foreach ($_POST as $what => $value) {
			if (substr($what, 0, 16) == 'newdladmin_group') {
				$vv = substr($what, 16);
				if ($vv != '-2') {
					$dlgrp[] = $vv;
				}
			}
		}
		$access = implode(',', $dlgrp);
		// insert the category
		$request = $smcFunc['db_insert'](
			'INSERT',
			'{db_prefix}tp_dlmanager',
			[
				'name' => 'string',
				'description' => 'string',
				'icon' => 'string',
				'category' => 'int',
				'type' => 'string',
				'downloads' => 'int',
				'views' => 'int',
				'file' => 'string',
				'created' => 'int',
				'last_access' => 'int',
				'filesize' => 'int',
				'parent' => 'int',
				'access' => 'string',
				'link' => 'string',
				'author_id' => 'int',
				'screenshot' => 'string',
				'rating' => 'string',
				'voters' => 'string',
				'subitem' => 'int'],
			[$name, $text, $icon, 0, 'dlcat', 0, 0, '', 0, 0, 0, $parent, $access, $link, $context['user']['id'], '', '', '', 0],
			['id']
		);
		$newcat = $smcFunc['db_insert_id']($request);
		redirectexit('action=tportal;sa=download;dl=admineditcat' . $newcat);
	}

	// check for access value
	if (!empty($_POST['dlsend'])) {
		$admgrp = [];
		$groupset = false;
		$dlgrp = [];
		$dlset = false;
		$visual = [];
		$visualset = false;
		$creategrp = [];
		$dlmanager_grp = [];
		$dlupload_grp = [];
		$dlcreatetopic_grp = [];

		// our settings array to send to updateTPSettings();
		$changeArray = [];

		foreach ($_POST as $what => $value) {
			if (substr($what, 0, 13) == 'dladmin_group') {
				$val = substr($what, 13);
				if ($val != '-2') {
					$admgrp[] = $val;
				}
				$groupset = true;
				$id = $value;
			}
			elseif (substr($what, 0, 8) == 'tp_group') {
				if ($value != '-2') {
					$dlgrp[] = $value;
				}
				$dlset = true;
			}
			elseif (substr($what, 0, 20) == 'tp_dl_visual_options') {
				if ($value != 'not') {
					$visual[] = $value;
				}
				$visualset = true;
			}
			elseif (substr($what, 0, 11) == 'tp_dlboards') {
				$creategrp[] = $value;
			}
		}
		if ($groupset) {
			$dlaccess = implode(',', $admgrp);
			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dlmanager
				SET access = {string:access}
				WHERE id = {int:item}',
				['access' => $dlaccess, 'item' => $id]
			);
		}

		if (!empty($_POST['dlsettings'])) {
			$changeArray['dl_createtopic_boards'] = implode(',', $creategrp);
		}

		if ($dlset) {
			$changeArray['dl_approve_groups'] = implode(',', $dlgrp);
		}

		if ($visualset) {
			$changeArray['dl_visual_options'] = implode(',', $visual);
		}

		$go = 0;

		if (!empty($_FILES['qup_dladmin_text']['tmp_name']) && (file_exists($_FILES['qup_dladmin_text']['tmp_name']) || is_uploaded_file($_FILES['qup_dladmin_text']['tmp_name']))) {
			$name = TPuploadpicture('qup_dladmin_text', $context['user']['id'] . 'uid');
			tp_createthumb($context['TPortal']['image_upload_path'] . $name, 50, 50, $context['TPortal']['image_upload_path'] . 'thumbs/thumb_' . $name);
		}
		if (!empty($_FILES['qup_blockbody']['tmp_name']) && (file_exists($_FILES['qup_dladmin_text']['tmp_name']) || is_uploaded_file($_FILES['qup_dladmin_text']['tmp_name']))) {
			$name = TPuploadpicture('qup_dladmin_text', $context['user']['id'] . 'uid');
			tp_createthumb($context['TPortal']['image_upload_path'] . $name, 50, 50, $context['TPortal']['image_upload_path'] . 'thumbs/thumb_' . $name);
		}

		// a screenshot from edit item screen?
		if (!empty($_FILES['tp_dluploadpic_edit']['tmp_name']) && (file_exists($_FILES['tp_dluploadpic_edit']['tmp_name']) || is_uploaded_file($_FILES['tp_dluploadpic_edit']['tmp_name']))) {
			$shot = true;
		}
		else {
			$shot = false;
		}

		if ($shot) {
			$sid = $_POST['tp_dluploadpic_editID'];
			$sfile = 'tp_dluploadpic_edit';
			$uid = $context['user']['id'] . 'uid';
			$dim = '1800';
			$suf = 'jpg,gif,png';
			$dest = $context['TPortal']['image_upload_path'] . 'dlmanager';
			$sname = TPuploadpicture($sfile, $uid, $dim, $suf, $dest);
			$screenshot = $sname;

			tp_createthumb($dest . '/' . $sname, $context['TPortal']['dl_screenshotsize'][0], $context['TPortal']['dl_screenshotsize'][1], $dest . '/thumb/' . $sname);
			tp_createthumb($dest . '/' . $sname, $context['TPortal']['dl_screenshotsize'][2], $context['TPortal']['dl_screenshotsize'][3], $dest . '/listing/' . $sname);

			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dlmanager
				SET screenshot = {string:ss}
				WHERE id = {int:item}',
				['ss' => $screenshot, 'item' => $sid]
			);
			$uploaded = true;
		}
		else {
			$screenshot = '';
			$uploaded = false;
		}

		if (isset($_POST['tp_dluploadpic_link']) && !$uploaded) {
			$sid = $_POST['tp_dluploadpic_editID'];
			$screenshot = $_POST['tp_dluploadpic_link'];
			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dlmanager
				SET screenshot = {string:ss}
				WHERE id = {int:item}',
				['ss' => $screenshot, 'item' => $sid]
			);
		}
		else {
			$screenshot = '';
		}

		// a new file uploaded?
		if (!empty($_FILES['tp_dluploadfile_edit']['tmp_name']) && is_uploaded_file($_FILES['tp_dluploadfile_edit']['tmp_name'])) {
			$shot = true;
		}
		else {
			$shot = false;
		}

		if ($shot) {
			$sid = $_POST['tp_dluploadfile_editID'];
			$shotname = $_FILES['tp_dluploadfile_edit']['name'];
			$sname = strtr($shotname, 'ŠŽšžŸÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØÙÚÛÜÝàáâãäåçèéêëìíîïñòóôõöøùúûüýÿ', 'SZszYAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy');
			$sname = strtr($sname, ['Þ' => 'TH', 'þ' => 'th', 'Ð' => 'DH', 'ð' => 'dh', 'ß' => 'ss', 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae', 'µ' => 'u']);
			$sname = preg_replace(['/\s/', '/[^\w_\.\-]/'], ['_', ''], $sname);
			$sname = time() . $sname;
			// check the size
			$dlfilesize = filesize($_FILES['tp_dluploadfile_edit']['tmp_name']);
			if ($dlfilesize > (1024 * $context['TPortal']['dl_max_upload_size'])) {
				unlink($_FILES['tp_dluploadfile_edit']['tmp_name']);
				$error = $txt['tp-dlmaxerror'] . ' ' . ($context['TPortal']['dl_max_upload_size']) . ' ' . $txt['tp-kb'] . '<br /><br />' . $txt['tp-dlerrorfile'] . ': ' . ceil($dlfilesize / 1024) . $txt['tp-kb'];
				fatal_error($error, false);
			}

			// check the extension
			$allowed = explode(',', $context['TPortal']['dl_allowed_types']);
			$match = false;
			foreach ($allowed as $extension => $value) {
				$ext = '.' . $value;
				$extlen = strlen($ext);
				if (substr($sname, strlen($sname) - $extlen, $extlen) == $ext) {
					$match = true;
				}
			}
			if (!$match) {
				unlink($_FILES['tp_dluploadfile_edit']['tmp_name']);
				$error = $txt['tp-dlexterror'] . ':<b> <br />' . $context['TPortal']['dl_allowed_types'] . '</b><br /><br />' . $txt['tp-dlerrorfile'] . ': <b>' . $sname . '</b>';
				fatal_error($error, false);
			}
			$success2 = move_uploaded_file($_FILES['tp_dluploadfile_edit']['tmp_name'], $context['TPortal']['download_upload_path'] . $sname);
			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dlmanager
				SET file = {string:file}
				WHERE id = {int:item}',
				['file' => $sname, 'item' => $sid]
			);
			$new_upload = true;
			// update filesize as well
			$value = filesize($context['TPortal']['download_upload_path'] . $sname);
			if (!is_numeric($value)) {
				$value = 0;
			}
			$smcFunc['db_query'](
				'',
				'
				UPDATE {db_prefix}tp_dlmanager
				SET filesize = {int:size}
				WHERE id = {int:item}',
				['size' => $value, 'item' => $sid]
			);
			$myid = $sid;
			$go = 2;
		}

		// get all values from forms
		foreach ($_POST as $what => $value) {
			if (substr($what, 0, 12) == 'dladmin_name') {
				$id = substr($what, 12);
				// no html here
				$value = strip_tags($value);
				if (empty($value)) {
					$value = '-no title-';
				}
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET name = {string:name}
					WHERE id = {int:item}',
					['name' => $value, 'item' => $id]
				);
			}
			elseif (substr($what, 0, 12) == 'dladmin_icon') {
				$id = substr($what, 12);
				if ($value != '') {
					$val = $boardurl . '/tp-downloads/icons/' . $value;
					$smcFunc['db_query'](
						'',
						'
						UPDATE {db_prefix}tp_dlmanager
						SET icon = {string:icon}
						WHERE id = {int:item}',
						['icon' => $val, 'item' => $id]
					);
				}
			}
			elseif (substr($what, 0, 12) == 'dladmin_text') {
				$id = substr($what, 12);
				if (is_numeric($id)) {
					// if we came from WYSIWYG then turn it back into BBC regardless.
					if (!empty($_REQUEST[$what . '_mode']) && isset($_REQUEST[$what])) {
						require_once $sourcedir . '/Subs-Editor.php';
						$_REQUEST[$what] = html_to_bbc($_REQUEST[$what]);
						// we need to unhtml it now as it gets done shortly.
						$_REQUEST[$what] = un_htmlspecialchars($_REQUEST[$what]);
						// we need this for everything else.
						$value = $_POST[$what] = $_REQUEST[$what];
					}
					if (isset($_POST['dladmin_text' . $id . '_pure']) && isset($_POST['dladmin_text' . $id . '_choice'])) {
						if ($_POST['dladmin_text' . $id . '_choice'] == 1) {
							$value = $_POST['dladmin_text' . $id];
						}
						else {
							$value = $_POST['dladmin_text' . $id . '_pure'];
						}
					}
					$smcFunc['db_query'](
						'',
						'
						UPDATE {db_prefix}tp_dlmanager
						SET description = {string:desc}
						WHERE id = {int:item}',
						['desc' => $value, 'item' => $id]
					);
				}
			}
			elseif (substr($what, 0, 14) == 'dladmin_delete') {
				$id = substr($what, 14);
				$request = $smcFunc['db_query'](
					'',
					'
						SELECT * FROM {db_prefix}tp_dlmanager
						WHERE id = {int:item}',
					['item' => $id]
				);
				if ($smcFunc['db_num_rows']($request) > 0) {
					$row = $smcFunc['db_fetch_assoc']($request);
					if ($row['type'] == 'dlitem') {
						$category = $row['category'];
						if ($category > 0) {
							$smcFunc['db_query'](
								'',
								'
									UPDATE {db_prefix}tp_dlmanager
									SET downloads = downloads - 1
									WHERE id = {int:cat} LIMIT 1',
								['cat' => $category]
							);
						}
						// delete both screenshot and file
						if (!empty($row['file']) && file_exists($context['TPortal']['download_upload_path'] . $row['file'])) {
							$succ = unlink($context['TPortal']['download_upload_path'] . $row['file']);
							if (!$succ) {
								$err = $txt['tp-dlfilenotdel'] . ' (' . $row['file'] . ')';
							}
						}
						if (!empty($row['screenshot']) && file_exists($boarddir . '/' . $row['screenshot'])) {
							$succ2 = unlink($boarddir . '/' . $row['screenshot']);
							if (!$succ2) {
								$err .= '<br />' . $txt['tp-dlssnotdel'] . ' (' . $row['screenshot'] . ')';
							}
						}
					}
					$smcFunc['db_free_result']($request);
				}
				$smcFunc['db_query'](
					'',
					'
					DELETE FROM {db_prefix}tp_dlmanager
					WHERE id = {int:item}',
					['item' => $id]
				);
				if (isset($err)) {
					fatal_error($err);
				}
				redirectexit('action=tportal;sa=download;dl=admincat' . $category);
			}
			elseif (substr($what, 0, 15) == 'dladmin_approve' && $value == 'ON') {
				$id = abs(substr($what, 15));
				$request = $smcFunc['db_query'](
					'',
					'
					SELECT category FROM {db_prefix}tp_dlmanager
					WHERE id = {int:item}',
					['item' => $id]
				);
				if ($smcFunc['db_num_rows']($request) > 0) {
					$row = $smcFunc['db_fetch_row']($request);
					$newcat = abs($row[0]);
					$smcFunc['db_query'](
						'',
						'
						UPDATE {db_prefix}tp_dlmanager
						SET category = {int:cat}
						WHERE id = {int:item}',
						['cat' => $newcat, 'item' => $id]
					);
					$smcFunc['db_query'](
						'',
						'
						DELETE FROM {db_prefix}tp_variables
						WHERE type = {string:type}
						AND value5 = {int:val5}',
						['type' => 'dl_not_approved', 'val5' => $id]
					);
					$smcFunc['db_free_result']($request);
				}
			}
			elseif (substr($what, 0, 16) == 'dl_admin_approve' && $value == 'ON') {
				$id = abs(substr($what, 16));
				$request = $smcFunc['db_query'](
					'',
					'
					SELECT category FROM {db_prefix}tp_dlmanager
					WHERE id = {int:item}',
					['item' => $id]
				);
				if ($smcFunc['db_num_rows']($request) > 0) {
					$row = $smcFunc['db_fetch_row']($request);
					$newcat = abs($row[0]);
					$smcFunc['db_query'](
						'',
						'
						UPDATE {db_prefix}tp_dlmanager
						SET category = {int:cat}
						WHERE id = {int:item}',
						['cat' => $newcat, 'item' => $id]
					);
					$smcFunc['db_query'](
						'',
						'
						DELETE FROM {db_prefix}tp_variables
						WHERE type = {string:type}
						AND value5 = {int:val5}',
						['type' => 'dl_not_approved', 'val5' => $id]
					);
					$smcFunc['db_free_result']($request);
				}
			}
			elseif (substr($what, 0, 16) == 'dladmin_category') {
				$id = substr($what, 16);
				// update, but not on negative values :)
				if ($value > 0) {
					$smcFunc['db_query'](
						'',
						'
						UPDATE {db_prefix}tp_dlmanager
						SET category = {int:cat}
						WHERE id = {int:item}',
						['cat' => $value, 'item' => $id]
					);
				}
			}
			elseif (substr($what, 0, 14) == 'dladmin_parent') {
				$id = substr($what, 14);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET parent = {int:parent}
					WHERE id = {int:item}',
					['parent' => $value, 'item' => $id]
				);
			}
			elseif (substr($what, 0, 15) == 'dladmin_subitem') {
				$id = substr($what, 15);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET subitem = {int:sub}
					WHERE id = {int:item}',
					['sub' => $value, 'item' => $id]
				);
			}
			elseif (substr($what, 0, 11) == 'tp_dlcatpos') {
				$id = substr($what, 11);
				if (!empty($_POST['admineditcatval'])) {
					$myid = $_POST['admineditcatval'];
					$go = 4;
				}

				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET downloads = {int:down}
					WHERE id = {int:item}',
					['down' => $value, 'item' => $id]
				);
			}
			elseif (substr($what, 0, 18) == 'dladmin_screenshot') {
				$id = substr($what, 18);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET screenshot = {string:ss}
					WHERE id = {int:item}',
					['ss' => $value, 'item' => $id]
				);
			}
			elseif (substr($what, 0, 12) == 'dladmin_link') {
				$id = substr($what, 12);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET link = {string:link}
					WHERE id = {int:item}',
					['link' => $value, 'item' => $id]
				);
			}
			elseif (substr($what, 0, 12) == 'dladmin_file' && !isset($new_upload)) {
				$id = substr($what, 12);
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET file = {string:file}
					WHERE id = {int:item}',
					['file' => $value, 'item' => $id]
				);
				$myid = $id;
				$go = 2;
			}
			elseif (substr($what, 0, 12) == 'dladmin_size' && !isset($new_upload)) {
				$id = substr($what, 12);
				// check the actual size
				$name = $_POST['dladmin_file' . $id];
				$value = filesize($context['TPortal']['download_upload_path'] . $name);
				if (!is_numeric($value)) {
					$value = 0;
				}
				$smcFunc['db_query'](
					'',
					'
					UPDATE {db_prefix}tp_dlmanager
					SET filesize = {int:size}
					WHERE id = {int:item}',
					['size' => $value, 'item' => $id]
				);
			}
			// from settings in DLmanager
			elseif ($what == 'tp_dl_allowed_types') {
				$changeArray['dl_allowed_types'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_usescreenshot') {
				$changeArray['dl_usescreenshot'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_createtopic') {
				$changeArray['dl_createtopic'] = $value;
				$go = 1;
			}
			elseif (substr($what, 0, 20) == 'tp_dl_screenshotsize') {
				// which one
				$who = substr($what, 20);
				// do we already have the results?
				static $all = null;
				if ($all == null) {
					$result = $smcFunc['db_query'](
						'',
						'
						SELECT value FROM {db_prefix}tp_settings
						WHERE name = {string:name} LIMIT 1',
						['name' => 'dl_screenshotsizes']
					);
					$row = $smcFunc['db_fetch_assoc']($result);
					$smcFunc['db_free_result']($result);
					$all = explode(',', $row['value']);
				}
				$all[$who] = $value;
				$changeArray['dl_screenshotsizes'] = implode(',', $all);
				$go = 1;
			}
			elseif ($what == 'tp_dl_showfeatured') {
				$changeArray['dl_showfeatured'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_wysiwyg') {
				$changeArray['dl_wysiwyg'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_showrecent') {
				$changeArray['dl_showlatest'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_showstats') {
				$changeArray['dl_showstats'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_showcategorytext') {
				$changeArray['dl_showcategorytext'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_limit_length') {
				$changeArray['dl_limit_length'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_featured') {
				$changeArray['dl_featured'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_introtext') {
				if (in_array($context['TPortal']['dl_wysiwyg'], ['bbc', 'html'])) {
					// if we came from WYSIWYG then turn it back into BBC regardless.
					if (!empty($_REQUEST['tp_dl_introtext']) && isset($_REQUEST['tp_dl_introtext'])) {
						require_once $sourcedir . '/Subs-Editor.php';
						// we need to unhtml it now as it gets done shortly.
						$_REQUEST['tp_dl_introtext'] = un_htmlspecialchars($_REQUEST['tp_dl_introtext']);
						// we need this for everything else.
						$value = $_POST['tp_dl_introtext'] = $_REQUEST['tp_dl_introtext'];
					}
				}
				$changeArray['dl_introtext'] = trim($value);
				$go = 1;
			}
			elseif ($what == 'tp_dluploadsize') {
				$changeArray['dl_max_upload_size'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_approveonly') {
				$changeArray['dl_approve'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dlallowupload') {
				$changeArray['dl_allow_upload'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dl_fileprefix') {
				$changeArray['dl_fileprefix'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_dltheme') {
				$changeArray['dlmanager_theme'] = $value;
				$go = 1;
			}
			elseif ($what == 'tp_show_download') {
				$changeArray['show_download'] = $value;
				$go = 1;
			}
		}

		// update all the changes settings finally
		updateTPSettings($changeArray);

		// if we came from useredit screen..
		if (isset($_POST['dl_useredit'])) {
			redirectexit('action=tportal;sa=download;dl=useredit' . $_POST['dl_useredit']);
		}

		if (!empty($newgo)) {
			$go = $newgo;
		}
		// guess not, admin screen then
		if ($go == 1) {
			redirectexit('action=tportal;sa=download;dl=adminsettings');
		}
		elseif ($go == 2) {
			redirectexit('action=tportal;sa=download;dl=adminitem' . $myid);
		}
		elseif ($go == 3) {
			redirectexit('action=tportal;sa=download;dl=admineditcat' . $myid);
		}
		elseif ($go == 4) {
			redirectexit('action=tportal;sa=download;dl=admincat' . $myid);
		}
	}
	// ****************

	TP_dlgeticons();
	// get all themes
	$context['TPthemes'] = [];
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT value AS name, id_theme as id_theme
		FROM {db_prefix}themes
		WHERE variable = {string:var}
		AND id_member = {int:id_mem}
		ORDER BY value ASC',
		['var' => 'name', 'id_mem' => 0]
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			$context['TPthemes'][] = [
				'id' => $row['id_theme'],
				'name' => $row['name']
			];
		}
		$smcFunc['db_free_result']($request);
	}

	// fetch all files from tp-downloads
	$context['TPortal']['tp-downloads'] = [];
	$count = 1;
	if ($handle = opendir($context['TPortal']['download_upload_path'])) {
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..' && $file != '.htaccess' && $file != 'icons') {
				$size = (floor(filesize($context['TPortal']['download_upload_path'] . $file) / 102.4) / 10);
				$context['TPortal']['tp-downloads'][$count] = [
					'id' => $count,
					'file' => $file,
					'size' => $size,
				];
				$count++;
			}
		}
		closedir($handle);
	}
	// get all membergroups for permissions
	$context['TPortal']['dlgroups'] = get_grps(true, true);

	// fetch all categories
	$sorted = [];
	$context['TPortal']['linkcats'] = [];
	$srequest = $smcFunc['db_query'](
		'',
		'
		SELECT id, name, description, icon, access, parent
		FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type} ORDER BY downloads ASC',
		['type' => 'dlcat']
	);
	if ($smcFunc['db_num_rows']($srequest) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($srequest)) {
			// for the linktree
			$context['TPortal']['linkcats'][$row['id']] = [
				'id' => $row['id'],
				'name' => $row['name'],
				'parent' => $row['parent'],
			];

			$sorted[$row['id']] = [
				'id' => $row['id'],
				'parent' => $row['parent'],
				'name' => $row['name'],
				'text' => $row['description'],
				'icon' => $row['icon'],
			];
		}
		$smcFunc['db_free_result']($srequest);
	}
	// sort them
	if (count($sorted) > 1) {
		$context['TPortal']['admuploadcats'] = chain('id', 'parent', 'name', $sorted);
	}
	else {
		$context['TPortal']['admuploadcats'] = $sorted;
	}

	$context['TPortal']['dl_admcats'] = [];
	$context['TPortal']['dl_admcats2'] = [];
	$context['TPortal']['dl_admitems'] = [];
	$context['TPortal']['dl_admcount'] = [];
	$context['TPortal']['dl_admsubmitted'] = [];
	$context['TPortal']['dl_allitems'] = [];
	// count items in each category
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT file, category
		FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}',
		['type' => 'dlitem']
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			if ($row['category'] < 0) {
				if (isset($context['TPortal']['dl_admsubmitted'][abs($row['category'])])) {
					$context['TPortal']['dl_admsubmitted'][abs($row['category'])]++;
				}
				else {
					$context['TPortal']['dl_admsubmitted'][abs($row['category'])] = 1;
				}
			}
			else {
				if (isset($context['TPortal']['dl_admcount'][$row['category']])) {
					$context['TPortal']['dl_admcount'][$row['category']]++;
				}
				else {
					$context['TPortal']['dl_admcount'][$row['category']] = 1;
				}
			}
			$context['TPortal']['dl_allitems'][] = $row['file'];
		}
		$smcFunc['db_free_result']($request);
	}

	// fetch all categories
	$admsub = substr($context['TPortal']['dlsub'], 5);
	if ($admsub == '') {
		$context['TPortal']['dl_title'] = $txt['tp-dladmin'];
		// fetch all categories with subcats
		$req = $smcFunc['db_query'](
			'',
			'
			SELECT * FROM {db_prefix}tp_dlmanager
			WHERE type = {string:type}
			ORDER BY downloads ASC',
			['type' => 'dlcat']
		);
		if ($smcFunc['db_num_rows']($req) > 0) {
			while ($brow = $smcFunc['db_fetch_assoc']($req)) {
				if (isset($context['TPortal']['dl_admcount'][$brow['id']])) {
					$items = $context['TPortal']['dl_admcount'][$brow['id']];
				}
				else {
					$items = 0;
				}

				if (isset($context['TPortal']['dl_admsubmitted'][$brow['id']])) {
					$sitems = $context['TPortal']['dl_admsubmitted'][$brow['id']];
				}
				else {
					$sitems = 0;
				}

				$context['TPortal']['admcats'][] = [
					'id' => $brow['id'],
					'name' => $brow['name'],
					'icon' => $brow['icon'],
					'access' => $brow['access'],
					'parent' => $brow['parent'],
					'description' => $brow['description'],
					'shortname' => $brow['link'],
					'items' => $items,
					'submitted' => $sitems,
					'total' => ($items + $sitems),
					'href' => $scripturl . '?action=tportal;sa=download;dl=admincat' . $brow['id'],
					'href2' => $scripturl . '?action=tportal;sa=download;dl=admineditcat' . $brow['id'],
					'href3' => $scripturl . '?action=tportal;sa=download;dl=admindelcat' . $brow['id'],
					'pos' => $brow['downloads'],
				];
			}
			$smcFunc['db_free_result']($req);
		}
	}
	elseif (substr($admsub, 0, 3) == 'cat') {
		// check if sorting is specified
		if (isset($_GET['dlsort']) && in_array($_GET['dlsort'], ['id', 'name', 'downloads', 'created', 'author_id'])) {
			$context['TPortal']['dlsort'] = $dlsort = $_GET['dlsort'];
		}
		else {
			$context['TPortal']['dlsort'] = $dlsort = 'id';
		}

		if (isset($_GET['asc'])) {
			$context['TPortal']['dlsort_way'] = $dlsort_way = 'asc';
		}
		else {
			$context['TPortal']['dlsort_way'] = $dlsort_way = 'desc';
		}

		$cat = substr($admsub, 3);
		// get the parent first
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT parent, name, link
			FROM {db_prefix}tp_dlmanager
			WHERE type = {string:type}
			AND id = {int:item}',
			['type' => 'dlcat', 'item' => $cat]
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			$row = $smcFunc['db_fetch_assoc']($request);
			$catparent = abs($row['parent']);
			$catname = $row['name'];
			$catshortname = $row['link'];
			$smcFunc['db_free_result']($request);
		}
		// fetch items within a category, soerted by name
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT dl.*, dl.author_id as author_id,m.real_name as real_name
			FROM {db_prefix}tp_dlmanager AS dl
			LEFT JOIN {db_prefix}members AS m
			ON dl.author_id = m.id_member
			WHERE abs(dl.category) = {int:cat}
			AND dl.type = {string:type}
			AND dl.subitem = {int:sub}
			ORDER BY dl.' . $dlsort . ' ' . $dlsort_way . '',
			['cat' => $cat, 'type' => 'dlitem', 'sub' => 0]
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			// set up the sorting links
			$context['TPortal']['sortlinks'] = '<span class="smalltext">' . $txt['tp-sortby'] . ': ';
			$what = ['id', 'name', 'downloads', 'created', 'author_id'];
			foreach ($what as $v) {
				if ($context['TPortal']['dlsort'] == $v) {
					$context['TPortal']['sortlinks'] .= '<a href="' . $scripturl . '?action=tportal;sa=download;dl=admincat' . $cat . ';dlsort=' . $v . ';';
					if ($context['TPortal']['dlsort_way'] == 'asc') {
						$context['TPortal']['sortlinks'] .= 'desc">' . $txt['tp-' . $v] . ' <img src="' . $settings['tp_images_url'] . '/TPsort_up.png" alt="" /></a> &nbsp;|&nbsp; ';
					}
					else {
						$context['TPortal']['sortlinks'] .= 'asc">' . $txt['tp-' . $v] . ' <img src="' . $settings['tp_images_url'] . '/TPsort_down.png" alt="" /></a> &nbsp;|&nbsp; ';
					}
				}
				else {
					$context['TPortal']['sortlinks'] .= '<a href="' . $scripturl . '?action=tportal;sa=download;dl=admincat' . $cat . ';dlsort=' . $v . ';desc">' . $txt['tp-' . $v] . '</a> &nbsp;|&nbsp; ';
				}
			}
			$context['TPortal']['sortlinks'] = substr($context['TPortal']['sortlinks'], 0, strlen($context['TPortal']['sortlinks']) - 15);
			$context['TPortal']['sortlinks'] .= '</span>';

			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$context['TPortal']['dl_admitems'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'icon' => $row['icon'],
					'category' => abs($row['category']),
					'file' => $row['file'],
					'filesize' => floor($row['filesize'] / 1024),
					'views' => $row['views'],
					'author_id' => $row['author_id'],
					'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
					'created' => timeformat($row['created']),
					'last_access' => timeformat($row['last_access']),
					'description' => $row['description'],
					'downloads' => $row['downloads'],
					'sshot' => $row['screenshot'],
					'link' => $row['link'],
					'href' => $scripturl . '?action=tportal;sa=download;dl=adminitem' . $row['id'],
					'approved' => $row['category'] < 0 ? '0' : '1',
					'approve' => $scripturl . '?action=tportal;sa=download;dl=adminapprove' . $row['id'],
				];
			}
			$smcFunc['db_free_result']($request);
		}
		// fetch all categories with subcats
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT * FROM {db_prefix}tp_dlmanager
			WHERE type = {string:type}
			ORDER BY downloads ASC, name ASC',
			['type' => 'dlcat']
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				if (isset($context['TPortal']['dl_admcount'][$row['id']])) {
					$items = $context['TPortal']['dl_admcount'][$row['id']];
				}
				else {
					$items = 0;
				}

				if (isset($context['TPortal']['dl_admsubmitted'][$row['id']])) {
					$sitems = $context['TPortal']['dl_admsubmitted'][$row['id']];
				}
				else {
					$sitems = 0;
				}

				$context['TPortal']['admcats'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'pos' => $row['downloads'],
					'icon' => $row['icon'],
					'shortname' => $row['link'],
					'access' => $row['access'],
					'parent' => $row['parent'],
					'description' => $row['description'],
					'items' => $items,
					'submitted' => $sitems,
					'total' => ($items + $sitems),
					'href' => $scripturl . '?action=tportal;sa=download;dl=admincat' . $row['id'],
					'href2' => $scripturl . '?action=tportal;sa=download;dl=admineditcat' . $row['id'],
					'href3' => $scripturl . '?action=tportal;sa=download;dl=admindelcat' . $row['id'],
				];
			}
			$smcFunc['db_free_result']($request);
		}
		// check to see if its child
		$parents = [];
		while ($catparent > 0) {
			$parents[$catparent] = [
				'id' => $catparent,
				'name' => $context['TPortal']['linkcats'][$catparent]['name'],
				'parent' => $context['TPortal']['linkcats'][$catparent]['parent']
			];
			$catparent = $context['TPortal']['linkcats'][$catparent]['parent'];
		}

		// make the linktree
		TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=admin', $txt['tp-dladmin']);

		if (isset($parents)) {
			$parts = array_reverse($parents, true);
			// add to the linktree
			foreach ($parts as $parent) {
				TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=admincat' . $parent['id'], $parent['name']);
			}
		}
		// add to the linktree
		TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=admincat' . $cat, $catname);
	}
	elseif ($context['TPortal']['dlsub'] == 'adminsubmission') {
		// check any submissions if admin
		$submitted = [];
		isAllowedTo('tp_dlmanager');
		$context['TPortal']['dl_admitems'] = [];
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT dl.id, dl.name, dl.file, dl.created, dl.filesize, dl.author_id as author_id, m.real_name as real_name
			FROM {db_prefix}tp_dlmanager AS dl
			LEFT JOIN {db_prefix}members AS m
				ON dl.author_id = m.id_member
			WHERE dl.type = {string:type}
				AND dl.category < 0',
			['type' => 'dlitem']
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			$rows = $smcFunc['db_num_rows']($request);
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$context['TPortal']['dl_admitems'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'file' => $row['file'],
					'filesize' => floor($row['filesize'] / 1024),
					'href' => $scripturl . '?action=tportal;sa=download;dl=adminitem' . $row['id'],
					'author' => (!empty($row['real_name']) ? '<a href="' . $scripturl . '?action=profile;u=' . $row['author_id'] . '">' . $row['real_name'] . '</a>' : $txt['tp-guest']),
					'date' => timeformat($row['created']),
				];
				$submitted[] = $row['id'];
			}
			$smcFunc['db_free_result']($request);
		}
		// check that submissions link to downloads
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT id,value5 FROM {db_prefix}tp_variables
			WHERE type = {string:type}',
			['type' => 'dl_not_approved']
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$what = $row['id'];
				if (!in_array($row['value5'], $submitted)) {
					$smcFunc['db_query'](
						'',
						'
						DELETE FROM {db_prefix}tp_variables
						WHERE id = {int:item}',
						['item' => $what]
					);
				}
			}
			$smcFunc['db_free_result']($request);
		}
	}
	elseif (substr($admsub, 0, 7) == 'editcat') {
		$context['TPortal']['dl_title'] = '<a href="' . $scripturl . '?action=tportal;sa=download;dl=admin">' . $txt['tp-dladmin'] . '</a>';
		$cat = substr($admsub, 7);
		// edit category
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT * FROM {db_prefix}tp_dlmanager
			WHERE id = {int:item}
			AND type = {string:type} LIMIT 1',
			['item' => $cat, 'type' => 'dlcat']
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			while ($row = $smcFunc['db_fetch_assoc']($request)) {
				$context['TPortal']['admcats'][] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'access' => $row['access'],
					'shortname' => $row['link'],
					'description' => $row['description'],
					'icon' => $row['icon'],
					'parent' => $row['parent'],
				];
			}
			$smcFunc['db_free_result']($request);
		}

		if ($context['TPortal']['dl_wysiwyg'] == 'bbc') {
			$context['TPortal']['editor_id'] = 'dladmin_text' . $context['TPortal']['admcats'][0]['id'];
			TP_prebbcbox($context['TPortal']['editor_id'], $context['TPortal']['admcats'][0]['description']);
		}
		elseif ($context['TPortal']['dl_wysiwyg'] == 'html') {
			TPwysiwyg_setup();
		}
	}
	elseif (substr($admsub, 0, 6) == 'delcat') {
		$context['TPortal']['dl_title'] = '<a href="' . $scripturl . '?action=tportal;sa=download;dl=admin">' . $txt['tp-dladmin'] . '</a>';
		$cat = substr($admsub, 6);
		// delete category and all item it's in
		$request = $smcFunc['db_query'](
			'',
			'
			DELETE FROM {db_prefix}tp_dlmanager
			WHERE type = {string:type}
			AND category = {int:cat}',
			['type' => 'dlitem', 'cat' => $cat]
		);
		$request = $smcFunc['db_query'](
			'',
			'
			DELETE FROM {db_prefix}tp_dlmanager
			WHERE id = {int:cat} LIMIT 1',
			['cat' => $cat]
		);
		redirectexit('action=tportal;sa=download;dl=admin');
	}
	elseif (substr($admsub, 0, 8) == 'settings') {
		$context['TPortal']['dl_title'] = $txt['tp-dlsettings'];
	}
	elseif (substr($admsub, 0, 4) == 'item') {
		$item = substr($admsub, 4);
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT * FROM {db_prefix}tp_dlmanager
			WHERE id = {int:item}
			AND type = {string:type} LIMIT 1',
			['item' => $item, 'type' => 'dlitem']
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			$row = $smcFunc['db_fetch_assoc']($request);

			// is it actually a subitem?
			if ($row['subitem'] > 0) {
				redirectexit('action=tportal;sa=download;dl=adminitem' . $row['subitem']);
			}

			// add in BBC editor before we call in template so the headers are there
			if ($context['TPortal']['dl_wysiwyg'] == 'bbc') {
				$context['TPortal']['editor_id'] = 'dladmin_text' . $item;
				TP_prebbcbox($context['TPortal']['editor_id'], $row['description']);
			}
			elseif ($context['TPortal']['dl_wysiwyg'] == 'html') {
				TPwysiwyg_setup();
			}
			// get all items for a list
			$context['TPortal']['admitems'] = [];
			$itemlist = $smcFunc['db_query'](
				'',
				'
				SELECT id, name FROM {db_prefix}tp_dlmanager
				WHERE id != {int:item}
				AND type = {string:type}
				AND subitem = 0
				ORDER BY name ASC',
				['item' => $item, 'type' => 'dlitem']
			);
			if ($smcFunc['db_num_rows']($itemlist) > 0) {
				while ($ilist = $smcFunc['db_fetch_assoc']($itemlist)) {
					$context['TPortal']['admitems'][] = [
						'id' => $ilist['id'],
						'name' => $ilist['name'],
					];
				}
			}

			// any additional files then..?
			$subitem = $row['id'];
			$fdata = [];
			$fetch = $smcFunc['db_query'](
				'',
				'
				SELECT id, name, file, downloads, filesize, created
				FROM {db_prefix}tp_dlmanager
				WHERE type = {string:type}
				AND subitem = {int:sub}',
				['type' => 'dlitem', 'sub' => $subitem]
			);

			if ($smcFunc['db_num_rows']($fetch) > 0) {
				while ($frow = $smcFunc['db_fetch_assoc']($fetch)) {
					if ($context['TPortal']['dl_fileprefix'] == 'K') {
						$ffs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
						$ffs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
					}
					elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
						$ffs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
					}

					$fdata[] = [
						'id' => $frow['id'],
						'name' => $frow['name'],
						'file' => $frow['file'],
						'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $frow['id'],
						'downloads' => $frow['downloads'],
						'created' => $frow['created'],
						'filesize' => $ffs,
					];
				}
				$smcFunc['db_free_result']($fetch);
			}
			if (!empty($row['screenshot'])) {
				if (substr($row['screenshot'], 0, 10) == 'tp-images/') {
					$sshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . $row['screenshot'];
				}
				else {
					$sshot = str_replace($boarddir, $boardurl, $context['TPortal']['image_upload_path']) . 'dlmanager/listing/' . $row['screenshot'];
				}
			}

			if (TPUtil::hasLinks($row['file'])) {
				$headers = get_headers($row['file'], 1);
				$headers = array_change_key_case($headers);
				$file_size = 0;
				if (isset($headers['content-length']) && isset($headers['content-length'][1])) {
					$file_size = floor((int)$headers['content-length'][1] / 1024);
				}
			}
			elseif ((substr($row['file'], 0, 14) != '- empty item -') && (file_exists($context['TPortal']['download_upload_path'] . $row['file']))) {
				$file_size = floor(filesize($context['TPortal']['download_upload_path'] . $row['file']) / 1024);
			}
			else {
				$file_size = 0;
			}

			$context['TPortal']['dl_admitems'][] = [
				'id' => $row['id'],
				'name' => $row['name'],
				'icon' => $row['icon'],
				'category' => $row['category'],
				'file' => $row['file'],
				'views' => $row['views'],
				'author_id' => $row['author_id'],
				'description' => $row['description'],
				'created' => timeformat($row['created']),
				'last_access' => timeformat($row['last_access']),
				'filesize' => $file_size,
				'downloads' => $row['downloads'],
				'sshot' => !empty($sshot) ? $sshot : '',
				'screenshot' => $row['screenshot'],
				'link' => $row['link'],
				'href' => $scripturl . '?action=tportal;sa=download;dl=adminitem' . $row['id'],
				'approved' => $row['category'] < 0 ? '0' : '1',
				'approve' => $scripturl . '?action=tportal;sa=download;dl=adminitem' . $row['id'],
				'subitem' => $fdata,
			];
			$author_id = $row['author_id'];
			$catparent = $row['category'];
			$itemname = $row['name'];

			$smcFunc['db_free_result']($request);
			$request = $smcFunc['db_query'](
				'',
				'
				SELECT mem.real_name as real_name
				FROM {db_prefix}members as mem
				WHERE mem.id_member = {int:id_mem}',
				['id_mem' => $author_id]
			);
			if ($smcFunc['db_num_rows']($request) > 0) {
				$row = $smcFunc['db_fetch_assoc']($request);
				$context['TPortal']['admcurrent']['member'] = $row['real_name'];
				$smcFunc['db_free_result']($request);
			}
			else {
				$context['TPortal']['admcurrent']['member'] = '-' . $txt['guest_title'] . '-';
			}
			// check to see if its child
			$parents = [];
			while ($catparent > 0) {
				$parents[$catparent] = [
					'id' => $catparent,
					'name' => $context['TPortal']['linkcats'][$catparent]['name'],
					'parent' => $context['TPortal']['linkcats'][$catparent]['parent']
				];
				$catparent = $context['TPortal']['linkcats'][$catparent]['parent'];
			}
			// make the linktree
			TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=admin', $txt['tp-dldownloads']);

			if (isset($parents)) {
				$parts = array_reverse($parents, true);
				// add to the linktree
				foreach ($parts as $parent) {
					TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=admincat' . $parent['id'], $parent['name']);
				}
			}
			// add to the linktree
			TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=adminitem' . $item, $itemname);
		}
		else {
			redirectexit('action=tportal;sa=download;dl=admin');
		}
	}

	loadTemplate('TPdladmin');

	if (loadLanguage('TPmodules') == false) {
		loadLanguage('TPmodules', 'english');
	}

	if (loadLanguage('TPortalAdmin') == false) {
		loadLanguage('TPortalAdmin', 'english');
	}

	// setup admin tabs according to subaction
	$context['admin_area'] = 'tp_dlmanager';
	$context['admin_tabs'] = [
		'title' => $txt['tp-dlheader1'],
		'help' => $txt['tp-dlheader2'],
		'description' => $txt['tp-dlheader3'],
		'tabs' => [],
	];
	if (allowedTo('tp_dlmanager')) {
		$context['TPortal']['subtabs'] = [
			'settings' => [
				'text' => 'tp-dltabs1',
				'url' => $scripturl . '?action=tportal;sa=download;dl=adminsettings',
				'active' => $context['TPortal']['dlsub'] == 'adminsettings',
			],
			'admin' => [
				'text' => 'tp-dltabs4',
				'url' => $scripturl . '?action=tportal;sa=download;dl=admin',
				'active' => substr($context['TPortal']['dlsub'], 0, 5) == 'admin' && $context['TPortal']['dlsub'] != 'adminsettings' && $context['TPortal']['dlsub'] != 'adminaddcat' && $context['TPortal']['dlsub'] != 'adminftp' && $context['TPortal']['dlsub'] != 'adminsubmission',
			],
			'addcategory' => [
				'text' => 'tp-dltabs2',
				'url' => $scripturl . '?action=tportal;sa=download;dl=adminaddcat',
				'active' => $context['TPortal']['dlsub'] == 'adminaddcat',
			],
			'submissions' => [
				'text' => 'tp-dlsubmissions',
				'url' => $scripturl . '?action=tportal;sa=download;dl=adminsubmission',
				'active' => $context['TPortal']['dlsub'] == 'adminsubmission',
			],
			'upload' => [
				'text' => 'tp-dltabs3',
				'url' => $scripturl . '?action=tportal;sa=download;dl=upload',
				'active' => $context['TPortal']['dlsub'] == 'upload',
			],
			'ftp' => [
				'text' => 'tp-dlftp',
				'url' => $scripturl . '?action=tportal;sa=download;dl=adminftp',
				'active' => $context['TPortal']['dlsub'] == 'adminftp',
			],
		];
	}
	$context['template_layers'][] = 'tpadm';
	$context['template_layers'][] = 'subtab';
	TPadminIndex('');
	$context['current_action'] = 'admin';
}
// edit screen for regular users
function TPortalDLUser($item)
{
	global $txt, $scripturl, $boarddir, $context, $smcFunc;

	// check that it is indeed yours
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT * FROM {db_prefix}tp_dlmanager
		WHERE id = {int:item}
		AND type = {string:type}
		AND author_id = {int:auth} LIMIT 1',
		['item' => $item, 'type' => 'dlitem', 'auth' => $context['user']['id']]
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		// ok, it is. :)
		$row = $smcFunc['db_fetch_assoc']($request);

		// is it actually a subitem?
		if ($row['subitem'] > 0) {
			redirectexit('action=tportal;sa=download;dl=useredit' . $row['subitem']);
		}

		// get all items for a list but only your own
		$context['TPortal']['useritems'] = [];
		$context['TPortal']['dl_useredit'] = [];
		$itemlist = $smcFunc['db_query'](
			'',
			'
			SELECT id, name FROM {db_prefix}tp_dlmanager
			WHERE id != {int:item}
			AND author_id = {int:auth}
			AND type = {string:type}
			AND subitem = 0
			ORDER BY name ASC',
			['item' => $item, 'auth' => $context['user']['id'], 'type' => 'dlitem']
		);
		if ($smcFunc['db_num_rows']($itemlist) > 0) {
			while ($ilist = $smcFunc['db_fetch_assoc']($itemlist)) {
				$context['TPortal']['useritems'][] = [
					'id' => $ilist['id'],
					'name' => $ilist['name'],
				];
			}
		}

		// any additional files then..?
		$subitem = $row['id'];
		$fdata = [];
		$fetch = $smcFunc['db_query'](
			'',
			'
			SELECT id, name, file, downloads, filesize
			FROM {db_prefix}tp_dlmanager
			WHERE type = {string:type}
			AND subitem = {int:sub}',
			['type' => 'dlitem', 'sub' => $subitem]
		);

		if ($smcFunc['db_num_rows']($fetch) > 0) {
			while ($frow = $smcFunc['db_fetch_assoc']($fetch)) {
				if ($context['TPortal']['dl_fileprefix'] == 'K') {
					$ffs = ceil($row['filesize'] / 1024) . $txt['tp-kb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'M') {
					$ffs = (ceil($row['filesize'] / 1000) / 1024) . $txt['tp-mb'];
				}
				elseif ($context['TPortal']['dl_fileprefix'] == 'G') {
					$ffs = (ceil($row['filesize'] / 1000000) / 1024) . $txt['tp-gb'];
				}

				$fdata[] = [
					'id' => $frow['id'],
					'name' => $frow['name'],
					'file' => $frow['file'],
					'href' => $scripturl . '?action=tportal;sa=download;dl=item' . $frow['id'],
					'downloads' => $frow['downloads'],
					'created' => $frow['created'],
					'filesize' => $ffs,
				];
			}
			$smcFunc['db_free_result']($fetch);
		}

		$context['TPortal']['dl_useredit'][] = [
			'id' => $row['id'],
			'name' => $row['name'],
			'icon' => $row['icon'],
			'category' => $row['category'],
			'file' => $row['file'],
			'views' => $row['views'],
			'author_id' => $row['author_id'],
			'description' => $row['description'],
			'created' => timeformat($row['created']),
			'last_access' => timeformat($row['last_access']),
			'filesize' => (substr($row['file'], 14) != '- empty item -') ? floor(filesize($context['TPortal']['download_upload_path'] . $row['file']) / 1024) : '0',
			'downloads' => $row['downloads'],
			'sshot' => $row['screenshot'],
			'link' => $row['link'],
			'href' => $scripturl . '?action=tportal;sa=download;dl=adminitem' . $row['id'],
			'approved' => $row['category'] < 0 ? '0' : '1',
			'approve' => $scripturl . '?action=tportal;sa=download;dl=adminitem' . $row['id'],
			'subitem' => $fdata,
		];
		$author_id = $row['author_id'];
		$catparent = $row['category'];
		$itemname = $row['name'];
		$description = $row['description'];

		$smcFunc['db_free_result']($request);
		$request = $smcFunc['db_query'](
			'',
			'
			SELECT real_name as real_name
			FROM {db_prefix}members
			WHERE id_member = {int:auth} LIMIT 1',
			['auth' => $author_id]
		);
		if ($smcFunc['db_num_rows']($request) > 0) {
			$row = $smcFunc['db_fetch_assoc']($request);
			$context['TPortal']['admcurrent']['member'] = $row['real_name'];
			$smcFunc['db_free_result']($request);
		}
		else {
			$context['TPortal']['admcurrent']['member'] = '-' . $txt['guest_title'] . '-';
		}
		// add to the linktree
		TPadd_linktree($scripturl . '?action=tportal;sa=download;dl=useredit' . $item, $txt['tp-useredit'] . ': ' . $itemname);
		$context['TPortal']['dlaction'] = 'useredit';
		// fetch allowed categories
		TP_dluploadcats();
		// get the icons
		TP_dlgeticons();

		loadTemplate('TPdlmanager');
		if (loadLanguage('TPmodules') == false) {
			loadLanguage('TPmodules', 'english');
		}
		if (loadLanguage('TPortalAdmin') == false) {
			loadLanguage('TPortalAdmin', 'english');
		}

		if ($context['TPortal']['dl_wysiwyg'] == 'bbc') {
			$context['TPortal']['editor_id'] = 'dladmin_text' . $item;
			TP_prebbcbox($context['TPortal']['editor_id'], $description);
		}
		elseif ($context['TPortal']['dl_wysiwyg'] == 'html') {
			TPwysiwyg_setup();
		}
	}
	else {
		redirectexit('action=tportal;sa=download;dl');
	}
}

function dlupdatefilecount($category, $total = true)
{
	global $smcFunc;

	// get all files in its own category first
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT COUNT(*) FROM {db_prefix}tp_dlmanager
		WHERE category = {int:cat}
		AND type = {string:type}',
		['cat' => $category, 'type' => 'dlitem']
	);
	$result = $smcFunc['db_fetch_row']($request);
	$r = $result[0];
	$smcFunc['db_query'](
		'',
		'
		UPDATE {db_prefix}tp_dlmanager
		SET files = {int:file}
		WHERE id = {int:item}',
		['file' => $r, 'item' => $category]
	);
}

function dlsort($a, $b)
{
	return strnatcasecmp($b['items'], $a['items']);
}
function dlsortviews($a, $b)
{
	return strnatcasecmp($b['views'], $a['views']);
}
function dlsortsize($a, $b)
{
	return strnatcasecmp($b['size'], $a['size']);
}
function dlsortdownloads($a, $b)
{
	return strnatcasecmp($b['downloads'], $a['downloads']);
}

function TPDLgetname($oldname)
{
	if (strlen($oldname) > 13 && is_numeric(substr($oldname, 0, 10))) {
		$newname = substr($oldname, 10);
	}
	else {
		$newname = $oldname;
	}

	return $newname;
}
function TP_dluploadcats()
{
	global $scripturl, $context, $smcFunc;

	// first: fetch all allowed categories
	$sorted = [];
	$request = $smcFunc['db_query'](
		'',
		'
		SELECT id, parent, name, access
		FROM {db_prefix}tp_dlmanager
		WHERE type = {string:type}
		ORDER BY name ASC',
		['type' => 'dlcat']
	);
	if ($smcFunc['db_num_rows']($request) > 0) {
		while ($row = $smcFunc['db_fetch_assoc']($request)) {
			$show = get_perm($row['access'], 'tp_dlmanager');
			if ($show) {
				$sorted[$row['id']] = [
					'id' => $row['id'],
					'name' => $row['name'],
					'parent' => $row['parent'],
					'href' => $scripturl . '?action=tportal;sa=download;dl=cat' . $row['id'],
				];
			}
		}
		$smcFunc['db_free_result']($request);
	}
	$context['TPortal']['cats'] = [];
	// sort them
	if (count($sorted) > 1) {
		$context['TPortal']['cats'] = $sorted;
		$context['TPortal']['uploadcats'] = chain('id', 'parent', 'name', $sorted);
		$context['TPortal']['uploadcats2'] = chain('name', 'parent', 'id', $sorted);
	}
	else {
		$context['TPortal']['uploadcats'] = $sorted;
		$context['TPortal']['uploadcats2'] = $sorted;
		$context['TPortal']['cats'] = $sorted;
	}
}

function TP_dlgeticons()
{
	global $context, $boarddir;

	// fetch icons, just read the directory
	$context['TPortal']['dlicons'] = [];
	if ($handle = opendir($boarddir . '/tp-downloads/icons')) {
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..' && $file != '.htaccess' && in_array(substr($file, (strlen($file) - 4), 4), ['.jpg', '.gif', '.png'])) {
				$context['TPortal']['dlicons'][] = $file;
			}
		}
		closedir($handle);
		sort($context['TPortal']['dlicons']);
	}
}
function TP_dlftpfiles()
{
	global $context, $boarddir;

	$count = 1;
	$sorted = [];
	if ($handle = opendir($context['TPortal']['download_upload_path'])) {
		while (false !== ($file = readdir($handle))) {
			if ($file != '.' && $file != '..' && $file != '.htaccess' && $file != 'icons') {
				$size = (floor(filesize($context['TPortal']['download_upload_path'] . $file) / 102.4) / 10);
				$sorted[$count] = [
					'id' => $count,
					'file' => $file,
					'size' => $size,
				];
				$count++;
			}
		}
		closedir($handle);
	}
	$context['TPortal']['tp-downloads'] = [];
	// sort them
	if (count($sorted) > 1) {
		$context['TPortal']['tp-downloads'] = chain('id', 'size', 'file', $sorted);
	}
	else {
		$context['TPortal']['tp-downloads'] = $sorted;
	}
}

function TPDownloadAdminAreas()
{
	global $context, $scripturl;

	if (allowedTo('tp_dlmanager')) {
		$context['admin_tabs']['custom_modules']['tpdownloads'] = [
			'title' => 'TPdownloads',
			'description' => '',
			'href' => $scripturl . '?action=tportal;sa=download;dl=admin',
			'is_selected' => isset($_GET['dl']),
		];
		$admin_set = true;
	}
}
