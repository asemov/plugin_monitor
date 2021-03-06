<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2008-2017 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$guest_account = true;

chdir('../../');
include_once('./include/auth.php');

set_default_action();

/* Record Start Time */
list($micro,$seconds) = explode(" ", microtime());
$start = $seconds + $micro;

$criticalities = array(
	0 => __('Disabled', 'monitor'),
	1 => __('Low', 'monitor'),
	2 => __('Medium', 'monitor'),
	3 => __('High', 'monitor'),
	4 => __('Mission Critical', 'monitor')
);

$iclasses = array(
	0 => 'deviceUnknown',
	1 => 'deviceDown',
	2 => 'deviceRecovering',
	3 => 'deviceUp',
	4 => 'deviceThreshold',
	5 => 'deviceDownMuted',
	6 => 'deviceUnmonitored',
	7 => 'deviceWarning',
	8 => 'deviceAlert',
);

$icolorsdisplay = array(
	0 => __('Unknown', 'monitor'),
	1 => __('Down', 'monitor'),
	2 => __('Recovering', 'monitor'),
	3 => __('Up', 'monitor'),
	4 => __('Triggered', 'monitor'),
	5 => __('Down (Muted/Acked)', 'monitor'),
	6 => __('No Availability Check', 'monitor'),
	7 => __('Warning Ping', 'monitor'),
	8 => __('Alert Ping', 'monitor'),
);

$classes = array(
	'monitor_exsmall' => __('Extra Small', 'monitor'),
	'monitor_small'   => __('Small', 'monitor'),
	'monitor_medium'  => __('Medium', 'monitor'),
	'monitor_large'   => __('Large', 'monitor')
);

$monitor_status = array(
	-1 => __('All Monitored', 'monitor'),
	0  => __('Not Up', 'monitor'),
	1  => __('Not Up or Triggered', 'monitor')
);

$monitor_view_type = array(
	'default'  => __('Default', 'monitor'),
	'list'     => __('List', 'monitor'),
	'tiles'    => __('Tiles', 'monitor'),
	'tilesadt' => __('Tiles & Time', 'monitor')
);

$monitor_grouping = array(
	'default'  => __('Default', 'monitor'),
	'tree'     => __('Tree', 'monitor'),
	'template' => __('Device Template', 'monitor')
);

global $thold_hosts;

if (!isset($_SESSION['muted_hosts'])) {
	$_SESSION['muted_hosts'] = array();
}

validate_request_vars(true);

$thold_hosts = check_tholds();

switch(get_nfilter_request_var('action')) {
	case 'ajax_status':
		ajax_status();
		break;
	case 'ajax_mute_all':
		mute_all_hosts();
		draw_page();
		break;
	case 'ajax_unmute_all':
		unmute_all_hosts();
		draw_page();
		break;
	case 'save':
		save_settings();
		break;
	default:
		draw_page();
}

exit;

function draw_page() {
	global $config, $iclasses, $icolorsdisplay;

	find_down_hosts();

	general_header();

	draw_filter_and_status();

	// Default with permissions = default_by_permissions
	// Tree  = group_by_tree
	$function = 'render_' . get_request_var('grouping');
	if (function_exists($function)) {
		print $function();
	} else {
		print render_default();
	}

	if (read_user_setting('monitor_legend', read_config_option('monitor_legend'))) {
		print "<div class='center monitor_legend'><table class='center'><tr>\n";
		foreach($iclasses as $index => $class) {
			print "<td class='center $class" . "Bg' style='width:11%;'>" . $icolorsdisplay[$index] . "</td>\n";
		}
		print "</tr></table></div>\n";
	}

	// If the host is down, we need to insert the embedded wav file
	$monitor_sound = get_monitor_sound();
	if (is_monitor_audible()) {
		print "<audio id='audio' loop src='" . htmlspecialchars($config['url_path'] . "plugins/monitor/sounds/" . $monitor_sound) . "'></audio>\n";
	}

	?>
	<script type='text/javascript'>
	var refreshMSeconds=99999999;
	var myTimer;

	function timeStep() {
		value = $('#timer').html() - 1;

		if (value <= 0) {
			applyFilter();
		} else {
			$('#timer').html(value);
			// What is a second, well if you are an
			// emperial storm tropper, it's just a little more than a second.
			myTimer = setTimeout(timeStep, 1284);
		}
	}

	function muteUnmuteAudio(mute) {
		if (mute) {
			$('audio').each(function(){
				this.pause();
				this.currentTime = 0;
			});
		} else if ($('#downhosts').val() == 'true') {
			$('audio').each(function(){
				this.play();
			});
		}
	}

	function closeTip() {
		$(document).tooltip('close');
	}

	function applyFilter(action) {
		if (typeof action == 'undefined') {
			action = '';
		}

		clearTimeout(myTimer);
		$('.fa-server, .fa-first-order').unbind();

		strURL  = 'monitor.php?header=false';
		if (action >= '') {
			strURL += '&action='+action;
		}
		strURL += '&refresh='+$('#refresh').val();
		strURL += '&grouping='+$('#grouping').val();
		strURL += '&tree='+$('#tree').val();
		strURL += '&template='+$('#template').val();
		strURL += '&view='+$('#view').val();
		strURL += '&crit='+$('#crit').val();
		strURL += '&size='+$('#size').val();
		strURL += '&mute='+$('#mute').val();
		strURL += '&status='+$('#status').val();

		loadPageNoHeader(strURL);
	}

	function saveFilter() {
		url='monitor.php?action=save' +
			'&refresh='  + $('#refresh').val() +
			'&grouping=' + $('#grouping').val() +
			'&tree='     + $('#tree').val() +
			'&template=' + $('#template').val() +
			'&view='     + $('#view').val() +
			'&crit='     + $('#crit').val() +
			'&size='     + $('#size').val() +
			'&status='   + $('#status').val();

		$.get(url, function(data) {
			$('#text').show().text('Filter Settings Saved').fadeOut(2000);
		});
	}

	function setupTooltips() {
	}

	$('#go').click(function() {
		applyFilter();
	});

	$('#sound').click(function() {
		if ($('#mute').val() == 'false') {
			$('#mute').val('true');
			muteUnmuteAudio(true);
			applyFilter('ajax_mute_all');
		} else {
			$('#mute').val('false');
			muteUnmuteAudio(false);
			applyFilter('ajax_unmute_all');
		}
	});

	$('#refresh, #view, #crit, #grouping, #size, #status, #tree, #template').change(function() {
		applyFilter();
	});

	$('#save').click(function() {
		saveFilter();
	});

	$(function() {
		// Clear the timeout to keep countdown accurate
		clearTimeout(myTimer);

		// Servers need tooltips
		$('.monitor_device_frame').find('i').tooltip({
			items: '.fa-server, .fa-first-order',
			open: function(event, ui) {
				if (typeof(event.originalEvent) == 'undefined') {
					return false;
				}

				var $id = $(ui.tooltip).attr('id');

				$('div.ui-tooltip').not('#'+ $id).remove();
			},
			close: function(event, ui) {
				ui.tooltip.hover(
				function () {
					$(this).stop(true).fadeTo(400, 1);
				},
				function() {
					$(this).fadeOut('400', function() {
						$(this).remove();
					});
				});
			},
			position: {my: "left:15 top", at: "right center"},
			content: function(callback) {
				var id = $(this).attr('id');
				$.get('monitor.php?action=ajax_status&id='+id, function(data) {
					callback(data);
				});
			}
		});

		// Start the countdown
		myTimer = setTimeout(timeStep, 1000);

		// Attempt to reposition the tooltips on resize
		$(window).resize(function() {
			$(document).tooltip('option', 'position', {my: "1eft:15 top", at: "right center"});
		});

		if ($('#mute').val() == 'true') {
			muteUnmuteAudio(true);
		} else {
			muteUnmuteAudio(false);
		}
		$('#main').css('margin-right', '15px');
	});

	</script>
	<?php

	print '<div class="center monitorFooter">' . get_filter_text() . '</div>';

	bottom_footer();
}

