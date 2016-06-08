<?php

	/*
	*	MUSIC FINGERPRINT SERVER
	*	--------------------------
	*		# Currently supports only mp3 files
	*
	*	ERROR DESCRIPTIONS
	*	-------------------
	*	002 - Cannot obtain duration of the uploaded music file
	*	003 - Metadata didn't get stored in db
	*	004 - Track fingerprint already exists in database
	*/

	/* ------- CONFIG -------- */
	$GLOBALS['METADATA_TABLE']    = "track_metadata";
	$GLOBALS['FINGERPRINT_TABLE'] = "track_fingerprints";
	$GLOBALS['UPLOAD_FOLDER']     = "uploads/";
	$GLOBALS['CODEGEN_PATH']      = "/home/zen/Desktop/codegen/echoprint-codegen";
	/* ------- CONFIG -------- */

	// global variables
	$last_track_id = null;

	// class for storing metadata information
	class TrackMetadata
	{
		public $artist;
		public $album;
		public $trackName;

		function set($trackName, $album = null, $artist = null)
		{
			$this->artist    = $artist;
			$this->album     = $album;
			$this->trackName = $trackName;
		}
	}

	// Add the track metadata to the database
	function saveTrackMetadata($output)
	{
		global $last_track_id;
		//displayMetadata($output);
		require_once('db_config.php');
		$meta = new TrackMetadata();
		$meta->set($output->metadata->title,$output->metadata->artist,$output->metadata->release);
		$query = "INSERT INTO ". $GLOBALS['METADATA_TABLE'] ." (track_name, artist, album) VALUES ('". $meta->trackName ."', '". $meta->artist ."', '". $meta->album ."')";
		$result = mysqli_query($db,$query);
		$last_track_id = mysqli_insert_id($db);
		mysqli_close($db);
		return $result;
	}

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

	// utility function to get json data as a php class instance
	function extractData($filename, $startOffset, $length, $saveMetadata = false)
	{
		$command = $GLOBALS['CODEGEN_PATH']." $filename $startOffset $length";
		echo $command."<br>";
		$output = shell_exec($command);
		$json = substr($output, 1, strlen($output)-3);
		$data = json_decode($json);
		$data = $data->code;
		if($saveMetadata)
		{
			if(!fingerprintExists($data))
			{
				saveTrackMetadata($data);
			}
			else
			{
				echo "Error (004) : Track already exists";
				die();
			}
		}
		unset($json);
		unset($output);
		return $data;
	}

	// function to check if a fingerprint already exists in the database
	function fingerprintExists($fingerprint)
	{
		$result = false;
		require('db_config.php');
		$hash = md5($fingerprint);
		$query = "SELECT track_id FROM ". $GLOBALS['FINGERPRINT_TABLE'] ." WHERE hash='$hash'";
		$result = mysqli_query($db,$query);
		if($result)
		{
			if(mysqli_num_rows($result) > 0)
			{
				$result = true;
			}
		}
		mysqli_close($db);
		return $result;
	}

	// function that splits the music file into 1 minute parts and adds their fingerprints to the database
	function addMusicFingerprint($filename,$duration)
	{
		$count = 0;
		$outputs = array();

		$numMinutes = floor($duration / 60);
		$numSeconds = $duration % 60;

		for($i=0;$i<$numMinutes;$i++)
		{
			$startOffset = 60 * $i;
			if($count == 0)
			{
				$data = extractData($filename,$startOffset,60,true);
			}
			else
			{
				$data = extractData($filename,$startOffset,60);
			}
			array_push($outputs, $data);
			$count++;
		}

		if($numSeconds > 0)
		{
			$startOffset = $numMinutes * 60;
			if($count == 0)
			{
				$data = extractData($filename,$startOffset,$numSeconds,true);
			}
			else
			{
				$data = extractData($filename,$startOffset,$numSeconds);
			}
			array_push($outputs, $data);
			$count++;
		}
		$count = 0;
		if($outputs != NULL)
		{
			global $last_track_id;
			if($last_track_id != null)
			{
				require('db_config.php');
				foreach($outputs as $output)
				{
					$hash = md5($output);
					$code = mysqli_escape_string($db,$output);
					$query = "INSERT INTO ". $GLOBALS['FINGERPRINT_TABLE'] ." (code, hash, minute, track_id) VALUES ('$code','$hash',$count,$last_track_id)";
					echo "<pre>$query</pre><br>";
					$tmp_result = mysqli_query($db,$query);
					echo "$count => $tmp_result";
					$count++;
				}
				mysqli_close($db);
			}
			else // for some reason, the metadata didn't get stored :(
			{
				echo "ERROR (003): cannot fingerprint track, please try again!";
			}
		}
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
						$filename = $GLOBALS['UPLOAD_FOLDER']."temp.".$ext;

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
								$duration = -1; // unable to get track size, file is corrupt maybe
							}

							if($duration != -1)
							{
								echo "calling method with $filename and $duration as params. <br>";
								addMusicFingerprint($filename,$duration);
							}
							else
							{
								echo "ERROR (002) : cannot fingerprint music track, please try again";
							}
						}
						unlink($filename);
					}
				}
			}
		?>
	</body>
</html>
