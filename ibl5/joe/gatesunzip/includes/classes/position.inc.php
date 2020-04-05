<?
/***************************************************************************
 *                                position.inc.php
 *                            -------------------
 *   begin                : Monday, Apr 7, 2008
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

define (kPositionK, 10);
define (kPositionP, 9);

class position {
  function option_list() {
    $statement = "select * from position order by position_id";
    $result = mysql_query($statement);
    while ($row = mysql_fetch_array($result)) {
      if ($row['position_id'] == $_GET['position_id']) {
	$selected = " selected";
      } else {
	$selected = '';
      }
      $html .= '
          <option value="'.$row['position_id'].'"'.$selected.'>'.$row['position_name'].'</option>';
    }
    return $html;
  }
}
?>