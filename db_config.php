<?php
	
	/* Database configuration */	

	$mysql_username = "root";
	$mysql_password = "";
	$mysql_hostname = "127.0.0.1";
	$mysql_database = "fingerprints";

	$db = mysqli_connect($mysql_hostname,$mysql_username,$mysql_password,$mysql_database) or die('Error : cannot connect to database');
?>