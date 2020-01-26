// -- DATABASE CONNECTION -->

    $conn=mysql_connect("localhost","iblhoops_chibul","oliver23") or die ("can't connect to server");
    @mysql_select_db("iblhoops_ibl5") or die ("can't select database");


// UPDATING ONLINE USER DATABASE
// (add these line below on top of your webpage)

    $uvisitor=$REMOTE_ADDR;
    $uvisitor.="|".gethostbyaddr($uvisitor);
    $utime=time();
    $exptime=$utime-300; // (in seconds)

    @mysql_query("delete from online where timevisit<$exptime");
    $uexists=@mysql_num_rows(@mysql_query("select id from online where visitor='$uvisitor'"));

    if ($uexists>0){
        @mysql_query("update online set timevisit='$utime' where visitor='$uvisitor'");
        } else {
        @mysql_query("insert into online (visitor,timevisit) values ('$uvisitor','$utime')");
    }


// DISPLAYING ONLINE USER DATABASE -->

    $rs=@mysql_query("select * from online");
    echo "<style><!--n";
    echo "body {font-family:verdana;font-size:10pt}n";
    echo "td {font-family:verdana;font-size:10pt}n";
    echo "--></style>n";
    echo "<div align=center><table><tr bgcolor=#CCCCCC>
            <td><b>Visitor IP/Host<td><b>Last visit</tr>";
    while ($ro=@mysql_fetch_array($rs)){
        echo "<tr><td>".$ro[visitor]."<td>".date('j M Y - H:i',$ro[timevisit])."</tr>";
    }
    echo "</table></div>";
    $jmlonline=@mysql_num_rows(@mysql_query("select id from online"));
    echo "<div align=center><b>There are $jmlonline user online</b></div>";

?>