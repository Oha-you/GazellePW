<?php
if (!check_perms('users_view_ips') || !check_perms('users_view_email')) {
    error(403);
}
View::show_header(Lang::get('tools', 'registration_log'), '', 'PageToolRegistrationLog');
define('USERS_PER_PAGE', 50);
list($Page, $Limit) = Format::page_limit(USERS_PER_PAGE);

$AfterDate = $_POST['after_date'];
$BeforeDate = $_POST['before_date'];
$DateSearch = false;
if (!empty($AfterDate) && !empty($BeforeDate)) {
    list($Y, $M, $D) = explode('-', $AfterDate);
    if (!checkdate($M, $D, $Y)) {
        error('Incorrect "after" date format');
    }
    list($Y, $M, $D) = explode('-', $BeforeDate);
    if (!checkdate($M, $D, $Y)) {
        error('Incorrect "before" date format');
    }
    $AfterDate = db_string($AfterDate);
    $BeforeDate = db_string($BeforeDate);
    $DateSearch = true;
}

$RS = "
	SELECT
		SQL_CALC_FOUND_ROWS
		m.ID,
		m.IP,
		m.ipcc,
		m.Email,
		m.Username,
		m.PermissionID,
		m.Uploaded,
		m.Downloaded,
		m.Enabled,
		i.Donor,
		i.Warned,
		i.JoinDate,
		(
			SELECT COUNT(h1.UserID)
			FROM users_history_ips AS h1
			WHERE h1.IP = m.IP
		) AS Uses,
		im.ID,
		im.IP,
		im.ipcc,
		im.Email,
		im.Username,
		im.PermissionID,
		im.Uploaded,
		im.Downloaded,
		im.Enabled,
		ii.Donor,
		ii.Warned,
		ii.JoinDate,
		(
			SELECT COUNT(h2.UserID)
			FROM users_history_ips AS h2
			WHERE h2.IP = im.IP
		) AS InviterUses
	FROM users_main AS m
		LEFT JOIN users_info AS i ON i.UserID = m.ID
		LEFT JOIN users_main AS im ON i.Inviter = im.ID
		LEFT JOIN users_info AS ii ON i.Inviter = ii.UserID
	WHERE";
if ($DateSearch) {
    $RS .= " i.JoinDate BETWEEN '$AfterDate' AND '$BeforeDate' ";
} else {
    $RS .= " i.JoinDate > '" . time_minus(3600 * 24 * 3) . "'";
}
$RS .= "
	ORDER BY i.Joindate DESC
	LIMIT $Limit";
$QueryID = $DB->query($RS);
$DB->query('SELECT FOUND_ROWS()');
list($Results) = $DB->next_record();
$DB->set_query_id($QueryID);
?>

<form action="" method="post" acclass="thin BoxBody">
    <input type="hidden" name="action" value="registration_log" />
    <?= Lang::get('tools', 'joined_after') ?>: <input class="Input" type="date" name="after_date" />
    <?= Lang::get('tools', 'joined_before') ?>: <input class="Input" type="date" name="before_date" />
    <input class="Button" type="submit" />
</form>

