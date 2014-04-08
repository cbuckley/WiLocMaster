<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$site['defaulttemplate'] = 'dashboard';
$site['defaultpageloc'] = 'includes';
$site['templates'] = array(
	'dashboard' => array(
    		'headers' => array('includes/header.php'),
		'footers' => array('includes/footer.php')
	),
);

$site['product'] = 'WiLoc';
$site['map'] = array(
    'home' => array(
        'title' => $site['product'] . " - Home",
	'menu' => 'Home'
    ),
    'overview' => array(
	'title' => $site['product'] . " - Overview",
        'menu' => 'Overview'
    ),
    'dashboard' => array(
        'title' => $site['product'] . " - Dashboard",
        'menu' => 'Dashboard'
    ),
    'statistics' => array(
	'title' => $site['product'] . " - Statistics",
	'menu' => 'Statistics'
    ),
    'map' => array(
        'title' => $site['product'] . " - Map View",
	'menu' => 'Map View'
    ),
    'logs' => array(
        'title' => $site['product'] . " - Log View",
	'menu' => 'Logs'
    ),
    'clients' => array(
        'title' => $site['product'] . " - Clients",
        'menu' => 'Clients'
    )
);

$go = isset($_GET['go']) ? $_GET['go'] : 'home';
if (array_key_exists($go, $site['map'])) {
	$template = (isset($site['map'][$go]['template'])) ? $site['map'][$go]['template'] : $site['defaulttemplate'];
	render_page($template, $go, $site);
}
else	{
	die("END");
}
function render_page($template, $page, $site)	{	
	foreach($site['templates'][$template]['headers'] as $header)	{
		if(file_exists($header)) { include $header; } 
	}
	if(array_key_exists('includes', $site['map'][$page]))	{
		foreach($site['map'][$page]['includes'] as $inc)	{
			if(file_exists($inc)) { include $inc;}
		}
	}
	else	{
		if(file_exists($site['defaultpageloc'] . "/". $page . ".php")) { include $site['defaultpageloc'] . "/". $page . ".php"; }
	}
	foreach($site['templates'][$template]['footers'] as $footer)    {
                if(file_exists($footer)) { include $footer; }
        }
}
?>
