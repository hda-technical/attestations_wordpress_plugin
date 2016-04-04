<?php
defined('ABSPATH') or die('');

/******************************************************************************
 *                                   AJAX
 ******************************************************************************/
function attestations_get_people_callback() {
    global $wpdb;
    $teachers = get_all_teachers();
    $results = $wpdb->get_results("SELECT p.id, p.name as pname, c.name as cname
            FROM {$wpdb->prefix}people as p
            LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id ORDER BY p.name", ARRAY_N);
    echo json_encode(['teachers' => $teachers, 'people' => $results]);
    wp_die();
}

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
        $l0 = current_level($row[0], $row[1]);
        if ($l0['num'] >= $l) {
            $l = $l0['num'];
            $s = $l0['str'];
        }
    }
    echo json_encode(['n' => $l, 's' => $s]);
    wp_die();
}

function attestations_new_person_callback() {
    global $wpdb;
    $name = mb_convert_case(sanitize_text_field($_POST['name']), MB_CASE_TITLE);
    $city = mb_convert_case(sanitize_text_field($_POST['city']), MB_CASE_TITLE);
    if (!$name) {
        echo json_encode(['error' => 'Не указано имя']);
        wp_die();
    }
    if (!mb_ereg_match('[^ ]+ [^ ]+', $name)) {
        echo json_encode(['error' => 'Имя не из двух слов']);
        wp_die();
    }
    if (!$city) {
        echo json_encode(['error' => 'Не указан город']);
        wp_die();
    }
    $city_id = $wpdb->get_var("SELECT c.id FROM {$wpdb->prefix}city as c WHERE c.name = '$city'");
    if ($city_id === null) {
        if (mb_detect_encoding($city, 'ASCII', true)) $webname = mb_convert_case($city, MB_CASE_LOWER);
        else $webname = mb_convert_case(transliterate($city), MB_CASE_LOWER);
        $wpdb->insert("{$wpdb->prefix}city", ['name' => $city, 'web_name' => $webname, 'enable' => 1]);
        $city_id = $wpdb->insert_id;
    }
    if ($wpdb->get_var("SELECT p.id FROM {$wpdb->prefix}people as p WHERE p.name = '$name' AND p.city_id='$city_id'") !== null) {
        echo json_encode(['error' => 'Такой человек уже есть']);
        wp_die();
    }
    $wpdb->insert("{$wpdb->prefix}people", ['name' => $name, 'city_id' => $city_id, 'id_u' => 0]);
    $person_id = $wpdb->insert_id;
    echo json_encode([$person_id, $name, $city]);
    wp_die();
}
/******************************************************************************
 *                                   Form
 ******************************************************************************/
