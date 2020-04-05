<?
/***************************************************************************
 *                                player.inc.php
 *                            -------------------
 *   begin                : Monday, May 12, 2008
 *   copyright            : (C) 2008 J. David Baker
 *   email                : me@jdavidbaker.com
 *
 ***************************************************************************/

/***************************************************************************
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 ***************************************************************************/

class player {
  function player($player_id) {
    $this->player_id = mysql_real_escape_string($player_id);
  }

  function draw() {
    global $login;
    $tables[] = "player";
    $wheres[] = "player.player_id = '".$this->player_id."'";
    $col[] = "player.player_name";
    $tables[] = "position";
    $wheres[] = "position.position_id = player.position_id";
    $tables[] = "combine_ratings";
    $wheres[] = "combine_ratings.position_id = position.position_id";
    $tables[] = "height_weight";
    $wheres[] = "height_weight.position_id = player.position_id";
    if (!$login->is_admin()) {
      $joins[] = "left join selection on selection.player_id = player.player_id and
selection.team_id = '".$login->team_id()."'";
      $col[] = "selection.player_id is_selected";
      $col[] = "selection.selection_priority";
    }
    $col[] = "player_fga";
    $col[] = "player_fgp";
    $col[] = "height_weight.*";
    $col[] = "player.position_id";
    $col[] = "position.position_name";
    $col[] = "player_age";
    $col[] = "player_height";
    $col[] = "player_weight";
    $col[] = "if (player_experience > 1, player_experience, 'R') player_experience";
    $col[] = "team_player.player_intelligence";
    $col[] = "team_player.player_personality";
    $col[] = "player.player_popularity";
    $col[] = "team_player.player_mentor_to";
    $col[] = "team_player.player_interviewed";
    $col[] = "team_player.player_impression";
    $col[] = "team_player.player_affinities";
    $col[] = "team_player.player_character";
    $col[] = "player.player_vol";
    $col[] = "player.player_solec";
    $col[] = "if (player.player_solec <= combine_ratings.combine_low_sole,
'color: #0b0; font-weight: bold',
if (player.player_solec >= combine_ratings.combine_high_sole,
'color: #00f; font-weight: bold', NULL)) player_solec_style";
    $col[] = "player.player_40";
    $col[] = "if (player.player_40 >= combine_ratings.combine_low_40, 'color: #0b0; font-weight: bold',
if (player.player_40 <= combine_ratings.combine_high_40, 'color: #00f; font-weight: bold', NULL)) player_40_style";
    $col[] = "player.player_bench";
    $col[] = "if (player.player_bench <= combine_ratings.combine_low_strength,
'color: #0b0; font-weight: bold',
if (player.player_bench >= combine_ratings.combine_high_strength,
'color: #00f; font-weight: bold', NULL)) player_bench_style";
    $col[] = "player.player_agil";
    $col[] = 'if (player.player_agil >= combine_ratings.combine_low_agil,
"color: #0b0; font-weight: bold",

if (player.player_agil <= combine_ratings.combine_high_agil,
"color: #00f; font-weight: bold", NULL)

) player_agil_style';
    $col[] = "player.player_broad";
    $col[] = 'if (player.player_broad <= combine_ratings.combine_low_broad,
"color: #0b0; font-weight: bold",

if (player.player_broad >= combine_ratings.combine_high_broad,
"color: #00f; font-weight: bold", NULL)

) player_broad_style';
    $col[] = "player.player_pos_drill";
    $col[] = 'if (combine_ratings.combine_high_pos is not null,

if (player.player_pos_drill <= combine_ratings.combine_low_pos,
"color: #0b0; font-weight: bold",

if (player.player_pos_drill >= combine_ratings.combine_high_pos,
"color: #00f; font-weight: bold", NULL)

)

,

NULL) player_pos_drill_style';
    $col[] = "player.player_developed";
    $joins[] = "left join player_comments on player_comments.team_id = '".mysql_real_escape_string($login->team_id())."' and
player_comments.player_id = player.player_id";
    $col[] = "player_comments.player_comments_text";

    $joins[] = "left join team_player on team_player.player_id = player.player_id and team_player.team_id = '".mysql_real_escape_string($login->team_id())."'";
    $joins[] = "left join pick on pick.player_id = player.player_id";
    $joins[] = "left join team on team.team_id = pick.team_id";
    $col[] = "team.team_name";
    $col[] = "pick.pick_id";

    $statement = "select ".implode(",",$col)." from (".implode(",",$tables).")
