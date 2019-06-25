<?php
require_once('loadclasses.php');
require_once('auth.php');

$page = new Page('Welcome.');
$page->getCached();

$page->addBody('<div class="col-xs-12 col-md-10 col-lg-8 center-block"><center><p>Fleet-Yo is a fleet management and tracking tool intended for small gangs and medium sized fleets. This tool is inspired by others out there which made use of the ingame browser (namely the Agony Fleet Manager) but also newer ones (Erik Kalkoken\'s Fleet report). Being a thing of the past I replaced all the ingame browser features with API calls using the EVE swagger interface, added some extra convenience and a few features, I thought might be useful and here we go. To get started just choose login from the top menu.</p></center></div>');
$show = '<div id="myCarousel" class="carousel slide" data-ride="carousel" data-interval="5000">
  <!-- Indicators -->
  <ol class="carousel-indicators">
    <li data-target="#myCarousel" data-slide-to="0" class="active"></li>
    <li data-target="#myCarousel" data-slide-to="1"></li>
    <li data-target="#myCarousel" data-slide-to="2"></li>
    <li data-target="#myCarousel" data-slide-to="3"></li>
    <li data-target="#myCarousel" data-slide-to="4"></li>
  </ol>

  <!-- Wrapper for slides -->
  <div class="carousel-inner" role="listbox">
    <div class="item active">
      <img class="carousel-img" src="img/fleetyo_1.jpg" alt="Fleet setup">
      <div class="carousel-caption">
        <h3>Fleet setup</h3>
        <p>Setting up a fleet is just a matter of pressing a button and signing in via EVE\'s SSO.</p>
      </div>
    </div>

    <div class="item">
      <img class="carousel-img" src="img/fleetyo_2.jpg" alt="Fitting submission">
      <div class="carousel-caption">
        <h3>Fitting submission</h3>
        <p>Fleet members can easily post their fittings to give the FC all the information he needs about the fleet composition.</p>
      </div>
    </div>

    <div class="item">
      <img class="carousel-img" src="img/fleetyo_3.jpg" alt="Fleet composition">
      <div class="carousel-caption">
        <h3>Fleet composition</h3>
        <p>The fleet composition shows exactly who, what and where. Information only accesible to the fleet boos can be shared with the FC and/or other fleet members</p>
      </div>
    </div>

    <div class="item">
      <img class="carousel-img" src="img/fleetyo_4.jpg" alt="Fleet history">
      <div class="carousel-caption">
        <h3>Fleet history</h3>
        <p>Review the fleets you participated in or FCed. Access permissions are set an managed by the FC.</p>
      </div>
    </div>

    <div class="item">
      <img class="carousel-img" src="img/fleetyo_5.jpg" alt="Fleet statistics">
      <div class="carousel-caption">
        <h3>Fleet statistics</h3>
        <p>Analyze fleet performance, composition, kills and losses...</p>
      </div>
    </div>

  </div>

  <!-- Left and right controls -->
  <a class="left carousel-control" href="#myCarousel" role="button" data-slide="prev">
    <span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span>
    <span class="sr-only">Previous</span>
  </a>
  <a class="right carousel-control" href="#myCarousel" role="button" data-slide="next">
    <span class="glyphicon glyphicon-chevron-right" aria-hidden="true"></span>
    <span class="sr-only">Next</span>
  </a>
</div>';
$page->addBody($show);
$page->display(true);
?>