function is_monitor_audible() {
	return get_monitor_sound() != '';
}

function get_monitor_sound() {
	$sound = read_user_setting('monitor_sound', read_config_option('monitor_sound'));
	clearstatcache();
	$file = dirname(__FILE__) . '/sounds/' . $sound;
	$exists = file_exists($file);
	return $exists ? $sound : '';
}

function find_down_hosts() {
	$dhosts = get_hosts_down_by_permission();
	if (sizeof($dhosts)) {
		set_request_var('downhosts', 'true');
		if (isset($_SESSION['muted_hosts'])) {
			$unmuted_hosts = array_diff($dhosts, $_SESSION['muted_hosts']);
			if (sizeof($unmuted_hosts)) {
				unmute_user();
			}
		} else {
			set_request_var('mute', 'false');
		}
	} else {
		unmute_all_hosts();
		set_request_var('downhosts', 'false');
	}
}

function mute_all_hosts() {
	$_SESSION['muted_hosts'] = get_hosts_down_by_permission();
	mute_user();
}

function unmute_all_hosts() {
	$_SESSION['muted_hosts'] = array();
	unmute_user();
}

function mute_user() {
	set_request_var('mute', 'true');
	set_user_setting('monitor_mute','true');
}

function unmute_user() {
	set_request_var('mute', 'false');
	set_user_setting('monitor_mute','false');
}