".implode(" ",$joins)."
where ".implode(" and ",$wheres);
    $row = mysql_fetch_array(mysql_query($statement));

    $html = file_get_contents("includes/html/player.html");
    
    $html = str_replace("%player_name%", "#".$this->player_id." ".$row['player_name'], $html);
    $html = str_replace("%position_name%", $row['position_name'], $html);
    $this->position_id = $row['position_id'];
    $html = str_replace("%combine%", $this->draw_combine($row), $html);
    $html = str_replace("%attributes%", $this->draw_attributes($row['position_id']), $html);
    if ($row['pick_id']) {
      $extra = "Selected by ".$row['team_name'].", pick ".calculate_pick($row['pick_id']);
      $selection = $row['pick_id'];
    } else {
      if (!$login->is_admin()) {
	if ($login->can_pick()) {
	  $extra = '<a href="make_pick.php?player_id='.$this->player_id.'">Select this player</a>';
	}
	if ($login->team_id()) {
	  if (!$row['is_selected']) {
	    $extra .= '
<p><form method="get" action="add_player_to_selection.php">
<input type="hidden" name="player_id" value="'.$this->player_id.'">
<input type="submit" value="Add to selections">
</form>';
	  } else {
	    $extra .= '
<p>Currently in your selection set, priority '.$row['selection_priority'];
	  }
	}
      }
    }
    $html = str_replace("%extra%", $extra, $html);
    $html = str_replace("%id%", $this->player_id, $html);
    if ($login->team_id()) {
      $html = str_replace("%comments%", file_get_contents('includes/html/player_comments.html'), $html);
      $html = str_replace("%id%", $this->player_id, $html);
    } else {
      $html = str_replace("%comments%", "", $html);
    }
    $html = str_replace("%comments%", $row['player_comments_text'], $html);
    $html = str_replace("%left_nav%", $this->left_nav($selection), $html);
    $html = str_replace("%right_nav%", $this->right_nav($selection), $html);
    
    return $html;
  }

  function draw_combine(&$row) {
    $html .= '
<table align="center">
  <tr>
    <td align="right">Age:</td>
    <td>'.$row['player_age'].'</td>
  </tr>
  <tr>
    <td align="right">Height:</td>';
    if ($row['player_height'] <= $row['height_short']) {
      $comment = "Short";
    } elseif ($row['player_height'] <= $row['height_wba']) {
      $comment = "Way Below Average";
    } elseif ($row['player_height'] <= $row['height_ba']) {
      $comment = "Below Average";
    } elseif ($row['player_height'] <= $row['height_avg']) {
      $comment = "Average";
    } elseif ($row['player_height'] <= $row['height_aa']) {
      $comment = "Above Average";
    } else {
      $comment = "Way Above Average";
    }
    $html .= '
    <td>'.height_convert($row['player_height']).' ('.$comment.')</td>
  </tr>
  <tr>
    <td align="right">Weight:</td>';
    if ($row['player_height'] <= $row['height_short']) {
      $comment = "Light";
    } elseif ($row['player_weight'] <= $row['weight_wba']) {
      $comment = "Way Below Average";
    } elseif ($row['player_weight'] <= $row['weight_ba']) {
      $comment = "Below Average";
    } elseif ($row['player_weight'] <= $row['weight_avg']) {
      $comment = "Average";
    } elseif ($row['player_weight'] <= $row['weight_aa']) {
      $comment = "Above Average";
    } elseif ($row['player_weight'] <= $row['weight_waa']) {
      $comment = "Way Above Average";
    } else {
      $comment = "Heavy";
    }
    $html .= '
    <td>'.$row['player_weight'].' lbs. ('.$comment.')</td>
  </tr>
  <tr>
    <td align="right">Experience:</td>
    <td>'.$row['player_experience'].'</td>
  </tr>';
    $text_array = array("player_interviewed"=>"Interviewed",
			"player_impression"=>"Impression",
			"player_solec"=>"Solecismic Score",
			"player_40"=>"40 Time",
			"player_bench"=>"Bench Reps",
			"player_agil"=>"Agility",
			"player_broad"=>"Broad Jump",
			"player_pos_drill"=>"Position Drill",
			"player_fga"=>"FGA",
			"player_fgp"=>"FGP");
    foreach($text_array as $key=>$title) {
      if ($row[$key]) {
	if ($key == "player_broad") {
	  $row[$key] = height_convert($row[$key]);
	}
	$html .= '
  <tr>
    <td align="right">'.$title.':</td>
    <td><span style="'.$row[$key.'_style'].'">'.$row[$key].'</td>
  </tr>';
      }
    }
    $bar_array = array("Intelligence"=>'player_intelligence',
		       "Personality"=>'player_personality',
		       "Popularity"=>'player_popularity',
		       "Volatility"=>'player_vol',
		       "Developed"=>'player_developed');
    $html .= $this->draw_single_val_boxes($bar_array, $row);
		       
    $html .= '
</table>';
    return $html;
  }

  function draw_single_val_boxes($array, $row) {
    foreach($array as $title=>$id) {
      if ($row[$id]) {
	$html .= '
  <tr>
    <td align="right">'.$title.':</td>
    <td>
      <div class="bar">
        <div class="bar_fill" style="width: '.$row[$id].'%"></div>
      </div>'.$row[$id].'
    </td>
  </tr>';
      }
    }
    return $html;
  }

  function draw_attributes($position_id) {
    $position_id = mysql_real_escape_string($position_id);
    global $login;
    // See if we have imported our own data
    $statement = "select * from team_player_to_attribute where team_id = '".$login->team_id()."' limit 1";
    if (mysql_num_rows(mysql_query($statement))) {
      $uploaded = true;
    } else {
      $uploaded = false;
    }
    if ($uploaded) {
      $statement = "select * from team_player where player_id = '".$this->player_id."' and team_id = '".$login->team_id()."'";
    } else {
      $statement = "select * from player where player_id = '".$this->player_id."'";
    }
    $html .='
<table align="center">';
    $row = mysql_fetch_array(mysql_query($statement));
    if ($row['player_current'] || $row['player_future']) {
      $html .= '
  <tr>
    <td align="right">Rating</td>
    <td>
      <div class="bar">
        <div class="bar_light" style="width: '.$row['player_future'].'%"></div>
        <div class="bar_fill" style="width: '.$row['player_current'].'%"></div>
      </div>'.$row['player_current'].'/'.$row['player_future'];
      $html .= '
    </td>
  </tr>';
    }
    $statement = "select * from attribute, position_to_attribute where
attribute.attribute_id = position_to_attribute.attribute_id and
position_to_attribute.position_id = '".$position_id."'
order by position_to_attribute_order";
    $result = mysql_query($statement);
    $i=0;
    $attributes = array();
    while ($row = mysql_fetch_array($result)) {
      $alias = "pa_$i";
      if ($uploaded) {
	$tables[] = "team_player_to_attribute $alias";
	$wheres[] = "$alias.team_id = '".$login->team_id()."'";
      } else {
	$tables[] = "player_to_attribute $alias";
      }
      $wheres[] = "$alias.player_id = '".$this->player_id."'";
      $wheres[] = "$alias.attribute_id = '".$row['attribute_id']."'";
      if ($row['attribute_id'] == 1) {
	$col[] = "$alias.player_to_attribute_low score_low_$i";
      } else {
	$col[] = "$alias.player_to_attribute_low score_low_$i";
	$col[] = "$alias.player_to_attribute_high score_high_$i";
      }
      $attributes[$i] = $row['attribute_name'];
      $i++;
    }
    $statement = "select ".implode(",",$col)." from ".implode(",",$tables)." where ".implode(" and ",$wheres);
    $result = mysql_query($statement);
    while ($row = mysql_fetch_array($result)) {
      foreach($attributes as $alias=>$name) {
	$html .= '
  <tr>
    <td align="right">'.$name.'</td>
    <td>
      <div class="bar">';
	if ($name == "Formations") {
	  $html .= '
        <div class="bar_fill" style="width: '.round($row['score_low_'.$alias]*100/17).'%"></div>
      </div>'.$row['score_low_'.$alias];
	} else {
	  $html .= '
        <div class="bar_light" style="width: '.$row['score_high_'.$alias].'%"></div>
        <div class="bar_fill" style="width: '.$row['score_low_'.$alias].'%"></div>
      </div>'.$row['score_low_'.$alias].'/'.$row['score_high_'.$alias];
	}
	$html .= '
    </td>
  </tr>';
      }
    }
    $html .= '
</table>';
    return $html;
  }
  
  function left_nav($selection) {
    global $login;
    // If we are a selection, show the previous pick
    $html .= '
<div style="padding: 5px">';
    $link = array();
    if ($selection) {
      $statement = "select * from pick, player, position, team where
pick_id < '$selection' and
pick.player_id = player.player_id and
position.position_id = player.position_id and
team.team_id = pick.team_id
order by pick_id desc limit 1";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['player_id']) {
	$link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">&laquo; Previous Pick:
'.$row['player_name'].' ('.$row['position_name'].') - '.$row['team_name'].'</a>';
      }
    }
    // If we aren't a team, this is as far as we go
    if ($login->team_id()) {
      // Previous available same position
      $statement = "select player.*, position.* from (player, position)
left join pick on pick.player_id = player.player_id
where pick.player_id is NULL and
position.position_id = player.position_id and
player.player_id < '".$this->player_id."' and
player.position_id = '".$this->position_id."'
order by player_id desc";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['player_id']) {
	$link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">&laquo; Previous Available '.$row['position_name'].':
#'.$row['player_id'].' '.$row['player_name'].'</a>';
      }
      // Previous available any position
      $statement = "select player.*, position.* from (player, position)
