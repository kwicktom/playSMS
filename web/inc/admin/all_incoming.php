<?php
defined('_SECURE_') or die('Forbidden');
if(!isadmin()){forcenoaccess();};

switch ($op) {
	case "all_incoming":
		$search_category = array(_('User') => 'username', _('Time') => 'in_datetime', _('From') => 'in_sender', _('Keyword') => 'in_keyword', _('Content') => 'in_message', _('Feature') => 'in_feature');
		$base_url = 'index.php?app=menu&inc=all_incoming&op=all_incoming';
		$search = themes_search($search_category, $base_url);
		$conditions = array('flag_deleted' => 0, 'in_status' => 1);
		$keywords = $search['dba_keywords'];
		$join = 'INNER JOIN '._DB_PREF_.'_tblUser AS B ON in_uid=B.uid';
		$count = dba_count(_DB_PREF_.'_tblSMSIncoming', $conditions, $keywords, '', $join);
		$nav = themes_nav($count, $search['url']);
		$extras = array('AND in_keyword' => '!= ""', 'ORDER BY' => 'in_id DESC', 'LIMIT' => $nav['limit'], 'OFFSET' => $nav['offset']);
		$list = dba_search(_DB_PREF_.'_tblSMSIncoming', '*', $conditions, $keywords, $extras, $join);

		$content = "
			<h2>"._('All incoming messages')."</h2>
			<p>".$search['form']."</p>
			<form id=fm_all_incoming name=fm_all_incoming action=\"index.php?app=menu&inc=all_incoming&op=actions\" method=post onSubmit=\"return SureConfirm()\">
			<input type=hidden name=go value=delete>
			<div class=actions_box>
				<div class=pull-left>
					<a href=\"index.php?app=menu&inc=all_incoming&op=actions&go=export\">".$core_config['icon']['export']."</a>
				</div>
				<div class=pull-right>
					<a href='#' onClick=\"return SubmitConfirm('"._('Are you sure you want to delete these items ?')."', 'fm_all_incoming');\">".$core_config['icon']['delete']."</a>
				</div>
			</div>
			<div class=table-responsive>
			<table class=playsms-table-list>
			<thead>
			<tr>
				<th width=20%>"._('User')."</th>
				<th width=20%>"._('From')."</th>
				<th width=20%>"._('Keyword')."</th>
				<th width=35%>"._('Content')."</th>
				<th width=5% class=\"sorttable_nosort\"><input type=checkbox onclick=CheckUncheckAll(document.fm_all_incoming)></th>
			</tr>
			</thead>
			<tbody>";

		$i = $nav['top'];
		$j = 0;
		for ($j=0;$j<count($list);$j++) {
			$list[$j] = core_display_data($list[$j]);
			$in_username = $list[$j]['username'];
			$in_id = $list[$j]['in_id'];
			$in_sender = $list[$j]['in_sender'];
			$p_desc = phonebook_number2name($in_sender);
			$current_sender = $in_sender;
			if ($p_desc) {
				$current_sender = "$in_sender<br />$p_desc";
			}
			$in_keyword = $list[$j]['in_keyword'];
			$in_datetime = core_display_datetime($list[$j]['in_datetime']);
			if ($c_feature = $list[$j]['in_feature']) {
				$c_feature = "<br />".$c_feature;
			}
			$in_status = ( $list[$j]['in_status'] == 1 ? '<span class=status_handled />' : '<span class=status_unhandled />' );
			$in_status = strtolower($in_status);
			$msg = $list[$j]['in_message'];
			$in_message = core_display_text($msg);
			$reply = '';
			$forward = '';
			if ($msg && $in_sender) {
				$reply = _a('index.php?app=menu&inc=send_sms&op=send_sms&do=reply&message='.urlencode($msg).'&to='.urlencode($in_sender), $core_config['icon']['reply']);
				$forward = _a('index.php?app=menu&inc=send_sms&op=send_sms&do=forward&message='.urlencode($msg), $core_config['icon']['forward']);
			}
			$c_message = "<div id=\"all_incoming_msg\">".$in_message."</div><div id=\"msg_label\">".$in_datetime."&nbsp;".$in_status."</div><div id=\"msg_option\">".$reply.$forward."</div>";
			$i--;
			$content .= "
				<tr>
					<td>$in_username</td>
					<td>$current_sender</td>
					<td>$in_keyword $c_feature</td>
					<td>$c_message</td>
					<td>
						<input type=hidden name=itemid".$j." value=\"$in_id\">
						<input type=checkbox name=checkid".$j.">
					</td>
				</tr>";
		}

		$content .= "
			</tbody>
			</table>
			</div>
			<div class=pull-right>".$nav['form']."</div>
			</form>";

		if ($err = $_SESSION['error_string']) {
			echo "<div class=error_string>$err</div>";
		}
		echo $content;
		break;
	case "actions":
		$nav = themes_nav_session();
		$search = themes_search_session();
		$go = $_REQUEST['go'];
		switch ($go) {
			case 'export':
				$conditions = array('flag_deleted' => 0, 'in_status' => 1);
				$join = 'INNER JOIN '._DB_PREF_.'_tblUser AS B ON in_uid=B.uid';
				$extras = array('AND in_keyword' => '!= ""');
				$list = dba_search(_DB_PREF_.'_tblSMSIncoming', '*', $conditions, $search['dba_keywords'], $extras, $join);
				$data[0] = array(_('User'), _('Time'), _('From'), _('Keyword'), _('Content'), _('Feature'), _('Status'));
				for ($i=0;$i<count($list);$i++) {
					$j = $i + 1;
					$data[$j] = array(
						$list[$i]['username'],
						core_display_datetime($list[$i]['in_datetime']),
						$list[$i]['in_sender'],
						$list[$i]['in_keyword'],
						$list[$i]['in_message'],
						$list[$i]['in_feature'],
						( $list[$i]['in_status'] == 1 ? _('handled') : _('unhandled')));
				}
				$content = core_csv_format($data);
				$fn = 'all_incoming-'.$core_config['datetime']['now_stamp'].'.csv';
				core_download($content, $fn, 'text/csv');
				break;
			case 'delete':
				for ($i=0;$i<$nav['limit'];$i++) {
					$checkid = $_POST['checkid'.$i];
					$itemid = $_POST['itemid'.$i];
					if(($checkid=="on") && $itemid) {
						$up = array('c_timestamp' => mktime(), 'flag_deleted' => '1');
						dba_update(_DB_PREF_.'_tblSMSIncoming', $up, array('in_id' => $itemid));
					}
				}
				$ref = $nav['url'].'&search_keyword='.$search['keyword'].'&page='.$nav['page'].'&nav='.$nav['nav'];
				$_SESSION['error_string'] = _('Selected incoming SMS has been deleted');
				header("Location: ".$ref);
		}
		break;
}

?>