function check_tholds() {
	$thold_hosts  = array();

	if (api_plugin_is_enabled('thold')) {
		return array_rekey(
			db_fetch_assoc('SELECT DISTINCT dl.host_id
				FROM thold_data AS td
				INNER JOIN data_local AS dl
				ON td.local_data_id=dl.id
				WHERE thold_alert > 0
				AND thold_enabled = "on"'),
			'host_id', 'host_id'
		);
	}

	return $thold_hosts;
}

function get_filter_text() {
	$filter = '<div class="center monitorFooterText">';

	switch(get_request_var('status')) {
	case '-1':
		$filter .= __('All Monitored Devices', 'monitor');
		break;
	case '0':
		$filter .= __('Monitored Devices either Down or Recovering', 'monitor');
		break;
	case '1':
		$filter .= __('Monitored Devices either Down, Recovering, with Breached Thresholds', 'monitor');
		break;
	default:
		$filter .= __('Unknown monitoring status (%s)', get_request_var('status'), 'monitor');
	}

	switch(get_request_var('crit')) {
	case '0':
		$filter .= __(', and All Criticalities', 'monitor');
		break;
	case '1':
		$filter .= __(', and of Low Criticality or Higher', 'monitor');
		break;
	case '2':
		$filter .= __(', and of Medium Criticality or Higher', 'monitor');
		break;
	case '3':
		$filter .= __(', and of High Criticality or Higher', 'monitor');
		break;
	case '4':
		$filter .= __(', and of Mission Critical Status', 'monitor');
		break;
	}

	$filter .= __('</div><div class="center monitorFooterTextBold">Remember to first select eligible Devices to be Monitored from the Devices page!</div>', 'monitor');

	return $filter;
}

function draw_filter_dropdown($id, $title, $settings = array(), $value = null) {
	if ($value == null) {
		$value = get_nfilter_request_var($id);
	}

	if (sizeof($settings)) {
		print '<div class="monitorFilterCell"><select id="' . $id . '" title="' . $title . '">' . PHP_EOL;
		foreach ($settings as $setting_value => $setting_name) {
			if ($value == null || $value == '') {
				$value = $setting_value;
			}

			$setting_selected = ($value == $setting_value) ? ' selected' : '';
			print '<option value="' . $setting_value . '"' . $setting_selected . '>' . $setting_name . '</option>' . PHP_EOL;
		}
		print '</select></div>' . PHP_EOL;
	} else {
		print "<input type='hidden' id='$id' value='$value'>" . PHP_EOL;
	}
}

function draw_filter_and_status() {
	global $criticalities, $page_refresh_interval, $classes, $monitor_grouping, $monitor_view_type, $monitor_status;

	print '<form class="monitorFilterForm"><div class="monitorFilterTable"><div class="monitorFilterRow">' . PHP_EOL;
	print '<div class="monitorFilterCell"><div class="monitorFilterTable"><div class="monitorFilterRow">' . PHP_EOL;

	draw_filter_dropdown('status', __esc('Device Status', 'monitor'), $monitor_status);
	draw_filter_dropdown('view', __esc('View Type', 'monitor'), $monitor_view_type);
	draw_filter_dropdown('grouping', __esc('Device Grouping', 'monitor'), $monitor_grouping);

	print '</div><div class="monitorFilterRow">';
	draw_filter_dropdown('crit', __esc('Select Minimum Criticality', 'monitor'), $criticalities);
	draw_filter_dropdown('size', __esc('Device Icon Size', 'monitor'), $classes);

	$trees = array();
	if (get_request_var('grouping') == 'tree') {
		$trees_allowed = array_rekey(get_allowed_trees(), 'id', 'name');
		if (sizeof($trees_allowed)) {
			$trees_prefix = array(-1 => __('All Trees', 'monitor'));
			$trees_suffix = array(-2 => __('Non-Tree Devices', 'monitor'));

			$trees = $trees_prefix + $trees_allowed + $trees_suffix;
		}
	}
	draw_filter_dropdown('tree', __esc('Select Tree', 'monitor'), $trees);

	$templates = array();
	if (get_request_var('grouping') == 'template') {
		$templates_allowed = array_rekey(db_fetch_assoc('SELECT id, name FROM host_template'), 'id', 'name');
		if (sizeof($templates_allowed)) {
			$templates_prefix = array(-1 => __('All Templates', 'monitor'));
			$templates_suffix = array(-2 => __('Non-Templated Devices', 'monitor'));

			$templates = $templates_prefix + $templates_allowed + $templates_suffix;
		}
	}
	draw_filter_dropdown('template', __esc('Select Template', 'monitor'), $templates);

	print '</div></div></div>' . PHP_EOL;
	draw_filter_dropdown('refresh', __esc('Refresh Frequency', 'monitor'), $page_refresh_interval);
	print '<div class="monitorFilterCell">' . PHP_EOL;
	print '<div class="monitorFilterTable"><div class="monitorFilerRow">' .PHP_EOL;
	print '<div class="monitorFilterCell">' . PHP_EOL;
	print '<input type="button" value="' . __esc('Refresh', 'monitor') . '" id="go" title="' . __esc('Refresh the Device List', 'monitor') . '">' . PHP_EOL;
	print '</div><div class="monitorFilterCell">' . PHP_EOL;
	print '<input type="button" value="' . __esc('Save', 'monitor') . '" id="save" title="' . __esc('Save Filter Settings', 'monitor') . '">' . PHP_EOL;
	print '</div><div class="monitorFilterCell">' . PHP_EOL;
	print '<input type="button" value="' . (get_request_var('mute') == 'false' ? get_mute_text():get_unmute_text()) . '" id="sound" title="' . (get_request_var('mute') == 'false' ? __('%s Alert for downed Devices', get_mute_text(), 'monitor'):__('%s Alerts for downed Devices', get_unmute_text(), 'monitor')) . '">' . PHP_EOL;
	print '<input id="downhosts" type="hidden" value="' . get_request_var('downhosts') . '"><input id="mute" type="hidden" value="' . get_request_var('mute') . '">' . PHP_EOL;
	print '</div></div><div class="monitorFilterRow"><div class="monitorFilterCell">' . PHP_EOL;
	print '<div class="monitorFilterText">' . __('Last Refresh: %s', date('g:i:s a', time()), 'monitor') . (get_request_var('refresh') < 99999 ? '<br/>' . __('Refresh Again in <i id="timer">%d</i> Seconds', get_request_var('refresh'), 'monitor'):'') . '</div>';

	print '</div></div></div></div></div></div></form>' . PHP_EOL;
	// Display the Current Time
}

function get_mute_text() {
	if (is_monitor_audible()) {
		return __('Mute', 'monitor');
	} else {
		return __('Acknowledge', 'monitor');
	}
}

function get_unmute_text() {
	if (is_monitor_audible()) {
		return __('Un-Mute', 'monitor');
	} else {
		return __('Reset', 'monitor');
	}
}

function save_settings() {
	validate_request_vars();

	if (sizeof($_REQUEST)) {
		foreach($_REQUEST as $var => $value) {
			switch($var) {
			case 'refresh':
				set_user_setting('monitor_refresh', get_request_var('refresh'));
				break;
			case 'grouping':
				set_user_setting('monitor_grouping', get_request_var('grouping'));
				break;
			case 'view':
				set_user_setting('monitor_view', get_request_var('view'));
				break;
			case 'crit':
				set_user_setting('monitor_crit', get_request_var('crit'));
				break;
			case 'mute':
				set_user_setting('monitor_mute', get_request_var('mute'));
				break;
			case 'size':
				set_user_setting('monitor_size', get_request_var('size'));
				break;
			case 'status':
				set_user_setting('monitor_status', get_request_var('status'));
				break;
			case 'tree':
				set_user_setting('monitor_tree', get_request_var('tree'));
				break;
			}
		}
	}

	validate_request_vars(true);
}

function validate_request_vars($force = false) {
	/* ================= input validation and session storage ================= */
	$filters = array(
		'refresh' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_refresh', read_config_option('monitor_refresh'), $force)
		),
		'mute' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_mute', 'false', $force)
		),
		'grouping' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_grouping', read_config_option('monitor_grouping'), $force)
		),
		'view' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_view', read_config_option('monitor_view'), $force)
		),
		'size' => array(
			'filter' => FILTER_CALLBACK,
			'options' => array('options' => 'sanitize_search_string'),
			'default' => read_user_setting('monitor_size', 'monior_medium', $force)
		),
		'crit' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_crit', '-1', $force)
		),
		'status' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_status', '-1', $force)
		),
		'tree' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_tree', '-1', $force)
		),
		'template' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => read_user_setting('monitor_template', '-1', $force)
		),
		'id' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '-1'
		),
		'rows' => array(
			'filter' => FILTER_VALIDATE_INT,
			'pageset' => true,
			'default' => '-1'
		),
		'page' => array(
			'filter' => FILTER_VALIDATE_INT,
			'default' => '1'
		),
		'filter' => array(
			'filter' => FILTER_CALLBACK,
			'pageset' => true,
			'default' => '',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_column' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'status',
			'options' => array('options' => 'sanitize_search_string')
		),
		'sort_direction' => array(
			'filter' => FILTER_CALLBACK,
			'default' => 'ASC',
			'options' => array('options' => 'sanitize_search_string')
		)
	);

	validate_store_request_vars($filters, 'sess_monitor');
	/* ================= input validation ================= */
}