<?
if ($DB->has_results()) {
?>
    <div class="BodyNavLinks">
        <?
        $Pages = Format::get_pages($Page, $Results, USERS_PER_PAGE, 11);
        echo $Pages;
        ?>
    </div>

    <table class="Table">
        <tr class="Table-rowHeader">
            <td class="Table-cell"><?= Lang::get('tools', 'user') ?></td>
            <td class="Table-cell"><?= Lang::get('tools', 'ratio') ?></td>
            <td class="Table-cell"><?= Lang::get('tools', 'email') ?></td>
            <td class="Table-cell"><?= Lang::get('tools', 'ip_address') ?></td>
            <td class="Table-cell"><?= Lang::get('tools', 'country') ?></td>
            <td class="Table-cell"><?= Lang::get('tools', 'host') ?></td>
            <td class="Table-cell"><?= Lang::get('tools', 'registered') ?></td>
        </tr>
        <?
        while (list($UserID, $IP, $IPCC, $Email, $Username, $PermissionID, $Uploaded, $Downloaded, $Enabled, $Donor, $Warned, $Joined, $Uses, $InviterID, $InviterIP, $InviterIPCC, $InviterEmail, $InviterUsername, $InviterPermissionID, $InviterUploaded, $InviterDownloaded, $InviterEnabled, $InviterDonor, $InviterWarned, $InviterJoined, $InviterUses) = $DB->next_record()) {
            $Row = $IP === $InviterIP ? 'a' : 'b';
        ?>
            <tr class="Table-row">
                <td class="Table-cell"><?= Users::format_username($UserID, true, true, true, true) ?><br /><?= Users::format_username($InviterID, true, true, true, true) ?></td>
                <td class="Table-cell"><?= Format::get_ratio_html($Uploaded, $Downloaded) ?><br /><?= Format::get_ratio_html($InviterUploaded, $InviterDownloaded) ?></td>
                <td class="Table-cell">
                    <span style="float: left;"><?= display_str($Email) ?></span>
                    <span style="float: right;"><a href="userhistory.php?action=email&amp;userid=<?= $UserID ?>" data-tooltip="<?= Lang::get('tools', 'history') ?>" class="brackets">H</a> <a href="/user.php?action=search&amp;email_history=on&amp;email=<?= display_str($Email) ?>" data-tooltip="<?= Lang::get('tools', 'search') ?>" class="brackets">S</a></span><br />
                    <span style="float: left;"><?= display_str($InviterEmail) ?></span>
                    <span style="float: right;"><a href="userhistory.php?action=email&amp;userid=<?= $InviterID ?>" data-tooltip="<?= Lang::get('tools', 'history') ?>" class="brackets">H</a> <a href="/user.php?action=search&amp;email_history=on&amp;email=<?= display_str($InviterEmail) ?>" data-tooltip="<?= Lang::get('tools', 'search') ?>" class="brackets">S</a></span><br />
                </td>
                <td class="Table-cell">
                    <span style="float: left;"><?= display_str($IP) ?></span>
                    <span style="float: right;"><?= display_str($Uses) ?> <a href="userhistory.php?action=ips&amp;userid=<?= $UserID ?>" data-tooltip="<?= Lang::get('tools', 'history') ?>" class="brackets">H</a> <a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?= display_str($IP) ?>" data-tooltip="<?= Lang::get('tools', 'search') ?>" class="brackets">S</a> <a href="http://whatismyipaddress.com/ip/<?= display_str($IP) ?>" data-tooltip="<?= Lang::get('tools', 'wi') ?>" class="brackets">WI</a></span><br />
                    <span style="float: left;"><?= display_str($InviterIP) ?></span>
                    <span style="float: right;"><?= display_str($InviterUses) ?> <a href="userhistory.php?action=ips&amp;userid=<?= $InviterID ?>" data-tooltip="<?= Lang::get('tools', 'history') ?>" class="brackets">H</a> <a href="/user.php?action=search&amp;ip_history=on&amp;ip=<?= display_str($InviterIP) ?>" data-tooltip="<?= Lang::get('tools', 'search') ?>" class="brackets">S</a> <a href="http://whatismyipaddress.com/ip/<?= display_str($InviterIP) ?>" data-tooltip="<?= Lang::get('tools', 'wi') ?>" class="brackets">WI</a></span><br />
                </td>
                <td class="Table-cell">
                    <?= $IPCC ?> <br />
                    <?= $InviterIPCC ?>
                </td>
                <td class="Table-cell">
                    <?= Tools::get_host_by_ajax($IP) ?><br />
                    <?= Tools::get_host_by_ajax($InviterIP) ?>
                </td>
                <td class="Table-cell">
                    <?= time_diff($Joined) ?><br />
                    <?= time_diff($InviterJoined) ?>
                </td>
            </tr>
        <?  } ?>
    </table>
    <div class="BodyNavLinks">
        <? echo $Pages; ?>
    </div>
<?
} else { ?>
    <h2 align="center"><?= Lang::get('tools', 'there_have_been_no_new_registrations_in_the_past_72_hours') ?></h2>
<?
}
View::show_footer();
?>