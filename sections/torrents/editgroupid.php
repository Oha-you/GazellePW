<?php

/***************************************************************
 * This page handles the backend of the "edit group ID" function
 * (found on edit.php). It simply changes the group ID of a
 * torrent.
 ****************************************************************/

if (!check_perms('torrents_edit')) {
    error(403);
}

$OldGroupID = $_POST['oldgroupid'];
$GroupID = $_POST['groupid'];
$TorrentID = $_POST['torrentid'];


if (!is_number($OldGroupID) || !is_number($GroupID) || !is_number($TorrentID) || !$OldGroupID || !$GroupID || !$TorrentID) {
    error(0);
}

if ($OldGroupID == $GroupID) {
    $Location = (empty($_SERVER['HTTP_REFERER'])) ? "torrents.php?action=edit&id={$GroupID}" : $_SERVER['HTTP_REFERER'];
    header(Lang::get('torrents', 'location') . ": {$Location}");
    die();
}

//Everything is legit, let's just confim they're not retarded
if (empty($_POST['confirm'])) {
    $DB->query("
		SELECT Name, SubName, Year
		FROM torrents_group
		WHERE ID = $OldGroupID");
    if (!$DB->has_results()) {
        //Trying to move to an empty group? I think not!
        // set_message(Lang::get('torrents', 'the_destination_torrent_group_does_not_exist'));
        $Location = (empty($_SERVER['HTTP_REFERER'])) ? "torrents.php?action=edit&id={$OldGroupID}" : $_SERVER['HTTP_REFERER'];
        header("Location: {$Location}");
        die();
    }
    $OldGroup = $DB->next_record(MYSQLI_ASSOC);
    $DB->query("
		SELECT Name, SubName, Year
		FROM torrents_group
		WHERE ID = $GroupID");
    if (!$DB->has_results()) {
        //Trying to move to an empty group? I think not!
        // set_message(Lang::get('torrents', 'the_destination_torrent_group_does_not_exist'));
        $Location = (empty($_SERVER['HTTP_REFERER'])) ? "torrents.php?action=edit&id={$OldGroupID}" : $_SERVER['HTTP_REFERER'];
        header("Location: {$Location}");
        error(Lang::get('torrents', 'the_destination_torrent_group_does_not_exist'));
    }
    $NewGroup = $DB->next_record(MYSQLI_ASSOC);

    View::show_header('', '', 'PageTorrentEditGroupId');
?>
    <div class="LayoutBody">
        <div class="BodyHeader">
            <h2 class="BodyHeader-nav"><?= Lang::get('torrents', 'torrent_group_id_change_confirmation') ?></h2>
        </div>
        <div class="BoxBody">
            <form class="confirm_form" name="torrent_group" action="torrents.php" method="post">
                <input type="hidden" name="action" value="editgroupid" />
                <input type="hidden" name="auth" value="<?= $LoggedUser['AuthKey'] ?>" />
                <input type="hidden" name="confirm" value="true" />
                <input type="hidden" name="torrentid" value="<?= $TorrentID ?>" />
                <input type="hidden" name="oldgroupid" value="<?= $OldGroupID ?>" />
                <input type="hidden" name="groupid" value="<?= $GroupID ?>" />
                <h3><?= Lang::get('torrents', 'you_are_attempt_to_move_from_before') ?><?= $TorrentID ?><?= Lang::get('torrents', 'you_are_attempt_to_move_from_after') ?>:</h3>
                <ul>
                    <li><a href="torrents.php?id=<?= $OldGroupID ?>"><?= Torrents::group_name($OldGroup, false) ?></a></li>
                </ul>
                <h3><?= Lang::get('torrents', 'into_the_group') ?>:</h3>
                <ul>
                    <li><a href="torrents.php?id=<?= $GroupID ?>"><?= Torrents::group_name($NewGroup, false) ?></a></li>
                </ul>
                <input class="Button" type="submit" value="Confirm" />
            </form>
        </div>
    </div>
<?
    View::show_footer();
} else {

    authorize();

    $DB->query("
		UPDATE torrents
		SET	GroupID = '$GroupID'
		WHERE ID = $TorrentID");

    // Delete old torrent group if it's empty now
    $DB->query("
		SELECT COUNT(ID)
		FROM torrents
		WHERE GroupID = '$OldGroupID'");
    list($TorrentsInGroup) = $DB->next_record();
    if ($TorrentsInGroup == 0) {
        // TODO: votes etc!
        $DB->query("
			UPDATE comments
			SET PageID = '$GroupID'
			WHERE Page = 'torrents'
				AND PageID = '$OldGroupID'");
        $Cache->delete_value("torrent_comments_{$GroupID}_catalogue_0");
        $Cache->delete_value("torrent_comments_$GroupID");
        Torrents::delete_group($OldGroupID);
    } else {
        Torrents::update_hash($OldGroupID);
    }
    Torrents::update_hash($GroupID);

    Misc::write_log("Torrent $TorrentID was edited by " . $LoggedUser['Username']); // TODO: this is probably broken
    Torrents::write_group_log($GroupID, 0, $LoggedUser['ID'], "merged group $OldGroupID", 0);
    $DB->query("
		UPDATE group_log
		SET GroupID = $GroupID
		WHERE GroupID = $OldGroupID");


    $Cache->delete_value("torrents_details_$GroupID");
    $Cache->delete_value("torrent_download_$TorrentID");
    header("Location: torrents.php?id=$GroupID");
}
