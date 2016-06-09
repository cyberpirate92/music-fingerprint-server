<?php
  require_once('TrackMetadata.php');

  // compares a given fingerprint with those in the Database
  function compareFingerprint($fingerprint)
  {
    // TODO: Come up with a comparison algorithm, currently using raw substring matching
    require('db_config.php');
    require('config.php');

    echo "Received Fingerprint : " . $fingerprint;
    $code = mysqli_escape_string($db,$fingerprint);
    $query = "SELECT track_id from ". $GLOBALS['FINGERPRINT_TABLE'] ." WHERE code LIKE *$fingerprint*";
    $result = mysqli_query($db,$query);
    if($result)
    {
      $numMatches = mysqli_num_rows($result);
      if($numMatches > 0)
      {
        echo "Matches found : " . $numMatches;
        while($row = mysqli_fetch_array($result))
        {
          echo "Matched : " . $row['track_id'];
          $meta = getTrackMetadata($row['track_id']);
          if($meta != NULL)
          {
            $meta->displayMetadata();
          }
          else
          {
            echo "Track Metadata NOT found!";
          }
        }
      }
    }
    else
    {
      return "No match found";
    }
    mysqli_close($db);
  }

  // returns a track metadata object for the given track_id or NULL if no track exists with the specified track_id
  function getTrackMetadata($trackID)
  {
    require('db_config.php');
    $metadata = NULL;
    $query = "SELECT * FROM " . $GLOBALS['METADATA_TABLE'] . " WHERE track_id = $trackID";
    $result = mysqli_query($db,$query);
    if($result)
    {
      $metdata = new TrackMetadata();
      while($row = mysqli_fetch_array($result))
      {
        $metadata->set($row['track_name'], $row['album'], $row['artist']);
        break;
      }
    }
    mysqli_close($db);
    return $metadata;
  }

  if(!empty($_GET) && $_GET['code'])
  {
    $code = $_GET['code'];
    compareFingerprint($code);
  }
?>
