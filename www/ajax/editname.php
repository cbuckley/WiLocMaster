<?php

$id = $_POST['id'];
preg_match("/cMac_(.+)/", $id, $searching);
if(array_key_exists(1,$searching))	{
	$name = mysql_real_escape_string($_POST['value']);
	$con = mysqli_connect("localhost","wiloc","q9MF2Jbed9S7DPmP","wiloc");
	$sql = "SELECT * from clients where clMac = '".$searching[1]."'";
	$result = mysqli_query($con, $sql);
	if($result->num_rows > 0)	{
		$sql2 = "update clients set clName = '".$name."' where clMac = '". $searching[1] ."'";
	}
	else	{
		$sql2= "INSERT INTO `wiloc`.`clients` (`clMac`, `clActive`, `clName`) VALUES ('".$searching[1]."', 0, '$name')";
	}
	$result2 = mysqli_query($con, $sql2);
	echo $name;
}

?>
