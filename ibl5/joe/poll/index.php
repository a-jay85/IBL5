<link rel="stylesheet" href="css/style.css" type="text/css" media="screen" />
<?php
include 'config.php';

if (isset($_POST['vote']) && isset($_POST['questions'])) {
    $query = mysql_query("SELECT `questions`.`pid` FROM  `responses`, `questions` WHERE `responses`.`qid`=`questions`.`id` AND `responses`.`ip`='" . $_SERVER['REMOTE_ADDR'] . "' AND pid=(SELECT pid FROM `questions` WHERE id='" . $_POST['questions'] . "' LIMIT 1)");
    if (mysql_num_rows($query) == 0) {
        $query = mysql_query("INSERT INTO `responses` (`qid`, `ip`) VALUES ('" . $_POST['questions'] . "', '" . $_SERVER['REMOTE_ADDR'] . "')");
    } else {
        $error = 'You Already Voted';
    }
} else if (!isset($_POST['questions']) && isset($_POST['vote'])) {
    $error = 'You Need To Select a Question';
}

include 'poll.php';
?>