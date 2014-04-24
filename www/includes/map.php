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
        	center: new google.maps.LatLng(52.8129216,-2.0818326),
	        zoom: 20
        };
	map = new google.maps.Map(document.getElementById("map-canvas"), mapOptions);
	image = new google.maps.MarkerImage("img/picon.png",
		null, 
	        new google.maps.Point(0,0),
	        new google.maps.Point(22, 25)
	);
	
		$("#clientspane").hide();	
	
		$("#clientsbutton").click(function()	{
			swapdisplay("clients");
			$(this).addClass("active");
		});
		$("#controllersbutton").click(function()    {
                	swapdisplay("controllers");
			$(this).addClass("active");
        	});

		function swapdisplay(disp)	{
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
        		newController(v);
		});
		$.each(clients, function(k,v)   {
                	newClient(v);
	        });
		setInterval("updateControllers()", 5000);
		setInterval("updateClients()", 5000);
	});

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
var clients =
<?php
$result = mysqli_query($con,"SELECT * FROM clients where clActive = 1") or die("Error: ".mysqli_error($con));
$clients= Array();
while($row = mysqli_fetch_array($result))       {
        $clients[$row['clMac']] = $row;
}
        echo json_encode($clients);
?>;

var heartbeats = function(message)	{
	controller = JSON.parse(message.body);
	if((controller.coId in controllers))	{
		controller.jseen = (new Date).getTime();
		if(controllers[controller.coId].beating == 0 || controllers[controller.coId].timing == 1)	{
			updateControllerStatus(controller.coId, "online");
		}
		controller.beating = 1;
		controller.marker = controllers[controller.coId].marker;
		controller.tricircle = controllers[controller.coId].tricircle;
		controllers[controller.coId] = controller;
	}
	else	{
		controller.beating = 1;
		newController(controller);
	}
	if($('*[data-controller="'+controller.coId+'"]').find("#name").html() != controller.coName)	{
		$('*[data-controller="'+controller.coId+'"]').find("#name").html(controller.coName);
	}
	$('*[data-controller="'+controller.coId+'"]').find('#ping').fadeIn(500).fadeOut(500);
}
var summaryPackets = function(message)      {
        curClient = JSON.parse(message.body);
        if((curClient.clMac in clients))    {
		loc = getTrilateration(curClient.controllers['fnk8rjf43e'],curClient.controllers['lrhcngawjz'],curClient.controllers['Mp2H715b45']);
		if (typeof clients[curClient.clMac].marker == "undefined") {
			clients[curClient.clMac].marker = new google.maps.Marker({
				position: new google.maps.LatLng(loc.x, loc.y),
				map: map,
				title:curClient.clMac
			});
		}
		else	{
			clients[curClient.clMac].marker.setPosition(new google.maps.LatLng(loc.x, loc.y));
			clients[curClient.clMac].marker.setMap(map);
		}
        }
        else    {
                newClient(curClient);
        }
	clients[curClient.clMac].beating = 1;
        clients[curClient.clMac].jseen = (new Date).getTime();
	updateClientStatus(curClient.clMac, "online");
	if(curClient.clMac == trilaterating)	{
		trilaterate(curClient);
	}
}
function trilaterate(client)	{
	$.each(client.controllers, function(k,v)	{
		parseFloat(v.lon);
		client.controllers.lon = parseFloat(client.controllers.lon);
		parseFloat(client.controllers.distance);
		if (typeof controllers[k].tricircle == "undefined") {
			controllers[k].tricircle = new google.maps.Circle({
				map: map,
				radius: v.distance * 1000,
				fillColor: '#AA0000',
			});
			controllers[k].tricircle.bindTo('center', controllers[k].marker, 'position');
		}
		else	{
			controllers[k].tricircle.setRadius(v.distance *1000);
			controllers[k].tricircle.setMap(map);
		}
	});
}
function clearTrilaterate()	{
	$.each(controllers, function(k,v)        {
		if (typeof controllers[k].tricircle != "undefined") {
			controllers[k].tricircle.setMap(null);
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

	P1 = new Vector(xA, yA, zA);
	P2 = new Vector(xB, yB, zB);
	P3 = new Vector(xC, yC, zC);
    
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
	
	x = (Math.pow(position1.distance,2) - Math.pow(position2.distance,2) + Math.pow(d,2))/(2*d);
	y = ((Math.pow(position1.distance,2) - Math.pow(position3.distance,2) + Math.pow(i,2) + Math.pow(j,2))/(2*j)) - ((i/j)*x);
	z = Math.sqrt(Math.pow(position1.distance,2) - Math.pow(x,2) - Math.pow(y,2));

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
function updateControllers()	{
	$.each(controllers, function(k, v)      {
		var warntime = (new Date).getTime() - 25000;
		var deadtime = (new Date).getTime() - 40000;
        	if(v.beating == 1)	{
			if(v.jseen < deadtime)	{
				updateControllerStatus(v.coId, "offline");
			}
			else if(v.jseen < warntime)	{
				updateControllerStatus(v.coId, "timing");
				v.timing = true;
			} 
		}
	});	
}
function updateClients()    {
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

function updateControllerStatus(controller, stat)	{
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
function updateClientStatus(client, stat)       {
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
	if(c.coActive == 0)	{
		beating = "info";
		status = "Unprovisioned";
	}
	else	{
		if(c.beating == 1)	{
			beating = "success";
			c.jseen = (new Date).getTime();
			status = "Online";
		}
		else	{
			c.jseen = c.coSeen;
			beating = "danger";
			status = "Offline";
		}
	}
	controllers[c.coId] = c;
	controllers[c.coId].marker = new google.maps.Marker({
                position: new google.maps.LatLng(c.clLat,c.clLon),
                point:(22,42),
                map: map,
                icon: image,
                draggable:true,
        });
	var row = $("<tr></tr>").addClass(beating).attr("data-controller", c.coId)
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
			$("<td></td>").html($("<span></span>").attr("id", "curTrack").hide().addClass("glyphicon glyphicon-ok"))
		)).click(function()	{
			if(typeof(trilaterating) != "undefined")	{
				$('#activeClients [data-clMac="'+trilaterating+'"]').find("#curTrack").hide();	
			}
			$('#activeClients [data-clMac="'+client.clMac+'"]').find("#curTrack").show();	
			trilaterating = client.clMac;
			clearTrilaterate();	
		}));
	updateClientStatus(client.clMac, "waiting");
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