function render_where_join(&$sql_where, &$sql_join) {
	if (get_request_var('crit') > 0) {
		$crit = ' AND h.monitor_criticality>=' . get_request_var('crit');
	} else {
		$crit = '';
	}

	if (get_request_var('status') == '0') {
		$sql_join  = '';
		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND h.status < 3
			AND (h.availability_method>0
				OR h.snmp_version>0
				OR (h.cur_time >= h.monitor_warn
					AND monitor_warn > 0)
				OR (h.cur_time >= h.monitor_alert
					AND h.monitor_alert > 0)
			)' . $crit;
	} elseif (get_request_var('status') == '1') {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';
		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND (h.status < 3
			OR (td.thold_enabled="on" AND td.thold_alert>0)
			OR ((h.availability_method>0 OR h.snmp_version>0)
				AND ((h.cur_time > h.monitor_warn AND h.monitor_warn > 0)
				OR (h.cur_time > h.monitor_alert AND h.monitor_alert > 0))
			))' . $crit;
	} else {
		$sql_join  = 'LEFT JOIN thold_data AS td ON td.host_id=h.id';
		$sql_where = 'WHERE h.disabled = ""
			AND h.monitor = "on"
			AND (h.availability_method>0 OR h.snmp_version>0
				OR (td.thold_enabled="on" AND td.thold_alert>0)
			)' . $crit;
	}
}

/* Render functions */
function render_default() {
	$result = '';

	$sql_where = '';
	$sql_join  = '';
	render_where_join($sql_where, $sql_join);

	$hosts  = db_fetch_assoc("SELECT DISTINCT h.*
		FROM host AS h
		$sql_join
		$sql_where
		ORDER BY description");

	if (sizeof($hosts)) {
		// Determine the correct width of the cell
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
			FROM host AS h
			$sql_join
			$sql_where");

		$function = 'render_header_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_header_ function */
			$result .= $function($hosts);
		}

		foreach($hosts as $host) {
			$result .= render_host($host, true, $maxlen);
		}

		$function = 'render_footer_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_footer_ function */
			$result .= $function($hosts);
		}
	}

	return $result;
}

function render_perms() {
	global $row_stripe;

	// Get the list of allowed devices first
	$hosts = get_allowed_devices();

	if (sizeof($hosts)) {
		$function = 'render_header_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_header_ function */
			$result .= $function($hosts);
		}

		foreach($hosts as $host) {
			$host_ids[] = $host['id'];
		}

		$sql_where = '';
		$sql_join  = '';
		render_where_join($sql_where, $sql_join);

		// Now query for the hosts in that list that should be displayed
		$hosts  = db_fetch_assoc("SELECT DISTINCT h.*
			FROM host AS h
			$sql_join
			$sql_where
			AND h.id IN(" . implode(',', $host_ids) . ")
			ORDER BY description");

		// Determine the correct width of the cell
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
			FROM host AS h
			WHERE id IN (" . implode(',', $host_ids) . ")");

		foreach ($hosts as $host) {
			$result .= render_host($host, true, $maxlen);
		}

		$function = 'render_footer_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_footer_ function */
			$result .= $function($hosts);
		}
	}

	return $result;
}

function render_template() {
	$result = '';

	$sql_where = '';
	$sql_join = '';

	render_where_join($sql_where, $sql_join);

	if (get_request_var('template') > 0) {
		$sql_where .= ($sql_where == '' ? '' : 'AND ') . 'ht.id = ' . get_request_var('template');
	}

	$sql_template  = 'INNER JOIN host_template AS ht ON h.host_template_id=ht.id ';
	if (get_request_var('template') == -2) {
		$sql_where .= ($sql_where == '' ? '' : 'AND ') . 'ht.id IS NULL';
		$sql_template = 'LEFT JOIN host_template AS ht ON h.host_template_id=ht.id ';
	}

	$sql = "SELECT DISTINCT
		h.*, ht.name AS host_template_name
		FROM host AS h
		$sql_template
		$sql_join
		$sql_where
		ORDER BY ht.name, h.description";

	$hosts  = db_fetch_assoc($sql);

	$ctemp = -1;
	$ptemp = -1;

	if (get_request_var('view') == 'tiles') {
		$offset  = 0;
		$offset2 = 0;
	} else {
		$offset  = 52;
		$offset2 = 38;
	}

	if (sizeof($hosts)) {
		$suppressGroups = false;
		$function = 'render_suppressgroups_'. get_request_var('view');
		if (function_exists($function)) {
			$suppressGroups = $function($hosts);
		}

		$function = 'render_header_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_header_ function */
			$result .= $function($hosts);
			$suppresGroups = true;
		}

		foreach($hosts as $host) {
			$host_ids[] = $host['id'];
		}

		// Determine the correct width of the cell
		$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
			FROM host AS h
			WHERE id IN (" . implode(',', $host_ids) . ")");

		$class = get_request_var('size');

		foreach($hosts as $host) {
			$ctemp = $host['host_template_id'];

			if (!$suppressGroups) {
				if ($ctemp != $ptemp && $ptemp > 0) {
					$result .= "</td></tr></table></div></div>\n";
				}

				if ($ctemp != $ptemp) {
					$result .= "<div class='monitor_main $class'><div class='monitor_frame'><table class='odd'><tr class='tableHeader'><th class='left'>" . $host['host_template_name'] . "</th></tr><tr><td class='center $class'>\n";
				}
			}

			$result .= render_host($host, true, $maxlen);

			if ($ctemp != $ptemp) {
				$ptemp = $ctemp;
			}
		}

		if ($ptemp == $ctemp && !$suppressGroups) {
			$result .= "</td></tr></table></div></div>\n";
		}

		$function = 'render_footer_' . get_request_var('view');
		if (function_exists($function)) {
			/* Call the custom render_footer_ function */
			$result .= $function($hosts);
		}

	}

	return $result;
}

