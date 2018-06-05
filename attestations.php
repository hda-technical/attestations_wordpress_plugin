<?php
/*
Plugin Name: HDA Attestations
Description: Attestation management for Historical Dance Association
Version:     0.1
Author:      Rostislav I. Kondratenko
License:     WTFPL
License URI: http://www.wtfpl.net/about/
Domain Path: /languages
Text Domain: attestations
*
* DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
*                    Version 2, December 2004
*
* Copyright (C) 2004 Sam Hocevar <sam@hocevar.net>
*
* Everyone is permitted to copy and distribute verbatim or modified
* copies of this license document, and changing it is allowed as long
* as the name is changed.
*
*            DO WHAT THE FUCK YOU WANT TO PUBLIC LICENSE
*   TERMS AND CONDITIONS FOR COPYING, DISTRIBUTION AND MODIFICATION
*
*  0. You just DO WHAT THE FUCK YOU WANT TO.
*/
defined('ABSPATH') or die('');
require_once (dirname(__FILE__) . '/functions.php');
register_activation_hook(__FILE__, 'atttestations_install');
register_deactivation_hook(__FILE__, 'atttestations_remove');

function atttestations_install() {
    $the_page_title = 'Аттестация';
    $the_page_name = 'attestations';
    delete_option('attestations_page_title');
    add_option('attestations_page_title', $the_page_name, '', 'yes');
    delete_option('attestations_page_name');
    add_option('attestations_page_name', $the_page_name, '', 'yes');
    delete_option('attestations_page_id');
    add_option('attestations_page_id', '0', '', 'yes');
    $the_page = get_page_by_title($the_page_name);
    if (!$the_page) {
        $_p = array();
        $_p['post_title'] = $the_page_title;
        $_p['post_name'] = $the_page_name;
        $_p['post_content'] = 'Главная страница аттестации';
        $_p['post_status'] = 'publish';
        $_p['post_type'] = 'page';
        $_p['comment_status'] = 'closed';
        $_p['ping_status'] = 'closed';
        $_p['post_category'] = array(1);
        $the_page_id = wp_insert_post($_p);
    } else {
        $the_page->post_status = 'publish';
        $the_page_id = wp_update_post($the_page);
    }
    delete_option('attestations_page_id');
    add_option('attestations_page_id', $the_page_id);
}

function atttestations_remove() {
    $the_page_id = get_option('attestations_page_id');
    if ($the_page_id) {
        wp_delete_post($the_page_id, true);
    }
    delete_option('attestations_page_title');
    delete_option('attestations_page_name');
    delete_option('attestations_page_id');
}
function attestations_css() {
    wp_register_style('attestations_css', plugins_url('styles/styles.css', __FILE__));
    wp_enqueue_style('attestations_css');
}
add_action('init', 'attestations_css');

function add_query_vars($aVars) {
    $aVars[] = "att_person_id";
    $aVars[] = "att_period_id";
    return $aVars;
}

add_filter('query_vars', 'add_query_vars');
function atttestations_parser($q) {
    $the_page_name = get_option('attestations_page_name');
    $the_page_id = get_option('attestations_page_id');
    if (!$q->did_permalink AND (isset($q->query_vars['page_id'])) AND (intval($q->query_vars['page_id']) == $the_page_id)) {
        $q->set('attestations_page_is_called', TRUE);
        return $q;
    } elseif (isset($q->query_vars['pagename']) AND (($q->query_vars['pagename'] == $the_page_name) OR (strpos($q->query_vars['pagename'], $the_page_name . '/') === 0))) {
        $q->set('attestations_page_is_called', TRUE);
        return $q;
    } else {
        $q->set('attestations_page_is_called', FALSE);
        return $q;
    }
}

