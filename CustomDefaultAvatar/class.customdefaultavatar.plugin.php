<?php if (!defined('APPLICATION')) exit();
/**
 * Custom Default Forum Avatar
 * A really simple vanilla forums plugin that will assign a default forum avatar based on a user from a custom url.
 *
 * @author Chris Ireland
 * @license GNU GPLv2
 */
 
// Define the plugin:
$PluginInfo['customdefaultavatar'] = array(
    "Name" => "Custom Default Forum Avatar",
    "Description" => "A really simple vanilla forums plugin that will assign a default forum avatar based on a user from a custom url",
    "Version" => "1.0",
    "RequiredApplications" => array("Vanilla" => "2.0.18"),
    "Author" => "Chris Ireland",
    "MobileFriendly" => TRUE,
    "SettingsPermission" => "Garden.Settings.Manage",
    "SettingsUrl" => "/settings/customdefaultavatar"
);
 
class CustomDefaultAvatarPlugin extends Gdn_Plugin
{
 
    /**
     * Creates a settings page
     *
     * @param $Sender
     */
    public function SettingsController_CustomDefaultAvatar_Create($Sender)
    {
        $Sender->Permission("Garden.Settings.Manage");
        $Sender->SetData("Title", T("Custom Default Forum Avatar"));
        $Sender->AddSideMenu("dashboard/settings/plugins");
 
        $Conf = new ConfigurationModule($Sender);
        $Conf->Initialize(array(
            "Plugins.CustomDefaultAvatarPlugin.ProfileUrl" => array(
                "Description" => T("Custom URL for profile pages"),
                "Default" => "https://minotar.net/avatar/%user%/176.png",
                "LabelCode" => T("%user% represents the user variable")
            ),
            "Plugins.CustomDefaultAvatarPlugin.AvatarUrl" => array(
                "Description" => T("Custom URL for messages"),
                "Default" => "https://minotar.net/avatar/%user%/48.png",
                "LabelCode" => T("%user% represents the user variable")
            ),
            "Plugins.CustomDefaultAvatarPlugin.Deleted" => array(
                "Description" => T("Username for deleted users"),
                "Default" => "Steve",
                "LabelCode" => T("What should deleted users be replaced with?")
            ),
            "Plugins.CustomDefaultAvatarPlugin.md5" => array(
                "Description" => T("Should usernames be hashed with md5?"),
                "Control" => "CheckBox",
                "LabelCode" => T("Hash usernames with md5"),
                "Default" => "0"
            )
 
        ));
 
        $Conf->RenderAll();
    }
 
    /**
     * Override Profile Pages
     *
     * @param $Sender
     * @param $Args
     */
    public function ProfileController_AfterAddSideMenu_Handler($Sender, $Args)
    {
        if (!$Sender->User->Photo) {
            $username = GetValue("Name", $Sender->User);
 
            // A fall-back for deleted users
            if ($username == "[Deleted User]") {
                $username = C("Plugins.CustomDefaultAvatarPlugin.Deleted", "Steve");
            }
 
            // Md5
            if(C("Plugins.CustomDefaultAvatarPlugin.md5", 0) == 1) {
                $username = md5($username);
            }
 
            // Handle Url
            $url = C("Plugins.CustomDefaultAvatarPlugin.ProfileUrl", "https://minotar.net/avatar/%user%/176.png");
            $url = str_replace("%user%", $username, $url);
 
            $Sender->User->Photo = $url;
        }
    }
}
 
 
if (!function_exists("UserPhotoDefaultUrl")) {
    /**
     * Overwrite any other instances
     *
     * @param $User
     * @param array $Options
     * @return string
     */
    function UserPhotoDefaultUrl($User, $Options = array())
    {
        $username = GetValue("Name", $User);
 
        // A fall-back for deleted users
        if ($username == "[Deleted User]") {
            $username = C("Plugins.CustomDefaultAvatarPlugin.Deleted", "Steve");
        }
 
        // Md5
        if(C("Plugins.CustomDefaultAvatarPlugin.md5", 0) == 1) {
            $username = md5($username);
        }
 
        // Handle Url
        $url = C("Plugins.CustomDefaultAvatarPlugin.ProfileUrl", "https://minotar.net/avatar/%user%/48.png");
        $url = str_replace("%user%", $username, $url);
 
 
        return $url;
    }
}
