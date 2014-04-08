<style type="text/css">
      #map-canvas { height:800px; width:800px;  }
    </style>
<script type="text/javascript" src="js/stomp.js"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB5CJ_M-2TJtQVx6CmxxZM1AQ0Tp2FzEdI&sensor=false"></script>
<script type="text/javascript">
var redCircle;
var redpi;
function initialize() {
	var mapOptions = {
        	center: new google.maps.LatLng(52.8129216,-2.0818326),
	        zoom: 20
        };
	var map = new google.maps.Map(document.getElementById("map-canvas"), mapOptions);
	var image = 'img/picon.png';
	var image = new google.maps.MarkerImage("img/picon.png",
		null, 
	        new google.maps.Point(0,0),
	        new google.maps.Point(22, 25)
	);
	var redpiloc = new google.maps.LatLng(52.812881,-2.081955);
	redpi = new google.maps.Marker({
		position: redpiloc,
		point:(22,42),
		map: map,
		icon: image,
		draggable:true,
	});
	
	/*circleOptions = {
		strokeColor: '#FF0000',
		strokeOpacity: 0.2,
		strokeWeight: 2,
		fillColor: '#FF0000',
		fillOpacity: 0.15,
		map: map,
		draggable:true,
		center:redpiloc,
		radius: 10
	};
	redCircle = new google.maps.Circle(circleOptions);*/
	}
	google.maps.event.addDomListener(window, 'load', initialize);
	$(document).ready(function()	{
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
		setInterval("updateControllers()", 5000);
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
		client.subscribe('/queue/web.heartbeats',heartbeats);
	}
}
var controllers = 
<?php
$con = mysqli_connect("localhost","wiloc","q9MF2Jbed9S7DPmP","wiloc");
$result = mysqli_query($con,"SELECT *, coSeen between (NOW() - interval 40 second) and NOW() as beating FROM controllers") or die("Error: ".mysqli_error($con));;
$controllers = Array();
while($row = mysqli_fetch_array($result))       {
        $controllers[$row['coId']] = $row;
}
        echo json_encode($controllers);
?>

var heartbeats = function(message)	{
	controller = JSON.parse(message.body);
	if((controller.coId in controllers))	{
		controller.jseen = (new Date).getTime();
		controller.beating = 1;
		if(controllers[controller.coId].beating == 0 || controllers[controller.coId].timing == 1)	{
			updateControllerStatus(controller.coId, "online");
		}
		controllers[controller.coId] = controller;
	}
	else	{
		newController(controller);
	}
	$('*[data-controller="'+controller.coId+'"]').find('#ping').fadeIn(500).fadeOut(500);
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
	var row = $("<tr></tr>").addClass(beating).attr("data-controller", c.coId)
	.append(Array(
		$("<td></td>").html(c.coName),
		$("<td></td>").html(status).attr("id", "status"),
		$("<td></td>").html($("<span></span>").attr("id", "ping").hide().addClass("glyphicon glyphicon-bell"))
	));
	$("#controllers").append(row);
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
			<th></th>
		</tr>
		
	</table>
	</div>
	<div id="clientspane">
	<h2>Clients</h2>
        <table class="table table-striped">
                <tr>
                        <th>Client</th>
                        <th>Status</th>
                        <th>MAC</th>
                </tr>
                <tr>
                        <td>Chris iPhone</td>
                        <td>Active</td>
                        <td>00:00:00:00:00:00</td>
                </tr>
		<tr>
                        <td>Chris MacBook</td>
                        <td class="warning">Timing</td>
                        <td>00:00:00:00:00:00</td>
                </tr>
        </table>
	</div>

</div>
  <div class="col-xs-12 col-md-8"><div id="map-canvas"/></div>
</div>