function render_tree() {
	$result = '';

	$leafs = array();

	if (get_request_var('tree') > 0) {
		$sql_where = 'gt.id=' . get_request_var('tree');
	} else {
		$sql_where = '';
	}

	if (get_request_var('tree') != -2) {
		$tree_list = get_allowed_trees(false, false, $sql_where);
	} else {
		$tree_list = array();
	}

	$function = 'render_header_' . get_request_var('view');
	if (function_exists($function)) {
		$hosts = array();

		/* Call the custom render_header_ function */
		$result .= $function($hosts);
	}

	if (sizeof($tree_list)) {
		$ptree = '';
		foreach($tree_list as $tree) {
			$tree_ids[$tree['id']] = $tree['id'];
		}

		$branchWhost = db_fetch_assoc("SELECT DISTINCT gti.graph_tree_id, gti.parent
			FROM graph_tree_items AS gti
			WHERE gti.host_id > 0
			AND gti.parent >= 0
			AND gti.graph_tree_id IN (" . implode(',', $tree_ids) . ")
			ORDER BY gti.graph_tree_id, position");

		if (sizeof($branchWhost)) {
			foreach($branchWhost as $b) {
				$titles[$b['graph_tree_id'] . ':' . $b['parent']] = ($b['parent'] > 0 ? db_fetch_cell_prepared('SELECT title
					FROM graph_tree_items
					WHERE id = ?
					AND graph_tree_id = ?
					ORDER BY graph_tree_id',
					array($b['parent'], $b['graph_tree_id'])) : __('Root Branch', 'monitor'));
			}

			foreach($titles as $index => $title) {
				list($graph_tree_id, $parent) = explode(':', $index);

				$oid = $parent;

				$sql_where = '';
				$sql_join  = '';

				render_where_join($sql_where, $sql_join);

				$hosts = db_fetch_assoc_prepared("SELECT h.*
					FROM host AS h
					INNER JOIN graph_tree_items AS gti
					ON h.id=gti.host_id
					$sql_join
					$sql_where
					AND parent = ?
					GROUP BY h.id",
					array($oid));

				$tree_name = db_fetch_cell_prepared('SELECT name
					FROM graph_tree
					WHERE id = ?',
					array($graph_tree_id));

				if ($ptree != $tree_name) {
					if ($ptree != '') {
						$result .= '</div></td></tr></table></div>';
					}
					$result .= '<div class="monitor_tree_title"><table class="odd"><tr class="tableHeader"><th>' . __('Tree:', 'monitor') . '&nbsp;' . $tree_name . '</th></tr><tr><td><div style="width:100%">';
					$ptree = $tree_name;
				}

				if (sizeof($hosts)) {
					foreach($hosts as $host) {
						$host_ids[] = $host['id'];
					}

					$class = get_request_var('size');

					// Determine the correct width of the cell
					$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
						FROM host AS h
						WHERE id IN (" . implode(',', $host_ids) . ")");

					$result .= '<div class="monitor_tree_frame"><table class="odd"><tr class="tableHeader"><th>' . $title . '</th></tr><tr><td class="center"><div>';

					foreach($hosts as $host) {
						$result .= render_host($host, true, $maxlen);
					}

					$result .= '</div></td></tr></table></div>';
				}
			}
		}

		$result .= '</div></td></tr></table></div>';
	}

	/* begin others - lets get the monitor items that are not associated with any tree */
	if (get_request_var('tree') < 0) {
		$hosts = get_host_non_tree_array();
		if (sizeof($hosts)) {
			foreach($hosts as $host) {
				$host_ids[] = $host['id'];
			}

			// Determine the correct width of the cell
			if (sizeof($host_ids)) {
				$maxlen = db_fetch_cell("SELECT MAX(LENGTH(description))
					FROM host AS h
					WHERE id IN (" . implode(',', $host_ids) . ")");
			} else {
				$maxlen = 100;
			}

			$result .= '<div class="monitor_tree_title"><table class="odd"><tr class="tableHeader"><th>' . __('Non-Tree Devices', 'monitor') . '</th></tr><tr><td><div style="width:100%">';
			foreach($hosts as $leaf) {
				$result .= render_host($leaf, true, $maxlen);
			}

			$result .= '</div></td></tr></table></div>';
		}
	}

	$function = 'render_footer_' . get_request_var('view');
	if (function_exists($function)) {
		/* Call the custom render_footer_ function */
		$result .= $function($hosts);
	}


	return $result;
}

/* Branch rendering */
function render_branch($leafs, $title = '') {
	global $render_style;
	global $row_stripe;

	$row_stripe=false;

	if ($title == '') {
		foreach ($leafs as $row) {
			/* get our proper branch title */
			$title = $row['branch_name'];
			break;
		}
	}
	if ($title == '') {
		/* Insert a default title */
		$title = 'Items';
		$title .= ' (' . sizeof($leafs) . ')';
	}
	//$branch_percentup = '%' . leafs_percentup($leafs);
	//$title .= " - $branch_percentup";

	/* select function to render here */
	$function = "render_branch_$render_style";
	if (function_exists($function)) {
		/* Call the custom render_branch_ function */
		return $function($leafs, $title);
	} else {
		return render_branch_tree($leafs, $title);
	}
}

function get_host_status($host,$real=false) {
	global $thold_hosts, $iclasses;

	/* If the host has been muted, show the muted Icon */
	if (array_key_exists($host['id'], $thold_hosts)) {
		$host['status'] = 4;
	} elseif (in_array($host['id'], $_SESSION['muted_hosts']) && $host['status'] == 1) {
		$host['status'] = 5;
	} elseif ($host['status'] == 3) {
		if ($host['cur_time'] > $host['monitor_alert'] && !empty($host['monitor_alert'])) {
			$host['status'] = 8;
		} elseif ($host['cur_time'] > $host['monitor_warn'] && !empty($host['monitor_warn'])) {
			$host['status'] = 7;
		}
	}

	// If wanting the real status, or the status is already known
	// return the real status, otherwise default to unknown
	return ($real || array_key_exists($host['status'], $iclasses)) ? $host['status'] : 0;
}

function get_host_status_description($status) {
	global $icolorsdisplay;

	if (array_key_exists($status, $icolorsdisplay)) {
		return $icolorsdisplay[$status];
	} else {
		return __('Unknown', 'monitor') . " ($status)";
	}
}

/*Single host  rendering */
function render_host($host, $float = true, $maxlen = 120) {
	global $thold_hosts, $config, $icolorsdisplay, $iclasses, $classes;

	//throw out tree root items
	if (array_key_exists('name', $host))  {
		return;
	}

	if (!is_device_allowed($host['id'])) {
		return;
	}

	if ($host['id'] <= 0) {
		return;
	}

	$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];

	if ($host['status'] == 3 && array_key_exists($host['id'], $thold_hosts)) {
		$host['status'] = 4;
		if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
			$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php';
		} else {
			$host['anchor'] = $config['url_path'] . 'plugins/thold/graph_thold.php';
		}
	}

	$host['real_status'] = get_host_status($host,true);
	$host['status'] = get_host_status($host);
	$host['iclass'] = $iclasses[$host['status']];

	$dt = '';
	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = get_timeinstate($host);
	}

	$function = 'render_host_' . get_request_var('view');

	if (function_exists($function)) {
		/* Call the custom render_host_ function */
		$result = $function($host);
	} else {
		$iclass = get_status_icon($host['status']);
		$fclass = get_request_var('size');

		if ($host['status'] <= 2 || $host['status'] == 5) {
			$result = "<div class='$fclass flash monitor_device_frame' style='width:" . max(120, $maxlen*7) . 'px;' . ($float ? 'float:left;':'') . "'><a href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $iclass " . $host['iclass'] . "'></i><br><span class='center'>" . trim($host['description']) . "</span><br><span style='font-size:10px;padding:2px;' class='deviceDown'>$dt</span></a></div>\n";
		} else {
			$result = "<div class='monitor_device_frame $fclass' style='width:" . max(120, $maxlen*7) . 'px;' . ($float ? 'float:left;':'') . "'><a href='" . $host['anchor'] . "'><i id=" . $host['id'] . " class='fa $iclass " . $host['iclass'] . "'></i><br>" . trim($host['description']) . "</a></div>\n";
		}
	}

	return $result;
}