left join pick on pick.player_id = player.player_id
where pick.player_id is NULL and
position.position_id = player.position_id and
player.player_id < '".$this->player_id."'
order by player_id desc";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['player_id']) {
	$link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">&laquo; Previous Available Player:
#'.$row['player_id'].' '.$row['player_name'].' ('.$row['position_name'].')</a>';
      }
      // If in our priority queue, previous in list
      $statement = "select * from selection where team_id = '".$login->team_id()."' and player_id = '".$this->player_id."'";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['selection_priority']) {
	$statement = "select * from selection, player, position where
team_id = '".$login->team_id()."' and
position.position_id = player.position_id and
selection_priority < '".$row['selection_priority']."' and
selection.player_id = player.player_id
order by selection_priority desc limit 1";
	$row = mysql_fetch_array(mysql_query($statement));
	if ($row['player_id']) {
	  $link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">&laquo; Previous In Queue (priority '.$row['selection_priority'].'):
#'.$row['player_id'].' '.$row['player_name'].' ('.$row['position_name'].')</a>';
	}
      }
    }

    $html .= implode("<br>",$link);
    $html .= '&nbsp;';
    $html .= '</div>';
    return $html;
  }

  function right_nav($selection) {
    global $login;
    // If we are a selection, show the next pick
    $html .= '
<div style="padding: 5px">';
    $link = array();
    if ($selection) {
      $statement = "select * from pick, player, position, team where
pick_id > '$selection' and
pick.player_id = player.player_id and
position.position_id = player.position_id and
team.team_id = pick.team_id
order by pick_id limit 1";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['player_id']) {
	$link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">Next Pick:
'.$row['player_name'].' ('.$row['position_name'].') - '.$row['team_name'].' &raquo;</a>';
      }
    }
    // If we aren't a team, this is as far as we go
    if ($login->team_id()) {
      // Next available same position
      $statement = "select player.*, position.* from (player, position)
left join pick on pick.player_id = player.player_id
where pick.player_id is NULL and
position.position_id = player.position_id and
player.player_id > '".$this->player_id."' and
player.position_id = '".$this->position_id."'
order by player_id";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['player_id']) {
	$link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">Next Available '.$row['position_name'].':
#'.$row['player_id'].' '.$row['player_name'].' &raquo;</a>';
      }
      // Next available any position
      $statement = "select player.*, position.* from (player, position)
