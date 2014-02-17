<?php if (!defined('APPLICATION')) exit();
// Metro Theme, using bittersweet as a skeleton
// Support can be found on the addon page
$ThemeInfo['Vetro'] = array(
    'Name' => 'Vetro',
    'Description' => 'A theme based on Microsoft\'s new Windows 8 Metro theme<br/>
   <ul>
    <li>3 Colour Options</li>
	<li>Minimal shadows</li>
	<li>Optimized for Fast Page Load</li>
   </ul>',
    'Version' => '1.6',
    'RequiredApplications' => array('Vanilla' => '2.1a'),
    'Author' => 'Chris Ireland',
    'AuthorEmail' => 'chris@tama63.co.uk',
    'AuthorUrl' => 'http://tama63.co.uk/',
    'Options' => array(
        'Description' => 'This theme has <b>3 colour</b> options. Find out more on <a href="http://www.vanillaforums.com/blog/help-tutorials/how-to-use-theme-options">Theme Options</a>.',
        'Styles' => array(
            'Vetro Orange and Green' => '%s_orgr',
            'Vetro Dark' => '%s_dark',
            'Vetro Blue and Magenta' => '%s'
        ),
    )
);
// End Theme Info