function get_status_icon($status) {
	if ($status == 1 && read_user_setting('monitor_sound') == 'First Orders Suite.mp3') {
		return 'fa-first-order fa-spin';
	} else {
		return 'fa-server';
	}
}

function monitor_print_host_time($status_time, $seconds = false) {
	// If the host is down, make a downtime since message
	$dt   = '';
	if (is_numeric($status_time)) {
		$sfd  = round($status_time / 100,0);
	} else {
		$sfd  = time() - strtotime($status_time);
	}
	$dt_d = floor($sfd/86400);
	$dt_h = floor(($sfd - ($dt_d * 86400))/3600);
	$dt_m = floor(($sfd - ($dt_d * 86400) - ($dt_h * 3600))/60);
	$dt_s = $sfd - ($dt_d * 86400) - ($dt_h * 3600) - ($dt_m * 60);

	if ($dt_d > 0 ) {
		$dt .= $dt_d . 'd:' . $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');
	} else if ($dt_h > 0 ) {
		$dt .= $dt_h . 'h:' . $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');
	} else if ($dt_m > 0 ) {
		$dt .= $dt_m . 'm' . ($seconds ? ':' . $dt_s . 's':'');;
	} else {
		$dt .= ($seconds ? $dt_s . 's':__('Just Up', 'monitor'));
	}

	return $dt;
}

