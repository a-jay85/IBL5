// -- DATABASE CONNECTION -->
require 'config.php';
mysql_connect($dbhost,$dbuname,$dbpass);
@mysql_select_db($dbname) or die("Unable to select database");


// UPDATING ONLINE USER DATABASE
// (add these line below on top of your webpage)

    $uvisitor=$REMOTE_ADDR;
    $uvisitor.="|".gethostbyaddr($uvisitor);
    $utime=time();
    $exptime=$utime-300; // (in seconds)

    @$db->sql_query("delete from online where timevisit<$exptime");
    $uexists=@$db->sql_numrows(@$db->sql_query("select id from online where visitor='$uvisitor'"));

    if ($uexists>0){
        @$db->sql_query("update online set timevisit='$utime' where visitor='$uvisitor'");
        } else {
        @$db->sql_query("insert into online (visitor,timevisit) values ('$uvisitor','$utime')");
    }


// DISPLAYING ONLINE USER DATABASE -->

    $rs=@$db->sql_query("select * from online");
    echo "<style><!--n";
    echo "body {font-family:verdana;font-size:10pt}n";
    echo "td {font-family:verdana;font-size:10pt}n";
    echo "--></style>n";
    echo "<div align=center><table><tr bgcolor=#CCCCCC>
            <td><b>Visitor IP/Host<td><b>Last visit</tr>";
    while ($ro=@$db->sql_fetchrow($rs)){
        echo "<tr><td>".$ro[visitor]."<td>".date('j M Y - H:i',$ro[timevisit])."</tr>";
    }
    echo "</table></div>";
    $jmlonline=@$db->sql_numrows(@$db->sql_query("select id from online"));
    echo "<div align=center><b>There are $jmlonline user online</b></div>";

?>
