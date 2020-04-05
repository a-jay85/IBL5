<?php
	
	$query = mysql_query("SELECT * FROM `poll` ORDER BY `id` DESC LIMIT 1");
	$rows = mysql_num_rows($query);
	
	if($rows > 0){
		$poll = mysql_fetch_array($query);
		$title = $poll['name'];
	} else {
		$title = 'No Poll Yet';
	}
	
	$query = mysql_query("SELECT COUNT(`id`) as hits FROM `responses` GROUP BY `qid`");
	while($row = mysql_fetch_array($query)){
		$me[] = $row['hits'];
	}
	$max = max($me);
	
	$query = mysql_query("SELECT `questions`.`pid` FROM  `responses`, `questions` WHERE `responses`.`qid`=`questions`.`id` AND `responses`.`ip`='".$_SERVER['REMOTE_ADDR']."' AND pid='".$poll['id']."'");
	
	if(mysql_num_rows($query) > 0){
	$total = mysql_query("SELECT `questions`.`pid` FROM  `responses`, `questions` WHERE `responses`.`qid`=`questions`.`id` AND pid='".$poll['id']."'");
	$total = mysql_num_rows($total);
?>
<table width="300" cellpadding="0" cellspacing="0" border="0" class="maintable" align="center">
	<tr>
		<td valign="top" align="center" class="title"><?php echo $title; ?></td>
	</tr>
	<?php
		$query = mysql_query("SELECT * FROM `questions` WHERE `pid`='".$poll['id']."' ORDER BY `question`");
		$questions = mysql_num_rows($query);
		if($questions > 0){
	?>
	<tr>
		<td valign="top" style="padding: 5px;">
		<table width="100%" cellpadding="0" cellspacing="0" border="0" class="question">
			<?php
				while($question = mysql_fetch_array($query)){
					$responses = mysql_query("SELECT count(id) as total FROM `responses` WHERE qid='".$question['id']."'");
					$responses = mysql_fetch_array($responses);
					
					if($total > 0 && $responses['total'] > 0){
						$percentage = round(($responses['total'] / $max) * 100);
					} else {
						$percentage = 0;
					}
					
					$percentage2 = 100 - $percentage;
			?>
				<tr>
					<td valign="top" nowrap="nowrap"><?php echo $question['question']; ?></td>
					<td valign="top" height="10" width="100%" style="padding: 0px 10px;">
					<table width="100%" cellpadding="0" cellspacing="0" border="0">
						<tr>
							<td valign="top" width="<?php echo $percentage; ?>%" <?php if($percentage > 0){?>style="background: url('images/bar.jpg') repeat-x;"<?php } ?>><img src="images/dot.gif" width="1" height="19" /></td>
							<td valign="top" width="<?php echo $percentage2; ?>%"></td>
						</tr>
					</table>
					</td>
					<td valign="top"><?php echo $responses['total']; ?></td>
				</tr>
			<?php
			}
			?>
			<tr>
				<td valign="top" colspan="3" align="center" style="padding: 10px 0px 0px 0px;">Total Votes: <?php echo $total; ?></td>
			</tr>
		</table>
		</td>
	</tr>
	<?php
		}
	?>
</table>
<?php
	} else {
?>
<table width="300" cellpadding="0" cellspacing="0" border="0" class="maintable" align="center">
	<tr>
		<td valign="top" align="center" class="title"><?php echo $title; ?></td>
	</tr>
	<?php
		$query = mysql_query("SELECT * FROM `questions` WHERE `pid`='".$poll['id']."' ORDER BY `question`");
		$questions = mysql_num_rows($query);
		if($questions > 0){
	?>
	<tr>
		<td valign="top" style="padding: 5px;">
		<form name="poll" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
		<table width="100%" cellpadding="0" cellspacing="0" border="0" class="question">
		<?php
			if(isset($error)){
		?>
			<tr>
				<td valign="top" colspan="2" align="center" style="padding: 0px 0px 10px 0px;"><?php echo $error; ?></td>
			</tr>
		<?php
			}
		?>
			<?php
				while($question = mysql_fetch_array($query)){
			?>
				<tr>
					<td valign="top" style="padding: 0px 10px 0px 0px;"><input type="radio" name="questions" value="<?php echo $question['id']; ?>" /></td>
					<td valign="top" width="100%"><?php echo $question['question']; ?></td>
				</tr>
			<?php
			}
			?>
			<tr>
				<td valign="top" colspan="2" align="center" style="padding: 10px 0px 0px 0px;"><input type="submit" name="vote" value="Submit Vote" /><br /></td>
			</tr>
		</table>
		</form>
		</td>
	</tr>
	<?php
		}
	?>
</table>
<?php
	}
?>
