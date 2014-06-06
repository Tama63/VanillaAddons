<?php if (!defined('APPLICATION')) exit();
/**
 * Class ThankfulPeoplePlugin
 *
 * @author Jerl Liandri
 * @author Chris Ireland <ireland63@gmal.com>
 * @license MIT <opensource.org/licenses/MIT> & X.Net <http://opensource.org/licenses/xnet.php>
 */

$PluginInfo['ThankfulPeople'] = array(
    'Name' => 'Thankful People',
    'Description' => 'Remake of classic Vanilla One extension. Instead of having people post appreciation and thankyou notes they can simply click the thanks link and have their username appear under that post (MySchizoBuddy). Modifications by hgtonight.',
    'Version' => '2.14.2.0.18.x',
    'Date' => 'Summer 2011',
    'Author' => 'Jerl Liandri & Forked by Chris Ireland',
    'RequiredApplications' => array('Vanilla' => '>=2.1'),
    'License' => 'MIT & X.Net'
);

class ThankfulPeoplePlugin extends Gdn_Plugin
{

    protected $ThankForComment = array();
    protected $CommentGroup = array();
    protected $DiscussionData = array();
    private $Session;

    /**
     * Create a new instance of the plugin, hooks into Garden's session method to allocate session data to the plugin
     */
    public function __construct()
    {
        $this->Session = Gdn::Session();
    }

