<style type="text/css">
      #map-canvas { height:800px; width:800px;  }
    </style>
<script type="text/javascript" src="js/stomp.js"></script>
<script type="text/javascript" src="https://maps.googleapis.com/maps/api/js?key=AIzaSyB5CJ_M-2TJtQVx6CmxxZM1AQ0Tp2FzEdI&sensor=false"></script>
<script type="text/javascript">

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
