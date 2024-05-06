<?php
defined('ABSPATH') or die('');
$month['01']='Январь';
$month['02']='Февраль';
$month['03']='Март';
$month['04']='Апрель';
$month['05']='Май';
$month['06']='Июнь';
$month['07']='Июль';
$month['08']='Август';
$month['09']='Сентябрь';
$month['10']='Октябрь';
$month['11']='Ноябрь';
$month['12']='Декабрь';

$att_levels = array(
		1 => '1',
		2 => '1+',
		3 => '2',
		4 => '2+',
		5 => '3',
		6 => '4'
	);
$att_rlevels = array(
		'1' => 1,
		'1+' => 2,
		'2' => 3,
		'2+' => 4,
		'3' => 5,
		'4' => 6
	);

$attestation_base_month = 36;

define('ATTESTATIONS_REMOVE_TIME_THRESHOLD', 86400*1);

function get_revolution_date() {
	return "2015-03-01";
}

function transliterate($textcyr = null, $textlat = null) {
    $cyr = array(
    'ж',  'ч',  'щ',   'ш',  'ю',  'а', 'б', 'в', 'г', 'д', 'е', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ъ', 'ь', 'я',
    'Ж',  'Ч',  'Щ',   'Ш',  'Ю',  'А', 'Б', 'В', 'Г', 'Д', 'Е', 'З', 'И', 'Й', 'К', 'Л', 'М', 'Н', 'О', 'П', 'Р', 'С', 'Т', 'У', 'Ф', 'Х', 'Ц', 'Ъ', 'Ь', 'Я');
    $lat = array(
    'zh', 'ch', 'sht', 'sh', 'yu', 'a', 'b', 'v', 'g', 'd', 'e', 'z', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'r', 's', 't', 'u', 'f', 'h', 'c', 'y', 'x', 'q',
    'Zh', 'Ch', 'Sht', 'Sh', 'Yu', 'A', 'B', 'V', 'G', 'D', 'E', 'Z', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'U', 'F', 'H', 'c', 'Y', 'X', 'Q');
    if($textcyr) return str_replace($cyr, $lat, $textcyr);
    else if($textlat) return str_replace($lat, $cyr, $textlat);
    else return null;
}

function to_roman($num) { //$num=5;
    // Make sure that we only use the integer portion of the value
    $n = intval($num);
    $result = '';
    // Declare a lookup array that we will use to traverse the number:
    $lookup = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
    foreach ($lookup as $roman => $value) {
        // Determine the number of matches
        $matches = intval($n / $value);
        // Store that many characters
        $result.= str_repeat($roman, $matches);
        // Substract that from the number
        $n = $n % $value;
    }
    if ($num == 0) $result = "N&frasl;A";
    // The Roman numeral should be built, return it
    return $result;
}
function old_level_number($level, $date, $calculate_date = '') {
    global $attestation_base_month;
    if ($calculate_date == '') {
        $now_month = date('Y') * 12 + date('m') - substr($date, 0, 4) * 12 - substr($date, 5, 2);
    } else {
        $now_date = date_create_from_format('Y-m-d', $calculate_date);
        $now_month = $now_date->format('Y') * 12 + $now_date->format('m') - substr($date, 0, 4) * 12 - substr($date, 5, 2);
    }
    if ($now_month < $attestation_base_month) {
        $l = $level;
    } else {
        $l_temp = $level - $now_month / 12 + 3;
        if ($l_temp > 1) {
            $l = floor($l_temp);
        } else {
            $l = 0;
        }
    }
    return $l;
}
function current_level($level, $d, $calculate_date = '', $nobr = false) {
    global $attestation_base_month;
    global $att_levels;
    $date_format = 'Y-m-d';
    if (!($calculate_date instanceof Datetime)) {
        if ($calculate_date) {
            $calculate_date = date_create_from_format($date_format, $calculate_date);
        } else {
            $calculate_date = new Datetime;
        }
    }
    $date = $d instanceof Datetime ? $d : date_create_from_format($date_format, $d);
    $revolution_date = date_create_from_format($date_format, get_revolution_date());
    $now_date = $calculate_date;
    $now_month = $now_date->format('Y') * 12 + $now_date->format('m') - $date->format('Y') * 12 - $date->format('m') - 1;
    $l_temp = 0;
    //print_r(array($now_month, $attestation_base_month));
    if ($now_month <= $attestation_base_month) {
        $l['str'] = "<span class=red_shadow>" . to_roman(substr($level, 0, 1)) . (strlen($level) > 1 ? substr($level, 1) : '') . "</span>&nbsp;";
        if ($date->format('m') == 1 && $date->format('d') > 29) $date->sub(new DateInterval('P2D'));
        $date->add(new DateInterval('P3Y1M'));
        $l['str'].= "<br><span class=txtsm>до " . $date->format('m') . "/" . ($date->format('Y')) . "</span>";
        $l['num'] = $level;
    } else {
        if ($date < $revolution_date && $revolution_date->diff($date)->format('%y') >= 3) {
            $_level = old_level_number(substr('' . $level, 0, 1), $date->format($date_format), $revolution_date->format($date_format));
            //обрахунок рівня за старою системою, якщо іспит складено за 3 і раніше роки до революції
            //print_R(array('old_level' => $_level, 'revo' => $revolution_date->format($date_format)));
            if ($_level > 0) {
                $delta_month = ($revolution_date->format('Y') * 12 + $revolution_date->format('m') - $date->format('Y') * 12 - $date->format('m') - 1) % 12;
                //				print_r($date->format($date_format));
                $date = $revolution_date->sub(new DateInterval('P' . $delta_month . 'M'));
                $now_month = $now_date->format('Y') * 12 + $now_date->format('m') - $date->format('Y') * 12 - $date->format('m') - 1;
                $level_index = array_search($_level, $att_levels);
                $l_temp = $level_index - ceil($now_month / 12);
                //	print_r(array('l_temp' => $l_temp,  'level' => $_level, 'level_index' => $level_index, 'now_month' => $now_month, $att_levels));

            }
        } else {
            $level_index = array_search($level, $att_levels);
            $l_temp = $level_index - ceil($now_month / 12) + 3;
        }
        if ($l_temp >= 1) {
            $l['num'] = $att_levels[$l_temp];
            $l['str'] = to_roman(substr($l['num'], 0, 1)) . (strlen($l['num']) > 1 ? substr($l['num'], 1) : '');
        } else {
            $l['num'] = 0;
            $l['str'] = "n&frasl;a";
        }
        $date->add(new DateInterval('P'.($level_index-$l_temp+3).'Y1M'));
        $l['str'] = "<span class=red_shadow>{$l['str']}</span><br/><span class=txtsm>не подтвержден<b>" . to_roman(substr($level, 0, 1)) . (strlen($level) > 1 ? substr($level, 1) : '') . "</b></span>";
        if ($l_temp >= 1) {
            $l['str'] .= "<br><span class=txtsm>до " . $date->format('m') . "/" . ($date->format('Y')) . "</span>";
        }
    }
    if ($level_index)
        $l['str'] = str_replace('<br>','',$l['str']);
    return $l;
}