function attestations_page_filter($posts) {
    global $wp_query;
    global $wpdb;
    if ($wp_query->get('attestations_page_is_called')) {
        $person_id = intval($wp_query->query_vars['att_person_id']);
        $period_id = intval($wp_query->query_vars['att_period_id']);
        $body = '';
        if ($person_id) {
            $results = $wpdb->get_results(" SELECT per.name, att.level, att.mark, att.date, att.id_moders, per.web_name
					FROM {$wpdb->prefix}attestation as att
					LEFT JOIN {$wpdb->prefix}period as per on att.id_period=per.id
					WHERE att.id_man='$person_id' AND att.valid
					ORDER BY per.sort, per.name, att.date, att.level", ARRAY_N);
            $attest = [];
            $usedmoders = array($person_id);
            foreach ($results as $row) {
                $attest[$row[0]]['level'] = $row[1];
                $attest[$row[0]]['mark'] = $row[2];
                $attest[$row[0]]['date'] = $row[3];
                $attest[$row[0]]['moders'] = $row[4];
                $attest[$row[0]]['per_web_name'] = $row[5];
                $attest[$row[0]]['period'] = $row[0];
                $attest[$row[0]]['history'].= substr($row[3], 5, 2) . "/" . substr($row[3], 0, 4) . " оценка <b>" . (to_roman(substr($row[1], 0, 1)) . (strlen($row[1]) > 1 ? substr($row[1], 1) : '')) . "</b>";
                if ((($row[1] == '2') || ($row[1] == '3')) && strlen($row[2])) $attest[$row[0]]['history'].= "(" . $row[2] . ")";
                $attest[$row[0]]['history'].= " <br>";
                $matches = [];
                if (preg_match_all("/([\d]+)/", $row[4], $matches)) {
                    foreach ($matches[1] as $id) {
                        if (($id > 0) && !in_array($id, $usedmoders)) $usedmoders[] = $id;
                    }
                }
            }
            $presults = $wpdb->get_results("SELECT p.id as pid , p.name as pname, c.id as cid, c.name as cname, c.web_name
				FROM {$wpdb->prefix}people as p
				LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id
				WHERE p.id in (" . implode(',', $usedmoders) . ")", ARRAY_N);
            $people = [];
            foreach ($presults as $row) {
                $people[$row[0]]['name'] = $row[1];
                $people[$row[0]]['city'] = $row[3];
                $people[$row[0]]['web_name'] = $row[4];
            }
            $title = $people[$person_id]['name'] . " (" . $people[$person_id]['city'] . ")";
            $body.= "<h3 class='att_h3'>Текущие уровни:</h3><br><span class=\"txtsm\">(на " . date('m') . "/" . date('Y') . ")</span>";
            foreach ($attest as $v1) {
                $body.= "<div class=\"attestations_period\" style=\"background-image: url('" . plugins_url("/img/periods/" . $v1['per_web_name'] . "-g.gif", __FILE__) . "');\"><p>";
                $examing_mods = "";
                if (preg_match_all("/([\d]+)/", $v1['moders'], $matches)) {
                    $examing_mods_array = $matches[1];
                    foreach ($examing_mods_array as $id) if ($id > 0) $examing_mods.= "/ " . $people[$id]['name'] . "<br>";
                }
                $l = current_level($v1['level'], $v1['date']);
                $body.= "<h3 class='att_h3'>" . $v1['period'] . "</h3><b>Текущий&nbsp;уровень:</b>&nbsp;" . $l['str'] . "<br>  <i> $examing_mods</i><span class=\"txtsm\"><br>- История аттестации -<br>" . $v1['history'] . "</span>";
                $body.= "</p></div>";
            }
        } else if ($period_id) {
            global $att_levels;
            global $att_rlevels;
            $r = $wpdb->get_row("SELECT name, web_name FROM {$wpdb->prefix}period WHERE id='$period_id'");
            $period = $r->name;
            $period_wname = $r->web_name;
            $results = $wpdb->get_results(
                    "SELECT per.name as pname, att.level, att.mark, att.date, att.id_moders, att.id_man, p.name as p2name, city.name, city.web_name
                        FROM {$wpdb->prefix}attestation as att 
                        LEFT JOIN {$wpdb->prefix}period as per ON att.id_period=per.id
                        LEFT JOIN {$wpdb->prefix}people as p ON att.id_man=p.id
                        LEFT JOIN {$wpdb->prefix}city as city ON p.city_id=city.id
                        WHERE per.id='$period_id' AND att.valid ORDER BY p.name, att.date DESC, att.dateinsert", ARRAY_N);
            $people = [];
            foreach ($results as $row) {
                $l = current_level($row[1], $row[3]);
                if ($l['num'] == '0')
                    continue;
                if (isset($people[$row[5]]) && $att_rlevels[$people[$row[5]]['level_num']] >= $att_rlevels[$l['num']])
                    continue;
                $people[$row[5]] = [
                    'name' => $row[6],
                    'city' => $row[7],
                    'level_num' => $l['num'],
                    'level_str' => $l['str'],
                    'date' => $row[3],
                    'id' => $row[5],
                    'history' => substr($row[3], 5, 2) . "/" . substr($row[3], 0, 4) . " оценка <b>" . to_roman(substr($row[1], 0, 1)) . (strlen($row[1]) > 1 ? substr($row[1], 1) : '') . "</b>" . (strlen($row[2]) ? "(" . $row[2] . ")" : '') . "<br>"
                    ];
            }
            $levels = [];
            foreach($people as $id => $p) {
                $levels[$p['level_num']][] = $p;
            }
            
            $title = "Аттестация по теме: " . $period;
            $body.= "<br><img src=".plugins_url("/img/periods/$period_wname.gif",__FILE__)." border=0><br><b>Текущие уровни:</b> <br><span class=txtsm>(на " . date('m') . "/" . date('Y') . ")</span>";
            $toplinks = [];
            foreach ($att_levels as $ln) {
                $pl = $levels[$ln];
                if (empty($pl))
                    continue;
                usort($pl, function($c1,$c2){return strcmp($c1['name'],$c2['name']);});
                $body.= "<a name='$ln'></a><h3 class=\"att_h3\">" . to_roman(substr($ln, 0, 1)) . (strlen($ln) > 1 ? substr($ln, 1) : '') . "</h3><ul>";
                $toplinks[]= "<a href='#" . $ln . "'>" . to_roman(substr($ln, 0, 1)) . (strlen($ln) > 1 ? substr($ln, 1) : '') . "</a> | ";
                foreach($pl as $p)
                    $body.= "<li><a href=\"" . add_query_arg('att_person_id', $p['id']) . "\">{$p['name']}</a>: {$p['level_str']} ({$p['city']})</li>";
                $body.= '</ul>';
            }
            $body = '<div class="attestations">|' . join('|',$toplinks) . '|' . $body . '</div>';
        } else {
            $title = 'Аттестованные в АИТ';
            $body.= '<h3>Принимают экзамены:</h3>';
            $teachers = get_all_teachers();
            $periods = get_attestation_periods();
            $t=[];
            foreach ($teachers as $period => $pt) {
                foreach($pt as $tid => $teacher)
                    $t[$period][$teacher[2]][$teacher[1]][] = [$tid,$teacher[0]];
            }
            foreach ($periods as $period) {
                if (empty($t[$period[2]]['4']) && empty($t[$period[2]]['3']))
                    continue;
                $body.= "<div class=\"attestations_period\" style=\"background-image: url('" . plugins_url("/img/periods/{$period[1]}-g.gif", __FILE__) . "');\">
	               <span class=txtsm><a href=\"" . add_query_arg('att_period_id', $period[2]) . "\"><h3 class='att_h3'>{$period[0]}</h3></a>";
                if (!empty($t[$period[2]]['4'])) {
                    $body .= "<p class=\"att_sub_level\">Все уровни:</p>";
                    foreach($t[$period[2]]['4'] as $city => $pteachers) {
                        $body.= "$city:<br>";
                        foreach($pteachers as $teacher) {
                            $body.= "<b><a href=\"" . add_query_arg('att_person_id', $teacher[0]) . "\">" . str_replace(" ", "&nbsp;", $teacher[1]) . "</a></b><br>";
                        }
                    }
                }
                if (!empty($t[$period[2]]['3'])) {
                    $body.="<p class=\"att_sub_level\">Только I уровень:</p>";
                    foreach($t[$period[2]]['3'] as $city => $pteachers) {
                        $body.= "$city:<br>";
                        foreach($pteachers as $teacher) {
                            $body.= "<b><a href=\"" . add_query_arg('att_person_id', $teacher[0]) . "\">" . str_replace(" ", "&nbsp;", $teacher[1]) . "</a></b><br>";
                        }
                    }
                }
                $body.= '</span></div>';
            }

            $body.= '<h3>Список аттестованых:</h3>';
            $results = $wpdb->get_results("SELECT p.id, p.name as pname, c.name as cname
				FROM {$wpdb->prefix}people as p
				LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id ORDER BY p.name", ARRAY_N);
            $letter = '';
            foreach ($results as $row) {
                if ($letter != mb_substr($row['1'], 0, 1)) {
                    if ($letter !== '') $body.= "</ul></div>";
                    $body.= "<div class='attestations_people'>";
                    $letter = mb_substr($row['1'], 0, 1);
                    $body.= "<h3 class='att_h3'><a name='$letter'></a>$letter</h3><ul>";
                    $toplinks.= "<a href='#$letter';>$letter</a>&nbsp;";
                }
                $body.= "<li><a href=\"" . add_query_arg('att_person_id', $row[0]) . "\">" . $row['1'] . "</a> <span class='txtsm'>(" . $row[2] . ")</span></li>";
            }
            $body.= "</span></div>";
            $body = '<div class="attestations">' . $toplinks . $body . '</div>';
        }
        $posts[0] = new stdClass();
        $posts[0]->post_title = $title;
        $posts[0]->post_content = $body;
        $posts[0]->post_type = 'page';
        $posts[0]->comment_status = 'closed';
        $posts[0]->post_name = $title;
    }
    return $posts;
}
add_filter('the_posts', 'attestations_page_filter');
add_filter('parse_query', 'atttestations_parser');


add_action( 'wp_ajax_att_people', 'attestations_get_people_callback' );

function attestations_get_people_callback() {
    global $wpdb;

    $teachers = get_all_teachers();
    $results = $wpdb->get_results("SELECT p.id, p.name as pname, c.name as cname
            FROM {$wpdb->prefix}people as p
            LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id ORDER BY p.name", ARRAY_N);
    echo json_encode(['teachers' => $teachers, 'people' => $results]);
    wp_die();
}

add_action( 'wp_ajax_att_person_add', 'attestations_new_person_callback' );

function attestations_get_person_levels_callback() {
    global $wpdb;
    $person_id = intval($_POST['person_id']);
    $period_id = intval($_POST['period_id']);
    $results = $wpdb->get_results("SELECT att.level, att.date, att.id_moders
                                FROM {$wpdb->prefix}attestation as att
                                WHERE att.id_man='$person_id' AND att.valid AND att.id_period = '$period_id'
                                ORDER BY att.date DESC, att.level", ARRAY_N);
    $l = '0';
    $s = '<span class=red_shadow>n&frasl;a</span>';
    foreach ($results as $row) {
        $l0 = current_level($row[0],$row[1]);
        if ($l0['num']>=$l) {
            $l = $l0['num'];
            $s = $l0['str'];
        }
    }
    echo json_encode(['n' => $l, 's'=> $s]);
    wp_die();
}
add_action( 'wp_ajax_att_get_levels', 'attestations_get_person_levels_callback' );

function attestations_new_person_callback() {
    global $wpdb;
    $name = mb_convert_case(sanitize_text_field($_POST['name']),MB_CASE_TITLE);
    $city = mb_convert_case(sanitize_text_field($_POST['city']),MB_CASE_TITLE);
    if (!$name) {
        echo json_encode(['error'=>'Не указано имя']);
        wp_die();
    }
    if (!mb_ereg_match('[^ ]+ [^ ]+',$name)) {
        echo json_encode(['error'=>'Имя не из двух слов']);
        wp_die();
    }
    if (!$city) {
        echo json_encode(['error'=>'Не указан город']);
        wp_die();
    }
    $city_id = $wpdb->get_var("SELECT c.id FROM {$wpdb->prefix}city as c WHERE c.name = '$city'");
    if ($city_id === null) {
        if (mb_detect_encoding($city, 'ASCII', true))
            $webname = mb_convert_case($city,MB_CASE_LOWER);
        else
            $webname = mb_convert_case(transliterate($city),MB_CASE_LOWER);
        $wpdb->insert("{$wpdb->prefix}city",['name'=>$city,'web_name' => $webname, 'enable' => 1]);
        $city_id = $wpdb->insert_id;
    }
    if ($wpdb->get_var("SELECT p.id FROM {$wpdb->prefix}people as p WHERE p.name = '$name' AND p.city_id='$city_id'") !== null) {
        echo json_encode(['error'=>'Такой человек уже есть']);
        wp_die();
    }
    $wpdb->insert("{$wpdb->prefix}people",['name'=>$name,'city_id' => $city_id, 'id_u' => 0]);
    $person_id = $wpdb->insert_id;
	echo json_encode([$person_id,$name,$city]);
	wp_die();
}

add_action('admin_menu', 'attestations_create_menu');
function attestations_create_menu() {
    $l = attestations_current_user_levels();
    if (array_search('3',$l) !== false || array_search('4',$l) !== false) {
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-css', plugins_url('styles/jquery-ui.css', __FILE__));
        add_menu_page('Аттестации', 'Аттестации', 'edit_posts', __FILE__, 'attestations_settings_page','dashicons-welcome-learn-more');
        add_submenu_page(__FILE__,'История оценок','История оценок','edit_posts','attestations_history_page','attestations_history_page');
    }
}

function attestations_history_page() {
    global $current_user;
    global $wpdb;
    global $att_rlevels;
    $l = attestations_current_user_levels();
    if (array_search('3',$l) === false && array_search('4',$l) === false)
        return false;
    $periods = get_attestation_periods();
    $cities = get_attestation_cities();
    $attestations = $wpdb->get_results(
        "SELECT att.id as aid,id_man,id_moders,id_period,level,
                mark,date,dateinsert,user_id,valid,
                p.name as pname,c.name as cname,c.id as cid, per.name as p2name
            FROM {$wpdb->prefix}attestation as att
            LEFT JOIN {$wpdb->prefix}people as p ON p.id=att.id_man
            LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id
            LEFT JOIN {$wpdb->prefix}period as per on per.id=att.id_period
            ORDER BY dateinsert DESC", ARRAY_N);
    $teacher_ids = [];
    $users = [];
    foreach ($attestations as $row) {
        $ids = array_flip(array_slice(explode(':',$row[2]),1,-1));
        $teacher_ids = $teacher_ids + $ids;
        if (!isset($users[$row[8]]))
            $users[$row[8]] = get_user_by('id', $row[8]);
    }
    unset($teacher_ids['']);
    $teacher_ids = array_keys($teacher_ids);
    $tresults = $wpdb->get_results("SELECT p.id as pid , p.name as pname, c.name as cname
                        FROM {$wpdb->prefix}people as p
                        LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id
                        WHERE p.id in (" . implode(',', $teacher_ids) . ") ORDER BY pname", ARRAY_N);
    $teachers = [];
    foreach ($tresults as $row) {
        $teachers[$row[0]]['name'] = $row[1];
        $teachers[$row[0]]['city'] = $row[2];
    }
    
    ?>
<div class="wrap">
<h2>История оценок</h2>
<h3>Фильтры</h3>
<p><label for="attestation_period">Период:</label><select id="attestation_period">
<option value="null">Все</option>
<?php
foreach ($periods as $period) {
    echo "<option value=\"{$period[2]}\">{$period[0]}</option>";
}
 ?></select><br>
<label for="attestation_level">Уровень:</label><select id="attestation_level">
    <option value="null">Все</option>
     <option value="1">I</option>
     <option value="2">I+</option>
     <option value="3">II</option>
     <option value="4">II+</option>
     <option value="5">III</option>
     <option value="6">IV</option>
</select><br>
<label for="attestation_city">Город:</label><select id="attestation_city">
<option value="null">Все</option>
<?php
foreach ($cities as $city) {
    echo "<option value=\"{$city[2]}\">{$city[0]}</option>";
}
 ?>
 </select><br>
<label for="attestation_teacher">Принимавший:</label><select id="attestation_teacher">
<option value="null">Все</option>
<?php
foreach ($teachers as $tid => $t) {
    echo "<option value=\"{$tid}\">{$t['name']} ({$t['city']})</option>";
}
 ?>
</select><br>
<label for="attestation_user">Вносивший:</label><select id="attestation_user">
<option value="null">Все</option>
<?php
foreach ($users as $uid => $u) {
    echo "<option value=\"{$uid}\">{$u->display_name}</option>";
}
?>
</select><br>
<label for="attestation_datef">Дата оценки от:</label><input type="text" class="attestation_date" id="attestation_datef" value=""/>
<label for="attestation_datet">до:</label><input type="text" class="attestation_date" id="attestation_datet" value=""/><br>
<label for="attestation_date2f">Дата внесения от:</label><input type="text" class="attestation_date" id="attestation_date2f" value=""/>
<label for="attestation_date2t">до:</label><input type="text" class="attestation_date" id="attestation_date2t" value=""/>
</p>
<ul id="attestation_list">
<?php
foreach ($attestations as $a) {
    $ids = array_slice(explode(':',$a[2]),1,-1);
    $ts = join(',',array_map(function($i) use ($teachers){return $teachers[$i]['name'];},$ids));
    $tcls = join(' ',array_map(function($i){return "attest_t$i";},$ids));
    $d2 = strtotime($a[7]);
    $d2s = strftime("Y-m-d",$d2);
    $s = '<span att_id="'.$a[0].'" class="dashicons dashicons-no '.(($d2 >= (time() - ATTESTATIONS_REMOVE_TIME_THRESHOLD))?'att_remove':'att_message').'"></span>';
    $s = $a[9]?$s:'<span class="dashicons dashicons-dismiss"></span>';
    echo "<li d1=\"{$a[6]}\" d2=\"{$d2s}\" class=\"attest_u{$a[8]} attest_pr{$a[3]} attest_c{$a[12]} attest_l{$att_rlevels[$a[4]]} $tcls\">{$s}<em>{$a[7]}/{$users[$a[8]]->display_name}</em> / {$a[6]} — <b>{$a[13]}</b> <span class=\"red_shadow\">".to_roman(substr($a[4], 0, 1)) . (strlen($a[4]) > 1 ? substr($a[4], 1) : '')."{$a[5]}</span> {$a[10]} ({$a[11]}): <span class=\"txtsm\">$ts".($a[9]?'':' / Запись удалена')."</span></li>";
}
?>
</ul>
</div>
<div id="att_message" class="ui-dialog ui-widget ui-widget-content ui-corner-all ui-front ui-draggable ui-resizable" tabindex="-1" role="dialog" aria-describedby="dialog" aria-labelledby="ui-id-1">
    <div class="ui-dialog-titlebar ui-widget-header ui-corner-all ui-helper-clearfix ui-draggable-handle">
        <span id="ui-id-1" class="ui-dialog-title">Удаление записи</span>
        <button type="button" class="ui-button ui-widget ui-state-default ui-corner-all ui-button-icon-only ui-dialog-titlebar-close" role="button" title="Закрыть">
            <span class="ui-button-icon-primary ui-icon ui-icon-closethick"></span>
            <span class="ui-button-text">Закрыть</span>
        </button>
    </div>
    <div id="dialog" class="ui-dialog-content ui-widget-content" >
        <label for="att_message_text">Эта запись была сделана слишком давно. Если вы считаете, что она ошибочкая, напишите сообщение администратору сайта, воспользовавшись формой ниже.</label><br>
        <textarea id="att_message_text"></textarea><br>
    </div>
    <div class="ui-dialog-buttonpane">
        <button class="button" id="att_send">Отправить</button>
    </div>
</div>
<script type="text/javascript">
jQuery(document).ready(function() {jQuery('.attestation_date').datepicker({dateFormat : 'yy-mm-dd',onSelect: function() {return jQuery(this).trigger('change');}});});
jQuery('#att_message .ui-dialog-titlebar-close').click(function() {jQuery('#att_message').hide();});
jQuery('.wrap select,.wrap input').change(function() {
    var classes = [];
    var c = jQuery('#attestation_period').val(); if (c!='null') classes.push('.attest_pr'+c);
    c = jQuery('#attestation_level').val(); if (c!='null') classes.push('.attest_l'+c);
    c = jQuery('#attestation_city').val(); if (c!='null') classes.push('.attest_c'+c);
    c = jQuery('#attestation_teacher').val(); if (c!='null') classes.push('.attest_t'+c);
    c = jQuery('#attestation_user').val(); if (c!='null') classes.push('.attest_u'+c);
    if (classes.length > 0) {
        jQuery('#attestation_list>li').hide();
        jQuery(classes.join('')).show();
    } else {
        jQuery('#attestation_list>li').show();
    }
    var d1f = jQuery('#attestation_datef').val();var d1t = jQuery('#attestation_datet').val();
    var d2f = jQuery('#attestation_date2f').val();var d2t = jQuery('#attestation_date2t').val();
    jQuery('#attestation_list>li:visible').filter(function() {
        var d1 = this.getAttribute("d1");var d2 = this.getAttribute("d2");
        return ((d1f && (d1 < d1f)) || (d1t && (d1 > d1t))) || ((d2f && (d2 < d2f)) || (d2t && (d2 > d2t)));
        }).hide();
});
jQuery('#attestation_list .att_remove').click(function(){
    var id = this.getAttribute('att_id');
    if (confirm('Вы действительно хотите удалить следующую отметку: ' + jQuery(this).parent().text() + ' ?')) {
        jQuery.post(ajaxurl, {'action': 'attestation_remove','id': id}, function(response) {
	    r = jQuery.parseJSON(response);
            if (r['error']) {
                jQuery(".error>p").last().text(r['error']);
                jQuery(".error").last().show();
            } else {
            }
        });
    }
    });
jQuery('#attestation_list .att_message').click(function(){
    var id = this.getAttribute('att_id');
    var pos = jQuery(this).offset();
    jQuery('#att_message').show();
    jQuery('#att_message').offset({top: pos.top + 10,left:pos.left + 10});
    jQuery('#att_message #att_message_text').text('Уважаемые администраторы сайта hda.org.ru,\n'+
                                                  'Я считаю, что запись об аттестации: ' + jQuery(this).parent().text() +
                                                  '\nБыла сделана ошибочно, и вот почему:\n\n\n<?php echo $current_user->display_name; ?>');
    });
jQuery('#attestation_list #att_send').click(function(){
    jQuery.post(ajaxurl, {'action': 'attestation_report','message': jQuery('#att_message #att_message_text').text()}, function(response) {});
});
</script>
<?php
}
add_action( 'wp_ajax_attestation_remove', 'attestations_attestation_remove_callback' );

function attestations_attestation_remove_callback() {
    global $current_user;
    global $wpdb;
    $l = attestations_current_user_levels();
    if (array_search('3',$l) === false && array_search('4',$l) === false)
        die();
    $email = get_option('admin_email');
    $id = intval ($_POST['id']);
    $date = $wpdb->get_var("SELECT dateinsert FROM {$wpdb->prefix}attestation WHERE id ='$id'");
    if ($date == null) {
        die("{'error':'Нет такой записи'}");
    }
    $date = strtotime($date);
    if ($date < (time() - ATTESTATIONS_REMOVE_TIME_THRESHOLD)) {
        die("{'error':'Запись была сделана слишком давно $date/".(time()- ATTESTATIONS_REMOVE_TIME_THRESHOLD)."'}");
    }
    if ($wpdb->update("{$wpdb->prefix}attestation",['valid'=>0],['id'=>$id, 'valid' => 1]) != 1) {
        die("{'error':'Не получилось удалить запись'}");
    }
    die("{'ok':null}");
}


function attestations_settings_page() {
    global $current_user;
    $l = attestations_current_user_levels();
    if (array_search('3',$l) === false && array_search('4',$l) === false)
        return false;
    $periods = get_attestation_periods();
?>
<div class="wrap">
<h2>База аттестации</h2>
<p><label for="attestation_period">Период:</label><select id="attestation_period"><?php
foreach ($periods as $period) {
    echo "<option value=\"{$period[2]}\">{$period[0]}</option>";
}
 ?></select>
 <label for="attestation_level">Уровень:</label><select id="attestation_level">
     <option value="1">I</option>
     <option value="2">I+</option>
     <option value="3">II</option>
     <option value="4">II+</option>
     <option value="5">III</option>
     <option value="6">IV</option>
 </select>
 <label for="attestation_date">Дата:</label><input type="text" class="attestation_date" id="attestation_date" value="<?php echo date("Y-m-d");?>"/>
 </p>
<div>
    <h3>Сдавали:</h3>
    <span class="dashicons dashicons-filter"></span><input id="people_search" class="people_search" type="edit"></input><br>
    <ul id="people_list" class="people_list"></ul>
    <ul id="people_list_b" class="people_list"></ul><br>
    <div id="new_person" >
        <div style="display:none" class="error"><p></p></div>
        <h4>Добавить человека:</h4>
        <label for="new_person_name">Фамилия и имя:</label><input id="new_person_name" type="edit" size="40"></input><br>
        <label for="new_person_city">Город:</label><input id="new_person_city" type="edit"></input>
        <input type="button" class="button" id="add_person" value="Добавить"></input>
    </div>
</div>

<div>
    <h3>Принимали:</h3>
    <span id="teachers"></span>

</div>

<form action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" method="POST">
    <input type="hidden" name="action" value="attestations_form">
    <input type="hidden" name="period" value="">
    <input type="hidden" name="level" value="">
    <input type="hidden" name="date" value="">
    <input type="hidden" name="teachers" value="">
    <input type="hidden" name="people" value="">
<?php
wp_nonce_field( 'attestations_new');
submit_button(); ?>

</form>
<script type="text/javascript" >
    var levels = <?php echo json_encode($l);?>;
    var person_id = <?php echo intval(get_usermeta($current_user->ID,'attestations_person')); ?>;
	var people;
    var have_level_4 = false;

    jQuery("#submit").click(function(){
        jQuery("input[name='period']").val(jQuery("#attestation_period").val());
        jQuery("input[name='level']").val(jQuery("#attestation_level").val());
        jQuery("input[name='level']").val(jQuery("#attestation_level").val());
        jQuery("input[name='date']").val(jQuery("#attestation_date").val());
        jQuery("input[name='teachers']").val(jQuery("#teachers>p>input:visible:checked").map(function() { return this.value; }).get().join(':'));
        jQuery("input[name='people']").val(jQuery("#people_list_b>li>span").map(function() { return this.getAttribute('person_id'); }).get().join(':'));
        });
    jQuery('#add_person').click(function($) {

		var data = {
                    'action': 'att_person_add',
                    'name': jQuery("#new_person_name").val(),
                    'city': jQuery("#new_person_city").val()
		};
		jQuery.post(ajaxurl, data, function(response) {
			r = jQuery.parseJSON(response);
            if (r['error']) {
                jQuery(".error>p").last().text(r['error']);
                jQuery(".error").last().show();
            } else {
                jQuery("#new_person_name").val('');
                jQuery("#new_person_city").val('');
                var l = jQuery('#people_list_b');
                l.append('<li id="attestation_person_'+ r[0] +'"><span class="person_add dashicons dashicons-no" person_id="'+r[0]+'"></span>'+r[1]+' ('+r[2]+')</li>')
                    .click(function(){remove_person(r[0]);});
            }
        });
    });
	jQuery(document).ready(function() {

		var data = {
			'action': 'att_people',
		};
		jQuery.post(ajaxurl, data, function(response) {
			people = jQuery.parseJSON(response);
            var l = jQuery('#people_list');
            l.empty();
            for (var p of people['people']) {
                l.append('<li id="attestation_person_'+ p[0] +'"><span class="person_add dashicons dashicons-plus" person_id="'+p[0]+'"></span>'+p[1]+' ('+p[2]+') <span class="att_level"></span></li>');
            }
            l = jQuery('#teachers');
            for (var per in people['teachers']) {
                if ((person_id in people['teachers'][per]) && people['teachers'][per][person_id][2] == 4)
                    have_level_4 = true;
                for (var i in people['teachers'][per]) {
                    var t = people['teachers'][per][i];
                    if (jQuery('#attestation_teacher_'+ i).size())
                        jQuery('#attestation_teacher_'+ i).addClass('attestation_teacher_'+ per+'_'+t[2]);
                    else
                        l.append('<p class="attestation_teacher_'+ per +'_'+t[2]+'" id="attestation_teacher_'+ i +'"><input type=checkbox value="'+i+'"></span>'+t[0]+' ('+t[1]+')</p>');
                    if (t[2]==4) {
                        jQuery('#attestation_teacher_'+ i).addClass('attestation_teacher_l4');
                    }
                }
            }
            filter_periods();
            filter_teacher();
            jQuery('#people_list>li>.person_add').click(function(){move_person(this.getAttribute('person_id'))});
		});
	});
    jQuery(document).ready(function() {
        jQuery('.attestation_date').datepicker({
            dateFormat : 'yy-mm-dd'
        });
    });
    jQuery('#people_search').bind('input',filter_people);
    function insert_sorted(list,item) {
        var children = list.children();
        var a = 0,b = children.length - 1;
        var t = item.text();
        if (t.localeCompare(children.eq(a).text()) < 0) {
            item.prependTo(list);
            return;
        }
        if (t.localeCompare(children.eq(b).text()) > 0) {
            item.appendTo(list);
            return;
        }
        while (b - a > 1) {
            var c = Math.floor((a + b)/2);
            if (t.localeCompare(children.eq(c).text()) < 0) {
                b = c;
            } else {
                a = c
            }
        }
        children.eq(a).after(item);
    }
    function move_person(id) {
        var e = jQuery("#attestation_person_"+id);
        if (e.closest('ul').attr('id') == 'people_list') {
            insert_sorted(jQuery("#people_list_b"),e);
            //e.appendTo("#people_list_b");
            jQuery("#attestation_person_"+id+">.person_add").addClass("dashicons-no").removeClass("dashicons-plus");
            discover_level(id);
        } else {
            e.children('.att_level').empty();
            insert_sorted(jQuery("#people_list"),e);
            //e.appendTo("#people_list");
            jQuery("#attestation_person_"+id+">.person_add").removeClass("dashicons-no").addClass("dashicons-plus");
            filter_people();
        }
    }
    function discover_level(person_id) {
        jQuery.post(ajaxurl, {'action':'att_get_levels','person_id':person_id,'period_id':jQuery('#attestation_period').val()},
            function(response) {
                r = jQuery.parseJSON(response);
                jQuery("#attestation_person_"+person_id+">.att_level").html(r['s']);
                jQuery("#attestation_person_"+person_id+">.att_level>br").remove();
        });
    }
    function filter_people() {
        var pattern = jQuery('#people_search').val().toLowerCase();
        jQuery.each(jQuery('#people_list>li'),function(){
           var v = jQuery(this).text().toLowerCase();
           jQuery(this).toggle(v.search(pattern) >= 0);
        });
    }
    jQuery('#attestation_period').change(function(){
        for(let p of jQuery("#people_list_b>li>.person_add").toArray())
            discover_level(p.getAttribute('person_id'));
        });
    jQuery('#attestation_period,#attestation_level').change(filter_teacher);
    function filter_teacher() {
        var per = jQuery('#attestation_period').val();
        jQuery("#teachers>p").hide();
        if (jQuery('#attestation_level').val() == 1)
            jQuery('.attestation_teacher_'+per+'_3,.attestation_teacher_'+per+'_4').show();
        else if (jQuery('#attestation_level').val() == 6) {
            if (people['teachers'][per])
                jQuery('.attestation_teacher_'+per + '_4').show();
            else {
                jQuery('.attestation_teacher_'+per + '_3').show();
                jQuery('.attestation_teacher_l4').show();
            }
        } else {
            jQuery('.attestation_teacher_'+per + '_4').show();
        }
    }
    function valid_teacher(per) {
        if (have_level_4 && !people['teachers'][per])
            return true;
        return person_id in people['teachers'][per];
    }
    function filter_periods() {
        var sel= false;
        for (var per in people['teachers']) {
            jQuery("#attestation_period>option[value='"+per+"']").prop("disabled",!valid_teacher(per));
            if (!sel && person_id in people['teachers'][per]) {
                jQuery("#attestation_period>option[value='"+per+"']").prop("selected",true);
                sel = true;
            }
        }
        filter_levels();
    }
    jQuery('#attestation_period').change(filter_levels);
    function filter_levels() {
        var per = jQuery('#attestation_period').val();
        if (have_level_4 && !people['teachers'][per]) {
            jQuery("#attestation_level>option[value!='6']").prop("disabled",true);
            jQuery("#attestation_level>option[value='6']").prop("disabled",false).prop("selected",true);

        } else {
            if (!(person_id in people['teachers'][per]))
                jQuery("#attestation_level>option").prop("disabled",true);
            else {
                if (people['teachers'][per][person_id][2] == 4)
                    jQuery("#attestation_level>option").prop("disabled",false);
                else {
                    jQuery("#attestation_level>option[value!='1']").prop("disabled",true);
                    jQuery("#attestation_level>option[value='1']").prop("disabled",false).prop("selected",true);
                }
            }
        }
        filter_teacher();
    }
	</script></div>

<?php
}

function attestations_submitted() {
    global $wpdb, $att_levels;
    check_admin_referer('attestations_new');
    $l = attestations_current_user_levels();
    $_all_teachers = get_all_teachers();
    $all_teachers = [];
    foreach ($_all_teachers as $p => $tt) {
        $all_teachers[$p] = [];
        foreach ($tt as $i => $t)
            $all_teachers[$p][$i] = $t[2];
    }

    $period = intval ($_POST['period']);
    $level = intval ($_POST['level']);
    $date = sanitize_text_field($_POST['date']);
    $people = array_map(intval,explode(':',$_POST['people']));
    $teachers = array_map(intval,explode(':',$_POST['teachers']));
    if ($level < 1 || $level > 6) {
        set_transient(get_current_user_id().'attestation_errors', "Не бывает такого уровня" );
        die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
    }
    if ($wpdb->get_var("SELECT per.id FROM {$wpdb->prefix}period as per WHERE per.id = '$period'") === null) {
        set_transient(get_current_user_id().'attestation_errors', "Не бывает такого периода" );
        die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
    }
    if (empty($people) || (array_search(0,$people)!==false)){
        set_transient(get_current_user_id().'attestation_errors', "Не выбраны экзаменованные" );
        die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
    }
    if (empty($teachers) || (array_search(0,$teachers)!==false)) {
        set_transient(get_current_user_id().'attestation_errors', "Не выбраны экзаменаторы" );
        die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
    }
    $d = DateTime::createFromFormat("Y-m-d", $date);
    if (!$d || $d->format("Y-m-d") != $date ) {
        set_transient(get_current_user_id().'attestation_errors', "Неправильно указана дата" );
        die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
    }
    set_transient(get_current_user_id().'attestation_errors', "" );
    $allowed = false;
    if (empty($all_teachers[$period]) && $level == 6 ) {
        if ($l[$period] == '3' || (array_search('4',$l)!==false)) {
            $allowed = true;
            foreach($teachers as $t) {
                $_a = false;
                if ($all_teachers[$period][$t] == '3')
                    $_a = true;
                else {
                    foreach($all_teachers as $tt) {
                        if ($tt[$t] == '4') {
                            $_a = true;
                            break;
                        }
                    }
                }
                if (!$_a) {
                    set_transient(get_current_user_id().'attestation_errors', "Эти люди не могут принимать этот уровень" );
                    die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
                }
            }
        }
    }
    if ($level == 1) {
        if ($l[$period] == '3' || $l[$period] == '4') {
            $allowed = true;
            foreach($teachers as $t) {
                if ($all_teachers[$period][$t] != '3' && $all_teachers[$period][$t] != '4') {
                    set_transient(get_current_user_id().'attestation_errors', "Эти люди не могут принимать этот уровень" );
                    die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
                }
            }
        }
    }
    if ($level != 1 && !$allowed) {
        if ($l[$period] == '4') {
            $allowed = true;
            foreach($teachers as $t) {
                if ($all_teachers[$period][$t] != '4') {
                    set_transient(get_current_user_id().'attestation_errors', "Эти люди не могут принимать этот уровень" );
                    die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
                }
            }
        }
    }
    if (!$allowed) {
        set_transient(get_current_user_id().'attestation_errors', "Вы не можете принимать этот уровень" );
        die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
    }
    $uid = get_current_user_id();
    $common_fields = "':".join(':',$teachers).":','$period','{$att_levels[$level]}',NULL,'$date',now(),$uid,'1'";
    $values = [];
    foreach($people as $p) {
        $values[] = "('$p',$common_fields)";
    }
    $query = "INSERT INTO {$wpdb->prefix}attestation (id_man,id_moders,id_period,level,mark,date,dateinsert,user_id,valid) VALUES ".implode(',',$values);
    if ($wpdb->query($query) === false)
        set_transient(get_current_user_id().'attestation_errors', "Ошибка добавления записей в базу" );
    else
        set_transient(get_current_user_id().'attestation_ok', "Записи добавлены в базу" );
	die( wp_redirect( admin_url( 'admin.php?page=attestations%2Fattestations.php' ) ) );
}

add_action( 'admin_post_attestations_form', 'attestations_submitted' );

function show_admin_notice() {
    if((($out = get_transient( get_current_user_id().'attestation_errors' ) ))) {
        delete_transient( get_current_user_id().'attestation_errors' );
        echo "<div class=\"error\"><p>$out</p></div>";
    }
    if((($out = get_transient( get_current_user_id().'attestation_ok' ) ))) {
        delete_transient( get_current_user_id().'attestation_ok' );
        echo "<div class=\"notice notice-success is-dismissible\"><p>$out</p></div>";
    }
}
add_action('admin_notices', "show_admin_notice");

function attestations_user_profile_fields($user) {
    global $wpdb;
	$results = $wpdb->get_results("SELECT p.id, p.name as pname, c.name as cname
		FROM {$wpdb->prefix}people as p
		LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id ORDER BY p.name", ARRAY_N);

    $person_id = get_the_author_meta( 'attestations_person', $user->ID );
?>
<table class="form-table">
<tr>
	<th>
		<label for="attestations_person">Имя в базе атестации</label>
	</th>
	<td>
        <select <?php if(!current_user_can( 'manage_options' )) echo 'disabled'; ?> name="attestations_person">
            <option value="null">Нет</option>
            <?php foreach ($results as $row) {
                echo "<option value=\"{$row[0]}\"".($person_id==$row[0]?'selected="selected"':'').">{$row[1]} ({$row[2]})</option>";
            }?>
        </select>
	</td>
</tr>
</table>
<?php
}
add_action('show_user_profile', 'attestations_user_profile_fields');
add_action('edit_user_profile', 'attestations_user_profile_fields');


add_action( 'personal_options_update', 'attestations_profile_fields' );
add_action( 'edit_user_profile_update', 'attestations_profile_fields' );

function attestations_profile_fields( $user_id ) {
	if ( !current_user_can( 'manage_options' ) )
		return false;
    if ($_POST['attestations_person'] === 'null') {
        update_usermeta($user_id, 'attestations_person', 'null');
        return true;
    }
    $person_id = intval($_POST['attestations_person']);
    if ($person_id == 0)
        return false;
    $args = array('meta_key' => 'attestations_person', 'meta_value' => $person_id);
    $search_users = get_users($args);
    foreach ($search_users as $u)
        update_usermeta($u->ID, 'attestations_person', 'null');
	update_usermeta($user_id, 'attestations_person', $person_id);
}