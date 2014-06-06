<?php if (!defined('APPLICATION')) exit();
/**
 * View Received Thanks
 *
 * @author Jerl Liandri
 * @author Chris Ireland <ireland63@gmal.com>
 * @license MIT <opensource.org/licenses/MIT> & X.Net <http://opensource.org/licenses/xnet.php>
 */

?>

<ul class="DataList SearchResults ThankObjects">
    <?php foreach ($this->ThankObjects as $Object) {
        $ThankCollection = GetValue($Object->ObjectID, GetValue($Object->Type, $this->ThankData));
        $ExcerptText = SliceString(Gdn_Format::Text($Object->ExcerptText), 200);
        if ($Object->Url) $ExcerptText = Anchor($ExcerptText, $Object->Url);
        ?>
        <li class="Item">
            <div class="ItemContent">
                <div class="Excerpt"><?php echo $ExcerptText; ?></div>
                <?php echo ThankfulPeoplePlugin::ThankedByBox($ThankCollection); ?>
            </div>
        </li>

    <?php } ?>

</ul>