function ajax_status() {
	global $thold_hosts, $config, $icolorsdisplay, $iclasses, $criticalities;

	$tholds = 0;

	if (isset_request_var('id') && get_filter_request_var('id')) {
		$id = get_request_var('id');

		$host = db_fetch_row_prepared('SELECT * FROM host WHERE id = ?', array($id));

		$host['anchor'] = $config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id'];

		if ($host['status'] == 3 && array_key_exists($host['id'], $thold_hosts)) {
			$host['status'] = 4;
			if (file_exists($config['base_path'] . '/plugins/thold/thold_graph.php')) {
				$host['anchor'] = $config['url_path'] . 'plugins/thold/thold_graph.php';
			} else {
				$host['anchor'] = $config['url_path'] . 'plugins/thold/graph_thold.php';
			}
		}

		if ($host['availability_method'] == 0) {
			$host['status'] = 6;
		}

		$host['real_status'] = get_host_status($host,true);
		$host['status'] = get_host_status($host);

		if (sizeof($host)) {
			if (api_plugin_user_realm_auth('host.php')) {
				$host_link = htmlspecialchars($config['url_path'] . 'host.php?action=edit&id=' . $host['id']);
			}

			// Get the number of graphs
			$graphs   = db_fetch_cell_prepared('SELECT count(*) FROM graph_local WHERE host_id = ?', array($host['id']));
			if ($graphs > 0) {
				$graph_link = htmlspecialchars($config['url_path'] . 'graph_view.php?action=preview&reset=1&host_id=' . $host['id']);
			}

			// Get the number of thresholds
			if (api_plugin_is_enabled('thold')) {
				$tholds = db_fetch_cell_prepared('SELECT count(*)
					FROM thold_data
					WHERE host_id = ?',
					array($host['id']));

				if ($tholds) {
					$thold_link = htmlspecialchars($config['url_path'] . 'plugins/thold/thold_graph.php?action=thold&reset=1&status=-1&host_id=' . $host['id']);
				}
			}

			// Get the number of syslogs
			if (api_plugin_is_enabled('syslog') && api_plugin_user_realm_auth('syslog.php')) {
				include($config['base_path'] . '/plugins/syslog/config.php');
				include_once($config['base_path'] . '/plugins/syslog/functions.php');
				$syslog_logs = syslog_db_fetch_cell_prepared('SELECT count(*)
					FROM syslog_logs
					WHERE host = ?',
					array($host['hostname']));

				$syslog_host = syslog_db_fetch_cell_prepared('SELECT host_id
					FROM syslog_hosts
					WHERE host = ?',
					array($host['hostname']));

				if ($syslog_logs && $syslog_host) {
					$syslog_log_link = htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=alerts&host_id=' . $syslog_host);
				}

				if ($syslog_host) {
					$syslog_link = htmlspecialchars($config['url_path'] . 'plugins/syslog/syslog/syslog.php?reset=1&tab=syslog&host_id=' . $syslog_host);
				}
			} else {
				$syslog_logs  = 0;
				$syslog_host  = 0;
			}

			$links = '';
			if (isset($host_link)) {
				$links .= '<a class="hyperLink monitor_link" href="' . $host_link . '">' . __('Edit Device', 'monitor') . '</a>';
			}
			if (isset($graph_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink monitor_link" href="' . $graph_link . '">' . __('View Graphs', 'monitor') . '</a>';
			}
			if (isset($thold_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink monitor_link" href="' . $thold_link . '">' . __('View Thresholds', 'monitor') . '</a>';
			}
			if (isset($syslog_log_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink monitor_link" href="' . $syslog_log_link . '">' . __('View Syslog Alerts', 'monitor') . '</a>';
			}
			if (isset($syslog_link)) {
				$links .= ($links != '' ? ', ':'') . '<a class="hyperLink monitor_link" href="' . $syslog_link . '">' . __('View Syslog Messages', 'monitor') . '</a>';
			}


			$iclass   = $iclasses[$host['status']];
			$sdisplay = get_host_status_description($host['real_status']);
			print "<table class='monitorHover'>
				<tr class='tableHeader'>
					<th class='left' colspan='2'>Device Status Information</th>
				</tr>
				<tr>
					<td>" . __('Device:', 'monitor') . "</td>
					<td><a class='hyperLink monitor_link' href='" . $host['anchor'] . "'>" . $host['description'] . "</a></td>
				</tr>" . (isset($host['monitor_criticality']) && $host['monitor_criticality'] > 0 ? "
				<tr>
					<td>" . __('Criticality:', 'monitor') . "</td>
					<td>" . $criticalities[$host['monitor_criticality']] . "</td>
				</tr>":"") . "
				<tr>
					<td>" . __('Status:', 'monitor') . "</td>
					<td class='$iclass'>$sdisplay</td>
				</tr>" . ($host['status'] < 3 || $host['status'] == 5 ? "
				<tr>
					<td>" . __('Admin Note:', 'monitor') . "</td>
					<td class='$iclass'>" . $host['monitor_text'] . "</td>
				</tr>":"") . ($host['availability_method'] > 0 ? "
				<tr>
					<td>" . __('IP/Hostname:', 'monitor') . "</td>
					<td>" . $host['hostname'] . "</td>
				</tr>":"") . ($host['notes'] != '' ? "
				<tr>
					<td>" . __('Notes:', 'monitor') . "</td>
					<td>" . $host['notes'] . "</td>
				</tr>":"") . (($graphs || $syslog_logs || $syslog_host || $tholds) ? "
				<tr>
					<td>" . __('Links:', 'monitor') . "</td>
					<td>" . $links . "
				 	</td>
				</tr>":"") . ($host['availability_method'] > 0 ? "
				<tr>
					<td class='nowrap'>" . __('Curr/Avg:', 'monitor') . "</td>
					<td>" . __('%d ms', $host['cur_time'], 'monitor') . ' / ' .  __('%d ms', $host['avg_time'], 'monitor') . "</td>
				</tr>":"") . (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0) ? "
				<tr>
					<td class='nowrap'>" . __('Warn/Alert:', 'monitor') . "</td>
					<td>" . __('%0.2d ms', $host['monitor_warn'], 'monitor') . ' / ' . __('%0.2d ms', $host['monitor_alert'], 'monitor') . "</td>
				</tr>":"") . "
				<tr>
					<td>" . __('Last Fail:', 'monitor') . "</td>
					<td>" . ($host['status_fail_date'] == '0000-00-00 00:00:00' ? __('Never', 'monitor'):$host['status_fail_date']) . "</td>
				</tr>
				<tr>
					<td>" . __('Time In State:', 'monitor') . "</td>
					<td>" . get_timeinstate($host) . "</td>
				</tr>
				<tr>
					<td>" . __('Availability:', 'monitor') . "</td>
					<td>" . round($host['availability'],2) . " %</td>
				</tr>" . ($host['snmp_version'] > 0 && ($host['status'] == 3 || $host['status'] == 2) ? "
				<tr>
					<td>" . __('Agent Uptime:', 'monitor') . "</td>
					<td>" . ($host['status'] == 3 || $host['status'] == 5 ? monitor_print_host_time($host['snmp_sysUpTimeInstance']):'N/A') . "</td>
				</tr>
				<tr>
					<td class='nowrap'>" . __('Sys Description:', 'monitor') . "</td>
					<td>" . $host['snmp_sysDescr'] . "</td>
				</tr>
				<tr>
					<td>" . __('Location:', 'monitor') . "</td>
					<td>" . $host['snmp_sysLocation'] . "</td>
				</tr>
				<tr>
					<td>" . __('Contact:', 'monitor') . "</td>
					<td>" . $host['snmp_sysContact'] . "</td>
				</tr>":"") . "
				</table>\n";
		}
	}
}

function render_header_default($hosts) {
	return "<div class='center monitor'>\n";
}

function render_header_tiles($hosts) {
	return render_header_default($hosts);
}

function render_header_tilesadt($hosts) {
	return render_header_default($hosts);
}

function render_header_list($hosts) {
	$display_text = array(
		'hostname'    => array('display' => __('Hostname', 'monitor'),         'align' => 'left', 'tip' => __('Hostname of device', 'monitor')),
		'description' => array('display' => __('Description', 'monitor'),      'align' => 'left'),
		'criticality' => array('display' => __('Criticality', 'monitor'),      'align' => 'left'),
		'avail'       => array('display' => __('Up %', 'monitor'),             'align' => 'right'),
		'status'      => array('display' => __('Status', 'monitor'),           'align' => 'center'),
		'duration'    => array('display' => __('Length in status', 'monitor'), 'align' => 'center'),
		'average'     => array('display' => __('Averages', 'monitor'),         'align' => 'left'),
		'warnings'    => array('display' => __('Warning', 'monitor'),          'align' => 'left'),
		'lastfail'    => array('display' => __('Last Fail', 'monitor'),        'align' => 'left'),
		'admin'       => array('display' => __('Admin', 'monitor'),            'align' => 'left'),
		'notes'       => array('display' => __('Notes', 'monitor'),            'align' => 'left')
	);

	$output = html_start_box(__('Monitored Devices', 'monitor'), '100%', '', '3', 'center', '');
	$output .= html_nav_bar('monitor.php', 1, 1, sizeof($hosts), sizeof($hosts), 4, 'Monitor');
	$output .= html_header($display_text, '', '', false);

	return $output;
}

function render_suppressgroups_list($hosts) {
	return true;
}

function render_footer_default($hosts) {
	return '</div>';
}

function render_footer_tiles($hosts) {
	return render_footer_default($hosts);
}

function render_footer_tilesadt($hosts) {
	return render_footer_default($hosts);
}

function render_footer_list($hosts) {
	html_end_box(false);
}

function render_host_list($host) {
	global $criticalities;
	if (!is_device_allowed($host['id'])) {
		return ;
	}

	$dt = '';
	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = get_timeinstate($host);
	} else {
		if ($host['status_rec_date'] != '0000-00-00 00:00:00') {
			$dt = get_timeinstate($host);
		} else {
			$dt = __('Never', 'monitor');
		}
	}

	$host_admin = '';
	if ($host['status'] < 3 || $host['status'] == 5 ) {
		$host_admin = $host['monitor_text'];
	}

	$host_crit = '';
	if (isset($host['monitor_criticality']) && $host['monitor_criticality'] > 0) {
		$host_crit = $criticalities[$host['monitor_criticality']];
	}

	$host_address = '';
	if ($host['availability_method'] > 0) {
		$host_address = $host['hostname'];
		$host_avg     =	__('%d ms', $host['cur_time'], 'monitor') . ' / ' .  __('%d ms', $host['avg_time'], 'monitor');
	}

	$host_warn = '';
	if (isset($host['monitor_warn']) && ($host['monitor_warn'] > 0 || $host['monitor_alert'] > 0)) {
		$host_warn = __('%0.2d ms', $host['monitor_warn'], 'monitor') . ' / ' . __('%0.2d ms', $host['monitor_alert'], 'monitor');
	}
	$host_datefail = $host['status_fail_date'] == '0000-00-00 00:00:00' ? __('Never', 'monitor'):$host['status_fail_date'];

	$sdisplay = get_host_status_description($host['real_status']);

	$result = form_alternate_row('liine' . $host['id'], true);
	$result .= form_selectable_cell('<a class="linkEditMain" href="' . $host['anchor'] . '">' . $host['hostname'] .'</a>', $host['id']);
	$result .= form_selectable_cell($host['description'], $host['id']);
	$result .= form_selectable_cell($host_crit, $host['id']);
	$result .= form_selectable_cell(round($host['availability'],2) . " %", $host['id'], '', 'text-align:right;');
	$result .= form_selectable_cell($sdisplay, $host['id'], '', 'text-align:center;');
	$result .= form_selectable_cell($dt, $host['id'], '', 'text-align: center;');
	$result .= form_selectable_cell($host_avg, $host['id']);
	$result .= form_selectable_cell($host_warn, $host['id']);
	$result .= form_selectable_cell($host_datefail, $host['id']);
	$result .= form_selectable_cell($host_admin, $host['id']);
	$result .= form_selectable_cell($host['notes'], $host['id']);
	$result .= form_end_row();

	return $result;
}

function render_host_tiles($host) {
	$class  = get_status_icon($host['status']);
	$fclass = get_request_var('size');

	if (!is_device_allowed($host['id'])) {
		return;
	}

	$result = "<div class='monitor_device_frame $fclass ${fclass}_tiles'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $class " . $host['iclass'] . "'></i></a></div>";

	return $result;
}

function render_host_tilesadt($host) {
	$dt = '';

	if (!is_device_allowed($host['id'])) {
		return;
	}

	$class  = get_status_icon($host['status']);
	$fclass = get_request_var('size');

	if ($host['status'] < 2 || $host['status'] == 5) {
		$dt = get_timeinstate($host);

		$result = "<div class='monitor_device_frame $fclass ${fclass}_tilesadt'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $class " . $host['iclass'] . "'></i><br><span class='monitor_device deviceDown'>$dt</span></a></div>\n";

		return $result;
	} else {
		if ($host['status_rec_date'] != '0000-00-00 00:00:00') {
			$dt = get_timeinstate($host);
		} else {
			$dt = __('Never', 'monitor');
		}

		$result = "<div class='monitor_device_frame $fclass ${fclass}_tilesadt'><a class='textSubHeaderDark' href='" . $host['anchor'] . "'><i id='" . $host['id'] . "' class='fa $class " . $host['iclass'] . "'></i><br><span class='monitor_device deviceUp'>$dt</span></a></div>\n";

		return $result;
	}

}

function get_hosts_down_by_permission() {
	global $render_style;

	$result = array();

	if (get_request_var('crit') > 0) {
		$sql_add_where = ' AND monitor_criticality >= ' . get_request_var('crit');
	} else {
		$sql_add_where = '';
	}

	if (get_request_var('grouping') == 'tree') {
		if (get_request_var('tree') > 0) {
			$devices = db_fetch_cell_prepared('SELECT GROUP_CONCAT(DISTINCT host_id) AS hosts
				FROM graph_tree_items
				WHERE host_id > 0
				AND graph_tree_id = ?',
				array(get_request_var('tree')));

			$sql_add_where .= ' AND h.id IN(' . $devices . ')';
		}
	}

	$sql_where = "h.monitor='on' $sql_add_where AND h.disabled='' AND h.status < 2 AND (h.availability_method>0 OR h.snmp_version>0)";

	// do a quick loop through to pull the hosts that are down
	$hosts = get_allowed_devices($sql_where);
	if (sizeof($hosts)) {
		foreach($hosts as $host) {
			$result[] = $host['id'];
			sort($result);
		}
	}
	return $result;
}

function get_host_tree_array() {
	return $leafs;
}

function get_host_non_tree_array() {
	$leafs = array();

	$sql_where = '';
	$sql_join  = '';

	render_where_join($sql_where, $sql_join);

	//$sql_where .= " AND ((host.disabled = '' AND host.monitor = 'on' AND (host.availability_method>0 OR host.snmp_version>0)) OR (title != ''))";

	$heirarchy = db_fetch_assoc("SELECT DISTINCT
		h.*, gti.title, gti.host_id, gti.host_grouping_type, gti.graph_tree_id
		FROM host AS h
		LEFT JOIN graph_tree_items AS gti
		ON h.id=gti.host_id
		$sql_join
		$sql_where
		AND gti.graph_tree_id IS NULL
		ORDER BY h.description");

	if (sizeof($heirarchy) > 0) {
		$leafs = array();
		$branchleafs = 0;
		foreach ($heirarchy as $leaf) {
			$leafs[$branchleafs] = $leaf;
			$branchleafs++;
		}
	}
	return $leafs;
}

/* Supporting functions */
function get_status_color($status=3) {
	$color = '#183C8F';
	switch ($status) {
		case 0: //error
			$color = '#993333';
			break;
		case 1: //error
			$color = '#993333';
			break;
		case 2: //recovering
			$color = '#7293B9';
			break;
		case 3: //ok
			$color = '#669966';
			break;
		case 4: //threshold
			$color = '#c56500';
			break;
		case 5: //muted
			$color = '#996666';
			break;
		default: //unknown
			$color = '#999999';
			break;
		}
	return $color;
}

function leafs_status_min($leafs) {
	global $thold_hosts;

	$breached = false;
	$result   = 3;

	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($result > $status) {
			$result = $status;
		}

		if ($status == 3 && array_key_exists($row['id'], $thold_hosts)) {
			$breached = true;
		}
	}

	if ($result == 3 && $breached) {
		$result = 4;
	}

	return $result;
}

function leafs_percentup($leafs) {
	$result  = 0;
	$countup = 0;
	$count   = sizeof($leafs);

	foreach ($leafs as $row) {
		$status = intval($row['status']);
		if ($status >= 3) {
			$countup++;
		}
	}

	if ($countup>=$count){
		return 100;
	}

	$result = round($countup/$count*100,0);

	return $result;
}

