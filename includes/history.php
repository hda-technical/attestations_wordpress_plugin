<?php
defined('ABSPATH') or die('');

function attestations_attestation_remove_callback() {
    global $current_user;
    global $wpdb;
    $l = attestations_current_user_levels();
    if (array_search('3', $l) === false && array_search('4', $l) === false) die();
    $id = intval($_POST['id']);
    $date = $wpdb->get_var("SELECT dateinsert FROM {$wpdb->prefix}attestation WHERE id ='$id'");
    if ($date == null) {
        die('{"error":"Нет такой записи"}');
    }
    $date = strtotime($date);
    if ($date < (time() - ATTESTATIONS_REMOVE_TIME_THRESHOLD)) {
        die('{"error":"Запись была сделана слишком давно ' . $date . '/' . (time() - ATTESTATIONS_REMOVE_TIME_THRESHOLD) . '"}');
    }
    if ($wpdb->update("{$wpdb->prefix}attestation", ['valid' => 0], ['id' => $id, 'valid' => 1]) != 1) {
        die('{"error":"Не получилось удалить запись"}');
    }
    wp_mail(get_option('admin_email'), 'Удалена запись об аттестации', "Пользователь {$current_user->user_login} удалил запись об аттестации ID: $id от: $date.");
    die('{"ok":null}');
}

function attestations_attestation_report_callback() {
    global $current_user;
    global $wpdb;
    $l = attestations_current_user_levels();
    if (array_search('3', $l) === false && array_search('4', $l) === false) die();
    $message = sanitize_text_field($_POST['message']);
    wp_mail(get_option('admin_email'), 'Просьба об удалении записи об аттестации', "Пользователь {$current_user->user_login} пишет: $message");
    die('{"ok":null}');
}



function attestations_history_page() {
    global $current_user;
    global $wpdb;
    global $att_rlevels;
    $l = attestations_current_user_levels();
    if (array_search('3', $l) === false && array_search('4', $l) === false) return false;
    $periods = get_attestation_periods();
    $cities = get_attestation_cities();
    $attestations = $wpdb->get_results("SELECT att.id as aid,id_man,id_moders,id_period,level,
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
        $ids = array_flip(array_slice(explode(':', $row[2]), 1, -1));
        $teacher_ids = $teacher_ids + $ids;
        if (!isset($users[$row[8]])) $users[$row[8]] = get_user_by('id', $row[8]);
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
        $ids = array_slice(explode(':', $a[2]), 1, -1);
        $ts = join(',', array_map(function ($i) use ($teachers) {
            return $teachers[$i]['name'];
        }, $ids));
        $tcls = join(' ', array_map(function ($i) {
            return "attest_t$i";
        }, $ids));
        $d2 = strtotime($a[7]);
        $d2s = strftime("Y-m-d", $d2);
        $s = '<span att_id="' . $a[0] . '" class="dashicons dashicons-no ' . (($d2 >= (time() - ATTESTATIONS_REMOVE_TIME_THRESHOLD)) ? 'att_remove' : 'att_message') . '"></span>';
        $s = $a[9] ? $s : '<span class="dashicons dashicons-dismiss"></span>';
        echo "<li d1=\"{$a[6]}\" d2=\"{$d2s}\" class=\"attest_u{$a[8]} attest_pr{$a[3]} attest_c{$a[12]} attest_l{$att_rlevels[$a[4]]} $tcls\">{$s}<em>{$a[7]}/{$users[$a[8]]->display_name}</em> / {$a[6]} — <b>{$a[13]}</b> <span class=\"red_shadow\">" . to_roman(substr($a[4], 0, 1)) . (strlen($a[4]) > 1 ? substr($a[4], 1) : '') . "{$a[5]}</span> {$a[10]} ({$a[11]}): <span class=\"txtsm\">$ts" . ($a[9] ? '' : ' / Запись удалена') . "</span></li>";
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
                alert(r['error']);
            } else {
                alert('Запись удалена');
                location.reload();
            }
        });
    }
    });
jQuery('#attestation_list .att_message').click(function(){
    var id = this.getAttribute('att_id');
    var pos = jQuery(this).offset();
    jQuery('#att_message').show();
    jQuery('#att_message').offset({top: pos.top + 10,left:pos.left + 10});
    jQuery('#att_message textarea').val('Уважаемые администраторы сайта hda.org.ru,\r\n'+
                                                  'Я считаю, что запись об аттестации: ' + jQuery(this).parent().text() +
                                                  '\r\nБыла сделана ошибочно, и вот почему:\r\n\r\n\r\n<?php echo $current_user->display_name; ?>');
    });
jQuery('#att_message #att_send').click(function(){
    jQuery('#att_message').hide();
    jQuery.post(ajaxurl, {'action': 'attestation_report','message': jQuery('#att_message textarea').val()}, function(response) {
            r = jQuery.parseJSON(response);
            if (r['error']) {
                jQuery(".error>p").last().text(r['error']);
                jQuery(".error").last().show();
            } else {
                alert('Сообщение администратору отправлено');
            }
    });
});
</script>
<?php
}
