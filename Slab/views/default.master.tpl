<!DOCTYPE html>
<html>
<head>
    {asset name="Head"}
</head>
<body id="{$BodyID}" class="{$BodyClass}">
<div id="Frame">
    <div id="Body">
        <div class="BreadcrumbsWrapper">
            <div class="Row">
                {breadcrumbs}
                <div class="MeModuleWrap">
                  {module name="MeModule" CssClass="Inline FlyoutRight"}
               </div>
            </div>
        </div>
        <div class="Head" id="Head">
            <div class="Row">
                <strong class="SiteTitle"><a href="{link path="/"}">{logo}</a></strong>

                <div class="SiteSearch">{searchbox}</div>
            </div>
        </div>
        <div class="Row">
            <div class="Column PanelColumn" id="Panel">
               {asset name="Panel"}
                     <!--
                     I've placed this optional advertising space below. Just comment out the line and replace "Advertising Space" with your 468x60 ad banner. 
      -->
                     <!-- <div class="AdSpace">Advertising Space</div> -->
            </div>
            <div class="Column ContentColumn" id="Content">
               {asset name="Content"}
            </div>
        </div>
    </div>
    <div id="Foot">
        <div class="Row">
            <a href="{vanillaurl}" class="PoweredByVanilla" title="Community Software by Vanilla Forums">Powered by
                Vanilla</a>
            {asset name="Foot"}
        </div>
    </div>
</div>
{event name="AfterBody"}
</body>
</html>