<style type="text/css">
      #map-canvas { height:800px; width:800px;  }
    </style>
<script type="text/javascript" src="js/stomp.js"></script>
<script type="text/javascript" src="jquery/jquery.jeditable.js"></script>
<script type="text/javascript" src="js/vector.js"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB5CJ_M-2TJtQVx6CmxxZM1AQ0Tp2FzEdI&sensor=false"></script>
<script type="text/javascript">
var redCircle;
var redpi;
var map;
var image;
var trilaterating;
$(document).ready(function()    {
	var mapOptions = {
        	center: new google.maps.LatLng(52.8129216,-2.0818326), //Centre of the map
	        zoom: 20
        };
	map = new google.maps.Map(document.getElementById("map-canvas"), mapOptions); //Initialise map
	image = new google.maps.MarkerImage("img/picon.png", // Pi Icon for the controllers
		null, 
	        new google.maps.Point(0,0),
	        new google.maps.Point(22, 25)
	);
	
		$("#clientspane").hide();//hide clients pane from view on start
	
		$("#clientsbutton").click(function()	{ //Client button swaps to clients
			swapdisplay("clients");
			$(this).addClass("active");
		});
		$("#controllersbutton").click(function()    { //Controller button swaps to controllers
                	swapdisplay("controllers");
			$(this).addClass("active");
        	});

		function swapdisplay(disp)	{ //Pane swap funcion to save duplicate code on each click
			if(disp == "clients")	{ 
				$("#controllerspane").hide();
				$("#controllersbutton").removeClass("active");
				$("#clientspane").fadeIn("fast");	
			}	
			else if(disp == "controllers")	{
				$("#clientspane").hide();
				$("#clientsbutton").removeClass("active");
				$("#controllerspane").fadeIn("fast");
			}
		}
		$.each(controllers, function(k, v)      {
        		newController(v); //Load controllers from JSON
		});
		$.each(clients, function(k,v)   {
                	newClient(v); // Load clients from JSON
	        });
		setInterval("updateControllers()", 5000);//update every 5 seconds
		setInterval("updateClients()", 5000);
	});
//Connect to stomp
var client, url;
url = "ws://fyp.cbuckley.com:61623/stomp";
client = Stomp.client(url);
client.connect("admin", "password", function(frame) {
	client.debug("connected to Stomp");
	startListening();	
});
var listening = false;
client.debug = function(str) {
};
function startListening() {
	if (!listening) {
		listening = true;
		client.subscribe('/topic/web.heartbeats',heartbeats);
		client.subscribe('/topic/web.summary',summaryPackets);
	}
}
//Load controllers using PHP JSON
var controllers = 
<?php
$con = mysqli_connect("localhost","wiloc","q9MF2Jbed9S7DPmP","wiloc");
$result = mysqli_query($con,"SELECT *, coSeen between (NOW() - interval 40 second) and NOW() as beating FROM controllers INNER JOIN controllerLocs on controllers.clId = controllerLocs.clId") or die("Error: ".mysqli_error($con));;
$controllers = Array();
while($row = mysqli_fetch_array($result))       {
        $controllers[$row['coId']] = $row;
}
        echo json_encode($controllers);
?>;
//Load clients into JSON
var clients =
<?php
$result = mysqli_query($con,"SELECT * FROM clients where clActive = 1") or die("Error: ".mysqli_error($con));
$clients= Array();
while($row = mysqli_fetch_array($result))       {
        $clients[$row['clMac']] = $row;
}
        echo json_encode($clients);