left join pick on pick.player_id = player.player_id
where pick.player_id is NULL and
position.position_id = player.position_id and
player.player_id > '".$this->player_id."'
order by player_id";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['player_id']) {
	$link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">Next Available Player:
#'.$row['player_id'].' '.$row['player_name'].' ('.$row['position_name'].') &raquo;</a>';
      }
      // If in our priority queue, next in list
      $statement = "select * from selection where team_id = '".$login->team_id()."' and player_id = '".$this->player_id."'";
      $row = mysql_fetch_array(mysql_query($statement));
      if ($row['selection_priority']) {
	$statement = "select * from selection, player, position where
team_id = '".$login->team_id()."' and
position.position_id = player.position_id and
selection_priority > '".$row['selection_priority']."' and
selection.player_id = player.player_id
order by selection_priority limit 1";
	$row = mysql_fetch_array(mysql_query($statement));
	if ($row['player_id']) {
	  $link[] = '
<a href="show_player.php?player_id='.$row['player_id'].'">Next In Queue (priority '.$row['selection_priority'].'):
#'.$row['player_id'].' '.$row['player_name'].' ('.$row['position_name'].') &raquo;</a>';
	}
      }
    }

    $html .= implode("<br>",$link);
    $html .= '&nbsp;';
    $html .= '</div>';
    return $html;
  }
}
?>