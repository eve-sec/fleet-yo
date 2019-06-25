<?php
require_once('loadclasses.php');
$page = new Page('About Fleet-Yo!');
$page->getCached();
$html = '<p>Fleet-Yo! &copy;'.date('Y').' Snitch Ashor of MBLOC.<br/><br/>
Version 1.0<br/><br/>
Licensed under the Apache License, Version 2.0 (the "License");<br/>
you may not use this file except in compliance with the License.<br/>
You may obtain a copy of the License at<br/>
<br/>
    <a href="http://www.apache.org/licenses/LICENSE-2.0">http://www.apache.org/licenses/LICENSE-2.0</a><br/>
<br/>
Unless required by applicable law or agreed to in writing, software<br/>
distributed under the License is distributed on an "AS IS" BASIS,<br/>
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.<br/>
See the License for the specific language governing permissions and<br/>
limitations under the License.<br/>
<br/>
Most of what this app does was inspired by <a href="https://github.com/agony-unleashed/fleet-manager">Neo\'s Fleet Manager</a>.<br/>
This app is built using php and the <a href="http://getbootstrap.com/">bootstrap</a> framework.<br/>
All interactions with EVE Online are done using the <a href=" https://esi.evetech.net/">EVE Swagger Interface</a><br/>
<br/>
Additional Software used:<br/>
<ul>
<li>ESI php client generated with <a href="http://swagger.io/swagger-codegen/">swagger-codegen</a></li>
<li>Auth was adopted from Fuzzy Steve\'s <a href="https://github.com/fuzzysteve/eve-sso-auth">EVE SSO Auth</a></li>
<li>Fuzzy Steve\'s <a href="https://www.fuzzwork.co.uk/">Static dump mysql conversion</a></li>
<li><a href="https://jquery.com/">jQuery</a></li>
<li>jQuery <a href="https://datatables.net/">datatables</a></li>
<li>Twitter <a href="https://twitter.github.io/typeahead.js/">typeahead.js</a></li>
<li>Nakupanda\'s <a href="https://nakupanda.github.io/bootstrap3-dialog/">Bootstrap Dialog</a></li>
<li><a href="https://eonasdan.github.io/bootstrap-datetimepicker/">Bootstrap 3 Datepicker</a></li>
<li><a href="https://www.chartjs.org/">ChartJS</a></li>
<li>The <a href="http://docs.guzzlephp.org/en/stable/">Guzzle</a> PHP HTTP client</li>
<li>Kevinrob\'s <a href="https://github.com/Kevinrob/guzzle-cache-middleware">Guzzle Cache Middleware</a></li>
<li>Local AccessToken verification using Spomky Lab\'s <a href="https://web-token.spomky-labs.com/">JWT Framework</a></li>
<li>The <a href="https://www.jstree.com/">jsTree</a> jQuery plugin.</li>
<li>ESI error rate limiting using rtheunissen\'s <a href="https://github.com/rtheunissen/guzzle-rate-limiter">Guzzle Rate-Limiter</a>.</li>
</ul>
<br/>
Special Thanks to a lot of very helpful people in #esi and #sso on the tweetfleet slack.<br/>
<br/>
So long,<br/>
o7, Snitch.
</p>
';
$page->addBody($html);
$page->display(true);
exit;
?>