?>;
//Heartbeat receipt
var heartbeats = function(message)	{
	controller = JSON.parse(message.body);
	if((controller.coId in controllers))	{//is controller already in the list
		controller.jseen = (new Date).getTime(); // Update local last seen time
		if(controllers[controller.coId].beating == 0 || controllers[controller.coId].timing == 1)	{ // If the controller is offline or timing 
			updateControllerStatus(controller.coId, "online"); //set to online as we have receievd a packet
		}
		controller.beating = 1; // Set beating to 1 for true
		//Update new var with current information
		controller.marker = controllers[controller.coId].marker;
		controller.tricircle = controllers[controller.coId].tricircle; 
		controllers[controller.coId] = controller; // Update the current controller object with the new one (gets any new names or locations)
	}
	else	{
		controller.beating = 1;  //Beating
		newController(controller); //Add the new controller
	}
	if($('*[data-controller="'+controller.coId+'"]').find("#name").html() != controller.coName)	{
		$('*[data-controller="'+controller.coId+'"]').find("#name").html(controller.coName);
	}
	$('*[data-controller="'+controller.coId+'"]').find('#ping').fadeIn(500).fadeOut(500); // Show the heartbeat blip 
}
var summaryPackets = function(message)      {
        curClient = JSON.parse(message.body);
        if((curClient.clMac in clients))    {//is client currently in system
		loc = getTrilateration(curClient.controllers['fnk8rjf43e'],curClient.controllers['lrhcngawjz'],curClient.controllers['Mp2H715b45']); // Trilaterate the location using JS
		if (typeof clients[curClient.clMac].marker == "undefined") { //if marker is not currently set then set it
			clients[curClient.clMac].marker = new google.maps.Marker({
				position: new google.maps.LatLng(loc.x, loc.y),
				map: map,
				title:curClient.clMac
			});
		}
		else	{
			clients[curClient.clMac].marker.setPosition(new google.maps.LatLng(loc.x, loc.y)); // if marker is set then update location
			clients[curClient.clMac].marker.setMap(map);
		}
        }
        else    {
                newClient(curClient); // new client if client is not in the system
        }
	clients[curClient.clMac].beating = 1;
        clients[curClient.clMac].jseen = (new Date).getTime(); // Set JS seen time
	updateClientStatus(curClient.clMac, "online"); // Update client to online
	if(curClient.clMac == trilaterating)	{ //if this is the current trilaterating client
		trilaterate(curClient); // Trilaterate the client
	}
}
function trilaterate(client)	{ // Add trilateration circles
	$.each(client.controllers, function(k,v)	{
		parseFloat(v.lon);
		client.controllers.lon = parseFloat(client.controllers.lon);
		parseFloat(client.controllers.distance);
		if (typeof controllers[k].tricircle == "undefined") { //initialise the circles if they dont exist
			controllers[k].tricircle = new google.maps.Circle({
				map: map,
				radius: v.distance * 1000,
				fillColor: '#AA0000',
			});
			controllers[k].tricircle.bindTo('center', controllers[k].marker, 'position'); // Bind the circle to the pi icon
		}
		else	{
			controllers[k].tricircle.setRadius(v.distance *1000); // update if they do exist
			controllers[k].tricircle.setMap(map);
		}
	});
}
function clearTrilaterate()	{
	$.each(controllers, function(k,v)        {
		if (typeof controllers[k].tricircle != "undefined") {
			controllers[k].tricircle.setMap(null); // Remove the trilateration circle from the map so they are not visible
		}
	});
}
function getTrilateration(position1, position2, position3) {
	earthR = 6371;
	//convert to cartesian format from ECEF
	xA = earthR *(Math.cos(Math.radians(position1.lat)) * Math.cos(Math.radians(position1.lon)));
	yA = earthR *(Math.cos(Math.radians(position1.lat)) * Math.sin(Math.radians(position1.lon)));
	zA = earthR *(Math.sin(Math.radians(position1.lat)));

	xB = earthR *(Math.cos(Math.radians(position2.lat)) * Math.cos(Math.radians(position2.lon)));
	yB = earthR *(Math.cos(Math.radians(position2.lat)) * Math.sin(Math.radians(position2.lon)));
	zB = earthR *(Math.sin(Math.radians(position2.lat)));

	xC = earthR *(Math.cos(Math.radians(position3.lat)) * Math.cos(Math.radians(position3.lon)));
	yC = earthR *(Math.cos(Math.radians(position3.lat)) * Math.sin(Math.radians(position3.lon)));
	zC = earthR *(Math.sin(Math.radians(position3.lat)));
	
	//Load as vector objects
	P1 = new Vector(xA, yA, zA);
	P2 = new Vector(xB, yB, zB);
	P3 = new Vector(xC, yC, zC);
    
	//ECEF trilateration algorithm using vectors
	var a = P2.subtract(P1);
    	var b = P2.subtract(P1).length();
	var ex = a.divide(b);
 	var c = P3.subtract(P1)
	var i = ex.dot(c);
	var intermediate = (P3.subtract(P1)).subtract(ex.multiply(i));
	var ey = intermediate.divide(intermediate.length());	
	var ez = ex.cross(ey);
	var d = P2.subtract(P1).length();
	var j = ey.dot(P3.subtract(P1));
	
	//ECEF trilateration results in x,y,z format
	x = (Math.pow(position1.distance,2) - Math.pow(position2.distance,2) + Math.pow(d,2))/(2*d);
	y = ((Math.pow(position1.distance,2) - Math.pow(position3.distance,2) + Math.pow(i,2) + Math.pow(j,2))/(2*j)) - ((i/j)*x);
	z = Math.sqrt(Math.pow(position1.distance,2) - Math.pow(x,2) - Math.pow(y,2));

	//Convert to geodetic lat lon
	var triPt = P1.add(ex.multiply(x)).add(ey.multiply(y)).add(ez.multiply(z));
	lat = Math.toDeg(Math.asin(triPt.z / earthR));
	lon = Math.toDeg(Math.atan2(triPt.y,triPt.x));
	var obj = {
		x:lat,
		y:lon
	}	
	return obj;
}
if (typeof(Math.radians) === "undefined") {
  Math.radians = function(num) {
    return num * Math.PI / 180;
  }
}
if (typeof(Math.toDeg) === "undefined") {
  Math.toDeg = function(num) {
    return num * 180 / Math.PI;
  }
}
function updateControllers()	{ // Update controllers timer sets if they have not been seen in x amount of time
	$.each(controllers, function(k, v)      {
		var warntime = (new Date).getTime() - 25000;
		var deadtime = (new Date).getTime() - 40000;
        	if(v.beating == 1)	{
			if(v.jseen < deadtime)	{ //If they are > 40 seconds set to offline
				updateControllerStatus(v.coId, "offline"); 
			}
			else if(v.jseen < warntime)	{ // if they are > 20 seconds set to timing
				updateControllerStatus(v.coId, "timing");
				v.timing = true;
			} 
		}
	});	
}
function updateClients()    { // Update clients sets to offline or waiting depending on time of inactivity
        $.each(clients, function(k, v)      {
                var warntime = (new Date).getTime() - 30000;
                var deadtime = (new Date).getTime() - 120000;
                if(v.beating == 1)      {
                        if(v.jseen < deadtime)  {
                                updateClientStatus(v.clMac, "waiting");
                        }
                        else if(v.jseen < warntime)     {
                                updateClientStatus(v.clMac, "timing");
                                v.timing = true;
                        }
                }
        });
}

