<?php

require 'mainfile.php';

$SlideUp = $_POST['SlideUp']; $Team = $_POST['team'];  $query="UPDATE `nuke_ibl_team_info` SET SlideUp = '$SlideUp' WHERE team_name = '$team' LIMIT 1"; $result=$db->sql_query($query);  echo "<HTML><HEAD><TITLE>Team Philosophy Change</TITLE><meta http-equiv=\"refresh\" content=\"0;url=modules.php?name=Free_Agency\"></HEAD><BODY> Changing your team philosophy and returning you to the Free Agency page... hang tight! </BODY></HTML>";
