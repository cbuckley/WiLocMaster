<?php

$clmac = $_GET['clmac'];
$tracking = $_GET['tracking'];
	if(isset($clmac) && isset($tracking))	{
	        $con = mysqli_connect("localhost","wiloc","q9MF2Jbed9S7DPmP","wiloc");
		$sql = "select * from clients where clMac = '$clmac'";
		$result = mysqli_query($con, $sql);
		if(mysqli_num_rows($result) > 0)	{
			$sql = "update clients set clActive = $tracking where clMac = '$clmac'";
		}
		else	{
			$sql = "INSERT INTO clients (`clMac`, `clActive`) VALUES ('$clmac', $tracking)";
		}

	        $result = mysqli_query($con, $sql);
	}


?>