function updateControllerStatus(controller, stat)	{ // Sets the UI elements to say offline, timing or online
	switch (stat)	{
	case "online":
		$('*[data-controller="'+controller+'"]').removeClass("warning danger success").addClass("success").find("#status").html("Online");
		controllers[controller].beating = 1;
	break;
	case "timing":
		$('*[data-controller="'+controller+'"]').removeClass("warning danger success").addClass("warning").find("#status").html("Timing");
	break;
	case "offline":
		$('*[data-controller="'+controller+'"]').removeClass("warning danger success").addClass("danger").find("#status").html("Offline");
		controllers[controller].beating = 0;
	break;
	}
}
function updateClientStatus(client, stat)       { //updates UI elements to set as seen,timing or waiting
        switch (stat)   {
        case "online":
		$('#activeClients [data-clMac="'+client+'"]').find("#status").html("Seen");
        break;
        case "timing":
		$('#activeClients [data-clMac="'+client+'"]').find("#status").html("Timing");
        break;
        case "waiting":
		$('#activeClients [data-clMac="'+client+'"]').find("#status").html("Waiting");
        	if (typeof clients[client].marker != "undefined") {
			clients[client].marker.setMap(null);	
		}
	break;
        }
}
function newController(c)	{
	var beating,status;
	if(c.coActive == 0)	{ // If the controller is inactive
		beating = "info";
		status = "Unprovisioned"; //set to unprovisioned
	}
	else	{
		if(c.beating == 1)	{
			beating = "success"; 
			c.jseen = (new Date).getTime();
			status = "Online"; //Set to cnline if active
		}
		else	{
			c.jseen = c.coSeen;
			beating = "danger";
			status = "Offline"; //set to offline if inactive
		}
	}
	controllers[c.coId] = c;
	controllers[c.coId].marker = new google.maps.Marker({ // Set a marker for the new controller
                position: new google.maps.LatLng(c.clLat,c.clLon),
                point:(22,42),
                map: map,
                icon: image,
                draggable:true,
        });
	var row = $("<tr></tr>").addClass(beating).attr("data-controller", c.coId) // Add the HTML to the page for the controller
	.append(Array(
		$("<td></td>").html(c.coName).attr("id", "name"),
		$("<td></td>").html(status).attr("id", "status"),
		$("<td></td>").html($("<span></span>").attr("id", "ping").hide().addClass("glyphicon glyphicon-record"))
	));
	$("#controllers").append(row);
}
function newClient(client)      {
        var dest;
        $("#activeClients").append(
        	$("<tr></tr>").attr("data-clMac", client.clMac).append(Array(
           		$("<td></td>").html(client.clName).attr("id", "cMac_"+client.clMac).editable("/ajax/editname.php", {
                                                indicator : "<img src='img/indicator.gif'>",
                                                tooltip   : "Click to Add",
                                                style  : "inherit"
                                         })
,
			$("<td></td>").html(client.clStatus).attr("id", "status"),
			$("<td></td>").html(client.clMac),
			$("<td></td>").html($("<span></span>").attr("id", "curTrack").hide().addClass("glyphicon glyphicon-ok")) //Set the HTML for the client
		)).click(function()	{ //If the client is clicked then trilaterate them and set the other client as being not trilaterating
			if(typeof(trilaterating) != "undefined")	{
				$('#activeClients [data-clMac="'+trilaterating+'"]').find("#curTrack").hide();	
			}
			$('#activeClients [data-clMac="'+client.clMac+'"]').find("#curTrack").show();	
			trilaterating = client.clMac;
			clearTrilaterate();	
		}));
	updateClientStatus(client.clMac, "waiting"); //Update the client to waiting until we receieve a summary
        clients[client.clMac] = client;
}

</script>
<div class="row">
  <div class="col-xs-6 col-md-4">
	<p>
		<button type="button" id="controllersbutton" class="btn btn-default active">Controllers</button>
		<button type="button" id="clientsbutton"  class="btn btn-default">Clients</button>
		
	</p>
	<div id="controllerspane">
	<h2>Controllers</h2>
	<table id="controllers" class="table table-striped">
		<tr>
			<th>Controllers</th>
			<th>Status</th>
			<th>Heartbeat</th>
		</tr>
		
	</table>
	</div>
	<div id="clientspane">
	<h2>Clients</h2>
        <table id="activeClients" class="table table-striped">
                <tr>
                        <th>Client</th>
                        <th>Status</th>
                        <th>MAC</th>
			<th>Tracking</th>
                </tr>
        </table>
	</div>

</div>
  <div class="col-xs-12 col-md-8"><div id="map-canvas"/></div>
</div>
