<?php
if (eregi("block-Donations.php",$PHP_SELF)) {
    Header("Location: index.php");
    die();
}
$content  =  "Donate here for the IBL vBulletin license!";
$content  .= "form action=\"https://www.paypal.com/cgi-bin/webscr\" method=\"post\">";
$content  .= "input type=\"hidden\" name=\"cmd\" value=\"_xclick\">";
$content  .= "input type=\"hidden\" name=\"business\" value=\"chibul@gmail.com\">";
$content  .= "input type=\"hidden\" name=\"item_name\" value=\"IBL\">";
$content  .= "input type=\"hidden\" name=\"no_note\" value=\"1\">";
$content  .= "input type=\"hidden\" name=\"currency_code\" value=\"USD\">";
$content  .= "input type=\"hidden\" name=\"tax\" value=\"0\">";
$content  .= "input type=\"image\" src=\"https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif\"
              border=\"0\" name=\"submit\" 
              alt=\"Donate here for the IBL vBulletin license!\">";
$content  .= "/form>";
?>