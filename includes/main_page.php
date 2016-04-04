<?php
defined('ABSPATH') or die('');

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
                $body.= "<div class=\"attestations_period\" style=\"background-image: url('" . plugins_url("/../img/periods/" . $v1['per_web_name'] . "-g.gif", __FILE__) . "');\"><p>";
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
            $results = $wpdb->get_results("SELECT per.name as pname, att.level, att.mark, att.date, att.id_moders, att.id_man, p.name as p2name, city.name, city.web_name
                        FROM {$wpdb->prefix}attestation as att 
                        LEFT JOIN {$wpdb->prefix}period as per ON att.id_period=per.id
                        LEFT JOIN {$wpdb->prefix}people as p ON att.id_man=p.id
                        LEFT JOIN {$wpdb->prefix}city as city ON p.city_id=city.id
                        WHERE per.id='$period_id' AND att.valid ORDER BY p.name, att.date DESC, att.dateinsert", ARRAY_N);
            $people = [];
            foreach ($results as $row) {
                $l = current_level($row[1], $row[3]);
                if ($l['num'] == '0') continue;
                if (isset($people[$row[5]]) && $att_rlevels[$people[$row[5]]['level_num']] >= $att_rlevels[$l['num']]) continue;
                $people[$row[5]] = ['name' => $row[6], 'city' => $row[7], 'level_num' => $l['num'], 'level_str' => $l['str'], 'date' => $row[3], 'id' => $row[5], 'history' => substr($row[3], 5, 2) . "/" . substr($row[3], 0, 4) . " оценка <b>" . to_roman(substr($row[1], 0, 1)) . (strlen($row[1]) > 1 ? substr($row[1], 1) : '') . "</b>" . (strlen($row[2]) ? "(" . $row[2] . ")" : '') . "<br>"];
            }
            $levels = [];
            foreach ($people as $id => $p) {
                $levels[$p['level_num']][] = $p;
            }
            $title = "Аттестация по теме: " . $period;
            $body.= "<br><img src=" . plugins_url("/../img/periods/$period_wname.gif", __FILE__) . " border=0><br><b>Текущие уровни:</b> <br><span class=txtsm>(на " . date('m') . "/" . date('Y') . ")</span>";
            $toplinks = [];
            foreach ($att_levels as $ln) {
                $pl = $levels[$ln];
                if (empty($pl)) continue;
                usort($pl, function ($c1, $c2) {
                    return strcmp($c1['name'], $c2['name']);
                });
                $body.= "<a name='$ln'></a><h3 class=\"att_h3\">" . to_roman(substr($ln, 0, 1)) . (strlen($ln) > 1 ? substr($ln, 1) : '') . "</h3><ul>";
                $toplinks[] = "<a href='#" . $ln . "'>" . to_roman(substr($ln, 0, 1)) . (strlen($ln) > 1 ? substr($ln, 1) : '') . "</a> | ";
                foreach ($pl as $p) $body.= "<li><a href=\"" . add_query_arg('att_person_id', $p['id']) . "\">{$p['name']}</a>: {$p['level_str']} ({$p['city']})</li>";
                $body.= '</ul>';
            }
            $body = '<div class="attestations">|' . join('|', $toplinks) . '|' . $body . '</div>';
        } else {
            $title = 'Аттестованные в АИТ';
            $body.= '<h3>Принимают экзамены:</h3>';
            $teachers = get_all_teachers();
            $periods = get_attestation_periods();
            $t = [];
            foreach ($teachers as $period => $pt) {
                foreach ($pt as $tid => $teacher) $t[$period][$teacher[2]][$teacher[1]][] = [$tid, $teacher[0]];
            }
            foreach ($periods as $period) {
                if (empty($t[$period[2]]['4']) && empty($t[$period[2]]['3'])) continue;
                $body.= "<div class=\"attestations_period\" style=\"background-image: url('" . plugins_url("/../img/periods/{$period[1]}-g.gif", __FILE__) . "');\">
                   <span class=txtsm><a href=\"" . add_query_arg('att_period_id', $period[2]) . "\"><h3 class='att_h3'>{$period[0]}</h3></a>";
                if (!empty($t[$period[2]]['4'])) {
                    $body.= "<p class=\"att_sub_level\">Все уровни:</p>";
                    foreach ($t[$period[2]]['4'] as $city => $pteachers) {
                        $body.= "$city:<br>";
                        foreach ($pteachers as $teacher) {
                            $body.= "<b><a href=\"" . add_query_arg('att_person_id', $teacher[0]) . "\">" . str_replace(" ", "&nbsp;", $teacher[1]) . "</a></b><br>";
                        }
                    }
                }
                if (!empty($t[$period[2]]['3'])) {
                    $body.= "<p class=\"att_sub_level\">Только I уровень:</p>";
                    foreach ($t[$period[2]]['3'] as $city => $pteachers) {
                        $body.= "$city:<br>";
                        foreach ($pteachers as $teacher) {
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