function attestations_current_user_levels()
{
    global $attestations_current_user_levels;
    global $current_user;
    global $wpdb;
    get_currentuserinfo();
    if (!$current_user)
        return [];
    $person_id = intval(get_user_meta($current_user->ID,'attestations_person',true));
    if (!$person_id)
        return [];
    if (isset($attestations_current_user_levels))
        return $attestations_current_user_levels;
    $results = $wpdb->get_results("SELECT att.id_period, att.level, att.date
            FROM {$wpdb->prefix}attestation as att
            WHERE att.id_man='$person_id' AND att.valid
            ORDER BY att.date, att.level", ARRAY_N);
    $res = [];
    foreach ($results as $row) {
        $attest[$row[0]]['level'] = $row[1];
        $attest[$row[0]]['date'] = $row[2];
        $attest[$row[0]]['period'] = $row[0];
        $l = current_level($row[1], $row[2]);
        if ($l['num'] != 0)
            $res[$row[0]] = $l['num'];
    }
    $attestations_current_user_levels = $res;
    return $res;
}

function get_all_teachers() {
    global $wpdb;
    global $attestation_periods;
	$results = $wpdb->get_results("SELECT per.name as pname, att.level, att.mark, att.date, att.id_moders, att.id_man, p.name as p2name, city.name as cname, per.web_name, per.id
					FROM {$wpdb->prefix}attestation as att
					LEFT JOIN {$wpdb->prefix}period as per on att.id_period=per.id
					LEFT JOIN {$wpdb->prefix}people as p on att.id_man=p.id
					LEFT JOIN {$wpdb->prefix}city as city on p.city_id=city.id
					WHERE (att.level='3' OR att.level='4') AND att.valid ORDER BY per.sort, city.name, p2name, att.date", ARRAY_N);
	$teachers = [];
    get_attestation_periods();
    foreach ($attestation_periods as $period) {
        $teachers[$period[2]] = [];
    }
	foreach ($results as $row) {
		$templevel = current_level($row[1], $row[3]);
		if ($templevel['num'] == '3' || $templevel['num'] == '4') {
			if (!isset($teachers[$row[9]]))
				$teachers[$row[9]] = [];
			if (!isset($teachers[$row[9]][$row[5]]) || intval($teachers[$row[9]][$row[5]][2]) < intval($templevel['num']))
				$teachers[$row[9]][$row[5]] = [$row[6],$row[7],$templevel['num']];
		}
	}
    return $teachers;
}

function get_attestation_periods() {
    global $wpdb;
    global $attestation_periods;
    if (isset($attestation_periods))
        return $attestation_periods;
    $attestation_periods = $wpdb->get_results("SELECT per.name as pname, per.web_name, per.id
    FROM {$wpdb->prefix}period as per
    ORDER BY per.sort",ARRAY_N);
    return $attestation_periods;
}

function get_attestation_cities() {
    global $wpdb;
    global $attestation_cities;
    if (isset($attestation_cities))
        return $attestation_cities;
    $attestation_cities = $wpdb->get_results("SELECT name,web_name,id
    FROM {$wpdb->prefix}city
    ORDER BY name",ARRAY_N);
    return $attestation_cities;
}
