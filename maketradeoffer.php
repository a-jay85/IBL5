<?php

$username = "iblhoops_chibul";
$password = "oliver23";
$database = "iblhoops_iblleague";

mysql_connect(localhost,$username,$password);
@mysql_select_db($database) or die( "Unable to select database");

$query0="SELECT * FROM nuke_ibl_trade_autocounter ORDER BY `counter` DESC";
$result0=mysql_query($query0);
$tradeofferid = mysql_result($result0,0,"counter")+1;

$query0a="INSERT INTO nuke_ibl_trade_autocounter ( `counter` ) VALUES ( '$tradeofferid') ";
$result0a=mysql_query($query0a);

$Team_Offering = $_POST['Team_Name'];
$Team_Receiving = $_POST['Team_Name2'];
$Swapat = $_POST['half'];
$Fields_Counter = $_POST['counterfields'];
$Fields_Counter = $Fields_Counter + 1;
$error = 0;
//-----CHECK IF SALARIES MATCH-----

$j=0;
while ($j < $Swapat)
{
	$Type=$_POST['type'.$j];
	$Index=$_POST['index'.$j];
	$Check=$_POST['check'.$j];
	$Salary=$_POST['contract'.$j];
	$PayrollA=$PayrollA+$Salary;
	if ($Check == "on")
	{
		$Total_SalaryA=$Total_SalaryA+$Salary;
		echo "Total Trade Salary My Team: $$Total_SalaryA<br>";
	}
	$j++;
}
echo "My Payroll: $$PayrollA<br><br>";
while ($j < $Fields_Counter)
{
	$Type=$_POST['type'.$j];
	$Index=$_POST['index'.$j];
	$Check=$_POST['check'.$j];
	$Salary=$_POST['contract'.$j];
	$PayrollB=$PayrollB+$Salary;
	if ($Check == "on")
	{
		$Total_SalaryB=$Total_SalaryB+$Salary;
		echo "Total Trade Salary His Team: $$Total_SalaryB<br>";
	}
	$j++;
}
echo "His Payroll: $$PayrollB<br><br>";
$New_PayrollA=$PayrollA-$Total_SalaryA+$Total_SalaryB;
$New_PayrollB=$PayrollB-$Total_SalaryB+$Total_SalaryA;
echo "Your New Payroll: $$New_PayrollA<br>His New Payroll: $$New_PayrollB<br>";
//if ($PayrollA < 7000)
//{
	if ($New_PayrollA > 7000)
	{
		echo "This trade is illegal since it puts you over the hard cap.";
		$error=1;
	}
//}else{
	//if ($New_PayrollA > $PayrollA)
	//{
		//echo "This trade is illegal since you are over the cap and can only make trades that lower your total salary";
		//$error=1;
	//}
//}

//if ($PayrollB < 7000)
//{
	if ($New_PayrollB > 7000)
	{
		echo "This trade is illegal since it puts other team over the hard cap.";
		$error=1;
	}
//}else{
	//if ($New_PayrollB > $PayrollB)
	//{
		//echo "This trade is illegal since other team is over the cap and can only make trades that lower their total salary";
		//$error=1;
	//}
//}

//-----END SALARY MATCH CHECK-----
if ($error == 0)
{

	$k=0;

	while ($k < $Swapat)
	{
	$Type=$_POST['type'.$k];
	$Index=$_POST['index'.$k];
	$Check=$_POST['check'.$k];
	if ($Check == "on")
	  {
	  $queryi = "INSERT INTO nuke_ibl_trade_info ( `tradeofferid` , `itemid` , `itemtype` , `from` , `to` , `approval` ) VALUES ( '$tradeofferid', '$Index', '$Type', '$Team_Offering', '$Team_Receiving' , '$Team_Receiving' )";
	  $resulti=mysql_query($queryi);
	  }
	$k++;
	}

	while ($k < $Fields_Counter)
	{
	$Type=$_POST['type'.$k];
	$Index=$_POST['index'.$k];
	$Check=$_POST['check'.$k];
	if ($Check == "on")
	  {
	  $queryi = "INSERT INTO nuke_ibl_trade_info ( `tradeofferid` , `itemid` , `itemtype` , `from` , `to` , `approval` ) VALUES ( '$tradeofferid', '$Index', '$Type', '$Team_Receiving', '$Team_Offering' , '$Team_Receiving' )";
	  $resulti=mysql_query($queryi);
	  }
	$k++;
	}
	echo "Trade Offer Entered Into Database. Go back <a href='http://www.iblhoops.net/modules.php?name=Team&op=reviewtrades'>Trade Review Page</a>";
}
?>