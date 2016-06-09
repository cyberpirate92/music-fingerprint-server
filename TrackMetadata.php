<?php

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

    function displayMetadata()
    {
      echo "<table>";
        echo "<tr>";
          echo "<td>" . $this->artist . "</td>";
          echo "<td>" . $this->album . "</td>";
          echo "<td>" . $this->trackName . "</td>";
        echo "</tr>";
      echo "</table>";
    }
  }

?>
