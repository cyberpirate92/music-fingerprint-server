<?php
	
	/* -------	CONFIG -------	*/
	$METADATA_TABLE    = "track_metadata";
	$FINGERPRINT_TABLE = "track_fingerprints";
	$UPLOAD_FOLDER     = "uploads/";
	$CODEGEN_PATH      = "/home/zen/Desktop/codegen/echoprint-codegen";
	/* ------- CONFIG -------- */

	// utility function for debugging 
	function displayMetadata($data)
	{
		echo "<div>";
		echo "Artist   : ". $data->metadata->artist . "<br>";
		echo "Album    : ". $data->metadata->release . "<br>";
		echo "Title    : ". $data->metadata->title ."<br>";
		echo "Duration : ". $data->metadata->duration . "<br>";
		echo "Version  : ". $data->metadata->version . "<br>";
		echo "codeCount: ". $data->code_count . "<br>";
		echo "Code     : ". $data->code . "<br>";
		echo "Code Hash: ". md5($data->code). "<br>";
		echo "</div>";
	}

	// function that splits the music file into 1 minute parts and adds their fingerprints to the database
	function addMusicFingerprint($data)
	{

	}
?>
<html>
	<head>
		<title> Ingest new music file </title>
	</head>
	<body>
		<h2> Add New Music Fingerprint </h2>
		<form action='<?php echo $_SERVER['PHP_SELF'];?>' method='POST' enctype='multipart/form-data'>
			<table>
			<tr>
				<td> File </td>
				<td> <input type='file' name='music_file' id='music_file' /> </td>
			</tr>
			<tr>
				<td>
					<input type='submit' name='submit' />
				</td>
			</tr>
			</table>
		</form>
		
		<?php
			if(!empty($_POST)) 
			{
				if(!empty($_FILES)) 
				{
					if($_FILES['music_file']) 
					{
						$ext = pathinfo($_FILES['music_file']['name'], PATHINFO_EXTENSION);
						$filename = $UPLOAD_FOLDER."temp.".$ext;

						if(move_uploaded_file($_FILES['music_file']['tmp_name'],$filename)) 
						{
							$duration = 0;
							$offset = 0;

							if(strtolower($ext) === "mp3") 
							{
								// extracting song duration
								require_once('utils.php');
								$music_file = new MP3File($filename);
								$duration = $music_file->getDuration();
								unset($music_file);
							}
							else 
							{
								$duration = 30; // setting this, as we don't know the track size
							}
							
							$command = "$CODEGEN_PATH $filename $offset $duration";
							$output = shell_exec($command);
							
							if($output != NULL) 
							{
								$json = substr($output, 1, strlen($output)-3);
								$data = json_decode($json);
								unset($json);
								displayMetadata($data);

								$artist = $data->metadata->artist;
								$album = $data->metadata->release;
								$title = $data->metadata->title;
								$duration = $data->metadata->duration;
								$version = $data->metadata->version;
								$code_count = $data->code_count;
								$code = $data->code;
								$code_hash = md5($data->code);


								require_once('db_config.php');
								$code = mysqli_escape_string($db,$code);
								$query = "INSERT INTO echoprints (artist,album,title,duration,version,code_count,code,code_hash) VALUES ('$artist','$album','$title',$duration,'$version','$code_count','$code','$code_hash')";
								//echo "<pre>$query</pre>";
								$result = mysqli_query($db,$query);
								//var_dump($result);
								mysqli_close($db);
								unlink($filename); // we're done with the music file, deleting it

							}
							else 
							{
								echo "ERROR: cannot fingerprint music track, please try again";
							}
						}
					}
				}
			}
		?>
	</body>
</html>