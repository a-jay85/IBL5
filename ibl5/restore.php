<?php 

require_once("mainfile.php");
$theme= "DeepBlue";
$sql = "UPDATE ".$prefix."_config SET Default_Theme = '".$theme."'";
$result = $db->sql_query($sql);
echo "Thanks for using this fix! Your theme has been reset to ".$theme."";

?>