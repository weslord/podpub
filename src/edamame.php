<?php
  class Edamame {
    protected $db;
    protected $series;
    protected $episodes;
    protected $verified = FALSE;

    function __construct($dbpath) {
      if (file_exists($dbpath)) {
        $dsn = "sqlite:".$dbpath;
        $this->db = new PDO($dsn); // add error handling...

        $this->adminVerify();

      } else {
        // this warning is clearly misplaced, need better error system
        echo "<div class=\"edamame-warning\">Database not found.</div>";
      }
      
    }

    protected function setTokens($email,$persistent){
      $token = bin2hex(random_bytes(32));
      $hashedToken = hash("sha256",$token);

      if ($persistent) {
        $cookieExpiry = time()+60*60*24*7;
        $tokenExpiry = time()+60*60*24*7;
        $persistent = TRUE;
      } else {
        $cookieExpiry = 0;
        $tokenExpiry = time()+60*30;
        $persistent = FALSE;
      }

      setcookie("edamame-admin-token",$token,$cookieExpiry);
      setcookie("edamame-admin-email",$email,$cookieExpiry);

      $query = $this->db->prepare('UPDATE admin SET token=:token, timestamp=:expiry, persistent=:persistent WHERE email = :email;');
      $query->execute(array(':token' => $hashedToken, ':expiry' => $tokenExpiry, ':persistent' => $persistent, ':email' => $email));
    }
    
    protected function adminVerify() {
      if ($_POST['login'] == "Log In"){
        $email = $_POST['email'];
        $formPass = $_POST['password'];
        
        $query = $this->db->prepare('SELECT password FROM admin WHERE email = :email;');
        $query->execute(array(':email' => $email));
        $dbPass = $query->fetch(PDO::FETCH_ASSOC)['password'];

        if (password_verify($formPass,$dbPass)){
          $this->setTokens($email,$_POST['remember']);
          $this->verified = TRUE;
        } else {
          $this->verified = FALSE;
        }
        
      } else if ($_COOKIE['edamame-admin-token']) {
        $userToken = hash("sha256",$_COOKIE['edamame-admin-token']);
        $email = $_COOKIE['edamame-admin-email'];

        $query = $this->db->prepare('SELECT token, persistent, timestamp FROM admin WHERE email=:email;');
        $query->execute(array(':email' => $email));
        $results = $query->fetch(PDO::FETCH_ASSOC);
        $dbToken = $results['token'];
        $persistent = $results['persistent'];
        $expiry = $results['timestamp'];

        if (dbToken && $expiry > time() && hash_equals($dbToken,$userToken)) {
          if ($_POST['login'] == "Log Out"){
            $query = $this->db->prepare('UPDATE admin SET token = null, timestamp = null, persistent = null WHERE email=:email');
            $query->execute(array(':email'=>$email));

            setcookie("edamame-admin-token",NULL,time()-3600);
            setcookie("edamame-admin-email",NULL,time()-3600);

            $this->verified = FALSE;
          } else {
            $this->setTokens($email,$persistent);
            $this->verified = TRUE;
          }
        } else {
         $this->verified = FALSE;
        }
      } else {
        $this->verified = FALSE;
      }
    }
    
    public function adminStatus() {
      return $this->verified;
    }
    
    public function adminLogin() {
      if ($this->verified){
        ?>
          <form enctype="multipart/form-data" method="post" action="">
            <input type="hidden" name="login" value="Log Out">
            <input type="submit" value="Log Out"/>
          </form>
        <?php
      } else {
        ?>
          <form enctype="multipart/form-data" method="post" action="">
            <input type="hidden" name="login" value="Log In">
            <input type="email" name="email">
            <input type="password" name="password">
            <label><input type="checkbox" name="remember">Remember Me</label>
            <br>
            <input type="submit" value="Log In"/>
          </form>
        <?php
      }
    }

    public function seriesInfo() {
      $this->series = $this->db->query('SELECT * FROM seriesinfo;')->fetch(PDO::FETCH_ASSOC);
      ?>
        <div id="edamame-series-info">
          <h2><?= $this->series['title']; ?></h2>
          <p><?= $this->series['longdesc']; ?></p>
          <img src="<?= $this->series['imageurl']?>" width="250px" height="250px" />
          <a href="feed.php">RSS feed</a><?php //get from db ?>
        </div>
      <?php
    } // seriesInfo

    protected function deleteEpisode($episodeNumber) {
      if ($this->verified) {
        $query = $this->db->prepare('DELETE FROM episodes WHERE number=:episode;');
        $query->execute(array(':episode' => $episodeNumber));
      }
    }

    public function listEpisodes() {
      if ($_POST['delete-episode']) {
        $this->deleteEpisode($_POST['delete-episode']);
      }
      if ($_GET['episode']) {
        $this->episodes = $this->db->query('SELECT * FROM episodes WHERE number = :episode ORDER BY number DESC;');
        $this->episodes->execute(array(':episode' => $_GET['episode']));
      } else {
        $this->episodes = $this->db->query('SELECT * FROM episodes ORDER BY number DESC;');
      }
      ?>
        <div id="edamame-episodes">
          <?php
            // reset pointer?
            while ($episode = $this->episodes->fetch(PDO::FETCH_ASSOC,PDO::FETCH_ORI_NEXT)) {
          ?>

            <div class="edamame-episode" id="edamame-ep-<?= $episode['number'] ?>">
              <h3 class="edamame-title"><a href="?episode=<?= $episode['number'] ?>"><?= $episode['number'] ?> - <?= $episode['title'] ?></a></h3>
              <span class="edamame-timestamp"><?= date('l F jS, Y', $episode['timestamp']); ?></span>
              <div class="edamame-longdesc"><?= str_replace(['<![CDATA[',']]>'],"",$episode['longdesc']) ?></div>
              <a class="edamame-mediaurl" href="<?= $episode['mediaurl'] ?>">mp3</a>
              <?php
                if ($this->verified) {
                  ?>
                  <form enctype="multipart/form-data" method="post" action="">
                    <input type="hidden" name="delete-episode" value="<?= $episode['number'] ?>">
                    <input type="submit" value="Delete Episode"/>
                  </form>
                  <?php
                }
              ?>
            </div>

          <?php } ?>

        </div>
      <?php

    } // listEpisodes

    public function adminSeries($formTargetPath = "") {
      if (! $this->verified) {
        echo "<div class=\"edamame-warning\">Please log in to edit series info</div>";
      } else {
        if ($_POST['form-type'] == "series") {
          $this->writeSeries();
        }

        $this->series = $this->db->query('SELECT * FROM seriesinfo;')->fetch(PDO::FETCH_ASSOC);
        $series = $this->series;
        include "series-form.inc";
      }
    } // adminSeries
    
    public function adminEpisode($formTargetPath = "") {
      if (! $this->verified) {
        echo "<div class=\"edamame-warning\">Please log in to edit episode info</div>";
      } else {
        if ($_POST['form-type'] == "episode") {
          $this->writeEpisode();
        }

        $this->series = $this->db->query('SELECT * FROM seriesinfo;')->fetch(PDO::FETCH_ASSOC);
        $series = $this->series;
        $this->episodes = $this->db->query('SELECT * FROM episodes ORDER BY number DESC;');

        $lastepisode = $this->episodes->fetch(PDO::FETCH_ASSOC,PDO::FETCH_ORI_NEXT);
        include "episode-form.inc";
      }
    }

    protected function writeEpisode() {
      $seriesupdate = $this->db->prepare("
        INSERT INTO `episodes` (
          number,
          title,
          artist,
          shortdesc,
          longdesc,
          mediatype,
          timestamp,
          duration)
        VALUES (
          :number,
          :title,
          :artist,
          :shortdesc,
          :longdesc,
          :mediatype,
          :timestamp,
          :duration);");

      $seriesupdate->execute(array(
        ':number' => $_POST['ep-number'],
        ':title' => $_POST['ep-title'],
        ':artist' => $_POST['ep-artist'],
        ':shortdesc' => $_POST['ep-shortdesc'],
        ':longdesc' => $_POST['ep-longdesc'],
        ':mediatype' => $_POST['ep-mediatype'],
        ':timestamp' => strtotime($_POST['ep-timestamp']),
        ':duration' => $_POST['ep-duration'],
        ));
    }

    public function writeData() {
      if ($_POST['form-type'] == "series") {
        $this->writeSeries();
      } else if ($_POST['form-type'] == "episode") {
        $this->writeEpisode();
      }
    }

    protected function writeSeries() {
      $seriesupdate = $this->db->prepare("
        UPDATE `seriesinfo`
        SET `title`=:title,
            `artist`=:artist,
            `copyright`=:copyright,
            `url`=:url,
            `owner`=:owner,
            `email`=:email,
            `shortdesc`=:shortdesc,
            `longdesc`=:longdesc,
            `category`=:category,
            `explicit`=:explicit,
            `language`=:language
        WHERE `_rowid_`='1';");

      $seriesupdate->execute(array(
        ':title' => $_POST['series-title'],
        ':artist' => $_POST['series-artist'],
        ':copyright' => $_POST['series-copyright'],
        ':url' => $_POST['series-url'],
        ':owner' => $_POST['series-owner'],
        ':email' => $_POST['series-email'],
        ':shortdesc' => $_POST['series-shortdesc'],
        ':longdesc' => $_POST['series-longdesc'],
        ':category' => $_POST['series-category'],
        ':explicit' => $_POST['series-explicit'],
        ':language' => $_POST['series-language'],
      ));

      if (!$imagedir) {
        $imagedir = getcwd();
      }

      // check $_FILE for errors, type, etc
      if ($_FILES['series-imageurl']['error'] == UPLOAD_ERR_OK) {
        // save to series cover location
        $imagepath = $imagedir . "/cover.png"; // check for type, set extension
        move_uploaded_file($_FILES['series-imageurl']['tmp_name'],$imagepath);

        // delete/archive existing, if different
      }
    }
    
    public function rss() {
      $series = $this->db->query('SELECT * FROM seriesinfo;')->fetch(PDO::FETCH_ASSOC);
      $episodes = $this->db->query('SELECT * FROM episodes ORDER BY number DESC;');

      include "feed.rss";
    }
  }

?>