function attestations_settings_page() {
    global $current_user;
    $l = attestations_current_user_levels();
    if (array_search('3', $l) === false && array_search('4', $l) === false) return false;
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
 <label for="attestation_date">Дата:</label><input type="text" class="attestation_date" id="attestation_date" value="<?php echo date("Y-m-d"); ?>"/>
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

<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
    <input type="hidden" name="action" value="attestations_form">
    <input type="hidden" name="period" value="">
    <input type="hidden" name="level" value="">
    <input type="hidden" name="date" value="">
    <input type="hidden" name="teachers" value="">
    <input type="hidden" name="people" value="">
<?php
    wp_nonce_field('attestations_new');
    submit_button(); ?>

</form>
<script type="text/javascript" >
    var levels = <?php echo json_encode($l); ?>;
    var person_id = <?php echo intval(get_usermeta($current_user->ID, 'attestations_person')); ?>;
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

/******************************************************************************
 *                                   POST
 ******************************************************************************/
function attestations_submitted() {
    global $wpdb, $att_levels;
    check_admin_referer('attestations_new');
    $l = attestations_current_user_levels();
    $_all_teachers = get_all_teachers();
    $all_teachers = [];
    foreach ($_all_teachers as $p => $tt) {
        $all_teachers[$p] = [];
        foreach ($tt as $i => $t) $all_teachers[$p][$i] = $t[2];
    }
    $period = intval($_POST['period']);
    $level = intval($_POST['level']);
    $date = sanitize_text_field($_POST['date']);
    $people = array_map(intval, explode(':', $_POST['people']));
    $teachers = array_map(intval, explode(':', $_POST['teachers']));
    if ($level < 1 || $level > 6) {
        set_transient(get_current_user_id() . 'attestation_errors', "Не бывает такого уровня");
        die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
    }
    if ($wpdb->get_var("SELECT per.id FROM {$wpdb->prefix}period as per WHERE per.id = '$period'") === null) {
        set_transient(get_current_user_id() . 'attestation_errors', "Не бывает такого периода");
        die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
    }
    if (empty($people) || (array_search(0, $people) !== false)) {
        set_transient(get_current_user_id() . 'attestation_errors', "Не выбраны экзаменованные");
        die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
    }
    if (empty($teachers) || (array_search(0, $teachers) !== false)) {
        set_transient(get_current_user_id() . 'attestation_errors', "Не выбраны экзаменаторы");
        die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
    }
    $d = DateTime::createFromFormat("Y-m-d", $date);
    if (!$d || $d->format("Y-m-d") != $date) {
        set_transient(get_current_user_id() . 'attestation_errors', "Неправильно указана дата");
        die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
    }
    set_transient(get_current_user_id() . 'attestation_errors', "");
    $allowed = false;
    if (empty($all_teachers[$period]) && $level == 6) {
        if ($l[$period] == '3' || (array_search('4', $l) !== false)) {
            $allowed = true;
            foreach ($teachers as $t) {
                $_a = false;
                if ($all_teachers[$period][$t] == '3') $_a = true;
                else {
                    foreach ($all_teachers as $tt) {
                        if ($tt[$t] == '4') {
                            $_a = true;
                            break;
                        }
                    }
                }
                if (!$_a) {
                    set_transient(get_current_user_id() . 'attestation_errors', "Эти люди не могут принимать этот уровень");
                    die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
                }
            }
        }
    }
    if ($level == 1) {
        if ($l[$period] == '3' || $l[$period] == '4') {
            $allowed = true;
            foreach ($teachers as $t) {
                if ($all_teachers[$period][$t] != '3' && $all_teachers[$period][$t] != '4') {
                    set_transient(get_current_user_id() . 'attestation_errors', "Эти люди не могут принимать этот уровень");
                    die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
                }
            }
        }
    }
    if ($level != 1 && !$allowed) {
        if ($l[$period] == '4') {
            $allowed = true;
            foreach ($teachers as $t) {
                if ($all_teachers[$period][$t] != '4') {
                    set_transient(get_current_user_id() . 'attestation_errors', "Эти люди не могут принимать этот уровень");
                    die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
                }
            }
        }
    }
    if (!$allowed) {
        set_transient(get_current_user_id() . 'attestation_errors', "Вы не можете принимать этот уровень");
        die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
    }
    $uid = get_current_user_id();
    $common_fields = "':" . join(':', $teachers) . ":','$period','{$att_levels[$level]}',NULL,'$date',now(),$uid,'1'";
    $values = [];
    foreach ($people as $p) {
        $values[] = "('$p',$common_fields)";
    }
    $query = "INSERT INTO {$wpdb->prefix}attestation (id_man,id_moders,id_period,level,mark,date,dateinsert,user_id,valid) VALUES " . implode(',', $values);
    if ($wpdb->query($query) === false) set_transient(get_current_user_id() . 'attestation_errors', "Ошибка добавления записей в базу");
    else set_transient(get_current_user_id() . 'attestation_ok', "Записи добавлены в базу");
    die(wp_redirect(admin_url('admin.php?page=attestations%2Fattestations.php')));
}

/******************************************************************************
 *                                   Notice
 ******************************************************************************/

function show_admin_notice() {
    if ((($out = get_transient(get_current_user_id() . 'attestation_errors')))) {
        delete_transient(get_current_user_id() . 'attestation_errors');
        echo "<div class=\"error\"><p>$out</p></div>";
    }
    if ((($out = get_transient(get_current_user_id() . 'attestation_ok')))) {
        delete_transient(get_current_user_id() . 'attestation_ok');
        echo "<div class=\"notice notice-success is-dismissible\"><p>$out</p></div>";
    }
}