    /**
     * Builds the UnThank method - allows the taking back of a thank.
     *
     * @param $Sender
     */
    public function PluginController_UnThankFor_Create($Sender)
    {
        // Get the user's ID from the Garden session method
        $SessionUserID = GetValue('UserID', $this->Session);

        // Check if they aren't a guest & have the permission before proceeding
        if ($SessionUserID > 0 && C('Plugins.ThankfulPeople.AllowTakeBack', False)) {
            $ThanksLogModel = new ThanksLogModel();
            $Type = GetValue(0, $Sender->RequestArgs);
            $ObjectID = GetValue(1, $Sender->RequestArgs);
            $ThanksLogModel->RemoveThank($Type, $ObjectID, $SessionUserID);

            if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
                $Target = GetIncomingValue('Target', 'discussions');
                Redirect($Target);
            }

            // Log and render
            $ThankfulPeopleDataSet = $ThanksLogModel->GetThankfulPeople($Type, $ObjectID);
            $Sender->SetData('NewThankedByBox', self::ThankedByBox($ThankfulPeopleDataSet->Result(), False));
            $Sender->Render();
        }
    }

    /**
     * Builds the Thank method - allows the receiving of a thank.
     *
     * @param $Sender
     * @throws Exception
     */
    public function PluginController_ThankFor_Create($Sender)
    {
        // Check if it's valid
        if (!$this->Session->IsValid()) return;

        // Start logging and grabbing from session
        $ThanksLogModel = new ThanksLogModel();
        $Type = GetValue(0, $Sender->RequestArgs);
        $ObjectID = GetValue(1, $Sender->RequestArgs);
        $Field = $ThanksLogModel->GetPrimaryKeyField($Type);
        $UserID = $ThanksLogModel->GetObjectInserUserID($Type, $ObjectID);

        // Throw a few errors
        if ($UserID == False) throw new Exception('Object has no owner.');
        if ($UserID == $this->Session->UserID) throw new Exception('You cannot thank yourself.');
        if (!self::IsThankable($Type)) throw new Exception("Not thankable ($Type).");

        // Make sure that user is not trying to say thanks twice.
        $Count = $ThanksLogModel->GetCount(array($Field => $ObjectID, 'InsertUserID' => $this->Session->User->UserID));
        if ($Count < 1) $ThanksLogModel->PutThank($Type, $ObjectID, $UserID);

        if ($Sender->DeliveryType() == DELIVERY_TYPE_ALL) {
            $Target = GetIncomingValue('Target', 'discussions');
            Redirect($Target);
        }

        // Render
        $ThankfulPeopleDataSet = $ThanksLogModel->GetThankfulPeople($Type, $ObjectID);
        $Sender->SetData('NewThankedByBox', self::ThankedByBox($ThankfulPeopleDataSet->Result(), False));
        $Sender->Render();
    }

    /**
     * Add a few things before a discussion is rendered
     *
     * @param $Sender
     */
    public function DiscussionController_Render_Before($Sender)
    {
        if (!($Sender->DeliveryType() == DELIVERY_TYPE_ALL && $Sender->SyndicationMethod == SYNDICATION_NONE)) return;
        $ThanksLogModel = new ThanksLogModel();
        $DiscussionID = $Sender->DiscussionID;
        $CommentIDs = ConsolidateArrayValuesByKey($Sender->CommentData->Result(), 'CommentID');
        $DiscussionCommentThankDataSet = $ThanksLogModel->GetDiscussionComments($DiscussionID, $CommentIDs);

        // Consolidate.
        foreach ($DiscussionCommentThankDataSet as $ThankData) {
            $CommentID = $ThankData->CommentID;
            if ($CommentID > 0) {
                $this->CommentGroup[$CommentID][] = $ThankData;
                $this->ThankForComment[$CommentID][] = $ThankData->UserID;
            } elseif ($ThankData->DiscussionID > 0) {
                $this->DiscussionData[$ThankData->UserID] = $ThankData;
            }
        }

        // Add assets
        $Sender->AddJsFile('jquery.expander.js');
        $Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
        $Sender->AddJsFile('plugins/ThankfulPeople/js/thankfulpeople.functions.js');

        $Sender->AddDefinition('ExpandThankList', T(' Show More'));
        $Sender->AddDefinition('CollapseThankList', T(' Hide'));
    }

    /**
     * Checks if a post can be thanked
     *
     * @param $Type
     * @return bool
     */
    public static function IsThankable($Type)
    {
        static $ThankOnly, $ThankDisabled;
        $Type = strtolower($Type);
        if (is_null($ThankOnly)) $ThankOnly = C('Plugins.ThankfulPeople.Only');
        if (is_array($ThankOnly)) {
            if (!in_array($Type, $ThankOnly)) return False;
        }
        if (is_null($ThankDisabled)) $ThankDisabled = C('Plugins.ThankfulPeople.Disabled');
        if (is_array($ThankDisabled)) {
            if (in_array($Type, $ThankDisabled)) return False;
        }
        return True;
    }

    /**
     * What actually displays the thank box to the ender use
     *
     * @param $Sender
     */
    public function DiscussionController_CommentInfo_Handler($Sender)
    {
        $EventArguments =& $Sender->EventArguments;
        $Type = $EventArguments['Type'];
        $Object = $EventArguments['Object'];

        $SessionUserID = $this->Session->UserID;
        if ($SessionUserID <= 0 || $Object->InsertUserID == $SessionUserID) return;

        if (!self::IsThankable($Type)) return;

        static $AllowTakeBack;
        if (is_null($AllowTakeBack)) $AllowTakeBack = C('Plugins.ThankfulPeople.AllowTakeBack', False);
        $AllowThank = True;

        switch ($Type) {
            case 'Discussion':
            {
                $DiscussionID = $ObjectID = $Object->DiscussionID;
                if (array_key_exists($SessionUserID, $this->DiscussionData)) $AllowThank = False;
                break;
            }
            case 'Comment':
            {
                $CommentID = $ObjectID = $Object->CommentID;
                if (array_key_exists($CommentID, $this->ThankForComment) && in_array($SessionUserID, $this->ThankForComment[$CommentID])) $AllowThank = False;
                break;
            }
        }

        // Check if a user can actually thank
        if ($AllowThank) {
            static $LocalizedThankButtonText;
            if ($LocalizedThankButtonText === Null) $LocalizedThankButtonText = T('ThankCommentOption', T('Thank'));
            $ThankUrl = 'plugin/thankfor/' . strtolower($Type) . '/' . $ObjectID . '?Target=' . $Sender->SelfUrl;
            $Option = '<span class="Thank">' . Anchor($LocalizedThankButtonText, $ThankUrl) . '</span>';
            echo $Option;

        } elseif ($AllowTakeBack) {
            // Allow unthank
            static $LocalizedUnThankButtonText;
            if (is_null($LocalizedUnThankButtonText)) $LocalizedUnThankButtonText = T('UnThankCommentOption', T('UnThank'));
            $UnThankUrl = 'plugin/unthankfor/' . strtolower($Type) . '/' . $ObjectID . '?Target=' . $Sender->SelfUrl;
            $Option = '<span class="UnThank">' . Anchor($LocalizedUnThankButtonText, $UnThankUrl) . '</span>';
            echo $Option;

        }
    }

    /**
     * Alias for DiscussionController_CommentInfo_Handler
     *
     * @param $Sender
     */
    public function DiscussionController_DiscussionInfo_Handler($Sender)
    {
        $this->DiscussionController_CommentInfo_Handler($Sender);
    }

    /**
     * Hook into the comment handler and inject
     *
     * @param $Sender
     * @throws Exception
     */
    public function DiscussionController_AfterCommentBody_Handler($Sender)
    {
        $Object = $Sender->EventArguments['Object'];
        $Type = $Sender->EventArguments['Type'];
        $ThankedByBox = False;
        switch ($Type) {
            case 'Comment':
            {
                $ThankedByCollection =& $this->CommentGroup[$Object->CommentID];
                if ($ThankedByCollection) $ThankedByBox = self::ThankedByBox($ThankedByCollection);
                break;
            }
            case 'Discussion':
            {
                if (count($this->DiscussionData) > 0) $ThankedByBox = self::ThankedByBox($this->DiscussionData);
                break;
            }
            default:
                throw new Exception('What...');
        }
        if ($ThankedByBox !== False) echo $ThankedByBox;

    }

    /**
     * Hook into the discussion body handler and inject
     *
     * @param $Sender
     * @throws Exception
     */
    public function DiscussionController_AfterDiscussionBody_Handler($Sender)
    {
        $Object = $Sender->EventArguments['Object'];
        $Type = $Sender->EventArguments['Type'];
        $ThankedByBox = False;
        switch ($Type) {
            case 'Comment':
            {
                $ThankedByCollection =& $this->CommentGroup[$Object->CommentID];
                if ($ThankedByCollection) $ThankedByBox = self::ThankedByBox($ThankedByCollection);
                break;
            }
            case 'Discussion':
            {
                if (count($this->DiscussionData) > 0) $ThankedByBox = self::ThankedByBox($this->DiscussionData);
                break;
            }
            default:
                throw new Exception('What...');
        }
        if ($ThankedByBox !== False) echo $ThankedByBox;

    }

    /**
     * Displays a post's thanks under it
     *
     * @param $Collection
     * @param bool $Wrap
     * @return string
     */
    public static function ThankedByBox($Collection, $Wrap = True)
    {
        $List = implode(' ', array_map('UserAnchor', $Collection));
        $ThankCount = count($Collection);
        //$ThankCountHtml = Wrap($ThankCount);
        $LocalizedPluralText = Plural($ThankCount, 'Thanked by %1$s', 'Thanked by %1$s');
        $Html = '<span class="ThankedBy">' . $LocalizedPluralText . '</span>' . $List;
        if ($Wrap) $Html = Wrap($Html, 'div', array('class' => 'ThankedByBox'));
        return $Html;
    }

    /**
     * Dump the raw data from the plugin
     *
     * @param $Sender
     */
    public function UserInfoModule_OnBasicInfo_Handler($Sender)
    {
        echo Wrap(T('Thanks '), 'dt', array('class' => 'ReceivedThankCount'));
        echo Wrap($Sender->User->ReceivedThankCount, 'dd', array('class' => 'ReceivedThankCount'));
    }

    /**
     * Inject the plugin's CSS file to the profile controller
     *
     * @param $Sender
     */
    public function ProfileController_Render_Before($Sender)
    {
        if (!($Sender->DeliveryType() == DELIVERY_TYPE_ALL && $Sender->SyndicationMethod == SYNDICATION_NONE)) return;
        $Sender->AddCssFile('plugins/ThankfulPeople/design/thankfulpeople.css');
    }

    /**
     * Add a tab to the profile that shows any thanks a user has received
     *
     * @param $Sender
     */
    public function ProfileController_AddProfileTabs_Handler($Sender)
    {
        $ReceivedThankCount = GetValue('ReceivedThankCount', $Sender->User);
        if ($ReceivedThankCount > 0) {
            $UserReference = ArrayValue(0, $Sender->RequestArgs, '');
            $Username = ArrayValue(1, $Sender->RequestArgs, '');
            $Thanked = T('Profile.Tab.Thanked', T('Thanks')) . '<span class="Aside"><span class="Count"> ' . $ReceivedThankCount . '</span></span>';
            $Sender->AddProfileTab($Thanked, 'profile/receivedthanks/' . $UserReference . '/' . $Username, 'Thanked');
        }
    }

    /**
     * Add a module to the profile that shows the thanks a user has received
     *
     * @param $Sender
     */
    public function ProfileController_ReceivedThanks_Create($Sender)
    {
        $UserReference = ArrayValue(0, $Sender->RequestArgs, '');
        $Username = ArrayValue(1, $Sender->RequestArgs, '');
        $Sender->GetUserInfo($UserReference, $Username);
        $ViewingUserID = $Sender->User->UserID;

        $ReceivedThankCount = $Sender->User->ReceivedThankCount;
        $Thanked = T('Profile.Tab.Thanked', T('Thanks')) . '<span class="Aside"><span class="Count"> ' . $ReceivedThankCount . '</span></span>';
        $View = $this->GetView('receivedthanks.php');
        $Sender->SetTabView($Thanked, $View);
        $ThanksLogModel = new ThanksLogModel();

        list($Sender->ThankData, $Sender->ThankObjects) = $ThanksLogModel->GetReceivedThanks(array('t.UserID' => $ViewingUserID), 0, 50);
        $Sender->Render();
    }

    /**
     * Cleans up the log every few days
     *
     * @param $Sender
     */
    public function Tick_Every_720_Hours_Handler($Sender)
    {
        ThanksLogModel::CleanUp();
        ThanksLogModel::RecalculateUserReceivedThankCount();
    }

    /**
     * Generates the plugin's tables
     */
    public function Structure()
    {
        Gdn::Structure()
            ->Table('User')
            ->Column('ReceivedThankCount', 'usmallint', 0)
            ->Set();

        Gdn::Structure()
            ->Table('ThanksLog')
            ->Column('UserID', 'umediumint', False, 'key')
            ->Column('CommentID', 'umediumint', 0)
            ->Column('DiscussionID', 'umediumint', 0)
            ->Column('DateInserted', 'datetime')
            ->Column('InsertUserID', 'umediumint', False, 'key')
            ->Engine('MyISAM')
            ->Set();

        $RequestArgs = Gdn::Controller()->RequestArgs;
        if (ArrayHasValue($RequestArgs, 'vanilla')) {
            ThanksLogModel::RecalculateUserReceivedThankCount();
        }

    }

    /**
     * Setup the plugin when it's enabled, generates the plugins' SQL structure
     */
    public function Setup()
    {
        $this->Structure();
    }
}