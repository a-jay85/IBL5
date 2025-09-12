<?php
require '../mainfile.php';

echo "<html><head><title>IBL5 Web Tools</title></head><body>";
echo "<h1>IBL5 Web Tools</h1>";
echo "<h2>Trade Management</h2>";
echo "<ul>";
echo "<li><a href='../modules/Trading/maketradeoffer.php'>Make Trade Offer</a></li>";
echo "<li><a href='../modules/Trading/accepttradeoffer.php'>Accept Trade Offer</a></li>";  
echo "<li><a href='../modules/Trading/rejecttradeoffer.php'>Reject Trade Offer</a></li>";
echo "</ul>";

echo "<h2>Free Agency</h2>";
echo "<ul>";
echo "<li><a href='../modules/Free_Agency/freeagentoffer.php'>Make Free Agent Offer</a></li>";
echo "<li><a href='../modules/Free_Agency/freeagentfinish.php'>Finish Free Agency</a></li>";
echo "<li><a href='../modules/Free_Agency/freeagentofferdelete.php'>Delete Free Agent Offer</a></li>";
echo "<li><a href='../modules/Free_Agency/upcomingfa.php'>Upcoming Free Agents</a></li>";
echo "</ul>";

echo "<h2>Draft</h2>";
echo "<ul>";
echo "<li><a href='../modules/Player/draft.php'>View Draft</a></li>";
echo "<li><a href='../modules/College_Scouting/draft_selection.php'>Draft Selection</a></li>";
echo "</ul>";

echo "</body></html>";
?>