<script type="text/javascript" src="js/stomp.js"></script>
<script type="text/javascript" src="jquery/jquery.jeditable.js"></script>
<script>
$(document).ready(function()	{
	$( "#trackinglist, #clientslist" ).droppable({
		drop: function( event, ui ) {
			$( this ).find( ".placeholder" ).remove();
	//		$( "<li></li>" ).text( ui.draggable.text() ).appendTo( this );
			$(this).append(ui.draggable);
			thismac = $(ui.draggable).find("#clDrag").attr("data-clmac");
			target = $(event.target).attr("id");
			var tracking;
			if(target == "trackinglist")	{
				tracking = 1;
			}
			else if(target == "clientslist")	{
				tracking = 0;
			}

			$.getJSON( "ajax/changetracking.php", {
				tracking:tracking,
				clmac:thismac
			});
		}
	});
	$.each(clients, function(k,v)	{
		newClient(v);
	});
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
                client.subscribe('/topic/web.ticks',ticks);
        }
}


var clients =
<?php
$con = mysqli_connect("localhost","wiloc","q9MF2Jbed9S7DPmP","wiloc");
$result = mysqli_query($con,"SELECT * FROM clients") or die("Error: ".mysqli_error($con));
$clients= Array();
while($row = mysqli_fetch_array($result))       {
        $clients[$row['clMac']] = $row;
}
        echo json_encode($clients);
?>;
var ticks = function(message)      {
        curClient = JSON.parse(message.body);
        if((curClient.clMac in clients))    {
		if(curClient.clActive == 1)	{
			
		}
		else	{
		
		}
        }
        else    {
                newClient(curClient);
        }
}

function newClient(client)	{
	var dest;
	if(client.clActive == 1)	{
		dest = "#trackinglist";
	}
	else	{
		dest = "#clientslist";
	}
		$(dest).append(
			$("<li></li>").addClass("list-group-item").append(
				$("<div></div>").addClass("row").attr("id", "clDrag").attr("data-clMac", client.clMac).append(Array(
					$("<div></div>").addClass("col-md-4").html(client.clMac),
					$("<div></div>").addClass("col-md-4").html(client.clName).attr("id", "cMac_"+client.clMac).editable("/ajax/editname.php", { 
						indicator : "<img src='img/indicator.gif'>",
						tooltip   : "Click to Add",
						style  : "inherit"
					 })
				))
			).draggable({
		              appendTo: "body",
		              helper: "clone"
		        })
		)
	clients[client.clMac] = client;
}

</script>
<table id="clients">
<tbody>
<h1 class="page-header">Clients</h1>

<div class="row">
	<div class="col-md-6">
		<h2 class="sub-header">Currently Available</h2>
			<ul class="list-group" id="clientslist">
				<li class="panel-heading list-group-item" id="title">
					 <div class="row">
                                                <div class="col-md-4">MAC Address</div>
						<div class="col-md-4">Name</div>
                                        </div>
				</li>
			</ul>
	</div>
	<div class="col-md-6">
		<h2 class="sub-header">Currently Tracking</h2>
			 <ul class="list-group" id="trackinglist">
                                <li class="panel-heading list-group-item">
                                         <div class="row">
                                                <div class="col-md-4">MAC Address</div>
                                                <div class="col-md-4">Name</div>
                                        </div>
                                </li>
                          </ul>
	</div>
</div>
