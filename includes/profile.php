<?php

function attestations_profile_fields($user_id) {
    if (!current_user_can('manage_options')) return false;
    if ($_POST['attestations_person'] === 'null') {
        update_usermeta($user_id, 'attestations_person', 'null');
        return true;
    }
    $person_id = intval($_POST['attestations_person']);
    if ($person_id == 0) return false;
    $args = array('meta_key' => 'attestations_person', 'meta_value' => $person_id);
    $search_users = get_users($args);
    foreach ($search_users as $u) update_usermeta($u->ID, 'attestations_person', 'null');
    update_usermeta($user_id, 'attestations_person', $person_id);
}

function attestations_user_profile_fields($user) {
    global $wpdb;
    $results = $wpdb->get_results("SELECT p.id, p.name as pname, c.name as cname
        FROM {$wpdb->prefix}people as p
        LEFT JOIN {$wpdb->prefix}city as c on p.city_id=c.id ORDER BY p.name", ARRAY_N);
    $person_id = get_the_author_meta('attestations_person', $user->ID);
?>
<table class="form-table">
<tr>
    <th>
        <label for="attestations_person">Имя в базе атестации</label>
    </th>
    <td>
        <select <?php if (!current_user_can('manage_options')) echo 'disabled'; ?> name="attestations_person">
            <option value="null">Нет</option>
            <?php foreach ($results as $row) {
        echo "<option value=\"{$row[0]}\"" . ($person_id == $row[0] ? 'selected="selected"' : '') . ">{$row[1]} ({$row[2]})</option>";
    } ?>
        </select>
    </td>
</tr>
</table>
<?php
}
